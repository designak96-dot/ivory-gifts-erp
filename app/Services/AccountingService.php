<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountingService
{
    public function __construct(private NumberingService $numbers) {}

    public function post(Model $reference, string $description, array $lines, ?string $date=null): JournalEntry
    {
        $debits=round(array_sum(array_column($lines,'debit')),2); $credits=round(array_sum(array_column($lines,'credit')),2);
        if ($debits<=0 || abs($debits-$credits)>0.001) throw new RuntimeException('Journal entry is not balanced.');
        return DB::transaction(function() use($reference,$description,$lines,$date){
            $entry=JournalEntry::create(['entry_number'=>$this->numbers->next('journal'),'entry_date'=>$date?:today(),'reference_type'=>$reference::class,'reference_id'=>$reference->getKey(),'status'=>'posted','description'=>$description,'posted_by'=>auth()->id(),'posted_at'=>now()]);
            foreach($lines as $line){
                $account=ChartOfAccount::where('code',$line['account'])->firstOrFail();
                $entry->lines()->create(['account_id'=>$account->id,'debit'=>$line['debit']??0,'credit'=>$line['credit']??0,'description'=>$line['description']??$description]);
            }
            return $entry->load('lines.account');
        });
    }

    /**
     * Reverses the posted journal entry for a given source record (invoice,
     * payment, etc.) — used when that record is deleted, per the
     * requirement that deleting must never corrupt the accounting picture.
     * The original entry is marked 'reversed' and kept (never deleted, for
     * audit purposes); a new mirrored entry is posted alongside it with
     * debit/credit swapped so the ledger nets back to zero for this record.
     * No-op if the record never actually posted an entry.
     */
    public function reverse(Model $reference, string $reason): ?JournalEntry
    {
        $original = JournalEntry::where('reference_type', $reference::class)
            ->where('reference_id', $reference->getKey())
            ->where('status', 'posted')
            ->with('lines')
            ->first();

        if (!$original) {
            return null;
        }

        return DB::transaction(function () use ($original, $reason) {
            $reversal = JournalEntry::create([
                'entry_number' => $this->numbers->next('journal'),
                'entry_date' => today(),
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'status' => 'posted',
                'description' => "Reversal: {$reason}",
                'reversal_of_id' => $original->id,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            foreach ($original->lines as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => "Reversal of: {$line->description}",
                ]);
            }

            $original->update(['status' => 'reversed']);

            return $reversal->load('lines.account');
        });
    }
}
