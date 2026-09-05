<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');          // e.g. "Starter", "Professional", "Premium"
            $table->string('slug')->unique(); // e.g. "starter", "professional", "premium"
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0); // KES per month; 0 = free
            $table->decimal('default_commission_rate', 5, 4)->default(0.1500); // stored as fraction: 0.1500 = 15%
            $table->unsignedTinyInteger('commission_type')->default(1); // 1=percentage, 2=fixed_amount
            $table->decimal('fixed_commission_amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // plan assigned to new doctors automatically
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the three marketplace plans
        DB::table('plans')->insert([
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For doctors getting started. Standard commission applies.',
                'monthly_price' => 0,
                'default_commission_rate' => 0.1500,
                'commission_type' => 1,
                'fixed_commission_amount' => 0,
                'is_active' => 1,
                'is_default' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For established practitioners. Reduced commission.',
                'monthly_price' => 5000,
                'default_commission_rate' => 0.1000,
                'commission_type' => 1,
                'fixed_commission_amount' => 0,
                'is_active' => 1,
                'is_default' => 0,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'For busy specialists. Lowest commission rate.',
                'monthly_price' => 15000,
                'default_commission_rate' => 0.0500,
                'commission_type' => 1,
                'fixed_commission_amount' => 0,
                'is_active' => 1,
                'is_default' => 0,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
