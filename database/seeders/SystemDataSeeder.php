<?php
namespace Database\Seeders;
use App\Models\{ChartOfAccount,NumberingSequence,Permission,ProductCategory,Role,Setting,TaxRate,Warehouse};
use Illuminate\Database\Seeder;
class SystemDataSeeder extends Seeder
{
    public function run():void
    {
        $permissions=[
            'dashboard.view'=>'View dashboard','customers.view'=>'View customers','customers.manage'=>'Manage customers','products.view'=>'View products','products.manage'=>'Manage products','quotations.view'=>'View quotations','quotations.manage'=>'Manage quotations','orders.view'=>'View sales orders','orders.manage'=>'Manage sales orders','orders.delete'=>'Delete sales orders','invoices.view'=>'View invoices','invoices.manage'=>'Manage invoices','invoices.delete'=>'Delete invoices','payments.manage'=>'Record payments','payments.delete'=>'Delete payments','production.view'=>'View production','production.manage'=>'Manage production','deliveries.view'=>'View deliveries','deliveries.manage'=>'Manage deliveries','purchases.view'=>'View purchasing','purchases.manage'=>'Manage purchasing','expenses.view'=>'View expenses','expenses.manage'=>'Manage expenses','inventory.view'=>'View inventory','inventory.manage'=>'Manage inventory','accounting.view'=>'View accounting','accounting.manage'=>'Manage accounting','reports.view'=>'View operational reports','reports.financial'=>'View financial reports','users.manage'=>'Manage users and roles','settings.manage'=>'Manage settings','backups.manage'=>'Manage backups','imports.manage'=>'Import data','exports.view'=>'Export data','audit.view'=>'View audit log','system.view'=>'View system health'];
        foreach($permissions as $name=>$label)Permission::updateOrCreate(['name'=>$name],['label'=>$label]);
        $roleMap=[
            'owner'=>array_keys($permissions),
            'accountant'=>['dashboard.view','customers.view','products.view','orders.view','invoices.view','invoices.manage','payments.manage','purchases.view','purchases.manage','expenses.view','expenses.manage','inventory.view','accounting.view','accounting.manage','reports.view','reports.financial','settings.manage','exports.view','audit.view','system.view'],
            'manager'=>['dashboard.view','customers.view','customers.manage','products.view','quotations.view','quotations.manage','orders.view','orders.manage','invoices.view','production.view','production.manage','deliveries.view','purchases.view','expenses.view','inventory.view','reports.view','reports.financial','exports.view','system.view'],
            'sales'=>['dashboard.view','customers.view','customers.manage','products.view','quotations.view','quotations.manage','orders.view','orders.manage','invoices.view','reports.view','exports.view'],
            'designer'=>['dashboard.view','customers.view','orders.view','production.view','production.manage','exports.view'],
            'production'=>['dashboard.view','products.view','orders.view','production.view','production.manage','inventory.view','exports.view'],
            'reception'=>['dashboard.view','customers.view','customers.manage','products.view','quotations.view','quotations.manage','orders.view','payments.manage'],
            'delivery_coordinator'=>['dashboard.view','customers.view','orders.view','orders.manage','deliveries.view','deliveries.manage','exports.view'],
            'driver'=>['dashboard.view','deliveries.view','deliveries.manage'],
            'auditor'=>['dashboard.view','customers.view','products.view','orders.view','invoices.view','purchases.view','expenses.view','inventory.view','accounting.view','reports.view','reports.financial','exports.view','audit.view','system.view'],
            'read_only'=>['dashboard.view','customers.view','products.view','orders.view','invoices.view','production.view','deliveries.view','purchases.view','expenses.view','inventory.view','reports.view','exports.view'],
        ];
        foreach($roleMap as $name=>$perms){$role=Role::updateOrCreate(['name'=>$name],['label'=>ucwords(str_replace('_',' ',$name))]);$role->permissions()->sync(Permission::whereIn('name',$perms)->pluck('id'));}
        $settings=['company_name'=>'Ivory Gifts','company_email'=>'','company_phone'=>'','company_address'=>'Abu Dhabi, United Arab Emirates','company_trn'=>'','currency'=>'AED','timezone'=>'Asia/Dubai','delivery_limit_per_day'=>'6'];foreach($settings as $key=>$value)Setting::updateOrCreate(['key'=>$key],['value'=>$value,'group'=>'general']);
        TaxRate::updateOrCreate(['name'=>'UAE VAT 5%'],['rate'=>5,'is_inclusive'=>false,'is_active'=>true]);
        $sequences=['customer'=>['CUS-','none'],'supplier'=>['SUP-','none'],'quotation'=>['QT-{YYYY}-','yearly'],'sales_order'=>['SO-{YYYY}{MM}-','monthly'],'invoice'=>['INV-{YYYY}-','yearly'],'payment'=>['PAY-{YYYY}-','yearly'],'production_job'=>['JOB-{YYYY}{MM}-','monthly'],'delivery_note'=>['DN-{YYYY}-','yearly'],'purchase_order'=>['PO-{YYYY}-','yearly'],'expense'=>['EXP-{YYYY}-','yearly'],'journal'=>['JE-{YYYY}-','yearly']];foreach($sequences as $type=>$cfg)NumberingSequence::updateOrCreate(['document_type'=>$type],['prefix'=>$cfg[0],'reset_policy'=>$cfg[1],'padding'=>5]);
        foreach(['Newborn Gifts','Baby Reception','Personalised Gifts','Stands & Displays','Cups & Tableware','Other'] as $name)ProductCategory::firstOrCreate(['name_en'=>$name]);
        Warehouse::firstOrCreate(['name'=>'Main Warehouse'],['location'=>'Abu Dhabi','is_active'=>true]);
        $accounts=[['1000','Cash','asset'],['1010','Bank','asset'],['1100','Accounts Receivable','asset'],['1200','Inventory','asset'],['1300','VAT Input','asset'],['2000','Accounts Payable','liability'],['2100','VAT Output','liability'],['2200','Customer Deposits','liability'],['3000','Owner Equity','equity'],['4000','Sales Revenue','income'],['5000','Cost of Goods Sold','expense'],['5100','Operating Expenses','expense']];foreach($accounts as [$code,$name,$type])ChartOfAccount::updateOrCreate(['code'=>$code],['name'=>$name,'type'=>$type,'is_active'=>true]);
    }
}
