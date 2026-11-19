<?php

namespace Webkul\Experiment\Support;

final class ExperimentStats
{
    public const MIN_SAMPLE_PER_VARIANT = 100;

    private const Z_95 = 1.959963984540054;

    /**
     * Compute frequentist + Bayesian A/B summary metrics, Holm-Bonferroni FWER adjustment, and SRM diagnostics.
     *
     * @param  array<int, array{key: string, name?: string, weight: int|string}>  $variants
     * @param  array<string, array{exposures: int, conversions: int, total_value: float}>  $countsByVariant
     * @return array<string, mixed>
     */
    public static function summarize(array $variants, array $countsByVariant): array
    {
        $controlKey = (string) ($variants[0]['key'] ?? 'control');
        $controlCounts = $countsByVariant[$controlKey] ?? ['exposures' => 0, 'conversions' => 0, 'total_value' => 0.0];
        $nControl = max(0, (int) $controlCounts['exposures']);
        $cControl = min($nControl, max(0, (int) $controlCounts['conversions']));
        $pControl = $nControl > 0 ? $cControl / $nControl : 0.0;

        $totalExposures = 0;
        $totalConversions = 0;
        $totalValue = 0.0;
        $allAdequate = count($variants) >= 2;
        $rows = [];
        $rawTreatmentPValues = [];

        foreach ($variants as $index => $variant) {
            $key = (string) $variant['key'];
            $weight = (int) $variant['weight'];
            $counts = $countsByVariant[$key] ?? ['exposures' => 0, 'conversions' => 0, 'total_value' => 0.0];
            $n = max(0, (int) $counts['exposures']);
            $c = min($n, max(0, (int) $counts['conversions']));
            $val = round((float) $counts['total_value'], 4);
            $rate = $n > 0 ? $c / $n : 0.0;

            $totalExposures += $n;
            $totalConversions += $c;
            $totalValue += $val;

            if ($n < self::MIN_SAMPLE_PER_VARIANT) {
                $allAdequate = false;
            }

            $isControl = $index === 0;
            $lift = null;
            $zScore = null;
            $rawPValue = null;
            $ciLow = null;
            $ciHigh = null;
            $probBeatControl = null;

            if (! $isControl && $n > 0 && $nControl > 0) {
                $diff = $rate - $pControl;
                $lift = $pControl > 0.0 ? round(($diff / $pControl) * 100.0, 2) : null;

                [$lowDiff, $highDiff] = self::newcombeWilsonDiffCi($c, $n, $cControl, $nControl);
                $ciLow = round($lowDiff * 100.0, 3);
                $ciHigh = round($highDiff * 100.0, 3);

                $pooled = ($c + $cControl) / ($n + $nControl);
                $sePooled = sqrt($pooled * (1.0 - $pooled) * ((1.0 / $n) + (1.0 / $nControl)));

                if ($sePooled > 0.0) {
                    $rawZ = $diff / $sePooled;
                    $zScore = round($rawZ, 4);
                    $rawPValue = self::twoTailedPValue(abs($rawZ));
                } else {
                    $zScore = 0.0;
                    $rawPValue = 1.0;
                }

                $rawTreatmentPValues[$index] = $rawPValue;
                $probBeatControl = round(self::betaProbBeatControl($c, $n, $cControl, $nControl) * 100.0, 2);
            }

            $rows[$index] = [
                'key' => $key,
                'name' => (string) ($variant['name'] ?? $key),
                'weight' => $weight,
                'is_control' => $isControl,
                'exposures' => $n,
                'conversions' => $c,
                'conversion_rate' => round($rate * 100.0, 3),
                'total_value' => round($val, 2),
                'relative_lift' => $lift,
                'z_score' => $zScore,
                'p_value' => $rawPValue !== null ? round($rawPValue, 6) : null,
                'p_value_adjusted' => null,
                'prob_beat_control' => $probBeatControl,
                'ci_95_low' => $ciLow,
                'ci_95_high' => $ciHigh,
                'verdict' => $isControl ? 'baseline' : 'insufficient_sample',
            ];
        }

        $adjustedMap = self::holmBonferroni($rawTreatmentPValues, max(1, count($variants) - 1));

        foreach ($rows as $index => &$row) {
            if ($row['is_control']) {
                continue;
            }

            $adj = $adjustedMap[$index] ?? null;
            $row['p_value_adjusted'] = $adj !== null ? round($adj, 6) : null;

            if ($row['exposures'] >= self::MIN_SAMPLE_PER_VARIANT && $nControl >= self::MIN_SAMPLE_PER_VARIANT && $adj !== null) {
                $diff = ($row['conversions'] / $row['exposures']) - $pControl;
                $row['verdict'] = $adj < 0.05
                    ? ($diff > 0 ? 'significant_positive' : 'significant_negative')
                    : 'inconclusive';
            }
        }
        unset($row);

        $srmPValue = self::sampleRatioMismatchPValue($rows, $totalExposures);

        return [
            'total_exposures' => $totalExposures,
            'total_conversions' => $totalConversions,
            'total_value' => round($totalValue, 2),
            'sample_adequate' => $allAdequate,
            'srm_p_value' => $srmPValue !== null ? round($srmPValue, 6) : null,
            'srm_warning' => $srmPValue !== null && $srmPValue < 0.001,
            'variants' => array_values($rows),
        ];
    }

