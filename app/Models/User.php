<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <-- agregar esto

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens; // <-- agregar HasApiTokens aquí

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // 'user' o 'admin'
        'estado', // 'Activo' o 'Inactivo'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Mutator para hashear password automáticamente
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = \Illuminate\Support\Facades\Hash::make($value);
    }
    // Un usuario puede tener muchas reservas
    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    // Opcional: un usuario podría reservar muchos turnos a través de reservas
    public function turnos()
    {
        return $this->hasManyThrough(Turno::class, Reserva::class, 'user_id', 'id', 'id', 'turno_id');
    }
}
