<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'النور للتوريدات العمومية — المركز الرئيسي', 'phone' => '01001239876', 'email' => 'sales@elnour-supplies.test', 'address' => 'العتية، القاهرة'],
            ['name' => 'الدلتا لقطع الغيار — فرع الجيزة', 'phone' => '01111239876', 'email' => 'info@delta-parts.test', 'address' => 'المنطقة الصناعية، 6 أكتوبر'],
            ['name' => 'المصرية للهندسة والتوريدات — فرع القاهرة', 'phone' => '01221239876', 'email' => 'contact@egy-eng.test', 'address' => 'شارع الجمهورية، القاهرة'],
            ['name' => 'التوحيد للمعدات — فرع الإسكندرية', 'phone' => '01551239876', 'email' => 'sales@eltawheed.test', 'address' => 'فيكتوريا، الإسكندرية'],
            ['name' => 'العبور للتجارة والتوكيلات — المركز الرئيسي', 'phone' => '01069876543', 'email' => 'info@elobour-trade.test', 'address' => 'العبور، المنطقة الصناعية الأولى'],
            ['name' => 'الأمانة للعدد والأدوات — فرع القاهرة', 'phone' => '01169876543', 'email' => 'sales@alamana-tools.test', 'address' => 'العتبة، القاهرة'],
            ['name' => 'النيل للتوريدات الصناعية — فرع الجيزة', 'phone' => '01269876543', 'email' => 'nile@industrial.test', 'address' => 'فيصل، الجيزة'],
            ['name' => 'الصفا للتجارة — فرع طنطا', 'phone' => '01569876543', 'email' => 'elsafa@trade.test', 'address' => 'طنطا، الغربية'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['email' => $supplier['email']], $supplier);
        }
    }
}
