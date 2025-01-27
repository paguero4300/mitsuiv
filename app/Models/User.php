<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'custom_fields',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'custom_fields' => 'array',
    ];

    // Método requerido por FilamentUser
    public function canAccessPanel(Panel $panel): bool
    {
        // Aquí puedes personalizar tus condiciones de acceso
        // Por ejemplo:
        return true; // Permite acceso a todos los usuarios
        // O más restrictivo:
        // return $this->hasRole('admin') || $this->email === 'admin@tudominio.com';
        // O por dominio:
        // return str_ends_with($this->email, '@tudominio.com');
    }
}