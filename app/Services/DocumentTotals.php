<?php
namespace App\Services;
class DocumentTotals
{
    public function calculate(array $items): array
    {
        $normalized=[]; $subtotal=0; $discountTotal=0; $taxTotal=0;
        foreach ($items as $item) {
            $qty=max(0,(float)($item['qty']??0)); $price=max(0,(float)($item['unit_price']??$item['rate']??$item['unit_cost']??0));
            $discount=max(0,(float)($item['discount']??0)); $rate=max(0,(float)($item['tax_rate']??5));
            $base=max(0,round($qty*$price-$discount,2)); $tax=round($base*$rate/100,2); $total=round($base+$tax,2);
            $normalized[]=array_merge($item,['qty'=>$qty,'unit_price'=>$price,'discount'=>$discount,'tax_rate'=>$rate,'tax_amount'=>$tax,'line_total'=>$total]);
            $subtotal+=round($qty*$price,2); $discountTotal+=$discount; $taxTotal+=$tax;
        }
        return ['items'=>$normalized,'subtotal'=>round($subtotal,2),'discount_total'=>round($discountTotal,2),'tax_total'=>round($taxTotal,2),'grand_total'=>round($subtotal-$discountTotal+$taxTotal,2)];
    }
}