    /**
     * Newcombe Method 10 hybrid Wilson score 95% confidence interval for (p_treatment - p_control).
     *
     * @return array{0: float, 1: float}
     */
    public static function newcombeWilsonDiffCi(int $cT, int $nT, int $cC, int $nC): array
    {
        if ($nT <= 0 || $nC <= 0) {
            return [-1.0, 1.0];
        }

        $cT = max(0, min($nT, $cT));
        $cC = max(0, min($nC, $cC));

        [$lT, $uT] = self::wilsonInterval($cT, $nT);
        [$lC, $uC] = self::wilsonInterval($cC, $nC);

        $pT = $cT / $nT;
        $pC = $cC / $nC;
        $diff = $pT - $pC;

        $low = $diff - sqrt((($pT - $lT) ** 2) + (($uC - $pC) ** 2));
        $high = $diff + sqrt((($uT - $pT) ** 2) + (($pC - $lC) ** 2));

        return [max(-1.0, $low), min(1.0, $high)];
    }

    /**
     * Single-proportion Wilson score 95% interval.
     *
     * @return array{0: float, 1: float}
     */
    private static function wilsonInterval(int $c, int $n): array
    {
        $c = max(0, min($n, $c));
        $p = $c / $n;
        $z2 = self::Z_95 * self::Z_95;
        $denom = 1.0 + ($z2 / $n);
        $center = ($p + ($z2 / (2.0 * $n))) / $denom;
        $half = (self::Z_95 * sqrt((($p * (1.0 - $p)) / $n) + ($z2 / (4.0 * $n * $n)))) / $denom;

        return [max(0.0, $center - $half), min(1.0, $center + $half)];
    }

    /**
     * Step-down Holm-Bonferroni family-wise error rate adjustment across multiple treatment arms.
     *
     * @param  array<int, float>  $rawPValues
     * @return array<int, float>
     */
    public static function holmBonferroni(array $rawPValues, ?int $hypothesisCount = null): array
    {
        if ($rawPValues === []) {
            return [];
        }

        $m = max(count($rawPValues), (int) $hypothesisCount);

        asort($rawPValues, SORT_NUMERIC);

        $adjusted = [];
        $runningMax = 0.0;
        $rank = 1;

        foreach ($rawPValues as $index => $p) {
            $scaled = ($m - $rank + 1) * $p;
            $runningMax = max($runningMax, $scaled);
            $adjusted[$index] = min(1.0, $runningMax);
            $rank++;
        }

        return $adjusted;
    }

