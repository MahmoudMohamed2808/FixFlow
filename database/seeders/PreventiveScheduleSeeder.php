<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\PreventiveSchedule;
use Illuminate\Database\Seeder;

class PreventiveScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (Asset::count() === 0) {
            (new AssetSeeder)->run();
        }

        $plans = [
            ['serial' => 'EG-CMP-001', 'title' => 'صيانة شهرية للكمبروسور — فلاتر وزيوت', 'freq' => 'monthly', 'val' => 1, 'due' => '+10 days'],
            ['serial' => 'EG-CMP-002', 'title' => 'فحص أسبوعي للكمبروسور الاحتياطي', 'freq' => 'weekly', 'val' => 1, 'due' => '-2 days'],
            ['serial' => 'EG-GEN-001', 'title' => 'تشغيل تجريبي أسبوعي للمولد + فحص سولار', 'freq' => 'weekly', 'val' => 1, 'due' => '+3 days'],
            ['serial' => 'EG-PAK-001', 'title' => 'صيانة شهرية لماكينة التعبئة — سيور وحساسات', 'freq' => 'monthly', 'val' => 1, 'due' => '+5 days'],
            ['serial' => 'EG-PAK-002', 'title' => 'صيانة شهرية لماكينة التعبئة — سيور وحساسات', 'freq' => 'monthly', 'val' => 1, 'due' => '-1 day'],
            ['serial' => 'EG-CNV-001', 'title' => 'فحص شد السيور والرولمان — كل أسبوعين', 'freq' => 'weekly', 'val' => 2, 'due' => '+7 days'],
            ['serial' => 'EG-MTR-001', 'title' => 'قياس اهتزاز وحرارة الموتور شهريًا', 'freq' => 'monthly', 'val' => 1, 'due' => '+14 days'],
            ['serial' => 'EG-PNL-001', 'title' => 'مراجعة لوحة التحكم وتنظيف الكونتاكتورات', 'freq' => 'monthly', 'val' => 1, 'due' => '+20 days'],
            ['serial' => 'EG-TRF-001', 'title' => 'فحص سنوي شامل للمحول — زيت وعزل', 'freq' => 'yearly', 'val' => 1, 'due' => '+60 days'],
            ['serial' => 'EG-BLR-001', 'title' => 'فحص الغلاية والصمامات — شهري', 'freq' => 'monthly', 'val' => 1, 'due' => '-5 days'],
            ['serial' => 'EG-HVAC-001', 'title' => 'تنظيف فلاتر التكييف المركزي', 'freq' => 'monthly', 'val' => 1, 'due' => '+2 days'],
            ['serial' => 'EG-PMP-001', 'title' => 'فحص الطلمبة والضغوط — أسبوعي', 'freq' => 'weekly', 'val' => 1, 'due' => '+1 day'],
        ];

        foreach ($plans as $plan) {
            $asset = Asset::where('serial_number', $plan['serial'])->first();

            if (! $asset) {
                continue;
            }

            PreventiveSchedule::firstOrCreate(
                ['asset_id' => $asset->id, 'title' => $plan['title']],
                [
                    'frequency_type' => $plan['freq'],
                    'frequency_value' => $plan['val'],
                    'last_generated_date' => now()->subMonth()->toDateString(),
                    'next_due_date' => now()->modify($plan['due'])->toDateString(),
                    'is_active' => true,
                ]
            );
        }
    }
}
