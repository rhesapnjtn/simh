<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getRoleLabelAttribute(): string
    {
        return data_get(config('permissions.roles.'.$this->role), 'label', $this->role);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['manager', 'general_manager', 'supervisor']);
    }

    public function isReceptionist(): bool
    {
        return in_array($this->role, ['receptionist', 'front_office']);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function permissions(): array
    {
        return config('permissions.roles.'.$this->role.'.permissions', []);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function canManageRooms(): bool
    {
        return in_array($this->role, ['admin', 'super_admin', 'manager', 'general_manager', 'receptionist', 'front_office']);
    }

    public function isStaff(): bool
    {
        return $this->role !== 'guest' && in_array($this->role, array_keys(config('permissions.roles', [])));
    }
}