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
        // Desactivar temporalmente las restricciones de claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Borrar todos los usuarios existentes
        User::truncate();

        // Reactivar las restricciones de claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Crear usuario administrador
        User::create([
            'name' => 'Lucio Adriel',
            'email' => 'lucioadriell@gmail.com',
            'password' => Hash::make('1234567890'),
            'role' => 'admin',
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

        User::create([
            'name' => 'Usuario 4',
            'email' => 'usuario4@example.com',
            'password' => Hash::make('123'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'Usuario 5',
            'email' => 'usuario5@example.com',
            'password' => Hash::make('123'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'Usuario 6',
            'email' => 'usuario6@example.com',
            'password' => Hash::make('123'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'Usuario 7',
            'email' => 'usuario7@example.com',
            'password' => Hash::make('123'),
            'role' => 'user',
        ]);
        
        
    }
}
