@php
    /**
     * Workbench sidebar (docs/design.md): the flat admin menu is shown in
     * labelled groups. An item whose key is not listed falls into `work`, so
     * packages that add menu items keep working without touching this view.
     */
    $groupKeys = [
        'overview' => ['dashboard', 'agent_briefing', 'agent_desk'],
        'agent'    => ['agent_assistant'],
        'system'   => ['help', 'settings', 'configuration'],
    ];

    $menuGroups = ['overview' => [], 'work' => [], 'agent' => [], 'system' => []];

    foreach (menu()->getItems('admin') as $menuItem) {
        $group = 'work';

        foreach ($groupKeys as $name => $keys) {
            if (in_array($menuItem->getKey(), $keys)) {
                $group = $name;

                break;
            }
        }

        $menuGroups[$group][] = $menuItem;
    }

    $itemClasses = 'flex min-h-[36px] w-full cursor-pointer items-center gap-2.5 rounded-md px-2.5 font-medium transition-colors group-[.sidebar-collapsed]/container:justify-center group-[.sidebar-collapsed]/container:px-0';

    $idleClasses = 'text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white';

    $activeClasses = 'bg-white text-gray-900 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-white dark:ring-gray-700';
@endphp

<div
    id="admin-sidebar"
    ref="sidebar"
    class="fixed top-0 z-[10002] flex h-full w-[232px] flex-col border-gray-200 bg-gray-100 transition-all duration-200 group-[.sidebar-collapsed]/container:w-[68px] dark:border-gray-800 dark:bg-gray-950 max-lg:hidden ltr:left-0 ltr:border-r rtl:right-0 rtl:border-l"
