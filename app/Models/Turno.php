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
        'ajuste_minutos',
        'estado',
        'precio',
        'sena',   
        'cancha' 
    ];

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    public function usuarios()
    {
        return $this->hasManyThrough(User::class, Reserva::class, 'turno_id', 'id', 'id', 'user_id');
    }
}