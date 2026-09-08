<?php

namespace Database\Seeders;

use App\Models\ClinicSession;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ClinicSessionsSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        Doctor::with('doctorFacilities.schedules')->get()->each(function ($doctor) use ($today) {
            foreach ($doctor->doctorFacilities as $df) {
                if (! $df->is_active) {
                    continue;
                }

                foreach ($df->schedules as $schedule) {
                    if (! $schedule->is_active) {
                        continue;
                    }

                    for ($i = 0; $i < 14; $i++) {
                        $date = $today->copy()->addDays($i);

                        if ($date->dayOfWeek !== $schedule->day_of_week) {
                            continue;
                        }

                        ClinicSession::create([
                            'doctor_id' => $doctor->id,
                            'facility_id' => $df->facility_id,
                            'doctor_facility_schedule_id' => $schedule->id,
                            'session_date' => $date,
                            'start_time' => $schedule->start_time,
                            'end_time' => $schedule->end_time,
                            'slot_duration_minutes' => $schedule->slot_duration_minutes,
                            'max_appointments' => $schedule->max_appointments,
                            'consultation_fee' => $df->consultation_fee ?? $doctor->consultation_fee,
                            'status' => 'confirmed',
                            'doctor_confirmation' => 'confirmed',
                            'doctor_confirmed_at' => Carbon::now()->subDays(7),
                            'facility_confirmation' => 'confirmed',
                            'facility_confirmed_at' => Carbon::now()->subDays(7),
                        ]);
                    }
                }
            }
        });
    }
}
