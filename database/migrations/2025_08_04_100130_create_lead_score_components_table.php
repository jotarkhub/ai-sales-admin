<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_score_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_score_id')->constrained()->cascadeOnDelete();

            $table->string('component_key'); // urgency, budget_fit, need_match, dst.
            $table->string('label');
            $table->decimal('weight', 5, 2)->default(1);
            $table->decimal('raw_value', 10, 2)->nullable();
            $table->decimal('points', 6, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_score_components');
    }
};
