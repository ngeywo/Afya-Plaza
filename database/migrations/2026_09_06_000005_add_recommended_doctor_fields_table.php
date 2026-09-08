<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 23: Additional recommended doctor profile fields
 * used by the profile-completeness engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $t) {
            $t->json('languages')->nullable()->after('years_of_experience');
            $t->json('areas_of_practice')->nullable()->after('languages');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $t) {
            $t->dropColumn(['languages', 'areas_of_practice']);
        });
    }
};
