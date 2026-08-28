<?php
namespace Tests\Unit;
use App\Services\DocumentTotals;
use PHPUnit\Framework\TestCase;
class DocumentTotalsTest extends TestCase
{
    public function test_vat_and_discount_are_rounded_per_line():void{$r=(new DocumentTotals)->calculate([['qty'=>2,'unit_price'=>50,'discount'=>10,'tax_rate'=>5],['qty'=>1,'unit_price'=>20,'discount'=>0,'tax_rate'=>5]]);$this->assertSame(120.0,$r['subtotal']);$this->assertSame(10.0,$r['discount_total']);$this->assertSame(5.5,$r['tax_total']);$this->assertSame(115.5,$r['grand_total']);}
}
