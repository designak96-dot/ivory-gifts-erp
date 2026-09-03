<?php
namespace App\Http\Controllers;
use App\Models\{NumberingSequence,Setting,TaxRate};
use App\Services\BrandingService;
use Illuminate\Http\Request;
class SettingsController extends Controller
{
    public function __construct(private BrandingService $branding) {}

    public function index(){return view('settings.index',['settings'=>Setting::pluck('value','key'),'taxRates'=>TaxRate::all(),'sequences'=>NumberingSequence::orderBy('document_type')->get()]);}

    public function update(Request $r){$d=$r->validate(['company_name'=>'required|string|max:190','company_email'=>'nullable|email','company_phone'=>'nullable|string|max:30','company_address'=>'nullable|string','company_trn'=>'nullable|string|max:30','currency'=>'required|string|size:3','timezone'=>'required|string|max:80','delivery_limit_per_day'=>'required|integer|min:1|max:100']);foreach($d as $key=>$value)Setting::updateOrCreate(['key'=>$key],['value'=>$value,'group'=>'general']);return back()->with('success','Settings saved.');}

    public function tax(Request $r){$d=$r->validate(['name'=>'required|string|max:80','rate'=>'required|numeric|min:0|max:100','is_inclusive'=>'nullable|boolean','is_active'=>'nullable|boolean']);TaxRate::create($d);return back()->with('success','Tax rate added.');}

    /**
     * Company branding and document settings — Owner-only (enforced by the
     * settings.manage permission on the route, same as every other method
     * here). Text fields use the same updateOrCreate pattern as the
     * existing general settings; the logo/signature go through
     * BrandingService since they involve real file validation.
     */
    public function updateBranding(Request $r)
    {
        $d = $r->validate([
            'company_legal_name' => 'nullable|string|max:190',
            'company_trade_name' => 'nullable|string|max:190',
            'company_website' => 'nullable|url|max:190',
            'company_bank_details' => 'nullable|string|max:2000',
            'quotation_terms' => 'nullable|string|max:4000',
            'invoice_terms' => 'nullable|string|max:4000',
            'document_footer' => 'nullable|string|max:1000',
            'delivery_note_hide_prices' => 'nullable|boolean',
            'logo' => 'nullable|file|max:3072|mimetypes:image/png,image/jpeg,image/webp,image/svg+xml',
            'signature' => 'nullable|file|max:3072|mimetypes:image/png,image/jpeg,image/webp,image/svg+xml',
        ]);

        foreach (['company_legal_name','company_trade_name','company_website','company_bank_details','quotation_terms','invoice_terms','document_footer'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $d[$key] ?? null, 'group' => 'branding']);
        }
        Setting::updateOrCreate(['key' => 'delivery_note_hide_prices'], ['value' => $r->boolean('delivery_note_hide_prices') ? '1' : '0', 'group' => 'branding']);

        if ($r->hasFile('logo')) {
            $path = $this->branding->storeLogo($r->file('logo'));
            Setting::updateOrCreate(['key' => 'logo_path'], ['value' => $path, 'group' => 'branding']);
        }
        if ($r->hasFile('signature')) {
            $path = $this->branding->storeSignature($r->file('signature'));
            Setting::updateOrCreate(['key' => 'signature_path'], ['value' => $path, 'group' => 'branding']);
        }

        return back()->with('success', 'Branding settings saved.');
    }

    public function removeLogo()
    {
        $this->branding->removeLogo();
        return back()->with('success', 'Logo removed.');
    }

    public function removeSignature()
    {
        $this->branding->removeSignature();
        return back()->with('success', 'Signature removed.');
    }
}
