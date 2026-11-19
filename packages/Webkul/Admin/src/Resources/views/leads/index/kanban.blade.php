{!! view_render_event('admin.leads.index.kanban.before') !!}

<!-- Kanban Vue Component -->
<v-leads-kanban ref="leadsKanban">
    <div class="flex flex-col gap-4">
        <!-- Shimmer -->
        <x-admin::shimmer.leads.index.kanban />
    </div>
</v-leads-kanban>

{!! view_render_event('admin.leads.index.kanban.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-leads-kanban-template"
    >
        <template v-if="isLoading">
            <div class="flex flex-col gap-4">
                <x-admin::shimmer.leads.index.kanban />
            </div>
        </template>

        <template v-else>
            <div class="flex flex-col gap-4">
                @include('admin::leads.index.kanban.toolbar')

                {!! view_render_event('admin.leads.index.kanban.content.before') !!}

                <!-- Open pipeline, drawn to scale: each open stage is as wide as its share of open value -->
                <section
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                    aria-labelledby="lum-pipeline-scale"
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2
                            id="lum-pipeline-scale"
                            class="text-sm font-medium text-gray-600 dark:text-gray-300"
                        >
                            @lang('admin::app.leads.index.kanban.open-pipeline')
                        </h2>

                        <span class="text-xl font-semibold tabular-nums text-gray-900 dark:text-white">
                            @{{ $admin.formatPrice(openStagesAmount) }}
                        </span>
                    </div>

                    <div
                        class="mt-3 flex h-6 gap-0.5 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800"
                        role="img"
                        :aria-label="pipelineScaleLabel"
                    >
                        <template v-for="stage in openStages">
                            <span
                                class="lum-stage-swatch block min-w-[3px]"
                                :style="{ flex: parseFloat(stage.lead_value) / openStagesAmount, '--lum-stage': stageShade(stage) }"
                                v-if="parseFloat(stage.lead_value) > 0"
                            ></span>
                        </template>
                    </div>

                    <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-gray-600 dark:text-gray-300">
                        <li
                            class="flex items-center gap-1.5"
                            v-for="stage in stageLeads"
                        >
                            <span
                                class="lum-stage-swatch h-2.5 w-2.5 flex-none rounded-[3px]"
                                :style="{ '--lum-stage': stageShade(stage) }"
                            ></span>

                            @{{ stage.name }}

                            <span class="font-medium tabular-nums text-gray-900 dark:text-white">
                                @{{ $admin.formatPrice(stage.lead_value) }}
                            </span>

                            <span class="tabular-nums">· @{{ stage.leads.meta.total }}</span>
                        </li>
                    </ul>
                </section>

                <div class="flex gap-3 overflow-x-auto max-h-[calc(100vh-300px)] max-lg:max-h-none">
                    <!-- Stage Cards -->
                    <div
                        class="flex min-w-[284px] max-w-[284px] flex-col rounded-xl bg-gray-200 dark:bg-gray-900"
                        v-for="(stage, index) in stageLeads"
                    >
                        {!! view_render_event('admin.leads.index.kanban.content.stage.header.before') !!}

                        <!-- Stage Header: swatch, name, count, value, add -->
                        <div class="flex items-center gap-2 px-3 pb-1 pt-2.5">
                            <span
                                class="lum-stage-swatch h-2.5 w-2.5 flex-none rounded-[3px]"
                                :style="{ '--lum-stage': stageShade(stage) }"
                            ></span>

                            <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                @{{ stage.name }}
                            </span>

                            <span class="flex-none rounded-full bg-white px-2 text-xs font-medium tabular-nums leading-5 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                @{{ stage.leads.meta.total }}
                            </span>

                            <span class="flex-none text-xs tabular-nums text-gray-600 dark:text-gray-400 ltr:ml-auto rtl:mr-auto">
                                @{{ $admin.formatPrice(stage.lead_value) }}
                            </span>

                            @if (bouncer()->hasPermission('leads.create'))
                                <a
                                    :href="'{{ route('admin.leads.create') }}' + '?stage_id=' + stage.id"
                                    class="icon-add flex-none cursor-pointer rounded-md p-1 text-lg text-gray-600 transition-colors hover:bg-white hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
                                    :aria-label="'@lang('admin::app.leads.index.kanban.create-lead-btn')' + ': ' + stage.name"
                                    target="_blank"
                                >
                                </a>
                            @endif
                        </div>

                        {!! view_render_event('admin.leads.index.kanban.content.stage.header.after') !!}

                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.before') !!}

                        <!-- Draggable Stage Lead Cards -->
                        <draggable
                            class="flex h-[calc(100vh-317px)] flex-col gap-1.5 overflow-y-auto p-1.5 max-lg:h-auto max-lg:max-h-[70vh] max-lg:min-h-[120px]"
                            :class="{ 'justify-center': stage.leads.data.length === 0 }"
                            ghost-class="draggable-ghost"
                            handle=".lead-item"
                            v-bind="{animation: 200}"
                            :list="stage.leads.data"
                            item-key="id"
                            group="leads"
                            @scroll="handleScroll(stage, $event)"
                            @change="handleUpdate(stage, $event)"
                        >
                            <template #header>
                                <div
                                    class="flex flex-col items-center justify-center"
                                    v-if="! stage.leads.data.length"
                                >
                                    <img
                                        class="dark:mix-blend-exclusion dark:invert"
                                        src="{{ vite()->asset('images/empty-placeholders/pipedrive.svg') }}"
                                    >

                                    <div class="flex flex-col items-center gap-4">
                                        <div class="flex flex-col items-center gap-2">
                                            <p class="!text-base font-semibold dark:text-white">
                                                @lang('admin::app.leads.index.kanban.empty-list')
                                            </p>

                                            <p class="!text-sm text-gray-400 dark:text-gray-400">
                                                @lang('admin::app.leads.index.kanban.empty-list-description')
                                            </p>
                                        </div>

                                        @if (bouncer()->hasPermission('leads.create'))
                                            <a
                                                :href="'{{ route('admin.leads.create') }}' + '?stage_id=' + stage.id"
                                                class="secondary-button"
                                            >
                                                @lang('admin::app.leads.index.kanban.create-lead-btn')
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </template>

                            <!-- Lead Card -->
                            <template #item="{ element, index }">
                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.before') !!}

                                <a
                                    class="lead-item flex cursor-pointer flex-col gap-2.5 rounded-md border border-gray-300 bg-white p-3 text-gray-800 transition-colors hover:border-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-500"
                                    :href="'{{ route('admin.leads.view', 'replaceId') }}'.replace('replaceId', element.id)"
                                >
                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.before') !!}

                                    <!-- Header -->
                                    <div class="flex items-start justify-between gap-2">
                                        <div
                                            class="flex min-w-0 items-center gap-2"
                                            v-if="cardFields.contactPerson"
                                        >
                                            <x-admin::avatar ::name="element.person ? element.person.name : 'Unknown'" />

                                            <div class="flex min-w-0 flex-col">
                                                <span class="truncate text-xs font-medium">
                                                    @{{ element.person ? element.person.name : 'Unknown' }}
                                                </span>

                                                <span class="truncate text-xs text-gray-600 dark:text-gray-400">
                                                    @{{ element.person && element.person.organization ? element.person.organization.name : '' }}
                                                </span>
                                            </div>
                                        </div>

                                        <span
                                            class="flex-none text-sm font-semibold tabular-nums text-gray-900 dark:text-white ltr:ml-auto rtl:mr-auto"
                                            v-if="cardFields.leadValue"
                                        >
                                            @{{ element.formatted_lead_value }}
                                        </span>

                                        <div
                                            class="group relative"
                                            v-if="cardFields.rottenDays && element.rotten_days > 0"
                                        >
                                            <span class="icon-rotten cursor-default text-xl text-rose-600"></span>

                                            <div class="absolute -top-1 right-7 hidden w-max flex-col items-center group-hover:flex">
                                                <span class="whitespace-no-wrap relative rounded-md bg-black px-4 py-2 text-xs leading-none text-white shadow-lg">
                                                    @{{ "@lang('admin::app.leads.index.kanban.rotten-days', ['days' => 'replaceDays'])".replace('replaceDays', element.rotten_days) }}
                                                </span>

                                                <div class="absolute -right-1 top-2 h-3 w-3 rotate-45 bg-black"></div>
                                            </div>
                                        </div>
                                    </div>

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.after') !!}

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.before') !!}

                                    <!-- Lead Title -->
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        @{{ element.title }}
                                    </p>

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.after') !!}

                                    <div class="flex flex-wrap gap-1">
                                        <div
                                            class="flex items-center gap-1 rounded-md border border-gray-300 px-2 py-0.5 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300"
                                            v-if="cardFields.assignedUser && element.user"
                                        >
                                            <span class="icon-settings-user text-sm"></span>

                                            @{{ element.user.name }}
                                        </div>

                                        <div
                                            class="rounded-md border border-gray-300 px-2 py-0.5 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300"
                                            v-if="cardFields.source && element.source"
                                        >
                                            @{{ element.source.name }}
                                        </div>

                                        <div
                                            class="rounded-md border border-gray-300 px-2 py-0.5 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300"
                                            v-if="cardFields.type && element.type"
                                        >
                                            @{{ element.type.name }}
                                        </div>

                                        <!-- Tags -->
                                        <template v-if="cardFields.tags">
                                            <template v-for="tag in element.tags">
                                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.tag.before') !!}

                                                <div
                                                    class="rounded-md bg-gray-200 px-2 py-0.5 text-xs font-medium dark:bg-gray-800"
                                                    :style="{
                                                        backgroundColor: tag.color,
                                                        color: tagTextColor[tag.color]
                                                    }"
                                                >
                                                    @{{ tag.name }}
                                                </div>

                                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.tag.after') !!}
                                            </template>
                                        </template>
                                    </div>
                                </a>

                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.after') !!}
                            </template>
                        </draggable>

                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.leads.index.kanban.content.after') !!}
            </div>

            <!-- Show modal for additional information while updating the leads into won or lost stage. -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="stageUpdateForm"
                >
                <form @submit="handleSubmit($event, handleFormSubmit)">
                    <!-- Modal -->
                    <x-admin::modal
                        ref="stageUpdateModal"
                        @toggle="handleCloseModal"
                    >
                        <!-- Header -->
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.index.kanban.stages.need-more-info')
                            </h3>
                        </x-slot>

                        <!-- Content -->
                        <x-slot:content>
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="lead_pipeline_stage_id"
                                ::value="finalized.stage.id"
                            />

                            <!-- Won Value -->
                            <template v-if="finalized.stage.code == 'won'">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.index.kanban.stages.won-value')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="price"
                                        name="lead_value"
                                        ::value="finalized.lead.lead_value"
                                    />
                                </x-admin::form.control-group>
                            </template>

                            <!-- Lost Reason -->
                            <template v-else>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.index.kanban.stages.lost-reason')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="lost_reason"
                                    />
                                </x-admin::form.control-group>
                            </template>

                            <!-- Closed At -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.leads.index.kanban.stages.closed-at')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="datetime"
                                    name="closed_at"
                                    :label="trans('admin::app.leads.index.kanban.stages.closed-at')"
                                />

                                <x-admin::form.control-group.error control-name="closed_at"/>
                            </x-admin::form.control-group>
                        </x-slot>

                        <!-- Footer -->
                        <x-slot:footer>
                            <x-admin::button
                                class="primary-button"
                                :title="trans('admin::app.leads.index.kanban.stages.save-btn')"
                                ::loading="finalized.updating"
                                ::disabled="finalized.updating"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
        </template>
    </script>

    <script type="module">
        app.component('v-leads-kanban', {
            template: '#v-leads-kanban-template',

            data() {
                return {
                    available: {
                        columns: @json($columns),
                    },

                    applied: {
                        filters: {
                            columns: [],
                        }
                    },

                    finalized: {
                        lead: null,
                        stage: null,
                        updating: false,
                    },

                    stages: @json($pipeline->stages->toArray()),

                    stageLeads: {},

                    isLoading: true,

                    isLoadingMore: false,

                    cardFields: {
                        contactPerson: true,
                        rottenDays: true,
                        assignedUser: true,
                        leadValue: true,
                        source: true,
                        type: true,
                        tags: true,
                    },

                    tagTextColor: {
                        '#FEE2E2': '#DC2626',
                        '#FFEDD5': '#EA580C',
                        '#FEF3C7': '#D97706',
                        '#FEF9C3': '#CA8A04',
                        '#ECFCCB': '#65A30D',
                        '#DCFCE7': '#16A34A',
                    },
                };
            },

            computed: {
                /**
                 * Computes the total amount of leads across all stages.
                 *
                 * @return {number} The total amount of leads.
                 */
                totalStagesAmount() {
                    let totalAmount = 0;

                    for (let [key, stage] of Object.entries(this.stageLeads)) {
                        totalAmount += parseFloat(stage.lead_value);
                    }

                    return totalAmount;
                },

                /**
                 * Stages that still hold open leads (everything except won and lost), in pipeline order.
                 *
                 * @return {Array}
                 */
                openStages() {
                    return Object.values(this.stageLeads).filter(stage => ! ['won', 'lost'].includes(stage.code));
                },

                /**
                 * Total value of the open stages. The to-scale bar divides by this.
                 *
                 * @return {number}
                 */
                openStagesAmount() {
                    return this.openStages.reduce((total, stage) => total + (parseFloat(stage.lead_value) || 0), 0);
                },

                /**
                 * Text alternative for the to-scale bar.
                 *
                 * @return {string}
                 */
                pipelineScaleLabel() {
                    return this.openStages
                        .map(stage => `${stage.name}: ${this.$admin.formatPrice(stage.lead_value)}`)
                        .join(', ');
                },
            },

            mounted () {
                this.boot();

                this.$emitter.on('reload-datagrids', () => {
                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                });
            },

            methods: {
                /**
                 * Shade of the brand colour for a stage: open stages run from light to full
                 * strength in pipeline order, won is full strength and lost is left blank.
                 *
                 * @param {object} stage
                 * @returns {string} A percentage for the `--lum-stage` custom property.
                 */
                stageShade(stage) {
                    const index = this.openStages.findIndex(openStage => openStage.id === stage.id);

                    if (index < 0) {
                        return stage.code === 'won' ? '100%' : '0%';
                    }

                    if (this.openStages.length < 2) {
                        return '100%';
                    }

                    return Math.round(22 + 78 * index / (this.openStages.length - 1)) + '%';
                },

                /**
                 * Initialization: This function checks for any previously saved filters in local storage and applies them as needed.
                 *
                 * @returns {void}
                 */
                boot() {
                    this.cardFields = this.getCardFields();

                    let kanbans = this.getKanbans();

                    if (kanbans?.length) {
                        const currentKanban = kanbans.find(({ src }) => src === this.src);

                        if (currentKanban) {
                            this.applied.filters = currentKanban.applied.filters;

                            this.get()
                                .then(response => {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                });

                            return;
                        }
                    }

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Fetches the leads based on the applied filters.
                 *
                 * @param {object} requestedParams - The requested parameters.
                 * @returns {Promise} The promise object representing the request.
                 */
                get(requestedParams = {}) {
                    let params = {
                        search: '',
                        searchFields: '',
                        pipeline_id: "{{ request('pipeline_id') }}",
                        limit: 10,
                    };

                    this.applied.filters.columns.forEach((column) => {
                        if (column.index === 'all') {
                            if (! column.value.length) {
                                return;
                            }

                            params['search'] += `title:${column.value.join(',')};`;
                            params['searchFields'] += `title:like;`;

                            return;
                        }

                        /**
                         * Date range filters are sent as a dedicated parameter, not through the search
                         * string, so the server can resolve them the same way the datagrid does. A quick
                         * filter, such as `last_month`, is applied as a plain string, whereas a custom
                         * range is applied as a `[[from, to]]` pair.
                         */
                        if (column.type === 'date' || column.type === 'datetime') {
                            if (column.value.length) {
                                params[column.index] = column.value;
                            }

                            return;
                        }

                        /**
                         * If the column is a searchable dropdown, then we need to append the column value
                         * with the column label. Otherwise, we can directly append the column value.
                         */
                        params['search'] += column.filterable_type === 'searchable_dropdown'
                            ? `${column.index}:${column.value.map(option => option.value).join(',')};`
                            : `${column.index}:${column.value.join(',')};`;

                        params['searchFields'] += `${column.index}:${column.search_field};`;
                    });

                    return this.$axios
                        .get("{{ route('admin.leads.get') }}", {
                            params: {
                                ...params,

                                ...requestedParams,
                            }
                        })
                        .then(response => {
                            this.isLoading = false;

                            this.updateKanbans();

                            return response;
                        })
                        .catch(error => {
                            console.log(error)
                        });
                },

                /**
                 * Filters the leads based on the applied filters.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                filter(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index === 'all')),
                        ...filters.columns,
                    ];

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Searches the leads based on the applied filters.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                search(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index !== 'all')),
                        ...filters.columns,
                    ];

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Appends the leads to the stage.
                 *
                 * @param {object} params - The parameters to be appended.
                 * @returns {Promise}
                 */
                append(params) {
                    return this.get(params)
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                if (! this.stageLeads[sortOrder]) {
                                    this.stageLeads[sortOrder] = data;
                                } else {
                                    this.stageLeads[sortOrder].leads.data = this.stageLeads[sortOrder].leads.data.concat(data.leads.data);

                                    this.stageLeads[sortOrder].leads.meta = data.leads.meta;
                                }
                            }
                        });
                },

                /**
                 * Updates the stage with the latest lead data.
                 *
                 * @param {object} stage - The stage object.
                 * @param {object} event - The event object.
                 * @returns {void}
                 */
                handleUpdate(stage, event) {
                    if (event.moved) {
                        return;
                    }

                    if (
                        (stage.code === "won" || stage.code === "lost")
                        && event.added
                        && event.added.element
                    ) {
                        this.finalized.lead = event.added.element;

                        this.finalized.stage = stage;

                        this.toggleStageUpdateModal();

                        return;
                    }

                    if (event.removed) {
                        return;
                    }

                    this.updateStage("{{ route('admin.leads.stage.update', '__LEAD_ID__') }}".replace('__LEAD_ID__', event.added.element.id), {
                        'lead_pipeline_stage_id': stage.id
                    })
                        .then(response => {
                            this.get()
                                .then(response => {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                });

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch(error => {
                            this.get()
                                .then(response => {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                });

                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });
                },

                /**
                 * Updates the stage with the latest lead data.
                 *
                 * @param {string} url - The URL to update the stage.
                 * @param {object} params - The parameters to be updated.
                 *
                 * @returns {Promise} The promise object representing the request.
                 */
                updateStage(url, params) {
                    return this.$axios.put(url, params);
                },

                handleFormSubmit(params) {
                    this.finalized.updating = true;

                    this.updateStage("{{ route('admin.leads.stage.update', '__LEAD_ID__') }}".replace('__LEAD_ID__', this.finalized.lead.id), {
                        ...params,
                        lead_pipeline_stage_id: this.finalized.stage.id,
                    })
                        .then(response => {
                            this.toggleStageUpdateModal();

                            this.resetFinalized();

                            this.get()
                                .then(response => {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                });

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        }).finally(() => {
                            this.finalized.updating = false;
                        });
                },

                /**
                 * Resets the finalized lead and stage data.
                 *
                 * @returns {void}
                 */
                resetFinalized() {
                    this.finalized = {
                        lead: null,
                        stage: null,
                        updating: false,
                    };
                },

                /**
                 * Handles the close event of the modal.
                 *
                 * @returns {void}
                 */
                handleCloseModal(state) {
                    if (state.isActive) {
                        return;
                    }

                    this.resetFinalized();

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Toggles the stage update modal.
                 *
                 * @returns {void}
                 */
                toggleStageUpdateModal() {
                    this.$refs.stageUpdateModal.toggle();
                },

                /**
                 * Handles the scroll event on the stage leads.
                 *
                 * @param {object} stage - The stage object.
                 * @param {object} event - The scroll event.
                 * @returns {void}
                 */
                handleScroll(stage, event) {
                    const bottom = event.target.scrollHeight - event.target.scrollTop - event.target.clientHeight <= 5;

                    if (! bottom) {
                        return;
                    }

                    if (this.isLoadingMore) {
                        return;
                    }

                    if (this.stageLeads[stage.sort_order].leads.meta.current_page == this.stageLeads[stage.sort_order].leads.meta.last_page) {
                        return;
                    }

                    this.isLoadingMore = true;

                    this.append({
                        pipeline_stage_id: stage.id,
                        pipeline_id: stage.lead_pipeline_id,
                        page: this.stageLeads[stage.sort_order].leads.meta.current_page + 1,
                        limit: 10,
                    }).finally(() => {
                        this.isLoadingMore = false;
                    });
                },

                //=======================================================================================
                // Support for previous applied values in kanban's. All code is based on local storage.
                //=======================================================================================

                /**
                 * Updates the kanban's stored in local storage with the latest data.
                 *
                 * @returns {void}
                 */
                updateKanbans() {
                    let kanbans = this.getKanbans();

                    if (kanbans?.length) {
                        const currentKanban = kanbans.find(({ src }) => src === this.src);

                        if (currentKanban) {
                            kanbans = kanbans.map(kanban => {
                                if (kanban.src === this.src) {
                                    return {
                                        ...kanban,
                                        requestCount: ++kanban.requestCount,
                                        available: this.available,
                                        applied: this.applied,
                                    };
                                }

                                return kanban;
                            });
                        } else {
                            kanbans.push(this.getKanbanInitialProperties());
                        }
                    } else {
                        kanbans = [this.getKanbanInitialProperties()];
                    }

                    this.setKanbans(kanbans);
                },

                /**
                 * Returns the initial properties for a kanban.
                 *
                 * @returns {object} Initial properties for a kanban.
                 */
                getKanbanInitialProperties() {
                    return {
                        src: this.src,
                        requestCount: 0,
                        available: this.available,
                        applied: this.applied,
                    };
                },

                /**
                 * Returns the storage key for kanban's in local storage.
                 *
                 * @returns {string} Storage key for kanban's.
                 */
                getKanbansStorageKey() {
                    return 'kanbans';
                },

                /**
                 * Retrieves the kanban's stored in local storage.
                 *
                 * @returns {Array} Kanban's stored in local storage.
                 */
                getKanbans() {
                    let kanbans = localStorage.getItem(
                        this.getKanbansStorageKey()
                    );

                    return JSON.parse(kanbans) ?? [];
                },

                /**
                 * Sets the kanban's in local storage.
                 *
                 * @param {Array} kanbans - Kanban's to be stored in local storage.
                 * @returns {void}
                 */
                setKanbans(kanbans) {
                    localStorage.setItem(
                        this.getKanbansStorageKey(),
                        JSON.stringify(kanbans)
                    );
                },

                //=======================================================================================
                // Support for the customizable lead card fields. All code is based on local storage.
                //=======================================================================================

                /**
                 * Returns the storage key for the lead card field's visibility in local storage.
                 *
                 * @returns {string} Storage key for the lead card field's visibility.
                 */
                getCardFieldsStorageKey() {
                    return 'lead_kanban_card_fields';
                },

                /**
                 * Retrieves the lead card field's visibility stored in local storage, merged with the defaults.
                 *
                 * @returns {object} Lead card field's visibility.
                 */
                getCardFields() {
                    let storedCardFields = JSON.parse(
                        localStorage.getItem(this.getCardFieldsStorageKey())
                    );

                    return {
                        ...this.cardFields,
                        ...(storedCardFields ?? {}),
                    };
                },

                /**
                 * Updates the lead card field's visibility and stores it in local storage.
                 *
                 * @param {object} fields - Lead card field's visibility.
                 * @returns {void}
                 */
                updateCardFields(fields) {
                    this.cardFields = fields;

                    localStorage.setItem(
                        this.getCardFieldsStorageKey(),
                        JSON.stringify(fields)
                    );
                },
            }
        });
    </script>
@endPushOnce
