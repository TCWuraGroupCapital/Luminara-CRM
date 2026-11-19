<?php

namespace Webkul\Agent\Actions\Quotes;

class GetQuote extends QuoteAction
{
    public function name(): string
    {
        return 'quotes_get';
    }

    public function summary(): string
    {
        return 'Read one quote inside the caller\'s scope, including all line items.';
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
            'quote_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function handle(array $params): array
    {
        $quote = $this->scopedQuotes()
            ->whereKey($params['quote_id'])
            ->firstOrFail();

        return ['quote' => $this->present($quote, true)];
    }
}
