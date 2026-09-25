<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Precio extends Model
{
    protected $fillable = [
        'cancha',
        'precio',
        'sena',
        'requiere_4_jugadores',
    ];

    protected $casts = [
        'requiere_4_jugadores' => 'boolean',
    ];

    public static function paraCancha(string $cancha): self
    {
        return static::firstOrCreate(['cancha' => $cancha], ['precio' => 0, 'sena' => 0]);
    }
}
