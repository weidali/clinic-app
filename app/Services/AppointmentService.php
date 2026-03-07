<?php
namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppointmentService
{
    /**
     * Поиск доступных врачей по специализации
     */
    public function findDoctorsBySpecialization(string $specialization, int $experienceYears = null)
    {
        // Имитация бизнес-логики
        $cacheKey = "doctors:{$specialization}:{$experienceYears}";
        
        return Cache::remember($cacheKey, 300, function () use ($specialization, $experienceYears) {
            // добавляем задержку для демо
            usleep(rand(100000, 300000)); // 100-300ms
            
            $query = Doctor::where('specialization', $specialization);
            
            if ($experienceYears) {
                $query->where('experience_years', '>=', $experienceYears);
            }
            
            return $query->get();
        });
    }
    
    /**
     * Проверка доступных слотов у врача
     */
    public function getAvailableSlots(int $doctorId, string $date)
    {
        // Имитация запроса к "внешнему API расписания"
        $response = Http::timeout(2)->get('http://localhost:8000/api/internal/slots', [
            'doctor_id' => $doctorId,
            'date' => $date
        ]);
        
        return $response->json();
    }
    
    /**
     * Создание записи к врачу
     */
    public function createAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            // Проверяем, что слот еще свободен
            $slot = Slot::where('doctor_id', $data['doctor_id'])
                ->where('start_time', $data['appointment_date'])
                ->where('is_available', true)
                ->first();
            
            if (!$slot) {
                throw new \Exception('Слот недоступен');
            }
            
            // Создаем запись
            $appointment = Appointment::create($data);
            // Помечаем слот как занятый
            $slot->update(['is_available' => false]);
            // Отправляем уведомления (имитация)
            $this->sendNotifications($appointment);
            
            return $appointment;
        });
    }
    
    /**
     * Отправка уведомлений пациенту
     */
    protected function sendNotifications(Appointment $appointment)
    {
        // Имитация отправки SMS
        $smsResponse = Http::post('http://localhost:8000/api/internal/sms', [
            'phone' => $appointment->patient_phone,
            'message' => "Запись к врачу {$appointment->doctor->name} на {$appointment->appointment_date}"
        ]);
        
        // Имитация отправки Email
        $emailResponse = Http::post('http://localhost:8000/api/internal/email', [
            'email' => $appointment->patient_email,
            'subject' => 'Подтверждение записи',
            'body' => "Вы записаны к врачу..."
        ]);
        
        Log::info('Уведомления отправлены', [
            'appointment_id' => $appointment->id,
            'sms_sent' => $smsResponse->successful(),
            'email_sent' => $emailResponse->successful()
        ]);
    }
}
