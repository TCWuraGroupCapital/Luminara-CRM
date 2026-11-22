<x-admin::layouts>
    <x-slot:title>
        @lang('security::app.audit.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <x-admin::breadcrumbs name="security.audit_logs" />

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                @lang('security::app.audit.title')
            </h1>
            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                @lang('security::app.audit.subtitle')
            </p>

            <form method="GET" action="{{ route('admin.security.audit_logs.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
                <label class="flex min-w-[10rem] flex-col gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                    @lang('security::app.audit.filter-event')
                    <input type="text" name="event" value="{{ is_string(request('event')) ? request('event') : '' }}" maxlength="96" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm font-normal text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                </label>
                <label class="flex min-w-[8rem] flex-col gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                    @lang('security::app.audit.filter-entity')
                    <input type="text" name="entity" value="{{ is_string(request('entity')) ? request('entity') : '' }}" maxlength="96" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm font-normal text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                </label>
                <label class="flex min-w-[8rem] flex-col gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                    @lang('security::app.audit.filter-action')
                    <select name="action" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm font-normal text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                        <option value="">@lang('security::app.audit.filter-any')</option>
                        @foreach (['created', 'updated', 'deleted', 'exported'] as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>@lang('security::app.audit.action-'.$action)</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex min-w-[10rem] flex-col gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                    @lang('security::app.audit.filter-actor')
                    <input type="text" name="actor" value="{{ is_string(request('actor')) ? request('actor') : '' }}" maxlength="191" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm font-normal text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                </label>
                <label class="flex min-w-[8rem] flex-col gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                    @lang('security::app.audit.filter-workspace')
                    <input type="text" name="workspace" value="{{ is_string(request('workspace')) ? request('workspace') : '' }}" maxlength="191" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm font-normal text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                </label>
                <label class="flex min-w-[12rem] flex-col gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                    @lang('security::app.audit.filter-request')
                    <input type="text" name="request_id" value="{{ is_string(request('request_id')) ? request('request_id') : '' }}" maxlength="64" class="rounded border border-gray-300 bg-white px-2 py-1.5 font-mono text-sm font-normal text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                </label>
                <button type="submit" class="rounded bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white dark:bg-white dark:text-gray-900">
                    @lang('security::app.audit.filter-apply')
                </button>
                <a href="{{ route('admin.security.audit_logs.index') }}" class="rounded px-3 py-1.5 text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                    @lang('security::app.audit.filter-clear')
                </a>
            </form>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400">
                        <th class="px-4 py-3">@lang('security::app.audit.col-time')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-event')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-actor')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-workspace')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-target')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-fields')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-request')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-ip')</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($logs as $log)
                        <tr class="text-gray-800 dark:text-gray-200">
                            <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums">{{ $log->created_at?->toIso8601String() }}</td>
                            <td class="px-4 py-2.5 font-mono font-semibold">{{ $log->event }}</td>
                            <td class="px-4 py-2.5">{{ $log->actor_email ?? ($log->actor_id ? '#'.$log->actor_id : trans('security::app.audit.none')) }}</td>
                            <td class="px-4 py-2.5 font-mono">{{ $log->workspace ?? trans('security::app.audit.none') }}</td>
                            <td class="px-4 py-2.5 font-mono">{{ $log->auditable_type ? $log->auditable_type.':'.($log->auditable_id ?? '*') : trans('security::app.audit.none') }}</td>
                            <td class="px-4 py-2.5 font-mono">{{ $log->changedFieldNames() === [] ? trans('security::app.audit.none') : implode(', ', $log->changedFieldNames()) }}</td>
                            <td class="px-4 py-2.5 font-mono">{{ $log->request_id ?? trans('security::app.audit.none') }}</td>
                            <td class="px-4 py-2.5 font-mono tabular-nums">{{ $log->ip_address ?? trans('security::app.audit.none') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                @lang('security::app.audit.empty')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-admin::layouts>
