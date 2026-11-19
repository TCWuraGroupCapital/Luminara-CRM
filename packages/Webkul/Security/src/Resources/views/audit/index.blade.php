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
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400">
                        <th class="px-4 py-3">@lang('security::app.audit.col-time')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-event')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-actor')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-target')</th>
                        <th class="px-4 py-3">@lang('security::app.audit.col-ip')</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($logs as $log)
                        <tr class="text-gray-800 dark:text-gray-200">
                            <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums">{{ $log->created_at?->toIso8601String() }}</td>
                            <td class="px-4 py-2.5 font-mono font-semibold">{{ $log->event }}</td>
                            <td class="px-4 py-2.5">{{ $log->actor_email ?? ($log->actor_id ? '#'.$log->actor_id : '—') }}</td>
                            <td class="px-4 py-2.5 font-mono">{{ $log->auditable_type ? $log->auditable_type.':'.($log->auditable_id ?? '*') : '—' }}</td>
                            <td class="px-4 py-2.5 font-mono tabular-nums">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                @lang('security::app.audit.empty')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                {{ $logs->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-admin::layouts>
