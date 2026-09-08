<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 23 (Facility Payments): the platform fee applied when a patient pays
     * a facility. Always configurable — NEVER hard-coded. An active effective row
     * wins; else PlatformFeeService falls back to the value in config/services.php.
     */
    public function up(): void
    {
        Schema::create('platform_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->unsignedTinyInteger('fee_type'); // 1 = percentage, 2 = fixed
            $table->decimal('rate', 6, 4); // 0.0500 = 5% or fixed KES amount
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fees');
    }
};
