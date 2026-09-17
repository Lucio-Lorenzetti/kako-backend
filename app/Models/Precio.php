<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Precio extends Model
{
    protected $fillable = [
        'cancha',
        'precio',
        'sena',
    ];

    public static function paraCancha(string $cancha): self
    {
        return static::firstOrCreate(['cancha' => $cancha], ['precio' => 0, 'sena' => 0]);
    }
}
