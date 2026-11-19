<x-admin::layouts>
    <x-slot:title>
        @lang('experiment::app.index.title')
    </x-slot>

    <v-experiments></v-experiments>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-experiments-template"
        >
            <div class="flex flex-col gap-4">
                <!-- Header Bar -->
                <div class="flex flex-wrap items-center justify-between gap-2.5 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="flex flex-col gap-1">
                        <x-admin::breadcrumbs name="settings.experiments" />

                        <div class="text-xl font-bold text-gray-900 dark:text-white">
                            @lang('experiment::app.index.title')
                        </div>

                        <p class="max-w-[65ch] text-xs text-gray-600 dark:text-gray-400">
                            @lang('experiment::app.index.subtitle')
                        </p>
                    </div>

                    @if (bouncer()->hasPermission('settings.other_settings.experiments.create'))
                        <button
                            type="button"
                            class="primary-button"
                            @click="openCreateModal"
                        >
                            @lang('experiment::app.index.create-btn')
                        </button>
                    @endif
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
                        @click="fetchExperiments"
                    >
                        @lang('experiment::app.index.retry-btn')
                    </button>
                </div>

                <!-- Loading Skeleton Async State -->
                <div
                    v-if="isLoading"
                    class="grid gap-4"
                    aria-busy="true"
                >
                    <div
                        v-for="n in 2"
                        :key="n"
                        class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="shimmer mb-3 h-5 w-56 rounded"></div>
                        <div class="shimmer mb-4 h-3.5 w-96 max-w-full rounded"></div>
                        <div class="shimmer h-24 w-full rounded"></div>
                    </div>
                </div>

                <!-- Empty Async State -->
                <div
                    v-else-if="!loadError && experiments.length === 0"
                    class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="text-base font-semibold text-gray-900 dark:text-white">
                        @lang('experiment::app.index.empty-title')
                    </div>

                    <p class="max-w-[52ch] text-sm text-gray-600 dark:text-gray-400">
                        @lang('experiment::app.index.empty-description')
                    </p>

                    @if (bouncer()->hasPermission('settings.other_settings.experiments.create'))
                        <button
                            type="button"
                            class="primary-button mt-1"
                            @click="openCreateModal"
                        >
                            @lang('experiment::app.index.create-btn')
                        </button>
                    @endif
                </div>

                <!-- Success Telemetry Cards -->
                <div
                    v-else-if="!isLoading"
                    class="grid gap-4"
                >
                    <div
                        v-for="exp in experiments"
                        :key="exp.id"
                        class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                            <div class="flex flex-col gap-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-bold text-gray-900 dark:text-white">
                                        @{{ exp.name }}
                                    </span>

                                    <code class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        @{{ exp.code }}
                                    </code>

                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="statusBadgeClass(exp.status)"
                                    >
                                        @{{ statusLabel(exp.status) }}
                                    </span>

                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium tabular-nums"
                                        :class="exp.stats.sample_adequate ? 'bg-teal-50 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                    >
                                        @{{ exp.stats.sample_adequate ? labels.sampleReady : labels.sampleLow }}
                                    </span>

                                    <span
                                        v-if="exp.stats.srm_warning"
                                        class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-950/60 dark:text-red-300"
                                    >
                                        @lang('experiment::app.index.srm-warning') (p=@{{ exp.stats.srm_p_value }})
                                    </span>
                                </div>

                                <p
                                    v-if="exp.description"
                                    class="max-w-[65ch] text-xs text-gray-600 dark:text-gray-400"
                                >
                                    @{{ exp.description }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 text-xs tabular-nums">
                                <span class="rounded border border-gray-200 px-2.5 py-1 text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                    @lang('experiment::app.index.traffic'): @{{ (exp.traffic_allocation / 100).toFixed(1) }}%
                                </span>

                                @if (bouncer()->hasPermission('settings.other_settings.experiments.edit'))
                                    <button
                                        type="button"
                                        class="secondary-button"
                                        @click="openEditModal(exp)"
                                    >
                                        @lang('experiment::app.index.edit-btn')
                                    </button>
                                @endif

                                @if (bouncer()->hasPermission('settings.other_settings.experiments.delete'))
                                    <button
                                        type="button"
                                        class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40"
                                        @click="removeExperiment(exp)"
                                    >
                                        @lang('experiment::app.index.delete-btn')
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Variant Telemetry Table -->
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[640px] text-left text-xs tabular-nums">
                                <thead>
                                    <tr class="border-b border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-400">
                                        <th class="py-2 pr-3 font-semibold">@lang('experiment::app.table.variant')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.split')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.exposures')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.conversions')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.conv-rate')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.lift')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.p-value')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.ci-95')</th>
                                        <th class="px-3 py-2 font-semibold">@lang('experiment::app.table.prob-beat')</th>
                                        <th class="py-2 pl-3 font-semibold">@lang('experiment::app.table.value')</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                                    <tr
                                        v-for="row in exp.stats.variants"
                                        :key="row.key"
                                    >
                                        <td class="py-2.5 pr-3 font-medium">
                                            <span>@{{ row.name }}</span>
                                            <code class="ml-1.5 text-[11px] text-gray-500 dark:text-gray-400">(@{{ row.key }})</code>
                                            <span
                                                v-if="exp.winner_variant_key === row.key"
                                                class="ml-1.5 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                            >★</span>
                                        </td>
                                        <td class="px-3 py-2.5">@{{ (row.weight / 100).toFixed(2) }}%</td>
                                        <td class="px-3 py-2.5">@{{ row.exposures }}</td>
                                        <td class="px-3 py-2.5">@{{ row.conversions }}</td>
                                        <td class="px-3 py-2.5 font-semibold">@{{ row.conversion_rate.toFixed(2) }}%</td>
                                        <td class="px-3 py-2.5">
                                            <span v-if="row.is_control" class="text-gray-500 dark:text-gray-400">
                                                @lang('experiment::app.table.baseline')
                                            </span>
                                            <span
                                                v-else-if="row.relative_lift !== null"
                                                :class="row.relative_lift >= 0 ? 'text-teal-700 dark:text-teal-400 font-semibold' : 'text-red-700 dark:text-red-400 font-semibold'"
                                            >
                                                @{{ row.relative_lift >= 0 ? '+' : '' }}@{{ row.relative_lift.toFixed(2) }}%
                                            </span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span v-if="row.p_value_adjusted !== null && row.p_value_adjusted !== undefined">@{{ row.p_value_adjusted.toFixed(4) }}</span>
                                            <span v-else-if="row.p_value !== null">@{{ row.p_value.toFixed(4) }}</span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span v-if="row.ci_95_low !== null && row.ci_95_low !== undefined">[@{{ row.ci_95_low.toFixed(2) }}, @{{ row.ci_95_high.toFixed(2) }}] pp</span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span v-if="row.prob_beat_control !== null && row.prob_beat_control !== undefined">@{{ row.prob_beat_control.toFixed(1) }}%</span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="py-2.5 pl-3">@{{ row.total_value.toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Create / Edit Modal -->
                <div
                    v-if="showModal"
                    class="fixed inset-0 z-[10002] flex items-center justify-center bg-gray-950/50 p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-lg border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">
                                @{{ editingId ? labels.editTitle : labels.createTitle }}
                            </h2>

                            <button
                                type="button"
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                @click="closeModal"
                            >
                                ✕
                            </button>
                        </div>

                        <div
                            v-if="formError"
                            role="alert"
                            class="mb-4 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                        >
                            @{{ formError }}
                        </div>

                        <form
                            class="grid gap-3.5 text-sm"
                            @submit.prevent="submitForm"
                        >
                            <div class="grid gap-1">
                                <label for="exp-name" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    @lang('experiment::app.form.name') *
                                </label>
                                <input
                                    id="exp-name"
                                    v-model.trim="form.name"
                                    type="text"
                                    required
                                    maxlength="120"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="grid gap-1">
                                    <label for="exp-code" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        @lang('experiment::app.form.code') *
                                    </label>
                                    <input
                                        id="exp-code"
                                        v-model.trim="form.code"
                                        type="text"
                                        required
                                        :disabled="isLocked"
                                        pattern="^[a-z0-9_]+$"
                                        maxlength="64"
                                        placeholder="webform_hero_cta"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-900 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />
                                </div>

                                <div class="grid gap-1">
                                    <label for="exp-status" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        @lang('experiment::app.form.status') *
                                    </label>
                                    <select
                                        id="exp-status"
                                        v-model="form.status"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    >
                                        <option value="draft">@lang('experiment::app.statuses.draft')</option>
                                        <option value="running">@lang('experiment::app.statuses.running')</option>
                                        <option value="paused">@lang('experiment::app.statuses.paused')</option>
                                        <option value="concluded">@lang('experiment::app.statuses.concluded')</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-1">
                                <label for="exp-traffic" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    @lang('experiment::app.form.traffic-bp') (0–10000) *
                                </label>
                                <input
                                    id="exp-traffic"
                                    v-model.number="form.traffic_allocation"
                                    type="number"
                                    min="0"
                                    max="10000"
                                    required
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm tabular-nums text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />
                            </div>

                            <div
                                v-if="form.status === 'concluded'"
                                class="grid gap-1"
                            >
                                <label for="exp-winner" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    @lang('experiment::app.form.winner')
                                </label>
                                <select
                                    id="exp-winner"
                                    v-model="form.winner_variant_key"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                                    <option :value="null">@lang('experiment::app.form.winner-none')</option>
                                    <option
                                        v-for="v in form.variants"
                                        :key="v.key"
                                        :value="v.key"
                                    >
                                        @{{ v.name }} (@{{ v.key }})
                                    </option>
                                </select>
                            </div>

                            <div class="grid gap-1">
                                <label for="exp-desc" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    @lang('experiment::app.form.description')
                                </label>
                                <textarea
                                    id="exp-desc"
                                    v-model.trim="form.description"
                                    rows="2"
                                    maxlength="1000"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                ></textarea>
                            </div>

                            <div class="grid gap-2 rounded-md border border-gray-200 p-3 dark:border-gray-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        @lang('experiment::app.form.variants') (@{{ totalVariantWeight }} / 10000 bp)
                                    </span>

                                    <button
                                        v-if="!isLocked"
                                        type="button"
                                        class="text-xs font-semibold text-brandColor hover:underline dark:text-teal-400"
                                        @click="addVariant"
                                    >
                                        + @lang('experiment::app.form.add-variant')
                                    </button>
                                </div>

                                <div
                                    v-for="(variant, idx) in form.variants"
                                    :key="idx"
                                    class="grid grid-cols-12 items-center gap-2"
                                >
                                    <input
                                        v-model.trim="variant.key"
                                        type="text"
                                        required
                                        :disabled="isLocked"
                                        pattern="^[a-z0-9_]+$"
                                        placeholder="control"
                                        aria-label="Variant key"
                                        class="col-span-4 rounded border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-xs text-gray-900 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />
                                    <input
                                        v-model.trim="variant.name"
                                        type="text"
                                        required
                                        placeholder="Control"
                                        aria-label="Variant name"
                                        class="col-span-5 rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />
                                    <input
                                        v-model.number="variant.weight"
                                        type="number"
                                        min="1"
                                        max="9999"
                                        required
                                        :disabled="isLocked"
                                        aria-label="Variant weight"
                                        class="col-span-2 rounded border border-gray-300 bg-white px-2 py-1.5 text-xs tabular-nums text-gray-900 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />
                                    <button
                                        v-if="!isLocked && form.variants.length > 2"
                                        type="button"
                                        class="col-span-1 text-center text-xs text-red-600 hover:underline dark:text-red-400"
                                        @click="form.variants.splice(idx, 1)"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="secondary-button"
                                    @click="closeModal"
                                >
                                    @lang('experiment::app.form.cancel')
                                </button>

                                <button
                                    type="submit"
                                    class="primary-button"
                                    :disabled="isSaving"
                                >
                                    @lang('experiment::app.form.save')
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-experiments', {
                template: '#v-experiments-template',

                data() {
                    return {
                        experiments: [],
                        isLoading: true,
                        loadError: '',
                        showModal: false,
                        isSaving: false,
                        editingId: null,
                        isLocked: false,
                        formError: '',
                        form: this.defaultForm(),
                        labels: {
                            createTitle: @json(trans('experiment::app.form.create-title')),
                            editTitle: @json(trans('experiment::app.form.edit-title')),
                            sampleReady: @json(trans('experiment::app.index.sample-ready')),
                            sampleLow: @json(trans('experiment::app.index.sample-low')),
                            loadFailed: @json(trans('experiment::app.index.load-error')),
                            confirmDelete: @json(trans('experiment::app.index.delete-confirm')),
                            statuses: {
                                draft: @json(trans('experiment::app.statuses.draft')),
                                running: @json(trans('experiment::app.statuses.running')),
                                paused: @json(trans('experiment::app.statuses.paused')),
                                concluded: @json(trans('experiment::app.statuses.concluded')),
                            },
                        },
                    };
                },

                computed: {
                    totalVariantWeight() {
                        return this.form.variants.reduce((acc, item) => acc + (Number(item.weight) || 0), 0);
                    },
                },

                mounted() {
                    this.fetchExperiments();
                },

                methods: {
                    defaultForm() {
                        return {
                            name: '',
                            code: '',
                            description: '',
                            status: 'draft',
                            traffic_allocation: 10000,
                            winner_variant_key: null,
                            variants: [
                                { key: 'control', name: 'Control (A)', weight: 5000 },
                                { key: 'treatment', name: 'Treatment (B)', weight: 5000 },
                            ],
                        };
                    },

                    statusLabel(status) {
                        return this.labels.statuses[status] || status;
                    },

                    statusBadgeClass(status) {
                        const map = {
                            running: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
                            paused: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
                            concluded: 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300',
                            draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                        };

                        return map[status] || map.draft;
                    },

                    fetchExperiments() {
                        this.isLoading = true;
                        this.loadError = '';

                        this.$axios.get("{{ route('admin.settings.experiments.index') }}", {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        })
                            .then((response) => {
                                this.experiments = response.data.data || [];
                            })
                            .catch(() => {
                                this.loadError = this.labels.loadFailed;
                            })
                            .finally(() => {
                                this.isLoading = false;
                            });
                    },

                    openCreateModal() {
                        this.editingId = null;
                        this.isLocked = false;
                        this.formError = '';
                        this.form = this.defaultForm();
                        this.showModal = true;
                    },

                    openEditModal(exp) {
                        this.editingId = exp.id;
                        this.isLocked = Boolean(exp.started_at || (exp.stats && exp.stats.total_exposures > 0));
                        this.formError = '';
                        this.form = {
                            name: exp.name,
                            code: exp.code,
                            description: exp.description || '',
                            status: exp.status,
                            traffic_allocation: exp.traffic_allocation,
                            winner_variant_key: exp.winner_variant_key || null,
                            variants: JSON.parse(JSON.stringify(exp.variants)),
                        };
                        this.showModal = true;
                    },

                    closeModal() {
                        this.showModal = false;
                        this.formError = '';
                    },

                    addVariant() {
                        if (!this.isLocked && this.form.variants.length < 10) {
                            this.form.variants.push({
                                key: 'variant_' + (this.form.variants.length + 1),
                                name: 'Variant ' + (this.form.variants.length + 1),
                                weight: 1,
                            });
                        }
                    },

                    submitForm() {
                        this.isSaving = true;
                        this.formError = '';

                        const url = this.editingId
                            ? "{{ route('admin.settings.experiments.index') }}/" + this.editingId
                            : "{{ route('admin.settings.experiments.store') }}";

                        const method = this.editingId ? 'put' : 'post';

                        this.$axios[method](url, this.form)
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                this.closeModal();
                                this.fetchExperiments();
                            })
                            .catch((error) => {
                                const errors = error.response?.data?.errors;
                                this.formError = errors
                                    ? Object.values(errors).flat().join(' ')
                                    : (error.response?.data?.message || this.labels.loadFailed);
                            })
                            .finally(() => {
                                this.isSaving = false;
                            });
                    },

                    removeExperiment(exp) {
                        if (!window.confirm(this.labels.confirmDelete)) {
                            return;
                        }

                        this.$axios.delete("{{ route('admin.settings.experiments.index') }}/" + exp.id)
                            .then((response) => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                this.fetchExperiments();
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message || this.labels.loadFailed,
                                });
                            });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
