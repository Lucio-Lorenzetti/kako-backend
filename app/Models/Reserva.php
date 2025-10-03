<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'turno_id',
        'nombre_jugador',
        'whatsapp',
        'cantidad_jugadores',
        'necesita_paleta',
        'buscar_pareja',
        'estado',
        'referencia_pago'
    ];

    // Una reserva pertenece a un usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Una reserva pertenece a un turno
    public function turno()
    {
        return $this->belongsTo(Turno::class);
    }
}
