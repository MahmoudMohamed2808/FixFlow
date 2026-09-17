<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $categoryId = fn (string $name) => AssetCategory::where('name', $name)->value('id')
            ?? AssetCategory::first()?->id;

        $locationId = fn (string $name) => Location::where('name', $name)->value('id')
            ?? Location::first()?->id;

        $creatorId = User::where('email', 'ahmed.elsherif@fixflow.test')->value('id')
            ?? User::first()?->id;

        $assets = [
            ['name' => 'كمبروسور حلزوني 50 حصان — خط 1', 'serial' => 'EG-CMP-001', 'cat' => 'طلمبات وكمبروسرات', 'loc' => 'غرفة الكمبروسرات', 'status' => 'operational', 'mfr' => 'Atlas Copco', 'model' => 'GA-55', 'buy' => '2021-03-15', 'warranty' => '2026-03-15'],
            ['name' => 'كمبروسور حلزوني 30 حصان — خط 2', 'serial' => 'EG-CMP-002', 'cat' => 'طلمبات وكمبروسرات', 'loc' => 'غرفة الكمبروسرات', 'status' => 'under_maintenance', 'mfr' => 'Atlas Copco', 'model' => 'GA-37', 'buy' => '2022-06-01', 'warranty' => null],
            ['name' => 'مولد ديزل 250 ك.ف.أ — احتياطي', 'serial' => 'EG-GEN-001', 'cat' => 'مولدات ومحولات', 'loc' => 'الساحة الخارجية — المولدات', 'status' => 'operational', 'mfr' => 'Caterpillar', 'model' => 'DE250', 'buy' => '2020-11-20', 'warranty' => null],
            ['name' => 'مولد ديزل 100 ك.ف.أ — إداري', 'serial' => 'EG-GEN-002', 'cat' => 'مولدات ومحولات', 'loc' => 'الساحة الخارجية — المولدات', 'status' => 'broken', 'mfr' => 'Perkins', 'model' => 'P100', 'buy' => '2019-05-10', 'warranty' => null],
            ['name' => 'ماكينة تعبئة وتغليف — خط 1', 'serial' => 'EG-PAK-001', 'cat' => 'مكابس', 'loc' => 'خط الإنتاج 1', 'status' => 'operational', 'mfr' => 'Siemens', 'model' => 'PK-1200', 'buy' => '2021-09-01', 'warranty' => '2026-09-01'],
            ['name' => 'ماكينة تعبئة وتغليف — خط 2', 'serial' => 'EG-PAK-002', 'cat' => 'مكابس', 'loc' => 'خط الإنتاج 2', 'status' => 'operational', 'mfr' => 'Siemens', 'model' => 'PK-1200', 'buy' => '2023-01-12', 'warranty' => '2027-01-12'],
            ['name' => 'سير ناقل رئيسي — عنبر A', 'serial' => 'EG-CNV-001', 'cat' => 'سيور ناقلة', 'loc' => 'خط الإنتاج 1', 'status' => 'operational', 'mfr' => 'Demag', 'model' => 'CV-800', 'buy' => '2020-02-02', 'warranty' => null],
            ['name' => 'سير ناقل فرعي — عنبر A', 'serial' => 'EG-CNV-002', 'cat' => 'سيور ناقلة', 'loc' => 'خط الإنتاج 2', 'status' => 'under_maintenance', 'mfr' => 'Demag', 'model' => 'CV-600', 'buy' => '2020-02-02', 'warranty' => null],
            ['name' => 'موتور خط الإنتاج 45 كيلووات', 'serial' => 'EG-MTR-001', 'cat' => 'محركات', 'loc' => 'غرفة المحركات', 'status' => 'operational', 'mfr' => 'ABB', 'model' => 'M3BP-225', 'buy' => '2022-04-18', 'warranty' => '2027-04-18'],
            ['name' => 'موتور احتياطي 30 كيلووات', 'serial' => 'EG-MTR-002', 'cat' => 'محركات', 'loc' => 'مخزن قطع الغيار', 'status' => 'operational', 'mfr' => 'ABB', 'model' => 'M3BP-200', 'buy' => '2023-08-30', 'warranty' => '2028-08-30'],
            ['name' => 'لوحة تحكم خط 1', 'serial' => 'EG-PNL-001', 'cat' => 'لوحات تحكم', 'loc' => 'خط الإنتاج 1', 'status' => 'operational', 'mfr' => 'Schneider', 'model' => 'PLC-M580', 'buy' => '2021-03-15', 'warranty' => null],
            ['name' => 'لوحة تحكم خط 2', 'serial' => 'EG-PNL-002', 'cat' => 'لوحات تحكم', 'loc' => 'خط الإنتاج 2', 'status' => 'operational', 'mfr' => 'Schneider', 'model' => 'PLC-M580', 'buy' => '2023-01-12', 'warranty' => null],
            ['name' => 'محول كهرباء 500 ك.ف.أ', 'serial' => 'EG-TRF-001', 'cat' => 'مولدات ومحولات', 'loc' => 'غرفة الكهرباء الرئيسية', 'status' => 'operational', 'mfr' => 'ABB', 'model' => 'TR-500', 'buy' => '2018-07-07', 'warranty' => null],
            ['name' => 'غلاية بخار 2 طن', 'serial' => 'EG-BLR-001', 'cat' => 'غلايات وأفران', 'loc' => 'عنبر الإنتاج B', 'status' => 'operational', 'mfr' => 'Bosch', 'model' => 'UL-S-2000', 'buy' => '2021-12-01', 'warranty' => '2026-12-01'],
            ['name' => 'تكييف مركزي 20 طن — إداري', 'serial' => 'EG-HVAC-001', 'cat' => 'أنظمة تكييف', 'loc' => 'مصنع 6 أكتوبر — المبنى الرئيسي', 'status' => 'operational', 'mfr' => 'Carrier', 'model' => 'XCT-20', 'buy' => '2022-09-15', 'warranty' => '2027-09-15'],
            ['name' => 'طلمبة مياه غاطسة — تبريد', 'serial' => 'EG-PMP-001', 'cat' => 'طلمبات وكمبروسرات', 'loc' => 'غرفة المحركات', 'status' => 'operational', 'mfr' => 'Grundfos', 'model' => 'SP-60', 'buy' => '2023-05-20', 'warranty' => '2026-05-20'],
            ['name' => 'ونش علوي 5 طن', 'serial' => 'EG-CRN-001', 'cat' => 'مكابس', 'loc' => 'عنبر الإنتاج B', 'status' => 'operational', 'mfr' => 'Demag', 'model' => 'EK-5T', 'buy' => '2019-10-10', 'warranty' => null],
            ['name' => 'ماكينة لحام CO2', 'serial' => 'EG-WLD-001', 'cat' => 'مكابس', 'loc' => 'عنبر الإنتاج B', 'status' => 'retired', 'mfr' => 'ESAB', 'model' => 'MIG-500', 'buy' => '2015-04-04', 'warranty' => null],
            ['name' => 'ماكينة حقن بلاستيك — قديمة', 'serial' => 'EG-INJ-001', 'cat' => 'مكابس', 'loc' => 'عنبر الإنتاج B', 'status' => 'retired', 'mfr' => 'Haitian', 'model' => 'MA-1600', 'buy' => '2016-08-08', 'warranty' => null],
            ['name' => 'خلاط صناعي 500 لتر', 'serial' => 'EG-MIX-001', 'cat' => 'مكابس', 'loc' => 'خط الإنتاج 2', 'status' => 'broken', 'mfr' => 'Siemens', 'model' => 'MX-500', 'buy' => '2020-06-06', 'warranty' => null],
        ];

        foreach ($assets as $asset) {
            Asset::firstOrCreate(
                ['serial_number' => $asset['serial']],
                [
                    'name' => $asset['name'],
                    'category_id' => $categoryId($asset['cat']),
                    'location_id' => $locationId($asset['loc']),
                    'purchase_date' => $asset['buy'],
                    'warranty_expiry' => $asset['warranty'],
                    'status' => $asset['status'],
                    'manufacturer' => $asset['mfr'],
                    'model' => $asset['model'],
                    'created_by' => $creatorId,
                ]
            );
        }
    }
}
