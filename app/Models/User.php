<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    protected $fillable = ['name', 'username', 'password', 'role', 'department', 'status', 'preferred_locale'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime', 'auth_version' => 'integer'];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' && $this->status === 'active';
    }
}
