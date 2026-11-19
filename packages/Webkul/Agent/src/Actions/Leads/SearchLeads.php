<?php

namespace Webkul\Agent\Actions\Leads;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Lead\Models\Lead;

class SearchLeads extends LeadAction
{
    public function name(): string
    {
        return 'leads_search';
    }

    public function summary(): string
    {
        return 'List leads the caller may see, newest first, optionally filtered by words in the title.';
    }

    public function permission(): string
    {
        return 'leads';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function handle(array $params): array
    {
        $leads = $this->scopedLeads()
            ->with(['stage', 'person'])
            ->when($params['query'] ?? null, fn (Builder $query, string $words) => $query->where(
                'title', 'like', '%'.addcslashes($words, '%_\\').'%'
            ))
            ->latest('id')
            ->limit($params['limit'] ?? 20)
            ->get();

        return ['leads' => $leads->map(fn (Lead $lead): array => $this->present($lead))->all()];
    }
}
