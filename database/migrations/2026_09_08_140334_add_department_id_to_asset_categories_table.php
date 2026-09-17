<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            // nullable عن قصد: التصنيفات القديمة ليس لها قسم، ويتم تعبئتها أدناه.
            // القيد الصارم (required + unique) يُفرض على مستوى التحقق/النموذج.
            $table->foreignId('department_id')->nullable()->after('id')->constrained('departments')->nullOnDelete();
        });

        // تعبئة التصنيفات الموجودة مسبقًا بأول قسم متاح حتى لا تبقى يتيمة.
        $departmentId = DB::table('departments')->orderBy('id')->value('id');

        if ($departmentId) {
            DB::table('asset_categories')->whereNull('department_id')->update(['department_id' => $departmentId]);
        }
    }

    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
