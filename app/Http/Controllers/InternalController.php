<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Slot;

class InternalController extends Controller
{
    /**
     * Внутреннее API для получения слотов (имитация микросервиса)
     */
    public function getSlots(Request $request)
    {
        // Искусственная задержка для демо
        usleep(rand(50000, 150000)); // 50-150ms
        
        $slots = Slot::where('doctor_id', $request->doctor_id)
            ->whereDate('start_time', $request->date)
            ->where('is_available', true)
            ->get();
        
        return response()->json($slots);
    }
    
    /**
     * Имитация SMS-шлюза
     */
    public function sendSms(Request $request)
    {
        // Иногда ошибается (10% ошибок для демо)
        if (rand(1, 100) <= 10) {
            return response()->json(['error' => 'SMS service unavailable'], 503);
        }
        
        usleep(rand(20000, 50000)); // 20-50ms
        return response()->json(['success' => true]);
    }
    
    /**
     * Имитация Email-сервиса
     */
    public function sendEmail(Request $request)
    {
        usleep(rand(30000, 80000)); // 30-80ms
        return response()->json(['success' => true]);
    }
}