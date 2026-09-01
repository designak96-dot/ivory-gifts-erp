<?php

namespace App\Models;

class AccountTransfer extends BusinessModel
{
    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function fromAccount() { return $this->belongsTo(ChartOfAccount::class, 'from_account_id'); }
    public function toAccount() { return $this->belongsTo(ChartOfAccount::class, 'to_account_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
