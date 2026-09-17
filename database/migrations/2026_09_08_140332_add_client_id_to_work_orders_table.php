<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // client_id = null يعني شغل داخلي للشركة، لو متحدد يبقى شغل مدفوع لعميل خارجي
            $table->foreignId('client_id')->nullable()->after('asset_id')->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
