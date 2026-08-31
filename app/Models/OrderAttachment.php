<?php
namespace App\Models;
class OrderAttachment extends BusinessModel {
    public function order() { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public const CATEGORIES = ['Confirmed Order Proof', 'Artwork', 'Design approval', 'Customer file', 'Delivery photo', 'Signed document', 'Other'];
}
