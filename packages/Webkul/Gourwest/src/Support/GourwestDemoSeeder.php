<?php

namespace Webkul\Gourwest\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Experiment\Models\Experiment;
use Webkul\Experiment\Models\ExperimentEvent;
use Webkul\Gourwest\Models\CustomerProfile;
use Webkul\Gourwest\Models\ServiceTicket;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class GourwestDemoSeeder
{
    public function __construct(
        protected IntegrationSyncService $integrationSyncService
    ) {}

    /**
     * Idempotently seed Gourwest's 11 Brief stakeholders, 3 regional groups, 8 business system contracts,
     * 16 multi-country customer profiles (with 2 cross-region duplicate pairs), 10 regional SLA tickets,
     * and 1 active loyalty onboarding A/B experiment.
     *
     * @return array{
     *     groups_seeded: int,
     *     staff_seeded: int,
     *     integrations_seeded: int,
     *     customers_seeded: int,
     *     duplicate_pairs_ready: int,
     *     tickets_seeded: int,
     *     experiment_seeded: bool
     * }
     */
    public function seed(): array
    {
        $groups = $this->seedRegionalGroups();
        $roles = $this->seedGourwestRoles();
        $staff = $this->seedBriefStakeholders($roles, $groups);
        $organization = $this->seedGlobalOrganization($staff['sarah.williams@gourwest.example']->id);
        $integrationSummary = $this->integrationSyncService->seedEnterpriseIntegrationsAndEvents(true);
        $profiles = $this->seedGlobalCustomers($staff, $organization);
        $ticketsCount = $this->seedRegionalSlaTickets($staff, $profiles);
        $experimentSeeded = $this->seedLoyaltyOnboardingExperiment($staff['sophia.green@gourwest.example']->id);

        SecurityAuditRecorder::record(
            event: 'gourwest.demo_dataset.seeded',
            auditableType: 'gourwest_hub',
            auditableId: 'global',
            metadata: [
                'staff_count' => count($staff),
                'customers_count' => count($profiles),
                'integrations_count' => $integrationSummary['integrations_count'],
                'tickets_count' => $ticketsCount,
                'experiment_seeded' => $experimentSeeded,
            ]
        );

        return [
            'groups_seeded' => count($groups),
            'staff_seeded' => count($staff),
            'integrations_seeded' => $integrationSummary['integrations_count'],
            'customers_seeded' => count($profiles),
            'duplicate_pairs_ready' => 2,
            'tickets_seeded' => $ticketsCount,
            'experiment_seeded' => $experimentSeeded,
        ];
    }

    /**
     * @return array<string, Group>
     */
    private function seedRegionalGroups(): array
    {
        $definitions = [
            'ANZ Region' => 'Australia & New Zealand Regional CRM & Loyalty Operations',
            'ASEAN Region' => 'Singapore, Malaysia & Thailand Regional CRM & PDPA Operations',
            'UK & Canada Region' => 'United Kingdom & Canada Regional CRM, UK-GDPR & PIPEDA Operations',
        ];

        $groups = [];

        foreach ($definitions as $name => $description) {
            /** @var Group $group */
            $group = Group::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description]
            );

            $groups[$name] = $group;
        }

        return $groups;
    }

    /**
     * @return array<string, Role>
     */
    private function seedGourwestRoles(): array
    {
        /** @var Role $execRole */
        $execRole = Role::query()->updateOrCreate(
            ['name' => 'Gourwest Executive Leadership'],
            [
                'description' => 'Executive global oversight across all 7 Gourwest countries and 8 integrated systems',
                'permission_type' => 'all',
                'permissions' => null,
            ]
        );

        /** @var Role $governanceRole */
        $governanceRole = Role::query()->updateOrCreate(
            ['name' => 'Gourwest CRM Governance & Architecture'],
            [
                'description' => 'Enterprise CRM architecture, integration sync/replay, deduplication, and <3s warranty governance',
                'permission_type' => 'custom',
                'permissions' => [
                    'dashboard',
                    'gourwest',
                    'gourwest.customers',
                    'gourwest.integrations',
                    'gourwest.loyalty',
                    'gourwest.sla',
                    'gourwest.benchmark',
                    'contacts',
                    'contacts.persons',
                    'contacts.persons.view',
                    'leads',
                    'leads.view',
                    'activities',
                    'settings',
                    'settings.other_settings',
                    'settings.other_settings.experiments',
                    'settings.other_settings.security_audit',
                ],
            ]
        );

        /** @var Role $cxRole */
        $cxRole = Role::query()->updateOrCreate(
            ['name' => 'Gourwest Regional CX & Loyalty Specialist'],
            [
                'description' => 'Regional customer support, loyalty adjustments, and SLA ticket resolution within assigned scope',
                'permission_type' => 'custom',
                'permissions' => [
                    'dashboard',
                    'gourwest',
                    'gourwest.customers',
                    'gourwest.loyalty',
                    'gourwest.sla',
                    'contacts',
                    'contacts.persons',
                    'contacts.persons.view',
                ],
            ]
        );

        return [
            'executive' => $execRole,
            'governance' => $governanceRole,
            'cx' => $cxRole,
        ];
    }

    /**
     * @param  array<string, Role>  $roles
     * @param  array<string, Group>  $groups
     * @return array<string, User>
     */
    private function seedBriefStakeholders(array $roles, array $groups): array
    {
        $stakeholders = [
            [
                'name' => 'Sarah Williams (CEO)',
                'email' => 'sarah.williams@gourwest.example',
                'role' => 'executive',
                'view_permission' => 'global',
                'groups' => ['ANZ Region', 'ASEAN Region', 'UK & Canada Region'],
            ],
            [
                'name' => 'Michael Chen (CIO)',
                'email' => 'michael.chen@gourwest.example',
                'role' => 'executive',
                'view_permission' => 'global',
                'groups' => ['ANZ Region', 'ASEAN Region', 'UK & Canada Region'],
            ],
            [
                'name' => 'David Morgan (CFO)',
                'email' => 'david.morgan@gourwest.example',
                'role' => 'executive',
                'view_permission' => 'global',
                'groups' => ['ANZ Region'],
            ],
            [
                'name' => 'Emma Taylor (GM Customer Experience)',
                'email' => 'emma.taylor@gourwest.example',
                'role' => 'governance',
                'view_permission' => 'global',
                'groups' => ['ANZ Region', 'ASEAN Region', 'UK & Canada Region'],
            ],
            [
                'name' => 'Sophia Green (GM Marketing & Loyalty)',
                'email' => 'sophia.green@gourwest.example',
                'role' => 'governance',
                'view_permission' => 'global',
                'groups' => ['ANZ Region', 'ASEAN Region', 'UK & Canada Region'],
            ],
            [
                'name' => 'Olivia Brown (IT Services Manager)',
                'email' => 'olivia.brown@gourwest.example',
                'role' => 'governance',
                'view_permission' => 'global',
                'groups' => ['ANZ Region'],
            ],
            [
                'name' => 'Alexander Wilson (CRM Project Manager)',
                'email' => 'alexander.wilson@gourwest.example',
                'role' => 'governance',
                'view_permission' => 'global',
                'groups' => ['ANZ Region', 'ASEAN Region', 'UK & Canada Region'],
            ],
            [
                'name' => 'Rachel Nguyen (Database Manager)',
                'email' => 'rachel.nguyen@gourwest.example',
                'role' => 'governance',
                'view_permission' => 'global',
                'groups' => ['ASEAN Region'],
            ],
            [
                'name' => 'Ethan Carter (Security Manager)',
                'email' => 'ethan.carter@gourwest.example',
                'role' => 'governance',
                'view_permission' => 'global',
                'groups' => ['ANZ Region', 'UK & Canada Region'],
            ],
            [
                'name' => 'Nicole Harper (Applications Manager)',
                'email' => 'nicole.harper@gourwest.example',
                'role' => 'cx',
                'view_permission' => 'group',
                'groups' => ['UK & Canada Region'],
            ],
            [
                'name' => 'Hannah Brooks (Quality Manager)',
                'email' => 'hannah.brooks@gourwest.example',
                'role' => 'cx',
                'view_permission' => 'group',
                'groups' => ['ANZ Region'],
            ],
        ];

        $seededUsers = [];

        foreach ($stakeholders as $spec) {
            /** @var User|null $existing */
            $existing = User::query()->where('email', $spec['email'])->first();

            if ($existing !== null) {
                $existing->update([
                    'name' => $spec['name'],
                    'role_id' => $roles[$spec['role']]->id,
                    'status' => 1,
                    'view_permission' => $spec['view_permission'],
                ]);
                $user = $existing;
            } else {
                /** @var User $user */
                $user = User::query()->create([
                    'name' => $spec['name'],
                    'email' => $spec['email'],
                    'password' => Hash::make('Gw!Enterprise#2026$'.Str::random(16)),
                    'role_id' => $roles[$spec['role']]->id,
                    'status' => 1,
                    'view_permission' => $spec['view_permission'],
                ]);
            }

            $groupIds = [];

            foreach ($spec['groups'] as $groupName) {
                if (isset($groups[$groupName])) {
                    $groupIds[] = $groups[$groupName]->id;
                }
            }

            if ($groupIds !== []) {
                $user->groups()->syncWithoutDetaching($groupIds);
            }

            $seededUsers[$spec['email']] = $user;
        }

        return $seededUsers;
    }

    private function seedGlobalOrganization(int $ownerUserId): Organization
    {
        /** @var Organization $organization */
        $organization = Organization::query()->updateOrCreate(
            ['name' => 'Gourwest Global Hospitality Group'],
            [
                'address' => [
                    'address' => '120 Collins Street',
                    'city' => 'Melbourne',
                    'state' => 'VIC',
                    'postcode' => '3000',
                    'country' => 'AU',
                ],
                'user_id' => $ownerUserId,
            ]
        );

        return $organization;
    }

    /**
     * Seed 16 multi-country customer profiles across all 7 countries, including 2 intentional cross-region duplicate pairs.
     *
     * @param  array<string, User>  $staff
     * @return array<string, CustomerProfile>
     */
    private function seedGlobalCustomers(array $staff, Organization $organization): array
    {
        $emmaId = $staff['emma.taylor@gourwest.example']->id;
        $sophiaId = $staff['sophia.green@gourwest.example']->id;
        $rachelId = $staff['rachel.nguyen@gourwest.example']->id;
        $nicoleId = $staff['nicole.harper@gourwest.example']->id;
        $hannahId = $staff['hannah.brooks@gourwest.example']->id;

        $guests = [
            // Cross-Region Duplicate Pair #1: Liam O'Connor (AU Melbourne POS + SG Marina Bay Mobile App -> merges to 5,300 pts Platinum)
            [
                'member_number' => 'GW-AU-1001',
                'golden_uid' => 'GW-GLD-AU-1001',
                'name' => "Liam O'Connor",
                'email' => 'liam.oconnor@gourwest-vip.example',
                'phone' => '+61 412 889 102',
                'region_code' => 'AU',
                'home_outlet' => 'Melbourne Collins St Flagship',
                'consent_status' => 'verified',
                'points' => 3450,
                'spend_aud' => 4120.50,
                'owner_id' => $emmaId,
            ],
            [
                'member_number' => 'GW-SG-1002',
                'golden_uid' => 'GW-GLD-SG-1002',
                'name' => "Liam O'Connor (SG App)",
                'email' => 'LIAM.OCONNOR@gourwest-vip.example',
                'phone' => '61412889102',
                'region_code' => 'SG',
                'home_outlet' => 'Singapore Marina Bay Sands',
                'consent_status' => 'verified',
                'points' => 1850,
                'spend_aud' => 2180.00,
                'owner_id' => $rachelId,
            ],

            // Cross-Region Duplicate Pair #2: Charlotte Davies (UK London Roastery + CA Toronto Yorkville -> merges to 2,270 pts Gold)
            [
                'member_number' => 'GW-UK-1003',
                'golden_uid' => 'GW-GLD-UK-1003',
                'name' => 'Charlotte Davies',
                'email' => 'charlotte.davies@gourwest-vip.example',
                'phone' => '+44 7700 900412',
                'region_code' => 'UK',
                'home_outlet' => 'London Covent Garden Roastery',
                'consent_status' => 'verified',
                'points' => 1350,
                'spend_aud' => 1890.00,
                'owner_id' => $nicoleId,
            ],
            [
                'member_number' => 'GW-CA-1004',
                'golden_uid' => 'GW-GLD-CA-1004',
                'name' => 'Charlotte M. Davies',
                'email' => 'charlotte.m.travel@gourwest-vip.example',
                'phone' => '44-7700-900412',
                'region_code' => 'CA',
                'home_outlet' => 'Toronto Yorkville Lounge',
                'consent_status' => 'verified',
                'points' => 920,
                'spend_aud' => 1240.00,
                'owner_id' => $nicoleId,
            ],

            // 12 Distinct Golden Record Guests Across AU, NZ, SG, MY, TH, UK, CA
            [
                'member_number' => 'GW-AU-1005',
                'golden_uid' => 'GW-GLD-AU-1005',
                'name' => 'Amelia Vance',
                'email' => 'amelia.vance@gourwest-guest.example',
                'phone' => '+61 403 551 209',
                'region_code' => 'AU',
                'home_outlet' => 'Sydney Barangaroo Reserve',
                'consent_status' => 'verified',
                'points' => 6240,
                'spend_aud' => 7840.25,
                'owner_id' => $hannahId,
            ],
            [
                'member_number' => 'GW-AU-1006',
                'golden_uid' => 'GW-GLD-AU-1006',
                'name' => 'Noah Patterson',
                'email' => 'noah.patterson@gourwest-guest.example',
                'phone' => '+61 419 772 310',
                'region_code' => 'AU',
                'home_outlet' => 'Brisbane Eagle Street Pier',
                'consent_status' => 'verified',
                'points' => 840,
                'spend_aud' => 995.00,
                'owner_id' => $hannahId,
            ],
            [
                'member_number' => 'GW-NZ-1007',
                'golden_uid' => 'GW-GLD-NZ-1007',
                'name' => 'Aroha Tamati',
                'email' => 'aroha.tamati@gourwest-guest.example',
                'phone' => '+64 21 884 9201',
                'region_code' => 'NZ',
                'home_outlet' => 'Auckland Viaduct Harbour',
                'consent_status' => 'verified',
                'points' => 5410,
                'spend_aud' => 6310.80,
                'owner_id' => $hannahId,
            ],
            [
                'member_number' => 'GW-NZ-1008',
                'golden_uid' => 'GW-GLD-NZ-1008',
                'name' => 'Callum MacKenzie',
                'email' => 'callum.mackenzie@gourwest-guest.example',
                'phone' => '+64 27 339 1184',
                'region_code' => 'NZ',
                'home_outlet' => 'Wellington Lambton Quay',
                'consent_status' => 'verified',
                'points' => 2190,
                'spend_aud' => 2645.00,
                'owner_id' => $emmaId,
            ],
            [
                'member_number' => 'GW-SG-1009',
                'golden_uid' => 'GW-GLD-SG-1009',
                'name' => 'Mei Ling Tan',
                'email' => 'meiling.tan@gourwest-guest.example',
                'phone' => '+65 9128 4410',
                'region_code' => 'SG',
                'home_outlet' => 'Singapore Orchard ION',
                'consent_status' => 'verified',
                'points' => 7120,
                'spend_aud' => 8920.00,
                'owner_id' => $rachelId,
            ],
            [
                'member_number' => 'GW-MY-1010',
                'golden_uid' => 'GW-GLD-MY-1010',
                'name' => 'Farah Aisyah Razak',
                'email' => 'farah.razak@gourwest-guest.example',
                'phone' => '+60 12 398 7741',
                'region_code' => 'MY',
                'home_outlet' => 'Kuala Lumpur Pavilion Elite',
                'consent_status' => 'verified',
                'points' => 2860,
                'spend_aud' => 3410.50,
                'owner_id' => $rachelId,
            ],
            [
                'member_number' => 'GW-MY-1011',
                'golden_uid' => 'GW-GLD-MY-1011',
                'name' => 'Daniel Lim Wei Jie',
                'email' => 'daniel.lim@gourwest-guest.example',
                'phone' => '+60 16 882 1903',
                'region_code' => 'MY',
                'home_outlet' => 'Penang Gurney Plaza',
                'consent_status' => 'pending',
                'points' => 610,
                'spend_aud' => 740.00,
                'owner_id' => $sophiaId,
            ],
            [
                'member_number' => 'GW-TH-1012',
                'golden_uid' => 'GW-GLD-TH-1012',
                'name' => 'Niran Srisai',
                'email' => 'niran.srisai@gourwest-guest.example',
                'phone' => '+66 81 449 2831',
                'region_code' => 'TH',
                'home_outlet' => 'Bangkok Sukhumvit Flagship',
                'consent_status' => 'verified',
                'points' => 3320,
                'spend_aud' => 3980.00,
                'owner_id' => $rachelId,
            ],
            [
                'member_number' => 'GW-TH-1013',
                'golden_uid' => 'GW-GLD-TH-1013',
                'name' => 'Kanya Wattana',
                'email' => 'kanya.wattana@gourwest-guest.example',
                'phone' => '+66 89 773 5102',
                'region_code' => 'TH',
                'home_outlet' => 'Chiang Mai Nimman Bistro',
                'consent_status' => 'restricted',
                'points' => 490,
                'spend_aud' => 580.00,
                'owner_id' => $sophiaId,
            ],
            [
                'member_number' => 'GW-UK-1014',
                'golden_uid' => 'GW-GLD-UK-1014',
                'name' => 'Oliver Kensington',
                'email' => 'oliver.kensington@gourwest-guest.example',
                'phone' => '+44 7911 224890',
                'region_code' => 'UK',
                'home_outlet' => 'Manchester Spinningfields',
                'consent_status' => 'verified',
                'points' => 5680,
                'spend_aud' => 6950.00,
                'owner_id' => $nicoleId,
            ],
            [
                'member_number' => 'GW-CA-1015',
                'golden_uid' => 'GW-GLD-CA-1015',
                'name' => 'Élodie Tremblay',
                'email' => 'elodie.tremblay@gourwest-guest.example',
                'phone' => '+1 514 555 0194',
                'region_code' => 'CA',
                'home_outlet' => 'Montréal Old Port Atelier',
                'consent_status' => 'verified',
                'points' => 2450,
                'spend_aud' => 3120.00,
                'owner_id' => $nicoleId,
            ],
            [
                'member_number' => 'GW-CA-1016',
                'golden_uid' => 'GW-GLD-CA-1016',
                'name' => 'Marcus Chen-Bouchard',
                'email' => 'marcus.bouchard@gourwest-guest.example',
                'phone' => '+1 604 555 0148',
                'region_code' => 'CA',
                'home_outlet' => 'Vancouver Coal Harbour',
                'consent_status' => 'verified',
                'points' => 1120,
                'spend_aud' => 1410.00,
                'owner_id' => $emmaId,
            ],
        ];

        $profiles = [];

        foreach ($guests as $idx => $guest) {
            /** @var Person $person */
            $person = Person::query()->updateOrCreate(
                ['unique_id' => 'gw-member:'.$guest['member_number']],
                [
                    'name' => $guest['name'],
                    'emails' => [
                        ['value' => $guest['email'], 'label' => 'work'],
                    ],
                    'contact_numbers' => [
                        ['value' => $guest['phone'], 'label' => 'mobile'],
                    ],
                    'job_title' => 'Gourwest Loyalty Member ('.$guest['region_code'].')',
                    'organization_id' => $organization->id,
                    'user_id' => $guest['owner_id'],
                ]
            );

            /** @var CustomerProfile $profile */
            $profile = CustomerProfile::query()->updateOrCreate(
                ['loyalty_member_number' => $guest['member_number']],
                [
                    'person_id' => $person->id,
                    'owner_user_id' => $guest['owner_id'],
                    'golden_record_uid' => $guest['golden_uid'],
                    'region_code' => $guest['region_code'],
                    'home_outlet' => $guest['home_outlet'],
                    'privacy_regime' => RegionCatalog::privacyRegimeFor($guest['region_code']),
                    'consent_status' => $guest['consent_status'],
                    'loyalty_tier' => RegionCatalog::tierForPoints($guest['points']),
                    'loyalty_points' => $guest['points'],
                    'lifetime_spend_aud' => $guest['spend_aud'],
                    'is_primary_golden' => true,
                    'merged_into_profile_id' => null,
                    'last_synced_at' => now()->subMinutes(5 + $idx),
                ]
            );

            $profiles[$guest['member_number']] = $profile;
        }

        return $profiles;
    }

    /**
     * Seed 10 regional Customer Service SLA tickets across all categories and channels.
     *
     * @param  array<string, User>  $staff
     * @param  array<string, CustomerProfile>  $profiles
     */
    private function seedRegionalSlaTickets(array $staff, array $profiles): int
    {
        $emmaId = $staff['emma.taylor@gourwest.example']->id;
        $sophiaId = $staff['sophia.green@gourwest.example']->id;
        $rachelId = $staff['rachel.nguyen@gourwest.example']->id;
        $nicoleId = $staff['nicole.harper@gourwest.example']->id;
        $hannahId = $staff['hannah.brooks@gourwest.example']->id;
        $ethanId = $staff['ethan.carter@gourwest.example']->id;

        $tickets = [
            [
                'ticket_number' => 'GW-SLA-2001',
                'member' => 'GW-AU-1001',
                'user_id' => $emmaId,
                'region_code' => 'AU',
                'channel' => 'mobile_app',
                'category' => 'duplicate_account',
                'priority' => 'high',
                'status' => 'open',
                'subject' => 'Melbourne & Singapore loyalty wallets split across two member cards',
                'summary' => 'VIP traveller reports 1,850 Marina Bay Sands points missing from primary Melbourne Platinum progression.',
                'due_hours' => 4,
            ],
            [
                'ticket_number' => 'GW-SLA-2002',
                'member' => 'GW-UK-1003',
                'user_id' => $nicoleId,
                'region_code' => 'UK',
                'channel' => 'loyalty_desk',
                'category' => 'duplicate_account',
                'priority' => 'high',
                'status' => 'investigating',
                'subject' => 'Cross-border UK & Toronto mobile profiles share identical +44 mobile',
                'summary' => 'Guest requested single Golden Record consolidation under UK-GDPR & PIPEDA consent rules.',
                'due_hours' => 3,
            ],
            [
                'ticket_number' => 'GW-SLA-2003',
                'member' => 'GW-SG-1009',
                'user_id' => $rachelId,
                'region_code' => 'SG',
                'channel' => 'in_store_pos',
                'category' => 'loyalty_discrepancy',
                'priority' => 'critical',
                'status' => 'open',
                'subject' => 'Orchard ION Platinum voucher redemption delayed at POS terminal',
                'summary' => 'Real-time POS reward lookup exceeded legacy batch window prior to Luminara webhook cutover.',
                'due_hours' => 2,
            ],
            [
                'ticket_number' => 'GW-SLA-2004',
                'member' => 'GW-TH-1012',
                'user_id' => $rachelId,
                'region_code' => 'TH',
                'channel' => 'in_store_pos',
                'category' => 'order_sync',
                'priority' => 'high',
                'status' => 'open',
                'subject' => 'Bangkok Sukhumvit Terminal #04 offline flush pending replay',
                'summary' => 'Matched to dead-letter event GW-DLQ-TH-900901 awaiting one-click operator replay.',
                'due_hours' => 4,
            ],
            [
                'ticket_number' => 'GW-SLA-2005',
                'member' => 'GW-TH-1013',
                'user_id' => $ethanId,
                'region_code' => 'TH',
                'channel' => 'web_portal',
                'category' => 'privacy_request',
                'priority' => 'medium',
                'status' => 'investigating',
                'subject' => 'TH-PDPA marketing suppression & cross-border consent verification',
                'summary' => 'Guest exercised Thailand PDPA right to restrict promotional SMS while keeping loyalty receipts active.',
                'due_hours' => 12,
            ],
            [
                'ticket_number' => 'GW-SLA-2006',
                'member' => 'GW-NZ-1007',
                'user_id' => $hannahId,
                'region_code' => 'NZ',
                'channel' => 'contact_centre',
                'category' => 'delayed_response',
                'priority' => 'medium',
                'status' => 'resolved',
                'subject' => 'Auckland Viaduct catering tax invoice sync with ERP',
                'summary' => 'Resolved automatically once CDC ERP stream reconciled invoice GW-ERP-NZ-900104.',
                'due_hours' => 12,
            ],
            [
                'ticket_number' => 'GW-SLA-2007',
                'member' => 'GW-MY-1010',
                'user_id' => $rachelId,
                'region_code' => 'MY',
                'channel' => 'mobile_app',
                'category' => 'loyalty_discrepancy',
                'priority' => 'medium',
                'status' => 'open',
                'subject' => 'KL Pavilion double-points weekend bonus reconciliation',
                'summary' => 'Verifying MYR->AUD tier credit calculation on mobile ordering receipt.',
                'due_hours' => 8,
            ],
            [
                'ticket_number' => 'GW-SLA-2008',
                'member' => 'GW-CA-1015',
                'user_id' => $nicoleId,
                'region_code' => 'CA',
                'channel' => 'web_portal',
                'category' => 'privacy_request',
                'priority' => 'high',
                'status' => 'open',
                'subject' => 'Quebec Law 25 & PIPEDA bilingual data portability export request',
                'summary' => 'Montréal member requested verified JSON copy of unified order & loyalty history.',
                'due_hours' => 6,
            ],
            [
                'ticket_number' => 'GW-SLA-2009',
                'member' => 'GW-AU-1005',
                'user_id' => $hannahId,
                'region_code' => 'AU',
                'channel' => 'contact_centre',
                'category' => 'order_sync',
                'priority' => 'low',
                'status' => 'resolved',
                'subject' => 'Sydney Barangaroo corporate pre-order dietary note confirmation',
                'summary' => 'Confirmed real-time sync between Online Ordering and Sydney Barangaroo kitchen display.',
                'due_hours' => 24,
            ],
            [
                'ticket_number' => 'GW-SLA-2010',
                'member' => 'GW-UK-1014',
                'user_id' => $sophiaId,
                'region_code' => 'UK',
                'channel' => 'loyalty_desk',
                'category' => 'delayed_response',
                'priority' => 'medium',
                'status' => 'investigating',
                'subject' => 'Manchester Spinningfields VIP tasting invitation delivery check',
                'summary' => 'Checking Omnichannel Marketing Automation delivery receipt for Platinum tier cohort.',
                'due_hours' => 10,
            ],
        ];

        foreach ($tickets as $spec) {
            $profile = $profiles[$spec['member']] ?? null;
            $isResolved = $spec['status'] === 'resolved';

            ServiceTicket::query()->updateOrCreate(
                ['ticket_number' => $spec['ticket_number']],
                [
                    'person_id' => $profile?->person_id,
                    'user_id' => $spec['user_id'],
                    'region_code' => $spec['region_code'],
                    'channel' => $spec['channel'],
                    'category' => $spec['category'],
                    'priority' => $spec['priority'],
                    'status' => $spec['status'],
                    'subject' => $spec['subject'],
                    'summary' => $spec['summary'],
                    'sla_due_at' => now()->addHours($spec['due_hours']),
                    'resolved_at' => $isResolved ? now()->subMinutes(35) : null,
                ]
            );
        }

        return count($tickets);
    }

    private function seedLoyaltyOnboardingExperiment(int $ownerUserId): bool
    {
        if (! Schema::hasTable('experiments')) {
            return false;
        }

        /** @var Experiment $experiment */
        $experiment = Experiment::query()->updateOrCreate(
            ['code' => 'gourwest_loyalty_onboarding_v1'],
            [
                'name' => 'Gourwest 7-Region Loyalty Onboarding (Control vs Double-Points Welcome)',
                'description' => 'Evaluates standard loyalty enrollment vs 2x Welcome Points CTA across Gourwest regional WebForms.',
                'status' => Experiment::STATUS_RUNNING,
                'traffic_allocation' => 10000,
                'variants' => [
                    ['key' => 'control', 'name' => 'Control — Standard Loyalty Join', 'weight' => 5000],
                    ['key' => 'double_points_welcome', 'name' => 'Treatment — Double-Points First Order', 'weight' => 5000],
                ],
                'salt' => 'gw_loyalty_ab_26',
                'user_id' => $ownerUserId,
                'started_at' => now()->subDays(7),
                'ended_at' => null,
            ]
        );

        if (Schema::hasTable('experiment_events')) {
            $sampleCounts = [
                'control' => ['exposures' => 60, 'conversions' => 12, 'value' => 45.00],
                'double_points_welcome' => ['exposures' => 60, 'conversions' => 24, 'value' => 62.50],
            ];

            foreach ($sampleCounts as $variantKey => $metrics) {
                for ($i = 1; $i <= $metrics['exposures']; $i++) {
                    $subjectHash = hash('sha256', "gw-seed:{$variantKey}:{$i}");

                    ExperimentEvent::query()->updateOrCreate(
                        [
                            'experiment_id' => $experiment->id,
                            'subject_hash' => $subjectHash,
                            'event_type' => 'exposure',
                            'event_key' => 'default',
                        ],
                        [
                            'variant_key' => $variantKey,
                            'value' => 0,
                            'metadata' => ['source' => 'gourwest_seeder'],
                        ]
                    );

                    if ($i <= $metrics['conversions']) {
                        ExperimentEvent::query()->updateOrCreate(
                            [
                                'experiment_id' => $experiment->id,
                                'subject_hash' => $subjectHash,
                                'event_type' => 'conversion',
                                'event_key' => 'loyalty_signup',
                            ],
                            [
                                'variant_key' => $variantKey,
                                'value' => $metrics['value'],
                                'metadata' => ['source' => 'gourwest_seeder'],
                            ]
                        );
                    }
                }
            }
        }

        return true;
    }
}
