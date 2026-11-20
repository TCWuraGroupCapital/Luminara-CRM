@php
    /**
     * Workbench menu for small screens: the same labelled groups and the same quiet active
     * state as the desktop sidebar (docs/design.md), inside a drawer.
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

    /* Rows are 44px tall so they are comfortable to tap. */
    $itemClasses = 'flex min-h-[44px] w-full cursor-pointer items-center gap-3 rounded-md px-3 font-medium transition-colors';

    $idleClasses = 'text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white';

    $activeClasses = 'bg-white text-gray-900 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-white dark:ring-gray-700';
@endphp

<v-sidebar-drawer>
    <i class="icon-menu lg:hidden cursor-pointer rounded-md p-1.5 text-2xl hover:bg-gray-100 dark:hover:bg-gray-950 max-lg:block"></i>
</v-sidebar-drawer>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-sidebar-drawer-template"
    >
        <x-admin::drawer
            position="left"
            width="280px"
            class="lg:hidden [&>:nth-child(3)]:!m-0 [&>:nth-child(3)]:!rounded-l-none [&>:nth-child(3)]:max-sm:!w-[80%]"
        >
            <x-slot:toggle>
                <button
                    type="button"
                    class="icon-menu lg:hidden cursor-pointer rounded-md p-1.5 text-2xl hover:bg-gray-100 dark:hover:bg-gray-950 max-lg:block"
                    aria-label="@lang('admin::app.layouts.sidebar.label')"
                ></button>
            </x-slot>

            <x-slot:header>
                <x-admin::logo class="h-9" />
            </x-slot>

            <x-slot:content class="!bg-gray-100 !p-3 dark:!bg-gray-950">
                <nav
                    class="journal-scroll flex h-[calc(100vh-100px)] flex-col gap-0.5 overflow-auto"
                    aria-label="@lang('admin::app.layouts.sidebar.label')"
                >
                    @foreach ($menuGroups as $groupName => $groupItems)
                        @continue(! count($groupItems))

                        @if ($groupName === 'system')
                            <div class="mt-3 border-t border-gray-300 pt-3 dark:border-gray-800"></div>
                        @else
                            <p class="px-3 pb-1 pt-3 !text-xs font-medium text-gray-500 dark:text-gray-400">
                                @lang('admin::app.layouts.sidebar.groups.' . $groupName)
                            </p>
                        @endif

                        @foreach ($groupItems as $menuItem)
                            @php
                                $isActive = (bool) $menuItem->isActive();

                                $hasChildren = ! in_array($menuItem->getKey(), ['settings', 'configuration']) && $menuItem->haveChildren();
                            @endphp

                            @if ($hasChildren)
                                <details
                                    class="group/item"
                                    {{ $isActive ? 'open' : '' }}
                                >
                                    <summary class="{{ $itemClasses }} {{ $isActive ? 'text-gray-900 dark:text-white' : '' }} {{ $idleClasses }} list-none [&::-webkit-details-marker]:hidden">
                                        <span class="{{ $menuItem->getIcon() }} flex-none text-2xl"></span>

                                        <span class="min-w-0 flex-1 truncate">
                                            {{ $menuItem->getName() }}
                                        </span>

                                        <span class="icon-right-arrow flex-none text-lg transition-transform group-open/item:rotate-90 rtl:rotate-180"></span>
                                    </summary>

                                    <div class="mt-0.5 grid gap-0.5 border-gray-300 dark:border-gray-800 ltr:ml-[23px] ltr:border-l ltr:pl-2 rtl:mr-[23px] rtl:border-r rtl:pr-2">
                                        @foreach ($menuItem->getChildren() as $subMenuItem)
                                            <a
                                                href="{{ $subMenuItem->getUrl() }}"
                                                class="flex min-h-[40px] items-center rounded-md px-3 transition-colors {{ $subMenuItem->isActive() ? $activeClasses . ' font-medium' : $idleClasses }}"
                                                @if ($subMenuItem->isActive()) aria-current="page" @endif
                                            >
                                                {{ $subMenuItem->getName() }}
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <a
                                    href="{{ $menuItem->getUrl() }}"
                                    class="{{ $itemClasses }} {{ $isActive ? $activeClasses : $idleClasses }}"
                                    @if ($isActive) aria-current="page" @endif
                                >
                                    <span class="{{ $menuItem->getIcon() }} flex-none text-2xl"></span>

                                    <span class="min-w-0 flex-1 truncate">
                                        {{ $menuItem->getName() }}
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    @endforeach
                </nav>
            </x-slot>
        </x-admin::drawer>
    </script>

    <script type="module">
        app.component('v-sidebar-drawer', {
            template: '#v-sidebar-drawer-template',
        });
    </script>
@endPushOnce
