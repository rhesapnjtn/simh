<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'position',
        'department',
        'join_date',
        'salary',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'salary' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}