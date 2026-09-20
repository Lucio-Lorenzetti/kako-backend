<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. DESACTIVAR restricciones en PostgreSQL
        DB::statement('SET session_replication_role = "replica";');

        // 2. Limpiar la tabla
        User::truncate();

        // 3. REACTIVAR restricciones en PostgreSQL
        DB::statement('SET session_replication_role = "origin";');

        // Crear usuario administrador
        User::create([
            'name' => 'Nazarena Carlino',
            'email' => 'nazacarlino.nc@gmail.com',
            'password' => Hash::make('admin1'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Kako',
            'email' => 'kakocarlino1@gmail.com',
            'password' => Hash::make('admin2'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Lucio Adriel',
            'email' => 'lorenzettilucioadriel@gmail.com',
            'password' => Hash::make('42293176'),
            'role' => 'developer',
        ]);

        // Crear algunos usuarios de prueba
        User::create([
            'name' => 'Usuario 1',
            'email' => 'usuario1@example.com',
            'password' => Hash::make('123'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'Usuario 2',
            'email' => 'usuario2@example.com',
            'password' => Hash::make('123'),
            'role' => 'user',
        ]);

        // Si querés, podés agregar más usuarios con un bucle
        User::create([
            'name' => 'Usuario 3',
            'email' => 'usuario3@example.com',
            'password' => Hash::make('123'),
            'role' => 'user',
        ]);
                
        
    }
}
