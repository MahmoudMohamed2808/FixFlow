<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    /**
     * عينة صغيرة من سجل الأنشطة بأفعال حقيقية (تُسجَّل عادةً عبر ActivityLog::record
     * من الشاشات، لا عبر observers — انظر .ai/rules/models.md).
     */
    public function run(): void
    {
        if (User::count() === 0 || WorkOrder::count() === 0) {
            return;
        }

        if (ActivityLog::count() > 0) {
            return;
        }

        $supervisor = User::where('email', 'ahmed.elsherif@fixflow.test')->first() ?? User::first();
        $tech = User::where('email', 'mahmoud.elsayed@fixflow.test')->first() ?? User::first();

        foreach (WorkOrder::orderBy('id')->limit(8)->get() as $order) {
            ActivityLog::record('created', $order, $supervisor->id);

            if ($order->assigned_to) {
                ActivityLog::record("assigned:{$order->assigned_to}", $order, $supervisor->id);
            }

            if (in_array($order->status, ['in_progress', 'completed'], true)) {
                ActivityLog::record('update_status:in_progress', $order, $tech->id);
            }

            if ($order->status === 'completed') {
                ActivityLog::record('update_status:completed', $order, $tech->id);
            }
        }

        foreach (Asset::orderBy('id')->limit(4)->get() as $asset) {
            ActivityLog::record('created', $asset, $supervisor->id);
        }
    }
}
