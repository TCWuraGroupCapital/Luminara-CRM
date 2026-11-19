<x-admin::layouts>
    <x-slot:title>
        @lang('agent::app.assistant.title')
    </x-slot>

    @include('agent::partials.styles')

    <v-agent-assistant></v-agent-assistant>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-agent-assistant-template"
        >
            <div class="lum-agent">
                <div class="head">
                    <div>
                        <h1>@lang('agent::app.assistant.title')</h1>

                        {{-- EU AI Act Art. 50(1): say it is an AI, and which, before the first message. --}}
                        <p v-pre>@lang('agent::app.assistant.disclosure', ['model' => $model])</p>
                    </div>

                    <button
                        type="button"
                        class="btn"
                        :disabled="busy || ! turns.length"
                        @click="reset"
                    >
                        @lang('agent::app.assistant.new')
                    </button>
                </div>

                @unless ($configured)
                    <p class="notice">@lang('agent::app.assistant.errors.not-configured')</p>
                @endunless

                <div
                    ref="log"
                    class="log"
                    aria-live="polite"
                    aria-relevant="additions"
                    :aria-busy="busy ? 'true' : 'false'"
                >
                    <p
                        v-if="! turns.length && ! busy"
                        class="hint-empty"
                    >
                        @lang('agent::app.assistant.empty')
                    </p>

                    <div
                        v-for="(turn, index) in turns"
                        :key="index"
                        class="turn"
                        :class="turn.role"
                    >
                        <span class="by" v-text="labels[turn.role]"></span>

                        <ol
                            v-if="turn.calls?.length"
                            class="steps"
                            aria-label="@lang('agent::app.assistant.steps')"
                        >
                            <li
                                v-for="call in turn.calls"
                                :class="call.status"
                            >
                                <span class="mono" v-if="call.call_id">#@{{ call.call_id }}</span>
                                <span class="mono" v-text="call.action"></span>
                                <span v-text="statuses[call.status] ?? call.status"></span>
                            </li>
                        </ol>

                        <p class="text" v-text="turn.text"></p>

                        <a
                            v-if="turn.approvalsUrl"
                            :href="turn.approvalsUrl"
                            class="link"
                        >
                            @lang('agent::app.assistant.open-approvals')
                        </a>
                    </div>

                    <p
                        v-if="busy"
                        class="working"
                    >
                        @lang('agent::app.assistant.working')
                    </p>
                </div>

                <form
                    class="composer"
                    @submit.prevent="send"
                >
                    <label
                        for="agent-assistant-message"
                        class="sr-only"
                    >
                        @lang('agent::app.assistant.placeholder')
                    </label>

                    <textarea
                        id="agent-assistant-message"
                        v-model="draft"
                        maxlength="4000"
                        placeholder="@lang('agent::app.assistant.placeholder')"
                        :disabled="busy"
                        @keydown.enter.exact.prevent="send"
                    ></textarea>

                    <div class="row">
                        <span>@lang('agent::app.assistant.hint')</span>

                        <button
                            v-if="busy"
                            type="button"
                            class="btn danger"
                            :disabled="stopping"
                            @click="stop"
                        >
                            @lang('agent::app.assistant.stop')
                        </button>

                        <button
                            v-else
                            type="submit"
                            class="btn sign"
                            :disabled="! draft.trim()"
                        >
                            @lang('agent::app.assistant.send')
                        </button>
                    </div>
                </form>
            </div>
        </script>

        <script type="module">
            app.component('v-agent-assistant', {
                template: '#v-agent-assistant-template',

                data() {
                    return {
                        turns: [],

                        draft: @json($initialDraft ?? ''),

                        busy: false,

                        stopping: false,

                        labels: {
                            user: @json(trans('agent::app.assistant.you')),
                            assistant: @json(trans('agent::app.assistant.agent')),
                            error: @json(trans('agent::app.assistant.agent')),
                        },

                        statuses: @json(trans('agent::app.approvals.status')),
                    };
                },

                methods: {
                    send() {
                        const message = this.draft.trim();

                        if (! message || this.busy) {
                            return;
                        }

                        this.turns.push({ role: 'user', text: message });
                        this.draft = '';
                        this.busy = true;
                        this.stopping = false;
                        this.scroll();

                        this.$axios.post("{{ route('admin.agent.assistant.send') }}", { message })
                            .then(({ data }) => {
                                this.turns.push({
                                    role: 'assistant',
                                    text: data.reply,
                                    calls: data.calls,
                                    approvalsUrl: data.approvals_url,
                                });
                            })
                            .catch((error) => {
                                this.turns.push({
                                    role: 'error',
                                    text: error.response?.data?.message ?? @json(trans('agent::app.assistant.errors.unavailable')),
                                });
                            })
                            .finally(() => {
                                this.busy = false;
                                this.stopping = false;
                                this.scroll();
                            });
                    },

                    stop() {
                        if (! this.busy || this.stopping) {
                            return;
                        }

                        this.stopping = true;

                        this.$axios.post("{{ route('admin.agent.assistant.stop') }}")
                            .catch(() => {});
                    },

                    reset() {
                        this.$axios.post("{{ route('admin.agent.assistant.reset') }}")
                            .then(() => this.turns = [])
                            .catch(() => {});
                    },

                    scroll() {
                        this.$nextTick(() => this.$refs.log.scrollTop = this.$refs.log.scrollHeight);
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
