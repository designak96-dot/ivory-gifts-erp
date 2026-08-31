<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Parses CSV and XLSX bank statements with zero external dependencies —
 * this environment cannot reach Packagist (composer install for any
 * spreadsheet library fails with an HTTP 403), so this reads XLSX files
 * directly: an XLSX is a ZIP archive containing XML, and PHP's built-in
 * ZipArchive + SimpleXML extensions are enough to read the worksheet
 * cells and shared-string table without a third-party library.
 *
 * PDF statements are accepted and stored securely, but are NOT
 * automatically parsed — there is no text-extraction capability
 * available in this environment either, and this is stated plainly
 * rather than faking a partial parse. The UI must make this limitation
 * clear to the user, not silently show an empty result.
 */
class BankStatementParserService
{
    private const COLUMN_ALIASES = [
        'date' => ['date', 'transaction date', 'txn date', 'value date', 'posting date'],
        'description' => ['description', 'narration', 'details', 'particulars', 'memo'],
        'bank_reference' => ['reference', 'bank reference', 'transaction id', 'txn id', 'ref no', 'ref'],
        'debit' => ['debit', 'withdrawal', 'withdrawals', 'dr'],
        'credit' => ['credit', 'deposit', 'deposits', 'cr'],
        'amount' => ['amount'],
        'balance' => ['balance', 'running balance'],
    ];

    /** @return array{rows: array<int, array{date:?string, description:?string, bank_reference:?string, debit:float, credit:float, amount:float, balance:?float}>, parsed: bool, message: ?string} */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match (true) {
            $extension === 'csv' => $this->parseCsv($file->getRealPath()),
            in_array($extension, ['xlsx', 'xls']) => $this->parseXlsx($file->getRealPath()),
            $extension === 'pdf' => ['rows' => [], 'parsed' => false, 'message' => 'PDF statements are stored securely but cannot be automatically read in this environment — please enter transactions manually, or upload the CSV/XLSX version of this statement if your bank provides one.'],
            default => ['rows' => [], 'parsed' => false, 'message' => 'Unsupported file type. Please upload CSV or XLSX.'],
        };
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return ['rows' => [], 'parsed' => false, 'message' => 'Could not read the uploaded file.'];

        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return ['rows' => [], 'parsed' => false, 'message' => 'The file appears to be empty.']; }

        $map = $this->mapColumns($header);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) continue; // skip blank rows
            $rows[] = $this->extractRow($line, $map);
        }
        fclose($handle);

        return ['rows' => $rows, 'parsed' => true, 'message' => null];
    }

    private function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['rows' => [], 'parsed' => false, 'message' => 'Could not open the XLSX file — it may be corrupted.'];
        }

        // Shared strings: XLSX stores repeated text values once, referenced by index from cells.
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sxml = simplexml_load_string($sharedXml);
            foreach ($sxml->si as $si) {
                $sharedStrings[] = (string) ($si->t ?? implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r ?? []))));
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            return ['rows' => [], 'parsed' => false, 'message' => 'Could not find worksheet data in the XLSX file.'];
        }

        $sxml = simplexml_load_string($sheetXml);
        $grid = [];
        foreach ($sxml->sheetData->row as $row) {
            $rowIndex = (int) $row['r'];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/([A-Z]+)(\d+)/', $ref, $m);
                $colLetters = $m[1] ?? '';
                $colIndex = $this->columnLettersToIndex($colLetters);
                $type = (string) $cell['t'];
                $raw = isset($cell->v) ? (string) $cell->v : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int) $raw] ?? '';
                } elseif ($type === 'str' || $type === 'inlineStr') {
                    $value = $raw;
                } else {
                    $value = $raw; // numeric or date-serial — treated as plain string, parsed later
                }
                $grid[$rowIndex][$colIndex] = $value;
            }
        }

        if (empty($grid)) return ['rows' => [], 'parsed' => false, 'message' => 'The spreadsheet appears to be empty.'];

        ksort($grid);
        $rowNumbers = array_keys($grid);
        $headerRowNum = $rowNumbers[0];
        $headerRow = $grid[$headerRowNum];
        ksort($headerRow);
        $header = array_values($headerRow);
        $map = $this->mapColumns($header);

        $rows = [];
        foreach ($grid as $rowNum => $cells) {
            if ($rowNum === $headerRowNum) continue;
            ksort($cells);
            $maxCol = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) $line[] = $cells[$i] ?? '';
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) continue;
            $rows[] = $this->extractRow($line, $map);
        }

        return ['rows' => $rows, 'parsed' => true, 'message' => null];
    }

    private function columnLettersToIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }
        return $index - 1;
    }

    private function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $col) {
            $normalized = strtolower(trim((string) $col));
            foreach (self::COLUMN_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true) && !isset($map[$field])) {
                    $map[$field] = $i;
                }
            }
        }
        return $map;
    }

    private function extractRow(array $line, array $map): array
    {
        $get = fn (string $field) => isset($map[$field]) ? ($line[$map[$field]] ?? null) : null;

        $debit = $this->toFloat($get('debit'));
        $credit = $this->toFloat($get('credit'));
        $amountRaw = $get('amount');
        $amount = $amountRaw !== null ? $this->toFloat($amountRaw) : ($credit - $debit);

        return [
            'date' => $this->normalizeDate($get('date')),
            'description' => $get('description') !== null ? trim((string) $get('description')) : null,
            'bank_reference' => $get('bank_reference') !== null ? trim((string) $get('bank_reference')) : null,
            'debit' => $debit,
            'credit' => $credit,
            'amount' => $amount,
            'balance' => $get('balance') !== null ? $this->toFloat($get('balance')) : null,
        ];
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') return 0.0;
        return (float) str_replace([',', ' '], '', (string) $value);
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value || trim($value) === '') return null;
        // Excel serial date (numeric) — epoch 1899-12-30.
        if (is_numeric($value)) {
            try {
                return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
