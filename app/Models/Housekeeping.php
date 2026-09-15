<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Housekeeping extends Model
{
    protected $table = 'housekeeping';

    protected $fillable = [
        'room_id',
        'assigned_to',
        'task_type',
        'priority',
        'status',
        'notes',
        'scheduled_date',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}