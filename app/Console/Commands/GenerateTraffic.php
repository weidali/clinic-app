<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class GenerateTraffic extends Command
{
    protected $signature = 'traffic:generate {count=50 : Количество запросов}';
    protected $description = 'Генерирует тестовый трафик для демо OpenTelemetry';

    public function handle()
    {
        $count = $this->argument('count');
        $baseUri = Config::get('api.base_uri');

        $this->info("Генерирация {$count} запросов...");
        $this->newLine();

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            try {
                // 60% - успешные сценарии
                if (rand(1, 100) <= 60) {
                    $this->successfulAppointment($baseUri);
                }
                // 20% - поиск без записи
                elseif (rand(1, 100) <= 50) {
                    $this->searchOnly($baseUri);
                }
                // 20% - ошибки
                else {
                    $this->errorScenario($baseUri);
                }
            } catch (\Exception $e) {
                // Ловим ошибки, но продолжаем
            }

            $bar->advance();
            usleep(rand(100000, 300000));
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('✅ Генерация завершена!');

        // Показываем статистику
        $this->table(
            ['Всего записей', 'Сегодня', 'За последний час'],
            [
                [
                    Appointment::count(),
                    Appointment::whereDate('created_at', Carbon::today())->count(),
                    Appointment::where('created_at', '>=', Carbon::now()->subHour())->count()
                ]
            ]
        );
    }

    protected function successfulAppointment($baseUri)
    {
        $doctor = Doctor::inRandomOrder()->first();
        if (!$doctor) return;

        Http::get($baseUri . '/api/doctors/search', [
            'specialization' => $doctor->specialization
        ]);

        Appointment::create([
            'doctor_id' => $doctor->id,
            'patient_name' => 'Тест Пациент ' . rand(1, 100),
            'patient_phone' => '+7' . rand(9000000000, 9999999999),
            'patient_email' => 'patient' . rand(1, 100) . '@example.com',
            'appointment_date' => Carbon::now()->addDays(rand(1, 5))->setTime(rand(9, 16), 0),
            'status' => 'confirmed',
            'symptoms' => 'Тестовые симптомы'
        ]);
    }

    protected function searchOnly($baseUri)
    {
        $specializations = ['Терапевт', 'Кардиолог', 'Хирург', 'Педиатр', 'Невролог'];

        Http::get($baseUri . '/api/doctors/search', [
            'specialization' => $specializations[array_rand($specializations)]
        ]);
    }

    protected function errorScenario($baseUri)
    {
        $scenario = rand(1, 3);

        switch ($scenario) {
            case 1:
                Http::get($baseUri . '/api/slots', [
                    'doctor_id' => 999999,
                    'date' => Carbon::now()->format('Y-m-d')
                ]);
                break;
            case 2:
                Http::post($baseUri . '/api/appointments', [
                    'doctor_id' => 'not_a_number',
                    'patient_name' => '',
                    'patient_email' => 'not_an_email'
                ]);
                break;
            case 3:
                $doctor = Doctor::inRandomOrder()->first();
                if ($doctor) {
                    Http::timeout(1)->get($baseUri . '/api/internal/slots', [
                        'doctor_id' => $doctor->id,
                        'date' => Carbon::now()->format('Y-m-d')
                    ]);
                }
                break;
        }
    }
}
