<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 23 (Payment reliability): introduce the EXPIRED payment state for
     * abandoned/pending payments.
     *
     * The payments.status column is a native ENUM on MySQL/MariaDB and a plain
     * varchar on SQLite (Laravel's grammar), so we only need to alter the column
     * on MySQL-family drivers.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true) && ! app()->runningUnitTests()) {
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','processing','paid','failed','cancelled','refunded','partially_refunded','expired') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true) && ! app()->runningUnitTests()) {
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','processing','paid','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending'");
        }
    }
};
