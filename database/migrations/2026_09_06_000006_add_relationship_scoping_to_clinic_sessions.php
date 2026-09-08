<?php

use App\Models\DoctorFacility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 23 (Facility Management): optionally scope each clinic session to the
     * exact doctor-facility relationship it was created from. Backfilled from the
     * governing relationship (or the schedule that generated the session).
     */
    public function up(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            $table->foreignId('doctor_facility_id')->nullable()->after('facility_id')->constrained()->nullOnDelete();
            $table->index(['doctor_facility_id'], 'idx_clinic_sessions_doctor_facility');
        });

        // Backfill: prefer the relationship that produced the recurring schedule,
        // otherwise the single (doctor_id, facility_id) relationship.
        $schedule = DB::table('clinic_sessions as cs')
            ->join('doctor_facility_schedules as s', 's.id', '=', 'cs.doctor_facility_schedule_id')
            ->select('cs.id', 's.doctor_facility_id')
            ->get();

        foreach ($schedule as $row) {
            if (! $row->doctor_facility_id) {
                continue;
            }
            DB::table('clinic_sessions')->where('id', $row->id)->update(['doctor_facility_id' => $row->doctor_facility_id]);
        }

        $remaining = DB::table('clinic_sessions')->whereNull('doctor_facility_id')
            ->select('id', 'doctor_id', 'facility_id')
            ->get();

        foreach ($remaining as $row) {
            $relationship = DoctorFacility::where('doctor_id', $row->doctor_id)
                ->where('facility_id', $row->facility_id)
                ->first();
            if ($relationship) {
                DB::table('clinic_sessions')->where('id', $row->id)->update(['doctor_facility_id' => $relationship->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('clinic_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_clinic_sessions_doctor_facility');
            $table->dropForeign(['doctor_facility_id']);
            $table->dropColumn('doctor_facility_id');
        });
    }
};
