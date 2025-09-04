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
            'name' => 'Admin',
            'email' => 'lucioadriell@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // Crear algunos usuarios de prueba
        User::create([
            'name' => 'Usuario 1',
            'email' => 'usuario1@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'Usuario 2',
            'email' => 'usuario2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        // Si querés, podés agregar más usuarios con un bucle
        /*
        for ($i = 3; $i <= 5; $i++) {
            User::create([
                'name' => "Usuario $i",
                'email' => "usuario$i@example.com",
                'password' => Hash::make('password123'),
                'role' => 'user',
            ]);
        }
        */
    }
}
