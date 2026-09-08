<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('registry_number')->nullable()->after('license_number');
        });
        Schema::table('doctors', function (Blueprint $table) {
            $table->unique('license_number', 'doctors_license_number_unique');
            $table->unique('registry_number', 'doctors_registry_number_unique');
        });
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('registry_number')->nullable()->after('name');
            $table->unique('registry_number', 'facilities_registry_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique('doctors_license_number_unique');
            $table->dropUnique('doctors_registry_number_unique');
            $table->dropColumn('registry_number');
        });
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropUnique('facilities_registry_number_unique');
            $table->dropColumn('registry_number');
        });
    }
};
