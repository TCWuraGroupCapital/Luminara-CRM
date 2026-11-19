<?php

use Webkul\Experiment\Support\Bucketer;

it('deterministically assigns identical variants across repeated calls', function () {
    $variants = [
        ['key' => 'control', 'weight' => 5000],
        ['key' => 'treatment', 'weight' => 5000],
    ];

    $first = Bucketer::assignVariant('hero_cta', 'salt_abc123', 'subject-42', $variants);

    for ($i = 0; $i < 25; $i++) {
        expect(Bucketer::assignVariant('hero_cta', 'salt_abc123', 'subject-42', $variants))
            ->toBe($first);
    }
});

it('distributes 50/50 and 80/20 basis-point splits uniformly within tolerance', function () {
    $evenVariants = [
        ['key' => 'control', 'weight' => 5000],
        ['key' => 'treatment', 'weight' => 5000],
    ];

    $skewedVariants = [
        ['key' => 'control', 'weight' => 8000],
        ['key' => 'treatment', 'weight' => 2000],
    ];

    $evenCounts = ['control' => 0, 'treatment' => 0];
    $skewedCounts = ['control' => 0, 'treatment' => 0];
    $sampleSize = 10000;

    for ($i = 0; $i < $sampleSize; $i++) {
        $subject = "visitor-{$i}";
        $evenCounts[Bucketer::assignVariant('exp_even', 'salt_1', $subject, $evenVariants)]++;
        $skewedCounts[Bucketer::assignVariant('exp_skewed', 'salt_2', $subject, $skewedVariants)]++;
    }

    expect($evenCounts['control'])->toBeBetween(4750, 5250)
        ->and($evenCounts['treatment'])->toBeBetween(4750, 5250)
        ->and($skewedCounts['control'])->toBeBetween(7750, 8250)
        ->and($skewedCounts['treatment'])->toBeBetween(1750, 2250);
});

it('keeps assignments orthogonal across independent experiment codes', function () {
    $variants = [
        ['key' => 'control', 'weight' => 5000],
        ['key' => 'treatment', 'weight' => 5000],
    ];

    $sameAssignment = 0;
    $sampleSize = 4000;

    for ($i = 0; $i < $sampleSize; $i++) {
        $subject = "orthogonal-user-{$i}";
        $a = Bucketer::assignVariant('pricing_table_v1', 'salt_a', $subject, $variants);
        $b = Bucketer::assignVariant('onboarding_flow_v1', 'salt_b', $subject, $variants);

        if ($a === $b) {
            $sameAssignment++;
        }
    }

    expect($sameAssignment)->toBeBetween(1800, 2200);
});

it('preserves monotonic variant assignment as traffic ramps from 10% to 50% to 100%', function () {
    $variants = [
        ['key' => 'control', 'weight' => 5000],
        ['key' => 'treatment', 'weight' => 5000],
    ];

    $enrolledAt10 = 0;
    $enrolledAt50 = 0;

    for ($i = 0; $i < 2000; $i++) {
        $subject = "ramp-subject-{$i}";
        $at10 = Bucketer::assignVariant('ramp_exp', 'salt_ramp', $subject, $variants, 1000);
        $at50 = Bucketer::assignVariant('ramp_exp', 'salt_ramp', $subject, $variants, 5000);
        $at100 = Bucketer::assignVariant('ramp_exp', 'salt_ramp', $subject, $variants, 10000);

        expect($at100)->not->toBeNull();

        if ($at10 !== null) {
            $enrolledAt10++;
            expect($at50)->toBe($at10)
                ->and($at100)->toBe($at10);
        }

        if ($at50 !== null) {
            $enrolledAt50++;
            expect($at100)->toBe($at50);
        }

        expect(Bucketer::assignVariant('ramp_exp', 'salt_ramp', $subject, $variants, 0))->toBeNull();
    }

    expect($enrolledAt10)->toBeBetween(150, 250)
        ->and($enrolledAt50)->toBeBetween(920, 1080);
});

it('rejects malformed variant configurations with InvalidArgumentException', function (array $invalidVariants) {
    expect(fn () => Bucketer::assertValidVariants($invalidVariants))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'fewer than 2 variants' => [[
        ['key' => 'control', 'weight' => 10000],
    ]],
    'weights not summing to 10000' => [[
        ['key' => 'control', 'weight' => 4000],
        ['key' => 'treatment', 'weight' => 4000],
    ]],
    'duplicate variant keys' => [[
        ['key' => 'control', 'weight' => 5000],
        ['key' => 'control', 'weight' => 5000],
    ]],
    'zero or negative weight' => [[
        ['key' => 'control', 'weight' => 10000],
        ['key' => 'treatment', 'weight' => 0],
    ]],
    'invalid key characters' => [[
        ['key' => 'Control-Upper!', 'weight' => 5000],
        ['key' => 'treatment', 'weight' => 5000],
    ]],
]);

it('prevents colon-delimiter collisions across experimentCode and subjectId boundaries and accepts numeric string weights', function () {
    $variants = [
        ['key' => 'control', 'weight' => '5000'],
        ['key' => 'treatment', 'weight' => '5000'],
    ];

    Bucketer::assertValidVariants($variants);

    $differences = 0;

    for ($i = 0; $i < 200; $i++) {
        $b1 = Bucketer::bucket('variant', 'exp_a:part', 'salt_x', "user_{$i}");
        $b2 = Bucketer::bucket('variant', 'exp_a', 'salt_x', "part:user_{$i}");

        if ($b1 !== $b2) {
            $differences++;
        }
    }

    expect($differences)->toBeGreaterThan(190);
});
