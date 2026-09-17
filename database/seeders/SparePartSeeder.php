<?php

namespace Database\Seeders;

use App\Models\SparePart;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SparePartSeeder extends Seeder
{
    public function run(): void
    {
        if (Supplier::count() === 0) {
            (new SupplierSeeder)->run();
        }

        $suppliers = Supplier::pluck('id')->all();

        // مخزون سخي عمدًا: WorkOrderPart يخصم من المخزون تلقائيًا عند الاستخدام.
        $parts = [
            ['name' => 'فلتر زيت', 'code' => 'SP-1001', 'unit' => 'قطعة', 'stock' => 120, 'min' => 10, 'price' => 450],
            ['name' => 'فلتر هواء', 'code' => 'SP-1002', 'unit' => 'قطعة', 'stock' => 100, 'min' => 10, 'price' => 320],
            ['name' => 'سير ناقل مطاطي', 'code' => 'SP-1003', 'unit' => 'متر', 'stock' => 200, 'min' => 20, 'price' => 850],
            ['name' => 'رولمان بلي 6204', 'code' => 'SP-1004', 'unit' => 'قطعة', 'stock' => 150, 'min' => 15, 'price' => 280],
            ['name' => 'رولمان بلي 6308', 'code' => 'SP-1005', 'unit' => 'قطعة', 'stock' => 90, 'min' => 8, 'price' => 520],
            ['name' => 'كونتاكتور 32 أمبير', 'code' => 'SP-1006', 'unit' => 'قطعة', 'stock' => 40, 'min' => 4, 'price' => 1450],
            ['name' => 'بريكر 63 أمبير', 'code' => 'SP-1007', 'unit' => 'قطعة', 'stock' => 35, 'min' => 4, 'price' => 980],
            ['name' => 'فيوز حراري', 'code' => 'SP-1008', 'unit' => 'قطعة', 'stock' => 300, 'min' => 30, 'price' => 65],
            ['name' => 'لمبة إشارة LED', 'code' => 'SP-1009', 'unit' => 'قطعة', 'stock' => 250, 'min' => 20, 'price' => 45],
            ['name' => 'زيت هيدروليك 46', 'code' => 'SP-1010', 'unit' => 'لتر', 'stock' => 400, 'min' => 40, 'price' => 185],
            ['name' => 'شحم حراري', 'code' => 'SP-1011', 'unit' => 'علبة', 'stock' => 110, 'min' => 10, 'price' => 340],
            ['name' => 'طقم مسامير استانلس', 'code' => 'SP-1012', 'unit' => 'علبة', 'stock' => 130, 'min' => 12, 'price' => 220],
            ['name' => 'حساس حرارة PT100', 'code' => 'SP-1013', 'unit' => 'قطعة', 'stock' => 25, 'min' => 3, 'price' => 1150],
            ['name' => 'مضخة مياه 2 حصان', 'code' => 'SP-1014', 'unit' => 'قطعة', 'stock' => 12, 'min' => 2, 'price' => 6500],
            // صنفان تحت الحد عمدًا لتظهر تنبيهات المخزون في المتصفح.
            ['name' => 'سير V موديل B-52', 'code' => 'SP-1015', 'unit' => 'قطعة', 'stock' => 2, 'min' => 10, 'price' => 190],
            ['name' => 'فلتر سولار', 'code' => 'SP-1016', 'unit' => 'قطعة', 'stock' => 3, 'min' => 12, 'price' => 260],
            ['name' => 'تيل فرامل ونش', 'code' => 'SP-1017', 'unit' => 'طقم', 'stock' => 18, 'min' => 4, 'price' => 1750],
            ['name' => 'كابل نحاس 4×10مم', 'code' => 'SP-1018', 'unit' => 'متر', 'stock' => 300, 'min' => 50, 'price' => 420],
        ];

        foreach ($parts as $i => $part) {
            SparePart::firstOrCreate(
                ['code' => $part['code']],
                [
                    'name' => $part['name'],
                    'unit' => $part['unit'],
                    'quantity_in_stock' => $part['stock'],
                    'minimum_quantity' => $part['min'],
                    'unit_price' => $part['price'],
                    'supplier_id' => $suppliers[$i % count($suppliers)] ?? null,
                ]
            );
        }
    }
}
