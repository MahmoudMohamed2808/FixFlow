<?php

namespace App\Console\Commands;

use App\Models\SparePart;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyLowStock extends Command
{
    protected $signature = 'inventory:notify-low-stock';

    protected $description = 'Notify administrators about spare parts that have fallen below their minimum quantity.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lowStockParts = SparePart::query()
            ->whereColumn('quantity_in_stock', '<=', 'minimum_quantity')
            ->with('supplier')
            ->get();

        if ($lowStockParts->isEmpty()) {
            $this->info('No spare parts are below the minimum quantity.');

            return self::SUCCESS;
        }

        $admins = User::role('Admin')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new LowStockAlert($lowStockParts));
            $this->info("Found {$lowStockParts->count()} low-stock spare part(s) and notified administrators.");

            return self::SUCCESS;
        }

        $this->info("Found {$lowStockParts->count()} low-stock spare part(s); no Admin role seeded, nothing sent.");

        return self::SUCCESS;
    }
}
