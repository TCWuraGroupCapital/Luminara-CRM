<?php

declare(strict_types=1);

/**
 * Print total line coverage from a PHPUnit Clover report and fail under a floor.
 *
 * Total line coverage is covered executable lines divided by executable lines
 * (Clover project coveredstatements / statements). The Pest "Total" line is
 * that figure rounded to one decimal.
 *
 * Usage: php bin/coverage-line-total.php <clover.xml> <minimum-percent>
 */
if ($argc !== 3 || ! is_numeric($argv[2])) {
    fwrite(STDERR, "Usage: php bin/coverage-line-total.php <clover.xml> <minimum-percent>\n");
    exit(2);
}

$path = $argv[1];
$minimum = (float) $argv[2];

if (! is_file($path)) {
    fwrite(STDERR, "Clover XML not found at {$path}.\n");
    exit(2);
}

$xml = simplexml_load_file($path);

if ($xml === false || ! isset($xml->project->metrics)) {
    fwrite(STDERR, "Clover XML at {$path} has no project metrics.\n");
    exit(2);
}

$statements = (int) $xml->project->metrics['statements'];
$covered = (int) $xml->project->metrics['coveredstatements'];

if ($statements < 1) {
    fwrite(STDERR, "Clover XML at {$path} reports no executable lines.\n");
    exit(2);
}

$percentage = ($covered / $statements) * 100;
$shown = number_format($percentage, 2, '.', '');

echo sprintf(
    "Total line coverage: %s%% (%d/%d lines).\n",
    $shown,
    $covered,
    $statements,
);
echo sprintf("Enforced floor: %g%%.\n", $minimum);

$summaryPath = getenv('GITHUB_STEP_SUMMARY');

if (is_string($summaryPath) && $summaryPath !== '') {
    $color = match (true) {
        $percentage >= 90 => 'brightgreen',
        $percentage >= 75 => 'green',
        $percentage >= 60 => 'yellowgreen',
        $percentage >= 40 => 'yellow',
        $percentage >= 20 => 'orange',
        default => 'red',
    };

    $badge = sprintf(
        'https://img.shields.io/badge/line_coverage-%s%%25-%s',
        $shown,
        $color,
    );

    $minimumText = rtrim(rtrim(number_format($minimum, 2, '.', ''), '0'), '.');

    $markdown = <<<MD
### Line coverage

![Line coverage {$shown}%]({$badge})

Total line coverage: **{$shown}%** ({$covered} of {$statements} lines).

The floor for this job is **{$minimumText}%**. Clover XML is uploaded as the `clover-coverage` artifact.

MD;

    file_put_contents($summaryPath, $markdown, FILE_APPEND);
}

if ($percentage < $minimum) {
    fwrite(STDERR, sprintf(
        "Total line coverage %s%% is below the %g%% floor.\n",
        $shown,
        $minimum,
    ));
    exit(1);
}

exit(0);