    /**
     * Pearson chi-square Sample Ratio Mismatch (SRM) upper-tail p-value across variant weights.
     *
     * @param  array<int, array{weight: int, exposures: int}>  $rows
     */
    public static function sampleRatioMismatchPValue(array $rows, int $totalExposures): ?float
    {
        $k = count($rows);

        if ($k < 2 || $totalExposures < 50) {
            return null;
        }

        $totalWeight = array_sum(array_column($rows, 'weight'));

        if ($totalWeight <= 0) {
            return null;
        }

        $chi2 = 0.0;

        foreach ($rows as $row) {
            $expected = $totalExposures * ($row['weight'] / $totalWeight);

            if ($expected <= 0.0) {
                return null;
            }

            $chi2 += (($row['exposures'] - $expected) ** 2) / $expected;
        }

        $df = $k - 1;

        if ($df === 1) {
            return self::twoTailedPValue(sqrt($chi2));
        }

        return self::chiSquareUpperTail($chi2, $df);
    }

    /**
     * Conjugate Beta(1+c, 1+n-c) posterior probability that treatment beats control in [0, 1].
     */
    private static function betaProbBeatControl(int $cT, int $nT, int $cC, int $nC): float
    {
        $aT = 1.0 + $cT;
        $bT = 1.0 + ($nT - $cT);
        $aC = 1.0 + $cC;
        $bC = 1.0 + ($nC - $cC);

        $meanT = $aT / ($aT + $bT);
        $meanC = $aC / ($aC + $bC);

        $varT = ($aT * $bT) / ((($aT + $bT) ** 2) * ($aT + $bT + 1.0));
        $varC = ($aC * $bC) / ((($aC + $bC) ** 2) * ($aC + $bC + 1.0));

        $z = ($meanT - $meanC) / sqrt($varT + $varC);
        $tail = 0.5 * self::twoTailedPValue(abs($z));

        return $z >= 0.0 ? 1.0 - $tail : $tail;
    }

    /**
     * Exact finite-recurrence upper-tail chi-square probability Q(df/2, chi2/2).
     */
    private static function chiSquareUpperTail(float $chi2, int $df): float
    {
        if ($chi2 <= 0.0 || $df <= 0) {
            return 1.0;
        }

        $a = 0.5 * $chi2;

        if ($df % 2 === 0) {
            $m = intdiv($df, 2);
            $term = 1.0;
            $sum = 1.0;

            for ($j = 1; $j < $m; $j++) {
                $term *= $a / $j;
                $sum += $term;
            }

            return max(0.0, min(1.0, exp(-$a) * $sum));
        }

        $m = intdiv($df - 1, 2);
        $erfc = self::twoTailedPValue(sqrt($chi2));

        if ($m === 0) {
            return $erfc;
        }

        $term = 2.0 * sqrt($a / M_PI);
        $sum = $term;

        for ($j = 1; $j < $m; $j++) {
            $term *= $a / ($j + 0.5);
            $sum += $term;
        }

        return max(0.0, min(1.0, $erfc + exp(-$a) * $sum));
    }

    /**
     * Abramowitz & Stegun 26.2.17 standard normal two-tailed p-value approximation (error < 7.5e-8).
     */
    public static function twoTailedPValue(float $absZ): float
    {
        if ($absZ <= 0.0) {
            return 1.0;
        }

        if ($absZ > 8.3) {
            return 0.0;
        }

        $t = 1.0 / (1.0 + 0.2316419 * $absZ);
        $pdf = exp(-0.5 * $absZ * $absZ) / sqrt(2.0 * M_PI);
        $poly = (((((1.330274429 * $t) - 1.821255978) * $t + 1.781477937) * $t - 0.356563782) * $t + 0.319381530) * $t;

        return max(0.0, min(1.0, 2.0 * $pdf * $poly));
    }
}
