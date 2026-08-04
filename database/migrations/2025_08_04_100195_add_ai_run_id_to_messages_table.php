<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipisah dari migration create_messages_table karena messages <-> ai_runs saling
     * mereferensikan (foreign key melingkar). Tabel ai_runs harus sudah ada dulu.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('ai_run_id')->nullable()->after('sent_by')
                ->constrained('ai_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_run_id');
        });
    }
};
