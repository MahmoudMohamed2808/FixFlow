<?php

use App\Console\Commands\GenerateWorkOrdersFromSchedules;
use App\Console\Commands\NotifyLowStock;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateWorkOrdersFromSchedules::class)
    ->daily()
    ->description('إنشاء أوامر شغل وقائية للمواعيد المستحقة');

Schedule::command(NotifyLowStock::class)
    ->daily()
    ->description('تنبيه إداري عند انخفاض مخزون قطع الغيار');
