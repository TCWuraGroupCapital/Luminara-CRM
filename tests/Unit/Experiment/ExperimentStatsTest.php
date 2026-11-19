<?php

use Webkul\Experiment\Support\ExperimentStats;

it('handles zero exposures safely without division-by-zero errors', function () {
    $summary = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
        ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
    ], []);

    expect($summary['total_exposures'])->toBe(0)
        ->and($summary['total_conversions'])->toBe(0)
        ->and($summary['sample_adequate'])->toBeFalse()
        ->and($summary['variants'][0]['verdict'])->toBe('baseline')
        ->and($summary['variants'][1]['verdict'])->toBe('insufficient_sample')
        ->and($summary['variants'][1]['p_value'])->toBeNull();
});

it('flags insufficient sample when variant exposures are below 100', function () {
    $summary = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
        ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
    ], [
        'control' => ['exposures' => 50, 'conversions' => 5, 'total_value' => 250.0],
        'treatment' => ['exposures' => 50, 'conversions' => 20, 'total_value' => 1000.0],
    ]);

    expect($summary['sample_adequate'])->toBeFalse()
        ->and($summary['variants'][1]['verdict'])->toBe('insufficient_sample')
        ->and($summary['variants'][1]['relative_lift'])->toBe(300.0);
});

it('identifies statistically significant positive and negative treatments accurately', function () {
    $positive = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
        ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
    ], [
        'control' => ['exposures' => 1000, 'conversions' => 100, 'total_value' => 5000.0],
        'treatment' => ['exposures' => 1000, 'conversions' => 155, 'total_value' => 7750.0],
    ]);

    $treatmentRow = $positive['variants'][1];

    expect($positive['sample_adequate'])->toBeTrue()
        ->and($positive['total_value'])->toBe(12750.0)
        ->and($treatmentRow['conversion_rate'])->toBe(15.5)
        ->and($treatmentRow['relative_lift'])->toBe(55.0)
        ->and($treatmentRow['z_score'])->toBeGreaterThan(3.5)
        ->and($treatmentRow['p_value'])->toBeLessThan(0.001)
        ->and($treatmentRow['ci_95_low'])->toBeGreaterThan(2.5)
        ->and($treatmentRow['verdict'])->toBe('significant_positive');

    $negative = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
        ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
    ], [
        'control' => ['exposures' => 1000, 'conversions' => 150, 'total_value' => 1500.0],
        'treatment' => ['exposures' => 1000, 'conversions' => 95, 'total_value' => 950.0],
    ]);

    expect($negative['variants'][1]['verdict'])->toBe('significant_negative')
        ->and($negative['variants'][1]['relative_lift'])->toBeLessThan(0);
});

it('marks noisy small differences as inconclusive even with adequate sample size', function () {
    $summary = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
        ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
    ], [
        'control' => ['exposures' => 500, 'conversions' => 50, 'total_value' => 500.0],
        'treatment' => ['exposures' => 500, 'conversions' => 53, 'total_value' => 530.0],
    ]);

    expect($summary['sample_adequate'])->toBeTrue()
        ->and($summary['variants'][1]['p_value'])->toBeGreaterThan(0.05)
        ->and($summary['variants'][1]['verdict'])->toBe('inconclusive');
});

it('computes non-degenerate Newcombe-Wilson CIs at zero conversions, Holm-Bonferroni FWER adjustment, SRM warnings, and Bayesian prob_beat_control', function () {
    $zeroSummary = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
        ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
    ], [
        'control' => ['exposures' => 120, 'conversions' => 0, 'total_value' => 0.0],
        'treatment' => ['exposures' => 120, 'conversions' => 0, 'total_value' => 0.0],
    ]);

    expect($zeroSummary['variants'][1]['ci_95_low'])->toBeLessThan(0.0)
        ->and($zeroSummary['variants'][1]['ci_95_high'])->toBeGreaterThan(0.0)
        ->and($zeroSummary['variants'][1]['prob_beat_control'])->toBe(50.0);

    // Multi-arm Holm-Bonferroni & SRM detection
    $multiArm = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 2500],
        ['key' => 'v1', 'name' => 'Variant 1', 'weight' => 2500],
        ['key' => 'v2', 'name' => 'Variant 2', 'weight' => 2500],
        ['key' => 'v3', 'name' => 'Variant 3', 'weight' => 2500],
    ], [
        'control' => ['exposures' => 400, 'conversions' => 40, 'total_value' => 400.0],
        'v1' => ['exposures' => 400, 'conversions' => 58, 'total_value' => 580.0], // raw p ~ 0.049, Holm adjusted * 3 > 0.05 => inconclusive
        'v2' => ['exposures' => 400, 'conversions' => 42, 'total_value' => 420.0],
        'v3' => ['exposures' => 100, 'conversions' => 10, 'total_value' => 100.0], // severe SRM skew!
    ]);

    expect($multiArm['srm_warning'])->toBeTrue()
        ->and($multiArm['srm_p_value'])->toBeLessThan(0.0001)
        ->and($multiArm['variants'][1]['p_value'])->toBeGreaterThan(0.04)
        ->and($multiArm['variants'][1]['p_value_adjusted'])->toBeGreaterThan(0.10)
        ->and($multiArm['variants'][1]['verdict'])->toBe('inconclusive');

    // Exact chi-square returns 1.0 on balanced 3-arm split, and Holm-Bonferroni preserves full m=3 denominator even when an arm has 0 exposures
    $balancedThreeArm = ExperimentStats::summarize([
        ['key' => 'control', 'name' => 'Control', 'weight' => 3334],
        ['key' => 'v1', 'name' => 'Variant 1', 'weight' => 3333],
        ['key' => 'v2', 'name' => 'Variant 2', 'weight' => 3333],
    ], [
        'control' => ['exposures' => 100, 'conversions' => 10, 'total_value' => 100.0],
        'v1' => ['exposures' => 100, 'conversions' => 10, 'total_value' => 100.0],
        'v2' => ['exposures' => 100, 'conversions' => 10, 'total_value' => 100.0],
    ]);

    expect($balancedThreeArm['srm_p_value'])->toBeGreaterThan(0.9999)
        ->and(ExperimentStats::newcombeWilsonDiffCi(0, 0, 0, 0))->toBe([-1.0, 1.0]);

    $unexposedArmHolm = ExperimentStats::holmBonferroni([1 => 0.02], 3);
    expect(round($unexposedArmHolm[1], 4))->toBe(0.06);
});
