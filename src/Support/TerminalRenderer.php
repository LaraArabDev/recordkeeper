<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

use Illuminate\Support\Collection;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Render audit data to the terminal in table, diff, JSON, NDJSON, and CSV formats.
 *
 * @internal Used by console commands; not part of the public API.
 */
final class TerminalRenderer
{
    /**
     * Render a formatted ASCII table to stdout.
     *
     * @param  list<string>  $headers  Column header labels.
     * @param  list<array<string, scalar>>  $rows  Row data arrays keyed by column.
     */
    public static function table(array $headers, array $rows): void
    {
        if (empty($rows)) {
            echo "No results found.\n";

            return;
        }

        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        $line = '+'.implode('+', array_map(fn (int $w) => str_repeat('-', $w + 2), $widths)).'+';
        echo $line."\n";

        $headerRow = '|';
        foreach (array_values($headers) as $i => $h) {
            $headerRow .= ' '.str_pad((string) $h, $widths[$i]).' |';
        }
        echo $headerRow."\n".$line."\n";

        foreach ($rows as $row) {
            $dataRow = '|';
            foreach (array_values($row) as $i => $cell) {
                $dataRow .= ' '.str_pad((string) $cell, $widths[$i]).' |';
            }
            echo $dataRow."\n";
        }

        echo $line."\n";
    }

    /**
     * Render a color-coded before/after diff of the audit's modified attributes.
     *
     * @param  Audit  $audit  The audit record whose modifications to display.
     */
    public static function diff(Audit $audit): void
    {
        $modified = $audit->getModified();

        if (empty($modified)) {
            echo "  (no attribute changes)\n";

            return;
        }

        foreach ($modified as $attribute => $change) {
            echo "  \033[33m{$attribute}\033[0m\n";
            $old = $change['old'] ?? null;
            $new = $change['new'] ?? null;
            echo "  \033[31m- ".self::formatValue($old)."\033[0m\n";
            echo "  \033[32m+ ".self::formatValue($new)."\033[0m\n";
        }
    }

    /**
     * Output data as pretty-printed JSON.
     *
     * @param  mixed  $data  The data to encode and output as JSON.
     */
    public static function json(mixed $data): void
    {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * Output data as newline-delimited JSON (NDJSON).
     *
     * @param  list<array<string, mixed>>  $rows  The rows to output, one JSON object per line.
     */
    public static function ndjson(array $rows): void
    {
        foreach ($rows as $row) {
            echo json_encode($row)."\n";
        }
    }

    /**
     * Write CSV-formatted output to stdout.
     *
     * @param  list<string>  $headers  Column header labels for the first CSV row.
     * @param  list<array<string, scalar>>  $rows  Data rows to write after the header.
     */
    public static function csv(array $headers, array $rows): void
    {
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }

    /**
     * Map a collection of audits to row arrays.
     *
     * @param  Collection<int, Audit>  $audits  The audits to map.
     * @return list<array{id: int, event: string, subject: string, actor: string, changed: string, batch: string, created: string}>
     */
    public static function mapToRows(Collection $audits): array
    {
        return $audits->map(fn (Audit $a) => self::auditToRow($a))->all();
    }

    /**
     * Convert a single audit record into a display row array.
     *
     * @param  Audit  $audit  The audit record to convert.
     * @return array{id: int, event: string, subject: string, actor: string, changed: string, batch: string, created: string}
     */
    public static function auditToRow(Audit $audit): array
    {
        return [
            'id' => $audit->id,
            'event' => $audit->event,
            'subject' => class_basename($audit->auditable_type).' #'.$audit->auditable_id,
            'actor' => $audit->user_id
                ? (class_basename($audit->user_type ?? 'User').' #'.$audit->user_id)
                : 'system',
            'changed' => implode(', ', array_keys($audit->getModified() ?? [])),
            'batch' => $audit->batch_id ?? '',
            'created' => $audit->created_at?->diffForHumans() ?? '',
        ];
    }

    /**
     * Format a value for display in a diff output.
     *
     * @param  mixed  $value  The value to format.
     * @return string The human-readable string representation.
     */
    private static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '(null)';
        }
        if ($value === '***' || (is_string($value) && str_starts_with($value, '__encrypted:'))) {
            return '*** (redacted/encrypted)';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
