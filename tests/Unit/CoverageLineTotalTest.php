<?php

declare(strict_types=1);

/**
 * The coverage job fails by running bin/coverage-line-total.php.
 * These checks lock that pass and fail behaviour.
 */
test('coverage floor passes and writes the measured shields badge', function () {
    $clover = coverageLineTotalFixture(17504, 28301);
    $summary = tempnam(sys_get_temp_dir(), 'summary');
    $previous = getenv('GITHUB_STEP_SUMMARY');
    putenv('GITHUB_STEP_SUMMARY='.$summary);

    try {
        [$exit, $stdout] = runCoverageLineTotal($clover, '61');
        $markdown = (string) file_get_contents($summary);

        expect($exit)->toBe(0)
            ->and($stdout)->toContain('Total line coverage: 61.85% (17504/28301 lines).')
            ->and($stdout)->toContain('Enforced floor: 61%.')
            ->and($markdown)->toContain('https://img.shields.io/badge/line_coverage-61.85%25-yellowgreen')
            ->and($markdown)->toContain('The floor for this job is **61%**.');
    } finally {
        coverageLineTotalRestoreSummary($previous);
        @unlink($clover);
        @unlink($summary);
    }
});

test('coverage floor fails the command when coverage drops', function () {
    $clover = coverageLineTotalFixture(17504, 28301);

    try {
        [$exit, $stdout, $stderr] = runCoverageLineTotal($clover, '62');

        expect($exit)->toBe(1)
            ->and($stdout)->toContain('Total line coverage: 61.85%')
            ->and($stderr)->toContain('Total line coverage 61.85% is below the 62% floor.');
    } finally {
        @unlink($clover);
    }
});

test('coverage floor rejects a file that is not a clover report', function () {
    $path = tempnam(sys_get_temp_dir(), 'clover');
    file_put_contents($path, '<not-clover/>');

    try {
        [$exit, , $stderr] = runCoverageLineTotal($path, '61');

        expect($exit)->toBe(2)
            ->and($stderr)->toContain('has no project metrics');
    } finally {
        @unlink($path);
    }
});

function coverageLineTotalFixture(int $covered, int $statements): string
{
    $path = tempnam(sys_get_temp_dir(), 'clover');
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1" name="Clover Coverage">
    <file name="Example.php">
      <metrics statements="1" coveredstatements="0"/>
    </file>
    <metrics files="1" statements="{$statements}" coveredstatements="{$covered}"/>
  </project>
</coverage>
XML;
    file_put_contents($path, $xml);

    return $path;
}

/**
 * @return array{0: int, 1: string, 2: string}
 */
function runCoverageLineTotal(string $clover, string $minimum): array
{
    $command = [
        PHP_BINARY,
        dirname(__DIR__, 2).'/bin/coverage-line-total.php',
        $clover,
        $minimum,
    ];
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), (string) $stdout, (string) $stderr];
}

function coverageLineTotalRestoreSummary(string|false $previous): void
{
    if ($previous === false) {
        putenv('GITHUB_STEP_SUMMARY');

        return;
    }

    putenv('GITHUB_STEP_SUMMARY='.$previous);
}
