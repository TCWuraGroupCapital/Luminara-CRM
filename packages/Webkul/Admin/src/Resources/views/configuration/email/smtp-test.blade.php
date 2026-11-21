{{-- Settings > Configuration > Email > Outgoing mail: send a test email with the saved settings. --}}
<div class="mb-4 flex flex-wrap items-center gap-3">
    <v-smtp-test></v-smtp-test>
</div>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-smtp-test-template"
    >
        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="secondary-button"
                :disabled="sending"
                @click="send"
            >
                @lang('admin::app.configuration.index.email.smtp.account.test-button')
            </button>

            <p class="text-xs text-gray-600 dark:text-gray-300">
                @lang('admin::app.configuration.index.email.smtp.account.test-info')
            </p>
        </div>
    </script>

    <script type="module">
        app.component('v-smtp-test', {
            template: '#v-smtp-test-template',

            data() {
                return {
                    sending: false,
                };
            },

            methods: {
                send() {
                    this.sending = true;

                    this.$axios.post("{{ route('admin.configuration.email.smtp_test') }}")
                        .then((response) => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? "@lang('admin::app.configuration.index.email.smtp.test-failed', ['reason' => ''])" });
                        })
                        .finally(() => {
                            this.sending = false;
                        });
                },
            },
        });
    </script>
@endPushOnce
