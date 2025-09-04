<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'hora',
        'estado', // disponible, reservado, inactivo
        'precio',
        'cancha' // si manejas más de una cancha
    ];

    // Un turno puede tener muchas reservas (en este caso 0 o 1)
    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    // Opcional: acceder al usuario que reservó
    public function usuarios()
    {
        return $this->hasManyThrough(User::class, Reserva::class, 'turno_id', 'id', 'id', 'user_id');
    }
}
