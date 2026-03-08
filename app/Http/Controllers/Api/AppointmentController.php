<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    protected AppointmentService $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    /**
     * Поиск врачей
     */
    public function searchDoctors(Request $request)
    {
        $request->validate([
            'specialization' => 'required|string',
            'experience_years' => 'nullable|integer|min:0'
        ]);

        $doctors = $this->appointmentService->findDoctorsBySpecialization(
            $request->specialization,
            $request->experience_years
        );

        Log::info('Поиск врачей выполнен', [
            'specialization' => $request->specialization,
            'found' => $doctors->count()
        ]);

        return response()->json([
            'success' => true,
            'doctors' => $doctors
        ]);
    }

    /**
     * Получение доступных слотов
     */
    public function getSlots(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date'
        ]);

        $slots = $this->appointmentService->getAvailableSlots(
            $request->doctor_id,
            $request->date
        );

        return response()->json([
            'success' => true,
            'slots' => $slots
        ]);
    }

    /**
     * Создание записи
     */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'patient_name' => 'required|string',
            'patient_phone' => 'required|string',
            'patient_email' => 'required|email',
            'appointment_date' => 'required|date',
            'symptoms' => 'nullable|string'
        ]);

        try {
            $appointment = $this->appointmentService->createAppointment($request->all());

            Log::info('Запись создана успешно', [
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_name' => $appointment->patient_name
            ]);

            return response()->json([
                'success' => true,
                'appointment' => $appointment
            ], 201);

        } catch (\Exception $e) {
            Log::error('Ошибка создания записи', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
