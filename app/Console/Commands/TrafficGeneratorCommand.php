<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;

class TrafficGeneratorCommand extends Command
{
    protected $signature = 'traffic:simulate
                            {--requests=100 : Количество запросов}
                            {--rate=0.5 : Коэффициент сэмплирования}';

    protected $description = 'Генерация тестового трафика для OpenTelemetry';

    private TracerInterface $tracer;
    private array $specializations = ['Терапевт', 'Кардиолог', 'Хирург', 'Педиатр', 'Невролог'];

    public function __construct(TracerInterface $tracer)
    {
        parent::__construct();
        $this->tracer = $tracer;
    }

    public function handle()
    {
        $requests = (int) $this->option('requests');
        $this->info("Запуск генерации $requests запросов...");
        $this->newLine();
        $baseUri = Config::get('api.base_uri');

        $bar = $this->output->createProgressBar($requests);
        $bar->setFormat('verbose');

        for ($i = 0; $i < $requests; $i++) {
            $span = $this->tracer->spanBuilder('traffic.scenario')
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->setAttribute('scenario.number', $i + 1)
                ->setAttribute('scenario.total', $requests)
                ->startSpan();

            $scope = $span->activate();

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

                $span->setAttribute('scenario.status', 'success');
            } catch (\Exception $e) {
                $span->recordException($e);
                $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
                $span->setAttribute('scenario.status', 'error');

                $this->error("❌ Ошибка в запросе " . ($i + 1) . ": " . $e->getMessage());
            } finally {
                $scope->detach();
                $span->end();
            }

            // Прогресс бар
            $bar->advance();

            // Пауза между запросами
            usleep(rand(100000, 500000));
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Генерация завершена!");
        $this->line("📊 Zipkin: http://localhost:9411");
    }

    private function successfulAppointment(string $baseUri): void
    {
        // Создаем дочерний span для успешной записи
        $span = $this->tracer->spanBuilder('scenario.successful_appointment')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $scope = $span->activate();

        try {
            $doctor = Doctor::inRandomOrder()->first();

            if (!$doctor) {
                throw new \Exception('Нет доступных врачей');
            }

            $span->setAttribute('doctor.id', $doctor->id);
            $span->setAttribute('doctor.specialization', $doctor->specialization);

            // 1. Поиск врача
            $this->searchDoctor($doctor->specialization, $baseUri);

            // 2. Получение слотов
            $date = Carbon::now()->addDays(rand(1, 5))->format('Y-m-d');
            $slots = $this->getAvailableSlots($doctor->id, $date, $baseUri);

            // 3. Создание записи
            if (!empty($slots)) {
                $this->createAppointment($doctor, $slots[0], $date, $baseUri);
            }

            $span->setAttribute('scenario.steps_completed', 3);
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function searchDoctor(string $specialization, string $baseUri): void
    {
        $span = $this->tracer->spanBuilder('api.search_doctor')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.method', 'GET')
            ->setAttribute('http.url', '/api/doctors/search')
            ->setAttribute('specialization', $specialization)
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = Http::get($baseUri . '/api/doctors/search', [
                'specialization' => $specialization
            ]);

            $span->setAttribute('http.status_code', $response->status());
            $span->setAttribute('http.response_size', strlen($response->body()));

            if ($response->failed()) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'API вернул ошибку');
            }
        } catch (\Exception $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function getAvailableSlots(int $doctorId, string $date, string $baseUri): array
    {
        $span = $this->tracer->spanBuilder('api.get_slots')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.method', 'GET')
            ->setAttribute('http.url', '/api/slots')
            ->setAttribute('doctor.id', $doctorId)
            ->setAttribute('date', $date)
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = Http::get($baseUri . '/api/slots', [
                'doctor_id' => $doctorId,
                'date' => $date
            ]);

            $span->setAttribute('http.status_code', $response->status());

            if ($response->successful()) {
                $data = $response->json();
                $slots = $data['slots'] ?? [];
                $span->setAttribute('slots.found', count($slots));

                return $slots;
            }

            return [];
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function createAppointment(Doctor $doctor, array $slot, string $date, string $baseUri): void
    {
        $span = $this->tracer->spanBuilder('api.create_appointment')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.method', 'POST')
            ->setAttribute('http.url', '/api/appointments')
            ->setAttribute('doctor.id', $doctor->id)
            ->setAttribute('appointment.date', $date)
            ->startSpan();

        $scope = $span->activate();

        try {
            $patientId = rand(1, 100);

            $span->setAttribute('patient.id', $patientId);

            $response = Http::post($baseUri . '/api/appointments', [
                'doctor_id' => $doctor->id,
                'patient_name' => "Тест Пациент {$patientId}",
                'patient_phone' => '+7' . rand(9000000000, 9999999999),
                'patient_email' => "patient{$patientId}@example.com",
                'appointment_date' => $slot['start_time'],
                'symptoms' => 'Тестовые симптомы #' . rand(1000, 9999)
            ]);

            $span->setAttribute('http.status_code', $response->status());

            if ($response->successful()) {
                $data = $response->json();
                $span->setAttribute('appointment.id', $data['id'] ?? 'unknown');
            }
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function searchOnly(string $baseUri): void
    {
        $span = $this->tracer->spanBuilder('scenario.search_only')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $scope = $span->activate();

        try {
            $specialization = $this->specializations[array_rand($this->specializations)];
            $span->setAttribute('specialization', $specialization);

            $this->searchDoctor($specialization, $baseUri);
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function errorScenario(string $baseUri): void
    {
        $span = $this->tracer->spanBuilder('scenario.error')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $scope = $span->activate();

        try {
            $scenario = rand(1, 3);
            $span->setAttribute('error.scenario', $scenario);

            switch ($scenario) {
                case 1: // Несуществующий врач
                    $span->setAttribute('error.type', 'invalid_doctor');
                    $this->getAvailableSlots(999999, Carbon::now()->format('Y-m-d'), $baseUri);
                    break;

                case 2: // Невалидные данные
                    $span->setAttribute('error.type', 'invalid_data');
                    $span = $this->tracer->spanBuilder('api.create_appointment_error')
                        ->setSpanKind(SpanKind::KIND_CLIENT)
                        ->setAttribute('http.method', 'POST')
                        ->setAttribute('http.url', '/api/appointments')
                        ->setAttribute('error.expected', true)
                        ->startSpan();

                    $scope2 = $span->activate();

                    try {
                        Http::post($baseUri . '/api/appointments', [
                            'doctor_id' => 'not_a_number',
                            'patient_email' => 'not_an_email'
                        ]);
                    } finally {
                        $scope2->detach();
                        $span->end();
                    }
                    break;

                case 3: // Конфликт (слот занят)
                    $span->setAttribute('error.type', 'slot_conflict');
                    $slot = Slot::where('is_available', false)->inRandomOrder()->first();

                    if ($slot) {
                        $this->createAppointment(
                            $slot->doctor,
                            ['start_time' => $slot->start_time],
                            $slot->start_time->format('Y-m-d'),
                            $baseUri
                        );
                    }
                    break;
            }
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
