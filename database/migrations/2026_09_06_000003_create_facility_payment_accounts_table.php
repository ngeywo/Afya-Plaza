<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 23 (Facility Payments): where patient money is collected FOR a facility.
     *
     * Only non-secret, user-facing details are stored (paybill/till numbers, bank
     * account numbers and names). Credentials/secrets for payment providers are
     * NEVER persisted here — patient→facility settlement is described by these
     * accounts so the UI can show "you are paying Facility X via M-Pesa Paybill".
     */
    public function up(): void
    {
        Schema::create('facility_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50); // mpesa | card | bank_transfer | cash
            $table->string('account_type', 30); // paybill | till_number | bank | mpesa_express | cash
            $table->string('account_name', 255)->nullable();
            $table->string('account_number', 191);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_payment_accounts');
    }
};
