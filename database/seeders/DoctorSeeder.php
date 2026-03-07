<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Doctor;
use App\Models\Slot;
use Carbon\Carbon;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = [
            ['name' => 'Иван Петров', 'specialization' => 'Терапевт', 'experience_years' => 10],
            ['name' => 'Мария Смирнова', 'specialization' => 'Кардиолог', 'experience_years' => 8],
            ['name' => 'Алексей Иванов', 'specialization' => 'Хирург', 'experience_years' => 15],
            ['name' => 'Елена Козлова', 'specialization' => 'Педиатр', 'experience_years' => 12],
            ['name' => 'Дмитрий Сидоров', 'specialization' => 'Невролог', 'experience_years' => 7],
        ];
        
        foreach ($doctors as $doctorData) {
            $doctor = Doctor::create($doctorData);
            
            // Создаем слоты на неделю для каждого врача
            for ($day = 0; $day < 7; $day++) {
                $date = Carbon::now()->addDays($day)->startOfDay();
                
                // Слоты с 9:00 до 17:00, каждый час
                for ($hour = 9; $hour < 17; $hour++) {
                    Slot::create([
                        'doctor_id' => $doctor->id,
                        'start_time' => $date->copy()->addHours($hour),
                        'end_time' => $date->copy()->addHours($hour + 1),
                        'is_available' => rand(0, 1) // 50/50 доступность
                    ]);
                }
            }
        }
        
        $this->command->info('Создано ' . Doctor::count() . ' врачей');
        $this->command->info('Создано ' . Slot::count() . ' слотов');
    }
}
