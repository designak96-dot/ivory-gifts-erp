<?php

namespace Tests\Unit;

use App\Services\FinanceMigrationImportService;
use App\Services\NumberingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FinanceAmountParsingTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_preview_reads_formatted_aed_amounts(string $amount, ?string $total, float $expected): void
    {
        $service = new FinanceMigrationImportService(new NumberingService);
        $preview = $service->preview([['category' => 'General', 'amount' => $amount, 'total_amount_tax' => $total]]);

        $this->assertTrue($preview['can_commit']);
        $this->assertSame($expected, $preview['total_ex_tax']);
        $this->assertSame([], $preview['warnings']);
    }

    public static function validAmounts(): array
    {
        return [
            'currency prefix' => ['AED 50.00', 'AED 52.50', 50.0],
            'thousands' => ['1,250.50', '1,313.03', 1250.5],
            'currency suffix' => ['50.00 AED', '', 50.0],
            'dirhams' => ['Dhs. 120', null, 120.0],
            'arabic currency' => ['د.إ 120', null, 120.0],
            'unicode spaces' => ["AED\u{00A0}1\u{202F}250.50", null, 1250.5],
            'explicit zero' => ['0', '0', 0.0],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_cells_block_preview_instead_of_becoming_zero(string $amount, ?string $total): void
    {
        $preview = (new FinanceMigrationImportService(new NumberingService))->preview([
            ['category' => 'General', 'amount' => $amount, 'total_amount_tax' => $total],
        ]);

        $this->assertFalse($preview['can_commit']);
        $this->assertStringContainsString('Row 1:', $preview['fatal_errors'][0]);
    }

    public static function invalidAmounts(): array
    {
        return [
            ['not paid', null], ['', null], ['50', 'unknown'], ['12,50', null],
            ['-50', null], ['50 USD', null], ['50abc', null], ['50', '-5'],
        ];
    }
}
