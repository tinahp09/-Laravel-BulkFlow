<?php

declare(strict_types=1);

namespace BulkFlow\Failure;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

final class FailureReportExporter
{
    public function toCsv(string $runId, string $path): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot write failure report to [%s].', $path));
        }

        try {
            fputcsv($handle, ['row_number', 'type', 'errors', 'payload']);

            RowFailureRecord::query()
                ->where('run_id', $runId)
                ->orderBy('row_number')
                ->each(static function (RowFailureRecord $failure) use ($handle): void {
                    fputcsv($handle, [
                        $failure->row_number,
                        $failure->type,
                        json_encode($failure->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        json_encode($failure->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]);
                });
        } finally {
            fclose($handle);
        }
    }

    public function toXlsx(string $runId, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValues(['row_number', 'type', 'errors', 'payload']));
            RowFailureRecord::query()->where('run_id', $runId)->orderBy('row_number')->each(static function (RowFailureRecord $failure) use ($writer): void {
                $writer->addRow(Row::fromValues([
                    $failure->row_number,
                    $failure->type,
                    json_encode($failure->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    json_encode($failure->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]));
            });
        } finally {
            $writer->close();
        }
    }
}
