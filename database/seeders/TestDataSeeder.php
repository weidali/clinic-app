<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        $specializations = ['Терапевт', 'Кардиолог', 'Хирург', 'Педиатр', 'Невролог'];

        // Создаем врачей
        foreach ($specializations as $spec) {
            for ($i = 0; $i < 3; $i++) {
                $doctor = Doctor::create([
                    'name' => "Доктор {$spec} " . ($i + 1),
                    'specialization' => $spec,
                    'experience_years' => rand(3, 25),
                ]);

                // Создаем слоты на неделю вперед
                for ($day = 0; $day < 7; $day++) {
                    $date = Carbon::now()->addDays($day);

                    for ($hour = 9; $hour <= 17; $hour += 2) {
                        $startTime = $date->copy()->setTime($hour, 0);
                        $endTime = $startTime->copy()->addHours(1);

                        Slot::create([
                            'doctor_id' => $doctor->id,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'is_available' => rand(0, 3) > 0, // 75% свободных
                        ]);
                    }
                }
            }
        }
    }
}
