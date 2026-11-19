<?php

namespace Webkul\Agent\Actions\Quotes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class DeleteQuote extends QuoteAction
{
    public function name(): string
    {
        return 'quotes_delete';
    }

    public function summary(): string
    {
        return 'Permanently delete a quote inside the caller\'s scope. Always waits for human approval.';
    }

    public function permission(): string
    {
        return 'quotes.delete';
    }

    public function approval(): string
    {
        return self::APPROVAL_ALWAYS;
    }

    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function subject(array $params): array
    {
        return [$this->snapshot($this->scopedQuotes()->whereKey($params['quote_id'])->firstOrFail())];
    }

    public function handle(array $params): array
    {
        $quote = $this->scopedQuotes()->whereKey($params['quote_id'])->firstOrFail();
        $id = $quote->getKey();

        Event::dispatch('quote.delete.before', $id);

        DB::table('attribute_values')
            ->where('entity_type', 'quotes')
            ->where('entity_id', $id)
            ->delete();

        $this->quoteRepository->delete($id);

        Event::dispatch('quote.delete.after', $id);

        return ['deleted_quote_id' => $id];
    }
}
