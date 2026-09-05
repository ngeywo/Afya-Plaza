<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 100);
            $t->string('resource_type', 80);
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->string('resource_label', 191)->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('reason', 255)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamps();
            $t->index(['resource_type', 'resource_id'], 'idx_audit_resource');
            $t->index(['action'], 'idx_audit_action');
            $t->index(['actor_id', 'created_at'], 'idx_audit_actor_date');
            $t->index(['created_at'], 'idx_audit_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
