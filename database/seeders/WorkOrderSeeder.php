<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Client;
use App\Models\PreventiveSchedule;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([AssetSeeder::class, ClientSeeder::class, UserSeeder::class, PreventiveScheduleSeeder::class, SparePartSeeder::class] as $seeder) {
            if ($this->dependencyMissing($seeder)) {
                (new $seeder)->run();
            }
        }

        $asset = fn (string $serial) => Asset::where('serial_number', $serial)->firstOrFail();
        $client = fn (string $email) => Client::where('email', $email)->first();
        $tech = fn (string $email) => User::where('email', $email)->firstOrFail();
        $part = fn (string $code) => SparePart::where('code', $code)->firstOrFail();

        $pmFor = fn (string $serial) => PreventiveSchedule::whereHas('asset', fn ($q) => $q->where('serial_number', $serial))->first()?->id;

        $orders = [
            // مكتملة — عميل خارجي مع قطع غيار وأجور (تظهر في الفواتير لاحقًا).
            ['code' => 'WO-1001', 'asset' => 'EG-CMP-001', 'client' => 'a.naggar@elnour-foods.test', 'type' => 'corrective', 'priority' => 'high', 'status' => 'completed', 'desc' => 'ارتفاع حرارة الكمبروسور — تم تغيير فلتر الزيت والهواء', 'rep' => 'mahmoud.elsayed@fixflow.test', 'to' => 'mahmoud.elsayed@fixflow.test', 'sched' => '-20 days 10:00', 'start' => '-20 days 10:30', 'end' => '-19 days 15:00', 'parts' => [['SP-1001', 2], ['SP-1002', 1]], 'labor' => [['mahmoud.elsayed@fixflow.test', 5]], 'comments' => [['karim.fouad@fixflow.test', 'العميل استلم والكمبروسور شغال بحرارة طبيعية.']], 'downtime' => ['-20 days 10:00', '-19 days 15:00']],
            ['code' => 'WO-1002', 'asset' => 'EG-PAK-001', 'client' => 'a.naggar@elnour-foods.test', 'type' => 'preventive', 'priority' => 'medium', 'status' => 'completed', 'desc' => 'صيانة وقائية شهرية لماكينة التعبئة — سيور وحساسات', 'rep' => 'ahmed.elsherif@fixflow.test', 'to' => 'mostafa.hassan@fixflow.test', 'sched' => '-15 days 09:00', 'start' => '-15 days 09:00', 'end' => '-15 days 16:00', 'pm' => 'EG-PAK-001', 'parts' => [['SP-1003', 4]], 'labor' => [['mostafa.hassan@fixflow.test', 7]], 'comments' => [['mostafa.hassan@fixflow.test', 'تم تغيير جزء من السير ومعايرة الحساسات.']]],
            ['code' => 'WO-1003', 'asset' => 'EG-GEN-002', 'client' => 'm.abdelaziz@delta-contracting.test', 'type' => 'corrective', 'priority' => 'urgent', 'status' => 'completed', 'desc' => 'المولد لا يعمل صباحًا — تم إصلاح دائرة السولار وتغيير الفلتر', 'rep' => 'karim.fouad@fixflow.test', 'to' => 'ibrahim.ramadan@fixflow.test', 'sched' => '-30 days 08:00', 'start' => '-30 days 08:30', 'end' => '-28 days 13:00', 'parts' => [['SP-1016', 2], ['SP-1008', 3]], 'labor' => [['ibrahim.ramadan@fixflow.test', 9]], 'comments' => [['ibrahim.ramadan@fixflow.test', 'تم التشغيل التجريبي 3 مرات بحضور العميل.']], 'downtime' => ['-30 days 08:00', '-28 days 13:00']],
            ['code' => 'WO-1004', 'asset' => 'EG-MIX-001', 'client' => 'karim@egy-pack.test', 'type' => 'corrective', 'priority' => 'high', 'status' => 'completed', 'desc' => 'اهتزاز شديد في الخلاط — تم تغيير رولمان البلي', 'rep' => 'mahmoud.elsayed@fixflow.test', 'to' => 'hany.farrag@fixflow.test', 'sched' => '-12 days 11:00', 'start' => '-12 days 11:00', 'end' => '-11 days 17:00', 'parts' => [['SP-1004', 2], ['SP-1011', 1]], 'labor' => [['hany.farrag@fixflow.test', 6]], 'comments' => []],
            ['code' => 'WO-1005', 'asset' => 'EG-BLR-001', 'client' => 'sameh@elsafa-dairy.test', 'type' => 'preventive', 'priority' => 'medium', 'status' => 'completed', 'desc' => 'فحص الغلاية والصمامات الشهري', 'rep' => 'ahmed.elsherif@fixflow.test', 'to' => 'walid.hamdy@fixflow.test', 'sched' => '-8 days 09:00', 'start' => '-8 days 09:00', 'end' => '-8 days 14:00', 'pm' => 'EG-BLR-001', 'parts' => [], 'labor' => [['walid.hamdy@fixflow.test', 5]], 'comments' => [['walid.hamdy@fixflow.test', 'الضغوط سليمة ولا توجد تسريبات.']]],
            // جارٍ تنفيذها.
            ['code' => 'WO-1006', 'asset' => 'EG-CMP-002', 'client' => 'tarek@elobour-eng.test', 'type' => 'corrective', 'priority' => 'high', 'status' => 'in_progress', 'desc' => 'صوت عالي من الكمبروسور الاحتياطي مع اهتزاز', 'rep' => 'mostafa.hassan@fixflow.test', 'to' => 'mostafa.hassan@fixflow.test', 'sched' => '-2 days 09:00', 'start' => '-2 days 09:30', 'end' => null, 'parts' => [['SP-1004', 1]], 'labor' => [['mostafa.hassan@fixflow.test', 3]], 'comments' => [['mostafa.hassan@fixflow.test', 'بانتظار وصول سير بديل من المورد.']], 'attach' => true],
            ['code' => 'WO-1007', 'asset' => 'EG-CNV-002', 'client' => null, 'type' => 'corrective', 'priority' => 'medium', 'status' => 'in_progress', 'desc' => 'تآكل في السير الفرعي — جارٍ الاستبدال', 'rep' => 'mahmoud.elsayed@fixflow.test', 'to' => 'mahmoud.elsayed@fixflow.test', 'sched' => '-1 day 10:00', 'start' => '-1 day 10:00', 'end' => null, 'parts' => [], 'labor' => [['mahmoud.elsayed@fixflow.test', 2]], 'comments' => []],
            ['code' => 'WO-1008', 'asset' => 'EG-PNL-002', 'client' => 'karim@egy-pack.test', 'type' => 'corrective', 'priority' => 'urgent', 'status' => 'in_progress', 'desc' => 'لوحة التحكم لا تستجيب لأوامر التشغيل', 'rep' => 'karim.fouad@fixflow.test', 'to' => 'hany.farrag@fixflow.test', 'sched' => '-3 hours', 'start' => '-2 hours', 'end' => null, 'parts' => [['SP-1006', 1]], 'labor' => [], 'comments' => [['ahmed.elsherif@fixflow.test', 'أولوية قصوى — خط العميل واقف.']], 'downtime' => ['-3 hours', null]],
            // مسندة ولم تبدأ.
            ['code' => 'WO-1009', 'asset' => 'EG-HVAC-001', 'client' => 'hany@elnile-pharma.test', 'type' => 'preventive', 'priority' => 'low', 'status' => 'assigned', 'desc' => 'تنظيف فلاتر التكييف المركزي', 'rep' => 'ahmed.elsherif@fixflow.test', 'to' => 'tarek.ezzat@fixflow.test', 'sched' => '+2 days 09:00', 'start' => null, 'end' => null, 'pm' => 'EG-HVAC-001', 'parts' => [], 'labor' => [], 'comments' => []],
            ['code' => 'WO-1010', 'asset' => 'EG-TRF-001', 'client' => null, 'type' => 'preventive', 'priority' => 'medium', 'status' => 'assigned', 'desc' => 'فحص سنوي للمحول — زيت وعزل', 'rep' => 'ahmed.elsherif@fixflow.test', 'to' => 'ibrahim.ramadan@fixflow.test', 'sched' => '+5 days 08:00', 'start' => null, 'end' => null, 'pm' => 'EG-TRF-001', 'parts' => [], 'labor' => [], 'comments' => []],
            // مفتوحة غير مسندة.
            ['code' => 'WO-1011', 'asset' => 'EG-CNV-001', 'client' => 'mona@elsharq-textile.test', 'type' => 'corrective', 'priority' => 'medium', 'status' => 'open', 'desc' => 'بلاغ من الوردية: صوت احتكاك في السير الرئيسي', 'rep' => 'walid.hamdy@fixflow.test', 'to' => null, 'sched' => '+1 day 10:00', 'start' => null, 'end' => null, 'parts' => [], 'labor' => [], 'comments' => []],
            ['code' => 'WO-1012', 'asset' => 'EG-PMP-001', 'client' => null, 'type' => 'corrective', 'priority' => 'low', 'status' => 'open', 'desc' => 'ضعف ضغط الطلمبة وخروج مياه متقطعة', 'rep' => 'tarek.ezzat@fixflow.test', 'to' => null, 'sched' => null, 'start' => null, 'end' => null, 'parts' => [], 'labor' => [], 'comments' => []],
            ['code' => 'WO-1013', 'asset' => 'EG-MTR-001', 'client' => 'walid@cairo-metal.test', 'type' => 'preventive', 'priority' => 'low', 'status' => 'open', 'desc' => 'قياس اهتزاز وحرارة الموتور — طلب عميل', 'rep' => 'karim.fouad@fixflow.test', 'to' => null, 'sched' => '+7 days 11:00', 'start' => null, 'end' => null, 'parts' => [], 'labor' => [], 'comments' => []],
            // ملغاة.
            ['code' => 'WO-1014', 'asset' => 'EG-WLD-001', 'client' => null, 'type' => 'corrective', 'priority' => 'low', 'status' => 'cancelled', 'desc' => 'بلاغ عن ماكينة اللحام المتقاعدة — أُلغي لخروجها من الخدمة', 'rep' => 'mahmoud.elsayed@fixflow.test', 'to' => null, 'sched' => '-40 days 09:00', 'start' => null, 'end' => null, 'parts' => [], 'labor' => [], 'comments' => [['ahmed.elsherif@fixflow.test', 'أُلغي: المعدة متقاعدة ولا تستحق الإصلاح.']]],
            // مكتملة إضافية لتنويع التقارير والفواتير.
            ['code' => 'WO-1015', 'asset' => 'EG-GEN-001', 'client' => 'm.abdelaziz@delta-contracting.test', 'type' => 'preventive', 'priority' => 'medium', 'status' => 'completed', 'desc' => 'تشغيل تجريبي أسبوعي للمولد وتغيير فلتر السولار', 'rep' => 'ibrahim.ramadan@fixflow.test', 'to' => 'ibrahim.ramadan@fixflow.test', 'sched' => '-25 days 08:00', 'start' => '-25 days 08:00', 'end' => '-25 days 12:00', 'pm' => 'EG-GEN-001', 'parts' => [['SP-1016', 1]], 'labor' => [['ibrahim.ramadan@fixflow.test', 4]], 'comments' => []],
            ['code' => 'WO-1016', 'asset' => 'EG-PAK-002', 'client' => 'tarek@elobour-eng.test', 'type' => 'corrective', 'priority' => 'high', 'status' => 'completed', 'desc' => 'توقف مفاجئ لخط 2 — عطل حساس وتمت المعايرة', 'rep' => 'mostafa.hassan@fixflow.test', 'to' => 'mostafa.hassan@fixflow.test', 'sched' => '-18 days 14:00', 'start' => '-18 days 14:00', 'end' => '-18 days 19:00', 'parts' => [['SP-1013', 1]], 'labor' => [['mostafa.hassan@fixflow.test', 5]], 'comments' => [['mostafa.hassan@fixflow.test', 'تمت التجربة بحضور مشرف الوردية.']], 'downtime' => ['-18 days 14:00', '-18 days 19:00'], 'attach' => true],
            ['code' => 'WO-1017', 'asset' => 'EG-CNV-001', 'client' => null, 'type' => 'corrective', 'priority' => 'low', 'status' => 'completed', 'desc' => 'تشحيم رولمان السير الرئيسي (داخلي)', 'rep' => 'walid.hamdy@fixflow.test', 'to' => 'walid.hamdy@fixflow.test', 'sched' => '-10 days 09:00', 'start' => '-10 days 09:00', 'end' => '-10 days 11:00', 'parts' => [['SP-1011', 1]], 'labor' => [['walid.hamdy@fixflow.test', 2]], 'comments' => []],
            ['code' => 'WO-1018', 'asset' => 'EG-PNL-001', 'client' => 'sherif@elkhaleeg-hvac.test', 'type' => 'corrective', 'priority' => 'medium', 'status' => 'completed', 'desc' => 'تغيير كونتاكتور تالف في لوحة خط 1', 'rep' => 'hany.farrag@fixflow.test', 'to' => 'hany.farrag@fixflow.test', 'sched' => '-6 days 13:00', 'start' => '-6 days 13:00', 'end' => '-6 days 16:00', 'parts' => [['SP-1006', 1], ['SP-1008', 2]], 'labor' => [['hany.farrag@fixflow.test', 3]], 'comments' => []],
            ['code' => 'WO-1019', 'asset' => 'EG-CRN-001', 'client' => null, 'type' => 'preventive', 'priority' => 'medium', 'status' => 'completed', 'desc' => 'فحص الونش وتيل الفرامل (داخلي)', 'rep' => 'ahmed.elsherif@fixflow.test', 'to' => 'omar.abdelrahman@fixflow.test', 'sched' => '-5 days 09:00', 'start' => '-5 days 09:00', 'end' => '-5 days 12:00', 'parts' => [], 'labor' => [['omar.abdelrahman@fixflow.test', 3]], 'comments' => []],

            ['code' => 'WO-1020', 'asset' => 'EG-MTR-002', 'client' => 'walid@cairo-metal.test', 'type' => 'corrective', 'priority' => 'medium', 'status' => 'assigned', 'desc' => 'تجهيز الموتور الاحتياطي للتركيب الأسبوع القادم', 'rep' => 'karim.fouad@fixflow.test', 'to' => 'khaled.mansour@fixflow.test', 'sched' => '+3 days 10:00', 'start' => null, 'end' => null, 'parts' => [], 'labor' => [], 'comments' => []],
        ];

        foreach ($orders as $data) {
            $order = WorkOrder::firstOrCreate(
                ['code' => $data['code']],
                [
                    'asset_id' => $asset($data['asset'])->id,
                    'client_id' => $data['client'] ? $client($data['client'])?->id : null,
                    'type' => $data['type'],
                    'priority' => $data['priority'],
                    'status' => $data['status'],
                    'description' => $data['desc'],
                    'reported_by' => $tech($data['rep'])->id,
                    'assigned_to' => $data['to'] ? $tech($data['to'])->id : null,
                    'schedule_id' => isset($data['pm']) ? $pmFor($data['pm']) : null,
                    'scheduled_date' => $data['sched'] ? now()->modify($data['sched']) : null,
                    'started_at' => $data['start'] ? now()->modify($data['start']) : null,
                    'completed_at' => $data['end'] ? now()->modify($data['end']) : null,
                ]
            );

            foreach ($data['parts'] as [$code, $qty]) {
                $spare = $part($code);

                if (! $order->parts()->where('spare_part_id', $spare->id)->exists()) {
                    $order->parts()->create([
                        'spare_part_id' => $spare->id,
                        'quantity_used' => $qty,
                        'unit_cost' => $spare->unit_price,
                    ]);
                }
            }

            foreach ($data['labor'] as [$email, $hours]) {
                $technician = $tech($email);

                if (! $order->laborEntries()->where('user_id', $technician->id)->exists()) {
                    $order->laborEntries()->create([
                        'user_id' => $technician->id,
                        'hours_spent' => $hours,
                        'hourly_rate' => $technician->hourly_rate ?? 150,
                    ]);
                }
            }

            foreach ($data['comments'] as [$email, $text]) {
                $author = $tech($email);

                if (! $order->comments()->where('user_id', $author->id)->where('comment', $text)->exists()) {
                    $order->comments()->create(['user_id' => $author->id, 'comment' => $text]);
                }
            }

            if (! empty($data['attach']) && $order->attachments()->count() === 0) {
                $order->attachments()->create(['file_path' => 'attachments/'.$order->code.'-report.pdf']);
            }

            if (! empty($data['downtime'])) {
                [$start, $end] = $data['downtime'];

                if ($order->downtimeRecord()->doesntExist()) {
                    $order->downtimeRecord()->create([
                        'asset_id' => $order->asset_id,
                        'start_time' => now()->modify($start),
                        'end_time' => $end ? now()->modify($end) : null,
                    ]);
                }
            }
        }
    }

    private function dependencyMissing(string $seeder): bool
    {
        return match ($seeder) {
            AssetSeeder::class => Asset::count() === 0,
            ClientSeeder::class => Client::count() === 0,
            UserSeeder::class => User::where('email', 'mahmoud.elsayed@fixflow.test')->doesntExist(),
            PreventiveScheduleSeeder::class => PreventiveSchedule::count() === 0,
            SparePartSeeder::class => SparePart::count() === 0,
            default => false,
        };
    }
}
