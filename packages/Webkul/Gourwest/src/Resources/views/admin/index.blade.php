<x-admin::layouts>
    <x-slot:title>
        @lang('gourwest::app.index.title')
    </x-slot>

    <v-gourwest-hub></v-gourwest-hub>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-gourwest-hub-template"
        >
            <div class="flex flex-col gap-4">
                <!-- Executive Header Bar -->
                <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-1">
                        <x-admin::breadcrumbs name="gourwest" />

                        <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                            @lang('gourwest::app.index.title')
                        </h1>

                        <p class="max-w-[65ch] text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                            @lang('gourwest::app.index.subtitle')
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="secondary-button"
                            :disabled="isLoading"
                            @click="fetchOverview"
                        >
                            @lang('gourwest::app.index.refresh-btn')
                        </button>

                        @if (bouncer()->hasPermission('gourwest.customers') && bouncer()->getAuthorizedUserIds() === null)
                            <button
                                type="button"
                                class="primary-button"
                                :disabled="isBusy"
                                @click="seedDemoDataset"
                            >
                                @lang('gourwest::app.index.seed-btn')
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Error Async State -->
                <div
                    v-if="loadError"
                    role="alert"
                    class="flex items-center justify-between gap-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                >
                    <span>@{{ loadError }}</span>

                    <button
                        type="button"
                        class="secondary-button"
                        @click="fetchOverview"
                    >
                        @lang('gourwest::app.index.retry-btn')
                    </button>
                </div>

                <!-- Loading Skeleton Async State -->
                <div
                    v-if="isLoading"
                    class="grid gap-4"
                    aria-busy="true"
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="n in 4"
                            :key="n"
                            class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div class="shimmer mb-2 h-3.5 w-32 rounded"></div>
                            <div class="shimmer mb-2 h-7 w-24 rounded"></div>
                            <div class="shimmer h-3 w-40 rounded"></div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                        <div class="shimmer mb-4 h-8 w-80 max-w-full rounded"></div>
                        <div class="shimmer h-48 w-full rounded"></div>
                    </div>
                </div>

                <!-- Empty Async State -->
                <div
                    v-else-if="!loadError && overview.kpis.total_profiles === 0 && overview.integrations.length === 0"
                    class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="text-base font-semibold text-gray-900 dark:text-white">
                        @lang('gourwest::app.index.empty-title')
                    </div>

                    <p class="max-w-[58ch] text-sm text-gray-600 dark:text-gray-400">
                        @lang('gourwest::app.index.empty-description')
                    </p>

                    @if (bouncer()->hasPermission('gourwest.customers') && bouncer()->getAuthorizedUserIds() === null)
                        <button
                            type="button"
                            class="primary-button mt-1"
                            :disabled="isBusy"
                            @click="seedDemoDataset"
                        >
                            @lang('gourwest::app.index.seed-btn')
                        </button>
                    @endif
                </div>

                <!-- Active Cockpit Async State -->
                <template v-else-if="!isLoading">
                    <!-- 4-Column Executive KPI Strip -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('gourwest::app.kpis.golden-records')
                            </div>
                            <div class="mt-1.5 flex items-baseline gap-2 tabular-nums">
                                <span class="text-2xl font-bold text-gray-900 dark:text-white">
                                    @{{ overview.kpis.golden_customers }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    / @{{ overview.kpis.total_profiles }} profiles
                                </span>
                            </div>
                            <div class="mt-1 text-xs tabular-nums" :class="overview.kpis.duplicate_clusters > 0 ? 'text-amber-700 dark:text-amber-400 font-medium' : 'text-emerald-700 dark:text-emerald-400'">
                                @{{ overview.kpis.duplicate_clusters }} duplicate cluster(s) pending
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('gourwest::app.kpis.loyalty-liability')
                            </div>
                            <div class="mt-1.5 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                                @{{ formatInt(overview.kpis.total_loyalty_points) }} pts
                            </div>
                            <div class="mt-1 text-xs tabular-nums text-gray-600 dark:text-gray-400">
                                AUD $@{{ formatCurrency(overview.kpis.total_lifetime_spend_aud) }} lifetime spend
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('gourwest::app.kpis.integrations-health')
                            </div>
                            <div class="mt-1.5 flex items-baseline gap-2 tabular-nums">
                                <span class="text-2xl font-bold text-gray-900 dark:text-white">
                                    @{{ overview.kpis.healthy_integrations }} / @{{ overview.kpis.total_integrations }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">online</span>
                            </div>
                            <div class="mt-1 text-xs tabular-nums" :class="overview.kpis.dead_letter_events > 0 ? 'text-red-700 dark:text-red-400 font-medium' : 'text-emerald-700 dark:text-emerald-400'">
                                @{{ overview.kpis.dead_letter_events }} dead-letter event(s)
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('gourwest::app.kpis.sla-latency')
                            </div>
                            <div class="mt-1.5 flex items-baseline gap-2 tabular-nums">
                                <span class="text-2xl font-bold" :class="overview.kpis.passes_3s_sla ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'">
                                    @{{ Number(overview.kpis.p95_latency_ms || 0).toFixed(1) }} ms
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">p95 (&lt;= 3,000 ms)</span>
                            </div>
                            <div class="mt-1 text-xs tabular-nums text-gray-600 dark:text-gray-400">
                                @{{ overview.kpis.open_sla_tickets }} open regional SLA ticket(s)
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs + 7-Region Filter Bar -->
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center gap-1.5" role="tablist">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                role="tab"
                                :aria-selected="activeTab === tab.key"
                                class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                :class="activeTab === tab.key ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                                @click="selectTab(tab.key)"
                            >
                                @{{ tab.label }}
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-1 text-xs tabular-nums">
                            <button
                                type="button"
                                class="rounded px-2 py-1 font-medium transition"
                                :class="selectedRegion === '' ? 'bg-teal-100 text-teal-900 dark:bg-teal-950/70 dark:text-teal-300' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                                @click="filterByRegion('')"
                            >
                                @lang('gourwest::app.customers.all-regions')
                            </button>
                            <button
                                v-for="reg in overview.regions"
                                :key="reg.code"
                                type="button"
                                class="rounded px-2 py-1 font-mono font-semibold transition"
                                :class="selectedRegion === reg.code ? 'bg-teal-100 text-teal-900 dark:bg-teal-950/70 dark:text-teal-300' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                                @click="filterByRegion(reg.code)"
                            >
                                @{{ reg.code }} (@{{ reg.golden_customers }})
                            </button>
                        </div>
                    </div>

                    <!-- TAB 1: UNIFIED 7-REGION CUSTOMERS & GOLDEN RECORDS -->
                    <div
                        v-if="activeTab === 'customers'"
                        class="flex flex-col gap-4"
                    >
                        <!-- Cross-Region Duplicate Alert Banner -->
                        <div
                            v-if="overview.duplicate_candidates.length > 0"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50/90 px-4 py-3 text-xs text-amber-950 dark:border-amber-800/70 dark:bg-amber-950/40 dark:text-amber-200"
                        >
                            <div class="flex flex-col gap-1">
                                <div class="font-bold">
                                    @lang('gourwest::app.customers.duplicates-banner') (@{{ overview.duplicate_candidates.length }})
                                </div>
                                <div class="flex flex-wrap gap-3 tabular-nums">
                                    <span
                                        v-for="dup in overview.duplicate_candidates"
                                        :key="dup.primary_profile_id"
                                        class="rounded bg-white/80 px-2 py-0.5 font-mono dark:bg-gray-900/70"
                                    >
                                        @{{ dup.customer_name }} [@{{ dup.regions.join(' + ') }}] &rarr; @{{ formatInt(dup.combined_points) }} pts (@{{ dup.projected_tier }})
                                    </span>
                                </div>
                            </div>

                            @if (bouncer()->hasPermission('gourwest.customers'))
                                <button
                                    type="button"
                                    class="primary-button"
                                    :disabled="isBusy"
                                    @click="deduplicateProfiles"
                                >
                                    @lang('gourwest::app.customers.deduplicate-btn')
                                </button>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <input
                                    v-model.trim="searchQuery"
                                    type="search"
                                    placeholder="@lang('gourwest::app.customers.search-placeholder')"
                                    class="w-full max-w-md rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    @keyup.enter="fetchOverview"
                                />

                                <button
                                    type="button"
                                    class="secondary-button"
                                    @click="fetchOverview"
                                >
                                    Search
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[820px] text-left text-xs tabular-nums">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-400">
                                            <th class="py-2 pr-3 font-semibold">Guest &amp; Golden UID</th>
                                            <th class="px-3 py-2 font-semibold">Region &amp; Outlet</th>
                                            <th class="px-3 py-2 font-semibold">Privacy &amp; Consent</th>
                                            <th class="px-3 py-2 font-semibold">Member Card &amp; Tier</th>
                                            <th class="px-3 py-2 font-semibold">Points</th>
                                            <th class="px-3 py-2 font-semibold">Spend (AUD)</th>
                                            <th class="py-2 pl-3 font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-gray-800 dark:text-gray-200">
                                        <tr
                                            v-for="cust in overview.customers"
                                            :key="cust.id"
                                            :class="cust.is_primary_golden ? '' : 'opacity-60'"
                                        >
                                            <td class="py-2.5 pr-3">
                                                <div class="font-semibold text-gray-900 dark:text-white">
                                                    @{{ cust.customer_name }}
                                                </div>
                                                <div class="mt-0.5 flex items-center gap-1.5 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                                    <span>@{{ cust.golden_record_uid }}</span>
                                                    <span
                                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                        :class="cust.is_primary_golden ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                                    >
                                                        @{{ cust.is_primary_golden ? labels.goldenBadge : labels.mergedBadge }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono font-bold dark:bg-gray-800">@{{ cust.region_code }}</span>
                                                <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">@{{ cust.home_outlet }}</div>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-gray-800">@{{ cust.privacy_regime }}</code>
                                                <div class="mt-0.5 text-[11px]" :class="cust.consent_status === 'verified' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'">
                                                    @{{ cust.consent_status }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <div class="font-mono font-medium">@{{ cust.loyalty_member_number }}</div>
                                                <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold" :class="tierBadgeClass(cust.loyalty_tier)">
                                                    @{{ cust.loyalty_tier }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 font-bold">@{{ formatInt(cust.loyalty_points) }}</td>
                                            <td class="px-3 py-2.5">$@{{ formatCurrency(cust.lifetime_spend_aud) }}</td>
                                            <td class="py-2.5 pl-3">
                                                @if (bouncer()->hasPermission('gourwest.loyalty'))
                                                    <button
                                                        v-if="cust.is_primary_golden"
                                                        type="button"
                                                        class="secondary-button"
                                                        @click="openLoyaltyModal(cust)"
                                                    >
                                                        @lang('gourwest::app.customers.adjust-points-btn')
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: 8-SYSTEM ENTERPRISE INTEGRATION HUB -->
                    <div
                        v-if="activeTab === 'integrations'"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-xs dark:border-gray-800 dark:bg-gray-900">
                            <div>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">@lang('gourwest::app.integrations.webhook-endpoint'):</span>
                                <code class="ml-1.5 rounded bg-gray-100 px-2 py-0.5 font-mono text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                    POST /api/gourwest/webhooks/{systemCode} (Header: X-Gourwest-Signature)
                                </code>
                            </div>

                            @if (bouncer()->hasPermission('gourwest.integrations'))
                                <button
                                    v-if="overview.kpis.dead_letter_events > 0"
                                    type="button"
                                    class="primary-button"
                                    :disabled="isBusy"
                                    @click="replayDeadLetters"
                                >
                                    @lang('gourwest::app.integrations.replay-btn') (@{{ overview.kpis.dead_letter_events }})
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div
                                v-for="sys in overview.integrations"
                                :key="sys.id"
                                class="flex flex-col justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-gray-900 dark:text-white">@{{ sys.name }}</span>
                                            <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-gray-800">@{{ sys.contract_version }}</code>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Owner: <strong class="text-gray-700 dark:text-gray-300">@{{ sys.vendor_owner }}</strong> &bull; Mode: <code>@{{ sys.sync_mode }}</code>
                                        </div>
                                    </div>

                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="sys.status === 'healthy' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                    >
                                        @{{ sys.status }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-xs tabular-nums dark:border-gray-800">
                                    <div class="flex items-center gap-4 text-gray-600 dark:text-gray-300">
                                        <span>Latency: <strong>@{{ sys.last_latency_ms }} ms</strong></span>
                                        <span>24h Synced: <strong>@{{ formatInt(sys.records_synced_24h) }}</strong></span>
                                        <span v-if="sys.failed_events_count > 0" class="font-semibold text-red-700 dark:text-red-400">
                                            DLQ: @{{ sys.failed_events_count }}
                                        </span>
                                    </div>

                                    @if (bouncer()->hasPermission('gourwest.integrations'))
                                        <button
                                            type="button"
                                            class="secondary-button"
                                            :disabled="isBusy"
                                            @click="syncSingleIntegration(sys)"
                                        >
                                            @lang('gourwest::app.integrations.sync-btn')
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="mb-3 text-sm font-bold text-gray-900 dark:text-white">
                                @lang('gourwest::app.integrations.recent-events')
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[680px] text-left text-xs tabular-nums">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-400">
                                            <th class="py-2 pr-3">System</th>
                                            <th class="px-3 py-2">Region</th>
                                            <th class="px-3 py-2">Event Type</th>
                                            <th class="px-3 py-2">External Ref</th>
                                            <th class="px-3 py-2">Status</th>
                                            <th class="py-2 pl-3">Latency</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-gray-800 dark:text-gray-200">
                                        <tr v-for="ev in overview.sync_events" :key="ev.id">
                                            <td class="py-2 pr-3 font-medium">@{{ ev.system_name || ev.system_code }}</td>
                                            <td class="px-3 py-2 font-mono">@{{ ev.region_code }}</td>
                                            <td class="px-3 py-2 font-mono">@{{ ev.event_type }}</td>
                                            <td class="px-3 py-2 font-mono">@{{ ev.external_ref }}</td>
                                            <td class="px-3 py-2">
                                                <span
                                                    class="rounded px-2 py-0.5 text-[11px] font-semibold"
                                                    :class="ev.status === 'dead_letter' ? 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'"
                                                >
                                                    @{{ ev.status }}
                                                </span>
                                                <div v-if="ev.error_message" class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">
                                                    @{{ ev.error_message }}
                                                </div>
                                            </td>
                                            <td class="py-2 pl-3">@{{ ev.latency_ms }} ms</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: LOYALTY & REGIONAL SLA DESK -->
                    <div
                        v-if="activeTab === 'sla'"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-sm font-bold text-gray-900 dark:text-white">
                                @lang('gourwest::app.tabs.sla')
                            </div>

                            @if (bouncer()->hasPermission('gourwest.sla'))
                                <button
                                    type="button"
                                    class="primary-button"
                                    @click="openTicketModal"
                                >
                                    @lang('gourwest::app.sla.new-ticket-btn')
                                </button>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[780px] text-left text-xs tabular-nums">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-400">
                                            <th class="py-2 pr-3">Ticket #</th>
                                            <th class="px-3 py-2">Region &amp; Channel</th>
                                            <th class="px-3 py-2">Category &amp; Priority</th>
                                            <th class="px-3 py-2">Subject &amp; Guest</th>
                                            <th class="px-3 py-2">Owner</th>
                                            <th class="px-3 py-2">Status</th>
                                            <th class="py-2 pl-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-gray-800 dark:text-gray-200">
                                        <tr v-for="tkt in overview.tickets" :key="tkt.id">
                                            <td class="py-2.5 pr-3 font-mono font-bold">@{{ tkt.ticket_number }}</td>
                                            <td class="px-3 py-2.5">
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono font-bold dark:bg-gray-800">@{{ tkt.region_code }}</span>
                                                <div class="mt-0.5 text-[11px] text-gray-500">@{{ tkt.channel }}</div>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <div class="font-medium">@{{ tkt.category }}</div>
                                                <span class="mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="priorityBadgeClass(tkt.priority)">
                                                    @{{ tkt.priority }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 max-w-xs">
                                                <div class="font-semibold text-gray-900 dark:text-white">@{{ tkt.subject }}</div>
                                                <div v-if="tkt.customer_name" class="text-[11px] text-gray-500">Guest: @{{ tkt.customer_name }}</div>
                                            </td>
                                            <td class="px-3 py-2.5 text-[11px]">@{{ tkt.owner_name || '—' }}</td>
                                            <td class="px-3 py-2.5">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                    :class="tkt.status === 'resolved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                                >
                                                    @{{ tkt.status }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 pl-3">
                                                @if (bouncer()->hasPermission('gourwest.sla'))
                                                    <button
                                                        v-if="tkt.status !== 'resolved'"
                                                        type="button"
                                                        class="secondary-button"
                                                        :disabled="isBusy"
                                                        @click="resolveTicket(tkt)"
                                                    >
                                                        @lang('gourwest::app.sla.resolve-btn')
                                                    </button>
                                                    <span v-else class="text-emerald-700 dark:text-emerald-400 font-semibold">
                                                        @lang('gourwest::app.sla.resolved-label')
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: SERVICE WARRANTY & <3S BENCHMARK -->
                    <div
                        v-if="activeTab === 'benchmark'"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex flex-wrap items-center gap-3 tabular-nums">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold"
                                    :class="overview.benchmark.passes_3s_sla ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300'"
                                >
                                    @{{ overview.benchmark.passes_3s_sla ? labels.slaPass : labels.slaFail }}
                                </span>
                                <span class="text-xs text-gray-700 dark:text-gray-300">
                                    p50: <strong>@{{ Number(overview.benchmark.p50_ms || 0).toFixed(2) }} ms</strong> &bull;
                                    p95: <strong>@{{ Number(overview.benchmark.p95_ms || 0).toFixed(2) }} ms</strong> &bull;
                                    p99: <strong>@{{ Number(overview.benchmark.p99_ms || 0).toFixed(2) }} ms</strong>
                                    (n=@{{ overview.benchmark.samples }})
                                </span>
                            </div>

                            @if (bouncer()->hasPermission('gourwest.benchmark'))
                                <button
                                    type="button"
                                    class="primary-button"
                                    :disabled="isBusy"
                                    @click="runLiveBenchmark"
                                >
                                    @lang('gourwest::app.benchmark.run-btn')
                                </button>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="mb-3 text-sm font-bold text-gray-900 dark:text-white">
                                @lang('gourwest::app.benchmark.index-audit-title')
                            </h2>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs tabular-nums">
                                <div
                                    v-for="(audit, tableName) in overview.benchmark.index_audit"
                                    :key="tableName"
                                    class="rounded border border-gray-200 p-3 dark:border-gray-800"
                                >
                                    <div class="font-mono font-bold text-gray-900 dark:text-white">@{{ tableName }}</div>
                                    <div class="mt-1 text-gray-600 dark:text-gray-400">Indexes: @{{ audit.index_count }}</div>
                                    <div class="mt-1 font-semibold" :class="audit.ready ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'">
                                        @{{ audit.ready ? '5M Composite Ready' : 'Needs Index Review' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="mb-3 text-sm font-bold text-gray-900 dark:text-white">
                                @lang('gourwest::app.benchmark.privacy-matrix-title')
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-left text-xs tabular-nums">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-400">
                                            <th class="py-2 pr-3">Region</th>
                                            <th class="px-3 py-2">Privacy Statute</th>
                                            <th class="px-3 py-2">Regional Hub</th>
                                            <th class="px-3 py-2">Sovereignty Partition</th>
                                            <th class="py-2 pl-3">Golden Profiles</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-gray-800 dark:text-gray-200">
                                        <tr v-for="reg in overview.regions" :key="reg.code">
                                            <td class="py-2 pr-3 font-semibold">@{{ reg.name }} (<code>@{{ reg.code }}</code>)</td>
                                            <td class="px-3 py-2">@{{ reg.privacy_statute }}</td>
                                            <td class="px-3 py-2">@{{ reg.hub_name }}</td>
                                            <td class="px-3 py-2 font-mono text-[11px]">@{{ reg.data_residency }}</td>
                                            <td class="py-2 pl-3 font-bold">@{{ reg.golden_customers }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Loyalty Adjustment Modal -->
                <div
                    v-if="showLoyaltyModal"
                    class="fixed inset-0 z-[10002] flex items-center justify-center bg-gray-950/50 p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-3 text-base font-bold text-gray-900 dark:text-white">
                            @lang('gourwest::app.sla.loyalty-modal-title')
                        </h3>
                        <form class="grid gap-3 text-xs" @submit.prevent="submitLoyaltyAdjustment">
                            <div>
                                <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.points-delta') *</label>
                                <input
                                    v-model.number="loyaltyForm.points_delta"
                                    type="number"
                                    required
                                    class="w-full rounded border border-gray-300 bg-white px-3 py-2 tabular-nums dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.reason') *</label>
                                <input
                                    v-model.trim="loyaltyForm.reason"
                                    type="text"
                                    required
                                    maxlength="255"
                                    class="w-full rounded border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />
                            </div>
                            <div class="mt-2 flex justify-end gap-2">
                                <button type="button" class="secondary-button" @click="showLoyaltyModal = false">@lang('gourwest::app.sla.cancel-btn')</button>
                                <button type="submit" class="primary-button" :disabled="isBusy">@lang('gourwest::app.sla.save-btn')</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Create SLA Ticket Modal -->
                <div
                    v-if="showTicketModal"
                    class="fixed inset-0 z-[10002] flex items-center justify-center bg-gray-950/50 p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="w-full max-w-lg rounded-lg border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-3 text-base font-bold text-gray-900 dark:text-white">
                            @lang('gourwest::app.sla.ticket-modal-title')
                        </h3>
                        <form class="grid gap-3 text-xs" @submit.prevent="submitTicket">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.region') *</label>
                                    <select v-model="ticketForm.region_code" class="w-full rounded border border-gray-300 bg-white px-2.5 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                        <option v-for="code in ['AU','NZ','SG','MY','TH','UK','CA']" :key="code" :value="code">@{{ code }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.priority') *</label>
                                    <select v-model="ticketForm.priority" class="w-full rounded border border-gray-300 bg-white px-2.5 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                        <option value="low">low</option>
                                        <option value="medium">medium</option>
                                        <option value="high">high</option>
                                        <option value="critical">critical</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.channel') *</label>
                                    <select v-model="ticketForm.channel" class="w-full rounded border border-gray-300 bg-white px-2.5 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                        <option value="mobile_app">mobile_app</option>
                                        <option value="in_store_pos">in_store_pos</option>
                                        <option value="web_portal">web_portal</option>
                                        <option value="contact_centre">contact_centre</option>
                                        <option value="loyalty_desk">loyalty_desk</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.category') *</label>
                                    <select v-model="ticketForm.category" class="w-full rounded border border-gray-300 bg-white px-2.5 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                        <option value="loyalty_discrepancy">loyalty_discrepancy</option>
                                        <option value="duplicate_account">duplicate_account</option>
                                        <option value="delayed_response">delayed_response</option>
                                        <option value="order_sync">order_sync</option>
                                        <option value="privacy_request">privacy_request</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.subject') *</label>
                                <input v-model.trim="ticketForm.subject" type="text" required maxlength="191" class="w-full rounded border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                            </div>
                            <div>
                                <label class="mb-1 block font-semibold text-gray-700 dark:text-gray-300">@lang('gourwest::app.sla.summary')</label>
                                <textarea v-model.trim="ticketForm.summary" rows="2" maxlength="2000" class="w-full rounded border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white"></textarea>
                            </div>
                            <div class="mt-2 flex justify-end gap-2">
                                <button type="button" class="secondary-button" @click="showTicketModal = false">@lang('gourwest::app.sla.cancel-btn')</button>
                                <button type="submit" class="primary-button" :disabled="isBusy">@lang('gourwest::app.sla.save-btn')</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-gourwest-hub', {
                template: '#v-gourwest-hub-template',

                data() {
                    const savedTab = window.localStorage.getItem('gourwest_hub:active_tab') || 'customers';

                    return {
                        activeTab: ['customers', 'integrations', 'sla', 'benchmark'].includes(savedTab) ? savedTab : 'customers',
                        selectedRegion: '',
                        searchQuery: '',
                        isLoading: true,
                        isBusy: false,
                        loadError: '',
                        showLoyaltyModal: false,
                        showTicketModal: false,
                        loyaltyForm: { profile_id: null, points_delta: 250, reason: 'Cross-border goodwill credit' },
                        ticketForm: {
                            region_code: 'AU',
                            channel: 'mobile_app',
                            category: 'loyalty_discrepancy',
                            priority: 'high',
                            subject: '',
                            summary: '',
                        },
                        overview: {
                            kpis: {
                                golden_customers: 0,
                                total_profiles: 0,
                                duplicate_clusters: 0,
                                total_loyalty_points: 0,
                                total_lifetime_spend_aud: 0,
                                healthy_integrations: 0,
                                total_integrations: 0,
                                dead_letter_events: 0,
                                open_sla_tickets: 0,
                                p95_latency_ms: 0,
                                passes_3s_sla: true,
                            },
                            regions: [],
                            customers: [],
                            duplicate_candidates: [],
                            integrations: [],
                            sync_events: [],
                            tickets: [],
                            benchmark: { index_audit: {}, passes_3s_sla: true, samples: 25 },
                        },
                        tabs: [
                            { key: 'customers', label: @json(trans('gourwest::app.tabs.customers')) },
                            { key: 'integrations', label: @json(trans('gourwest::app.tabs.integrations')) },
                            { key: 'sla', label: @json(trans('gourwest::app.tabs.sla')) },
                            { key: 'benchmark', label: @json(trans('gourwest::app.tabs.benchmark')) },
                        ],
                        labels: {
                            loadFailed: @json(trans('gourwest::app.index.load-error')),
                            goldenBadge: @json(trans('gourwest::app.customers.golden-badge')),
                            mergedBadge: @json(trans('gourwest::app.customers.merged-badge')),
                            slaPass: @json(trans('gourwest::app.benchmark.sla-pass')),
                            slaFail: @json(trans('gourwest::app.benchmark.sla-fail')),
                        },
                    };
                },

                mounted() {
                    this.fetchOverview();
                },

                methods: {
                    selectTab(key) {
                        this.activeTab = key;
                        window.localStorage.setItem('gourwest_hub:active_tab', key);
                    },

                    filterByRegion(code) {
                        this.selectedRegion = code;
                        this.fetchOverview();
                    },

                    formatInt(val) {
                        return Number(val || 0).toLocaleString();
                    },

                    formatCurrency(val) {
                        return Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },

                    tierBadgeClass(tier) {
                        const map = {
                            Platinum: 'bg-slate-900 text-white dark:bg-teal-400 dark:text-gray-950',
                            Gold: 'bg-amber-100 text-amber-900 dark:bg-amber-950/70 dark:text-amber-300',
                            Silver: 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                            Bronze: 'bg-orange-100 text-orange-900 dark:bg-orange-950/60 dark:text-orange-300',
                        };

                        return map[tier] || map.Bronze;
                    },

                    priorityBadgeClass(priority) {
                        const map = {
                            critical: 'bg-red-100 text-red-800 dark:bg-red-950/70 dark:text-red-300',
                            high: 'bg-amber-100 text-amber-900 dark:bg-amber-950/70 dark:text-amber-300',
                            medium: 'bg-sky-100 text-sky-900 dark:bg-sky-950/70 dark:text-sky-300',
                            low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                        };

                        return map[priority] || map.medium;
                    },

                    fetchOverview() {
                        this.isLoading = true;
                        this.loadError = '';

                        this.$axios.get("{{ route('admin.gourwest.overview') }}", {
                            params: { region: this.selectedRegion, search: this.searchQuery },
                        })
                            .then((res) => {
                                if (res.data && res.data.data) {
                                    this.overview = res.data.data;
                                }
                            })
                            .catch(() => {
                                this.loadError = this.labels.loadFailed;
                            })
                            .finally(() => {
                                this.isLoading = false;
                            });
                    },

                    runAction(promise, onSuccess) {
                        this.isBusy = true;

                        promise
                            .then((res) => {
                                if (res.data?.message) {
                                    this.$emitter.emit('add-flash', { type: 'success', message: res.data.message });
                                }
                                if (onSuccess) {
                                    onSuccess(res.data);
                                }
                                this.fetchOverview();
                            })
                            .catch((err) => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: err.response?.data?.message || this.labels.loadFailed,
                                });
                            })
                            .finally(() => {
                                this.isBusy = false;
                            });
                    },

                    seedDemoDataset() {
                        this.runAction(this.$axios.post("{{ route('admin.gourwest.seed') }}"));
                    },

                    deduplicateProfiles() {
                        this.runAction(this.$axios.post("{{ route('admin.gourwest.deduplicate') }}"));
                    },

                    syncSingleIntegration(sys) {
                        const url = "{{ route('admin.gourwest.index') }}/integrations/" + sys.id + '/sync';
                        this.runAction(this.$axios.post(url, { region_code: this.selectedRegion || 'AU' }));
                    },

                    replayDeadLetters() {
                        this.runAction(this.$axios.post("{{ route('admin.gourwest.integrations.replay') }}"));
                    },

                    openLoyaltyModal(cust) {
                        this.loyaltyForm = {
                            profile_id: cust.id,
                            points_delta: 500,
                            reason: 'Cross-border loyalty reconciliation (' + cust.region_code + ')',
                        };
                        this.showLoyaltyModal = true;
                    },

                    submitLoyaltyAdjustment() {
                        this.runAction(
                            this.$axios.post("{{ route('admin.gourwest.loyalty.adjust') }}", this.loyaltyForm),
                            () => { this.showLoyaltyModal = false; }
                        );
                    },

                    openTicketModal() {
                        this.ticketForm.region_code = this.selectedRegion || 'AU';
                        this.ticketForm.subject = '';
                        this.ticketForm.summary = '';
                        this.showTicketModal = true;
                    },

                    submitTicket() {
                        this.runAction(
                            this.$axios.post("{{ route('admin.gourwest.tickets.store') }}", this.ticketForm),
                            () => { this.showTicketModal = false; }
                        );
                    },

                    resolveTicket(tkt) {
                        const url = "{{ route('admin.gourwest.index') }}/tickets/" + tkt.id + '/resolve';
                        this.runAction(this.$axios.post(url, { resolution_note: 'Verified and closed via Gourwest SLA Desk.' }));
                    },

                    runLiveBenchmark() {
                        this.runAction(this.$axios.post("{{ route('admin.gourwest.benchmark') }}"));
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
