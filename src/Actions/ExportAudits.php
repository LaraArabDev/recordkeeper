<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Database\Eloquent\Builder;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Export audit records to a file in JSON, CSV, or NDJSON format.
 *
 * All formats stream row-by-row to keep memory usage constant
 * regardless of dataset size.
 */
final class ExportAudits
{
    /**
     * Export audits from the given query to the provided file handle.
     *
     * @param  Builder  $query  The pre-filtered Eloquent query builder.
     * @param  resource  $handle  A writable file handle (already opened).
     * @param  string  $format  One of: json, csv, ndjson.
     * @return int Number of exported audit records.
     */
    public function __invoke(Builder $query, mixed $handle, string $format): int
    {
        return match ($format) {
            'json' => $this->exportJson($query, $handle),
            'csv' => $this->exportCsv($query, $handle),
            default => $this->exportNdjson($query, $handle),
        };
    }

    /**
     * Stream a valid JSON array without holding all rows in memory.
     *
     * @param  Builder  $query  The pre-filtered Eloquent query builder.
     * @param  resource  $handle  A writable file handle.
     * @return int Number of exported audit records.
     */
    private function exportJson(Builder $query, mixed $handle): int
    {
        $total = 0;
        $first = true;

        fwrite($handle, "[\n");

        $query->chunk(200, function ($audits) use ($handle, &$total, &$first): void {
            foreach ($audits as $audit) {
                $row = TerminalRenderer::auditToRow($audit);
                $prefix = $first ? '  ' : ",\n  ";
                fwrite($handle, $prefix.json_encode($row, JSON_UNESCAPED_UNICODE));
                $first = false;
                $total++;
            }
        });

        fwrite($handle, "\n]\n");
        fclose($handle);

        return $total;
    }

    /**
     * Stream CSV with header row on first record.
     *
     * @param  Builder  $query  The pre-filtered Eloquent query builder.
     * @param  resource  $handle  A writable file handle.
     * @return int Number of exported audit records.
     */
    private function exportCsv(Builder $query, mixed $handle): int
    {
        $total = 0;
        $headerWritten = false;

        $query->chunk(200, function ($audits) use ($handle, &$total, &$headerWritten): void {
            foreach ($audits as $audit) {
                $row = TerminalRenderer::auditToRow($audit);
                if (! $headerWritten) {
                    fputcsv($handle, array_keys($row));
                    $headerWritten = true;
                }
                fputcsv($handle, $row);
                $total++;
            }
        });

        fclose($handle);

        return $total;
    }

    /**
     * Stream newline-delimited JSON (one object per line).
     *
     * @param  Builder  $query  The pre-filtered Eloquent query builder.
     * @param  resource  $handle  A writable file handle.
     * @return int Number of exported audit records.
     */
    private function exportNdjson(Builder $query, mixed $handle): int
    {
        $total = 0;

        $query->chunk(200, function ($audits) use ($handle, &$total): void {
            foreach ($audits as $audit) {
                fwrite($handle, json_encode(TerminalRenderer::auditToRow($audit))."\n");
                $total++;
            }
        });

        fclose($handle);

        return $total;
    }
}
