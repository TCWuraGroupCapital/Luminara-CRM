<?php

namespace Webkul\Agent\Actions\Quotes;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Quote\Models\Quote;

class SearchQuotes extends QuoteAction
{
    public function name(): string
    {
        return 'quotes_search';
    }

    public function summary(): string
    {
        return 'Search quotes inside the caller\'s scope by subject or filter by person_id.';
    }

    public function permission(): string
    {
        return 'quotes';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100'],
            'person_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function handle(array $params): array
    {
        $quotes = $this->scopedQuotes()
            ->when($params['query'] ?? null, fn (Builder $query, string $words) => $query->where(
                'subject', 'like', '%'.addcslashes($words, '%_\\').'%'
            ))
            ->when($params['person_id'] ?? null, fn (Builder $query, int $personId) => $query->where('person_id', $personId))
            ->latest('id')
            ->limit($params['limit'] ?? 20)
            ->get();

        return [
            'quotes' => $quotes->map(fn (Quote $quote): array => $this->present($quote))->all(),
        ];
    }
}