>
    <!-- Brand and collapse toggle -->
    <div class="flex h-[60px] flex-none items-center justify-between gap-2 px-3 group-[.sidebar-collapsed]/container:h-auto group-[.sidebar-collapsed]/container:flex-col group-[.sidebar-collapsed]/container:py-3">
        <a
            href="{{ route('admin.dashboard.index') }}"
            class="flex min-w-0 items-center rounded-md"
        >
            <x-admin::logo class="h-9 group-[.sidebar-collapsed]/container:hidden" />

            <x-admin::logo
                variant="mark"
                class="hidden h-9 group-[.sidebar-collapsed]/container:inline-flex"
            />
        </a>

        {!! view_render_event('admin.layout.sidebar.toggle.before') !!}

        <v-sidebar-collapse></v-sidebar-collapse>

        {!! view_render_event('admin.layout.sidebar.toggle.after') !!}
    </div>

    <nav
        class="journal-scroll flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto px-3 pb-3 group-[.sidebar-collapsed]/container:overflow-visible"
        aria-label="@lang('admin::app.layouts.sidebar.label')"
    >
        @foreach ($menuGroups as $groupName => $groupItems)
            @continue(! count($groupItems))

            @if ($groupName === 'system')
                <!-- Settings stay pinned to the bottom -->
                <div class="mt-auto pt-4"></div>
            @else
                <p class="px-2.5 pb-1 pt-3 !text-xs font-medium text-gray-500 group-[.sidebar-collapsed]/container:hidden dark:text-gray-400">
                    @lang('admin::app.layouts.sidebar.groups.' . $groupName)
                </p>

                <div class="mx-2 my-2 hidden border-t border-gray-300 group-[.sidebar-collapsed]/container:block dark:border-gray-800"></div>
            @endif

            @foreach ($groupItems as $menuItem)
                @php
                    $isActive = (bool) $menuItem->isActive();

                    $hasChildren = ! in_array($menuItem->getKey(), ['settings', 'configuration']) && $menuItem->haveChildren();
                @endphp

                @if ($hasChildren)
                    <!-- Parent with an inline list of children -->
                    <details
                        class="group/item relative"
                        {{ $isActive ? 'open' : '' }}
                    >
                        <summary
                            class="{{ $itemClasses }} {{ $isActive ? 'text-gray-900 dark:text-white' : '' }} {{ $idleClasses }} list-none [&::-webkit-details-marker]:hidden"
                            title="{{ $menuItem->getName() }}"
                        >
                            <span class="{{ $menuItem->getIcon() }} flex-none text-xl"></span>

                            <span class="min-w-0 flex-1 truncate group-[.sidebar-collapsed]/container:hidden">
                                {{ $menuItem->getName() }}
                            </span>

                            <span class="icon-right-arrow flex-none text-lg transition-transform group-open/item:rotate-90 group-[.sidebar-collapsed]/container:hidden rtl:rotate-180"></span>
                        </summary>

                        <div class="mt-0.5 grid gap-0.5 border-gray-300 dark:border-gray-800 ltr:ml-[19px] ltr:border-l ltr:pl-2 rtl:mr-[19px] rtl:border-r rtl:pr-2 group-[.sidebar-collapsed]/container:absolute group-[.sidebar-collapsed]/container:top-0 group-[.sidebar-collapsed]/container:z-10 group-[.sidebar-collapsed]/container:!m-0 group-[.sidebar-collapsed]/container:w-44 group-[.sidebar-collapsed]/container:rounded-lg group-[.sidebar-collapsed]/container:!border group-[.sidebar-collapsed]/container:bg-white group-[.sidebar-collapsed]/container:!p-1 group-[.sidebar-collapsed]/container:shadow-lg group-[.sidebar-collapsed]/container:ltr:left-[52px] group-[.sidebar-collapsed]/container:rtl:right-[52px] group-[.sidebar-collapsed]/container:dark:bg-gray-900">
                            @foreach ($menuItem->getChildren() as $subMenuItem)
                                <a
                                    href="{{ $subMenuItem->getUrl() }}"
                                    class="flex min-h-[32px] items-center rounded-md px-2.5 transition-colors {{ $subMenuItem->isActive() ? $activeClasses . ' font-medium' : $idleClasses }}"
                                    @if ($subMenuItem->isActive()) aria-current="page" @endif
                                >
                                    <span class="truncate">{{ $subMenuItem->getName() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a
                        href="{{ $menuItem->getUrl() }}"
                        class="{{ $itemClasses }} {{ $isActive ? $activeClasses : $idleClasses }}"
                        title="{{ $menuItem->getName() }}"
                        @if ($isActive) aria-current="page" @endif
                    >
                        <span class="{{ $menuItem->getIcon() }} flex-none text-xl"></span>

                        <span class="min-w-0 flex-1 truncate group-[.sidebar-collapsed]/container:hidden">
                            {{ $menuItem->getName() }}
                        </span>
                    </a>
                @endif
            @endforeach
        @endforeach
    </nav>
</div>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-sidebar-collapse-template"
    >
        <button
            type="button"
            class="flex h-9 w-9 flex-none items-center justify-center rounded-md text-gray-600 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white max-lg:hidden"
            :aria-label="isCollapsed
                ? '@lang('admin::app.layouts.sidebar.expand')'
                : '@lang('admin::app.layouts.sidebar.collapse')'"
            :title="isCollapsed
                ? '@lang('admin::app.layouts.sidebar.expand')'
                : '@lang('admin::app.layouts.sidebar.collapse')'"
            :aria-expanded="isCollapsed ? 'false' : 'true'"
            aria-controls="admin-sidebar"
            @click="toggle"
        >
            <span
                class="icon-left-arrow text-2xl transition-transform"
                :class="[isCollapsed ? 'ltr:rotate-[180deg] rtl:rotate-[0]' : 'ltr:rotate-[0] rtl:rotate-[180deg]']"
            ></span>
        </button>
    </script>

    <script type="module">
        app.component('v-sidebar-collapse', {
            template: '#v-sidebar-collapse-template',

            data() {
                return {
                    isCollapsed: {{ (int) request()->cookie('sidebar_collapsed', 0) ? 1 : 0 }},
                }
            },

            methods: {
                toggle() {
                    this.isCollapsed = parseInt(this.isCollapsedCookie()) ? 0 : 1;

                    var expiryDate = new Date();

                    expiryDate.setMonth(expiryDate.getMonth() + 1);

                    document.cookie = 'sidebar_collapsed=' + this.isCollapsed + '; path=/; SameSite=Lax; expires=' + expiryDate.toGMTString();

                    this.$root.$refs.appLayout.classList.toggle('sidebar-collapsed');

                    this.$root.$refs.appLayout.classList.toggle('sidebar-not-collapsed');
                },

                isCollapsedCookie() {
                    const cookies = document.cookie.split(';');

                    for (const cookie of cookies) {
                        const [name, value] = cookie.trim().split('=');

                        if (name === 'sidebar_collapsed') {
                            return value;
                        }
                    }

                    return 0;
                },
            },
        });
    </script>
@endPushOnce
