<?php

namespace Webkul\Gourwest\Support;

final class RegionCatalog
{
    /**
     * Canonical 7-country Gourwest operational and privacy sovereignty metadata.
     *
     * @var array<string, array{
     *     code: string,
     *     name: string,
     *     currency: string,
     *     timezone: string,
     *     privacy_regime: string,
     *     privacy_statute: string,
     *     hub_name: string,
     *     data_residency: string,
     *     cluster: string
     * }>
     */
    private const REGIONS = [
        'AU' => [
            'code' => 'AU',
            'name' => 'Australia',
            'currency' => 'AUD',
            'timezone' => 'Australia/Melbourne',
            'privacy_regime' => 'AU-APPs',
            'privacy_statute' => 'Privacy Act 1988 (Cth) — Australian Privacy Principles (APPs 1–13)',
            'hub_name' => 'Melbourne Collins St Global HQ & Roastery',
            'data_residency' => 'APAC-Sydney Primary Sovereign Partition',
            'cluster' => 'ANZ Region',
        ],
        'NZ' => [
            'code' => 'NZ',
            'name' => 'New Zealand',
            'currency' => 'NZD',
            'timezone' => 'Pacific/Auckland',
            'privacy_regime' => 'NZ-PA2020',
            'privacy_statute' => 'New Zealand Privacy Act 2020 — Information Privacy Principles (IPP 1–13)',
            'hub_name' => 'Auckland Viaduct Harbour Hub',
            'data_residency' => 'ANZ Trans-Tasman Adequacy Partition',
            'cluster' => 'ANZ Region',
        ],
        'SG' => [
            'code' => 'SG',
            'name' => 'Singapore',
            'currency' => 'SGD',
            'timezone' => 'Asia/Singapore',
            'privacy_regime' => 'SG-PDPA',
            'privacy_statute' => 'Singapore Personal Data Protection Act 2012 (2020 Amendments)',
            'hub_name' => 'Marina Bay Financial Centre ASEAN Hub',
            'data_residency' => 'ASEAN-Singapore Encrypted Partition',
            'cluster' => 'ASEAN Region',
        ],
        'MY' => [
            'code' => 'MY',
            'name' => 'Malaysia',
            'currency' => 'MYR',
            'timezone' => 'Asia/Kuala_Lumpur',
            'privacy_regime' => 'MY-PDPA',
            'privacy_statute' => 'Malaysia Personal Data Protection Act 2010 (2024 Amendment Act)',
            'hub_name' => 'Kuala Lumpur Bukit Bintang Hub',
            'data_residency' => 'ASEAN-MY Cross-Border Consent Partition',
            'cluster' => 'ASEAN Region',
        ],
        'TH' => [
            'code' => 'TH',
            'name' => 'Thailand',
            'currency' => 'THB',
            'timezone' => 'Asia/Bangkok',
            'privacy_regime' => 'TH-PDPA',
            'privacy_statute' => 'Thailand Personal Data Protection Act B.E. 2562 (2019)',
            'hub_name' => 'Bangkok Sukhumvit Flagship Lounge',
            'data_residency' => 'ASEAN-TH Explicit Opt-In Partition',
            'cluster' => 'ASEAN Region',
        ],
        'UK' => [
            'code' => 'UK',
            'name' => 'United Kingdom',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'privacy_regime' => 'UK-GDPR',
            'privacy_statute' => 'UK GDPR & Data Protection Act 2018 (ICO IDTA Safeguards)',
            'hub_name' => 'London Covent Garden Regional Hub',
            'data_residency' => 'UK-London Adequacy & IDTA Partition',
            'cluster' => 'UK & Canada Region',
        ],
        'CA' => [
            'code' => 'CA',
            'name' => 'Canada',
            'currency' => 'CAD',
            'timezone' => 'America/Toronto',
            'privacy_regime' => 'CA-PIPEDA',
            'privacy_statute' => 'Canada PIPEDA & Provincial Privacy Statutes (Quebec Law 25)',
            'hub_name' => 'Toronto Yorkville Regional Hub',
            'data_residency' => 'North America-Central PIPEDA Partition',
            'cluster' => 'UK & Canada Region',
        ],
    ];

    /**
     * @return array<string, array{
     *     code: string,
     *     name: string,
     *     currency: string,
     *     timezone: string,
     *     privacy_regime: string,
     *     privacy_statute: string,
     *     hub_name: string,
     *     data_residency: string,
     *     cluster: string
     * }>
     */
    public static function all(): array
    {
        return self::REGIONS;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::REGIONS);
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     currency: string,
     *     timezone: string,
     *     privacy_regime: string,
     *     privacy_statute: string,
     *     hub_name: string,
     *     data_residency: string,
     *     cluster: string
     * }|null
     */
    public static function get(string $code): ?array
    {
        $normalized = strtoupper(trim($code));

        return self::REGIONS[$normalized] ?? null;
    }

    public static function isSupported(string $code): bool
    {
        return isset(self::REGIONS[strtoupper(trim($code))]);
    }

    public static function privacyRegimeFor(string $code): string
    {
        $region = self::get($code);

        return $region !== null ? $region['privacy_regime'] : 'AU-APPs';
    }

    public static function tierForPoints(int $points): string
    {
        if ($points >= 5000) {
            return 'Platinum';
        }

        if ($points >= 2000) {
            return 'Gold';
        }

        if ($points >= 750) {
            return 'Silver';
        }

        return 'Bronze';
    }

    public static function slaHoursForPriority(string $priority): int
    {
        return match (strtolower(trim($priority))) {
            'critical' => 2,
            'high' => 4,
            'medium' => 12,
            default => 24,
        };
    }
}
