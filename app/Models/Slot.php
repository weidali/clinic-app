<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slot extends Model
{
    /** @use HasFactory<\Database\Factories\SlotFactory> */
    use HasFactory;

    protected $fillable = ['doctor_id', 'start_time', 'end_time', 'is_available'];
    
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_available' => 'boolean'
    ];
    
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
