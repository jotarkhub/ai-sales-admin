<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('category'); // fitur, harga, promo, syarat, pembayaran, faq, kebijakan, dst.
            $table->string('title');
            $table->longText('content');

            // AI hanya boleh memakai item berstatus "published" & masih berlaku (effective/expiry).
            $table->string('status')->default('draft'); // draft | published
            $table->unsignedInteger('priority')->default(0);
            $table->string('source')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_items');
    }
};
