<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;

class GenerateTraffic extends Command
{
    protected $signature = 'traffic:generate {count=50 : Количество запросов}';
    protected $description = 'Генерирует тестовый трафик для демо OpenTelemetry в Zipkin';

    private array $stats = [
        'successful' => 0,
        'search_only' => 0,
        'errors' => 0,
        'total_time' => 0,
    ];

    public function handle()
    {
        $count = $this->argument('count');
        $baseUri = Config::get('api.base_uri');

        $this->info('Запуск генерации тестового трафика для Zipkin');
        $this->line('----------------------------------------');
        $this->info('📊 Zipkin UI: http://localhost:9411');
        $this->newLine();

        $bar = $this->output->createProgressBar($count);
        $bar->setFormat("%current%/%max% [%bar%] %percent:3s%%\n %message%");
        $bar->setMessage('Подготовка...');
        $bar->start();

        $startTime = microtime(true);

        for ($i = 0; $i < $count; $i++) {
            $requestStart = microtime(true);

            try {
                // 60% - успешные сценарии
                if (rand(1, 100) <= 60) {
                    $this->successfulAppointment($baseUri);
                    $this->stats['successful']++;
                    $bar->setMessage('✅ Успешная запись');
                }
                // 20% - поиск без записи
                elseif (rand(1, 100) <= 50) {
                    $this->searchOnly($baseUri);
                    $this->stats['search_only']++;
                    $bar->setMessage('Поиск без записи');
                }
                // 20% - ошибки
                else {
                    $this->errorScenario($baseUri);
                    $this->stats['errors']++;
                    $bar->setMessage('⚠️ Сценарий с ошибкой');
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $bar->setMessage('❌ ' . $e->getMessage());
            }

            $this->stats['total_time'] += (microtime(true) - $requestStart) * 1000;

            $bar->advance();
            usleep(rand(50000, 100000)); // 50-100ms
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        $avgTime = round($this->stats['total_time'] / $count, 2);

        $bar->finish();
        $this->newLine(2);

        $this->output->writeln('📈 <fg=green>Статистика генерации:</>');
        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Всего запросов', $count],
                ['Успешных записей', $this->stats['successful'] . ' (' . round($this->stats['successful']/$count*100) . '%)'],
                ['Поисков без записи', $this->stats['search_only'] . ' (' . round($this->stats['search_only']/$count*100) . '%)'],
                ['⚠Ошибок', $this->stats['errors'] . ' (' . round($this->stats['errors']/$count*100) . '%)'],
                ['Общее время', $totalTime . ' ms'],
                ['Среднее время на запрос', $avgTime . ' ms'],
            ]
        );

        $this->newLine();
        $this->info('📊 Данные в базе:');
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

        $this->newLine();
        $this->info('✅ Генерация завершена! Откройте Zipkin: http://localhost:9411');
    }

    protected function successfulAppointment($baseUri)
    {
        $tracer = Globals::tracerProvider()->getTracer('clinic-app');

        $mainSpan = $tracer->spanBuilder('appointment.booking')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('app.type', 'successful')
            ->setAttribute('app.operation', 'booking')
            ->startSpan();

        $scope = $mainSpan->activate();

        try {
            $doctor = Doctor::inRandomOrder()->first();
            if (!$doctor) {
                throw new \Exception('No doctors found');
            }

            // Поиск врача
            $searchSpan = $tracer->spanBuilder('api.doctors.search')
                ->setParent(Context::getCurrent())
                ->setAttribute('doctor.specialization', $doctor->specialization)
                ->setAttribute('http.method', 'GET')
                ->startSpan();

            $searchSpan->setAttribute('http.url', $baseUri . '/api/doctors/search');

            $response = Http::get($baseUri . '/api/doctors/search', [
                'specialization' => $doctor->specialization
            ]);

            $searchSpan->setAttribute('http.status_code', $response->status());
            $searchSpan->setAttribute('http.response_size', strlen($response->body()));

            $doctors = $response->json();
            $searchSpan->setAttribute('search.results_count', $this->safeCount($doctors));

            $searchSpan->end();

            // Создание записи...
            $createSpan = $tracer->spanBuilder('appointment.create')
                ->setParent(Context::getCurrent())
                ->setAttribute('doctor.id', $doctor->id)
                ->setAttribute('appointment.type', 'test')
                ->startSpan();

            $appointment = Appointment::create([
                'doctor_id' => $doctor->id,
                'patient_name' => 'Тест Пациент ' . rand(1, 100),
                'patient_phone' => '+7' . rand(9000000000, 9999999999),
                'patient_email' => 'patient' . rand(1, 100) . '@example.com',
                'appointment_date' => Carbon::now()->addDays(rand(1, 5))->setTime(rand(9, 16), 0),
                'status' => 'confirmed',
                'symptoms' => 'Тестовые симптомы #' . rand(1000, 9999)
            ]);

            $createSpan->setAttribute('appointment.id', $appointment->id);
            $createSpan->setAttribute('appointment.status', $appointment->status);
            $createSpan->end();

            $mainSpan->setAttribute('appointment.id', $appointment->id);
            $mainSpan->setStatus(StatusCode::STATUS_OK);

        } catch (\Throwable $e) {
            $mainSpan->recordException($e);
            $mainSpan->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $mainSpan->end();
        }
    }

