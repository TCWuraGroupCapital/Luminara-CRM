<x-admin::layouts>
    <x-slot:title>
        @lang('agent::app.desk.title')
    </x-slot>

    @include('agent::desk.styles')

    <v-agent-desk></v-agent-desk>

    @php
        $config = [
            'desk' => $desk,
            'locale' => str_replace('_', '-', app()->getLocale()),
            'currency' => config('app.currency'),
            'assistant' => $assistant,
            'urls' => [
                'data' => route('admin.agent.desk.data'),
                'stage' => str_replace('__id__', ':id', route('admin.leads.stage.update', '__id__')),
                'send' => route('admin.agent.assistant.send'),
                'stop' => route('admin.agent.assistant.stop'),
            ],
            't' => trans('agent::app.desk'),
            'assistantT' => trans('agent::app.assistant'),
            'statuses' => trans('agent::app.approvals.status'),
        ];
    @endphp

    @pushOnce('scripts')
        {{-- Every agent-written string reaches the page through textContent or esc(), never through this template. --}}
        <script type="text/x-template" id="v-agent-desk-template">
            <div class="lum-desk">
                <section class="hello" aria-labelledby="lum-h1">
                    <p class="date"><time datetime="{{ now()->toDateString() }}">{{ now()->isoFormat('dddd, D MMMM') }}</time></p>
                    <h1 id="lum-h1"></h1>
                    <p class="lede" id="lum-lede"></p>
                    <div class="hello-actions">
                        <button class="btn primary lg" type="button" id="lum-review-all">@lang('agent::app.desk.review-all')</button>

                        @if ($assistant)
                            <button class="btn lg" type="button" data-ask>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                @lang('agent::app.desk.ask')
                            </button>
                        @endif

                        <a class="link" href="#lum-activity">@lang('agent::app.desk.see-activity')</a>
                    </div>
                </section>

                <p class="notice" id="lum-no-pipeline" hidden>@lang('agent::app.desk.no-pipeline')</p>

                <section class="stage-card" aria-labelledby="lum-h-model">
                    <div class="stage-head">
                        <div>
                            <h2 id="lum-h-model">@lang('agent::app.desk.model-title')</h2>
                            <p class="legend">
                                <span class="key solid"></span><span class="model-only">@lang('agent::app.desk.legend-blocks')</span><span class="bars-only">@lang('agent::app.desk.legend-bars')</span>
                                <span class="key dashed"></span>@lang('agent::app.desk.legend-suggested')
                            </p>
                        </div>
                        <div class="stage-tools model-only">
                            <button class="chip-btn" type="button" id="lum-reset">@lang('agent::app.desk.reset-view')</button>
                            <button class="chip-btn" type="button" id="lum-list-toggle" aria-pressed="false">@lang('agent::app.desk.list-view')</button>
                        </div>
                    </div>

                    <div class="stage-grid">
                        <div class="model-stage model-only" id="lum-stage">
                            <canvas id="lum-scene" aria-hidden="true"></canvas>
                            <div class="labels" id="lum-labels" aria-hidden="true"></div>
                            <div class="tip" id="lum-tip" role="status" hidden></div>
                            <p class="drag-hint" id="lum-drag-hint">@lang('agent::app.desk.drag-hint')</p>
                            <div class="skeleton" id="lum-skeleton" aria-live="polite">
                                <div class="sk-lanes" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div>
                                <p>@lang('agent::app.desk.loading')</p>
                            </div>
                        </div>
                        <div class="bars" id="lum-bars" hidden></div>
                        <div class="stage-list" id="lum-list" hidden></div>

                        <aside class="sugs" aria-labelledby="lum-h-sugs">
                            <h3 id="lum-h-sugs">@lang('agent::app.desk.waiting')</h3>
                            <div class="sug-list" id="lum-sugs"></div>
                        </aside>
                    </div>
                    <p class="model-note" id="lum-model-note" hidden>@lang('agent::app.desk.no-3d')</p>
                    <dl class="totals" id="lum-totals"></dl>
                </section>

                <section id="lum-activity" aria-labelledby="lum-h-activity">
                    <div class="section-head">
                        <h2 id="lum-h-activity">@lang('agent::app.desk.activity-title')</h2>
                        <p>@lang('agent::app.desk.activity-info')</p>
                    </div>
                    <ol class="timeline" id="lum-timeline"></ol>
                    <p class="timeline-empty" id="lum-timeline-empty" hidden>@lang('agent::app.desk.activity-empty')</p>
                </section>

                <dialog class="review" id="lum-review" aria-labelledby="lum-rv-title">
                    <form method="dialog" class="review-inner">
                        <header class="review-top">
                            <span class="step" id="lum-rv-step"></span>
                            <div class="dots" id="lum-rv-dots" aria-hidden="true"></div>
                            <button class="icon-btn" value="close" aria-label="@lang('agent::app.desk.close')"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </header>
                        <div class="review-body" id="lum-rv-body"></div>
                    </form>
                </dialog>

                <dialog class="sheet" id="lum-deal" aria-labelledby="lum-deal-title">
                    <div class="sheet-inner" id="lum-deal-body"></div>
                </dialog>

                @if ($assistant)
                    <dialog class="sheet" id="lum-ask" aria-labelledby="lum-ask-title">
                        <div class="sheet-inner ask-inner">
                            <header class="sheet-top">
                                <div>
                                    <h2 id="lum-ask-title">@lang('agent::app.desk.ask')</h2>
                                    <p class="disclose" v-pre>@lang('agent::app.assistant.disclosure', ['model' => $model])</p>
                                </div>
                                <button class="icon-btn" type="button" id="lum-ask-close" aria-label="@lang('agent::app.desk.close')"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                            </header>
                            <div class="ask-log" id="lum-ask-log" aria-live="polite" aria-busy="false"></div>
                            <form class="composer" id="lum-composer">
                                <div class="suggest" id="lum-suggest">
                                    @foreach (trans('agent::app.desk.ask-examples') as $example)
                                        <button class="chip-btn" type="button" data-say="{{ $example }}">{{ $example }}</button>
                                    @endforeach
                                </div>
                                <label class="sr" for="lum-say">@lang('agent::app.assistant.placeholder')</label>
                                <div class="row">
                                    <textarea id="lum-say" rows="2" maxlength="4000" placeholder="@lang('agent::app.assistant.placeholder')"></textarea>
                                    <button class="btn danger" type="button" id="lum-stop" hidden>@lang('agent::app.assistant.stop')</button>
                                    <button class="btn primary" type="submit" id="lum-send">@lang('agent::app.assistant.send')</button>
                                </div>
                            </form>
                        </div>
                    </dialog>
                @endif

                <div class="toast" id="lum-toast" role="status" aria-live="polite" hidden></div>
            </div>
        </script>

        <script type="module">
            @include('agent::desk.scene')
            @include('agent::desk.app')

            app.component('v-agent-desk', {
                template: '#v-agent-desk-template',

                mounted() {
                    startDesk(this.$el, @json($config));
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
