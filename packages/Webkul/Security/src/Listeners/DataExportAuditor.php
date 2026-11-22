<?php

namespace Webkul\Security\Listeners;

use Illuminate\Support\Str;
use Webkul\Security\Support\SecurityAuditRecorder;

/**
 * Records one audit row when a datagrid finishes a CSV or Excel download.
 *
 * Filter values and row contents stay out of the log: a search box can contain a person's name.
 */
class DataExportAuditor
{
    /** @var \WeakMap<object, string>|null */
    private static ?\WeakMap $formats = null;

    /**
     * @param  array<int, mixed>  $payload
     */
    public function before(string $event, array $payload): void
    {
        $grid = $this->grid($payload);
        $format = $this->format($payload);

        if ($grid === null || $format === null) {
            return;
        }

        self::formats()[$grid] = $format;
    }

    /**
     * @param  array<int, mixed>  $payload
     */
    public function after(string $event, array $payload): void
    {
        $grid = $this->grid($payload);

        if ($grid === null) {
            return;
        }

        $formats = self::formats();
        $format = isset($formats[$grid]) ? $formats[$grid] : $this->requestFormat();

        if (isset($formats[$grid])) {
            unset($formats[$grid]);
        }

        if ($format === null) {
            return;
        }

        $name = Str::snake(class_basename($grid));

        if (preg_match('/^[a-z0-9_]{1,64}$/', $name) !== 1) {
            $name = 'datagrid';
        }

        SecurityAuditRecorder::record('data.exported', 'datagrid', $name, [
            'action' => 'exported',
            'format' => $format,
            'fields' => [],
        ]);
    }

    /**
     * @param  array<int, mixed>  $payload
     */
    private function grid(array $payload): ?object
    {
        foreach ($payload as $item) {
            if (is_object($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $payload
     */
    private function format(array $payload): ?string
    {
        foreach ($payload as $item) {
            if (is_string($item) && preg_match('/^[a-z0-9_]{1,32}$/', $item) === 1) {
                return $item;
            }
        }

        return null;
    }

    private function requestFormat(): ?string
    {
        $format = request()->input('format');

        return is_string($format) && preg_match('/^[a-z0-9_]{1,32}$/', $format) === 1 ? $format : null;
    }

    /**
     * @return \WeakMap<object, string>
     */
    private static function formats(): \WeakMap
    {
        return self::$formats ??= new \WeakMap;
    }
}