    protected function searchOnly($baseUri)
    {
        $tracer = Globals::tracerProvider()->getTracer('clinic-app');

        $span = $tracer->spanBuilder('appointment.search')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('app.operation', 'search_only')
            ->startSpan();

        $scope = $span->activate();

        try {
            $specializations = ['Терапевт', 'Кардиолог', 'Хирург', 'Педиатр', 'Невролог'];
            $spec = $specializations[array_rand($specializations)];

            $span->setAttribute('search.specialization', $spec);
            $span->setAttribute('http.method', 'GET');

            $response = Http::get($baseUri . '/api/doctors/search', [
                'specialization' => $spec
            ]);

            $span->setAttribute('http.status_code', $response->status());
            $span->setAttribute('http.response_time', $response->handlerStats()['total_time_us'] ?? 0);

            $doctors = $response->json();

            if (is_array($doctors)) {
                $span->setAttribute('search.results_count', count($doctors));
                $span->setAttribute('search.has_results', count($doctors) > 0);
            } else {
                $span->setAttribute('search.results_count', 0);
                $span->setAttribute('search.has_results', false);
                $span->setAttribute('search.response_type', gettype($doctors));

                // Логируем что пришло для отладки
                $span->setAttribute('search.raw_response', substr(json_encode($doctors), 0, 200));
            }

            $span->setStatus(StatusCode::STATUS_OK);

        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    protected function errorScenario($baseUri)
    {
        $tracer = Globals::tracerProvider()->getTracer('clinic-app');
        $scenario = rand(1, 3);

        $span = $tracer->spanBuilder('appointment.error')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('app.operation', 'error_scenario')
            ->setAttribute('error.scenario', $scenario)
            ->startSpan();

        $scope = $span->activate();

        try {
            switch ($scenario) {
                case 1: // Несуществующий врач
                    $span->setAttribute('error.type', 'invalid_doctor');

                    $response = Http::get($baseUri . '/api/slots', [
                        'doctor_id' => 999999,
                        'date' => Carbon::now()->format('Y-m-d')
                    ]);

                    $span->setAttribute('http.status_code', $response->status());
                    break;

                case 2: // Невалидные данные
                    $span->setAttribute('error.type', 'validation_error');

                    $response = Http::post($baseUri . '/api/appointments', [
                        'doctor_id' => 'not_a_number',
                        'patient_name' => '',
                        'patient_email' => 'not_an_email'
                    ]);

                    $span->setAttribute('http.status_code', $response->status());
                    break;

                case 3: // Таймаут
                    $span->setAttribute('error.type', 'timeout');
                    $span->setAttribute('http.timeout', '1s');

                    $doctor = Doctor::inRandomOrder()->first();
                    if ($doctor) {
                        Http::timeout(1)->get($baseUri . '/api/internal/slots', [
                            'doctor_id' => $doctor->id,
                            'date' => Carbon::now()->format('Y-m-d')
                        ]);
                    }
                    break;
            }

            $span->setStatus(StatusCode::STATUS_ERROR, 'Expected error scenario');

        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
