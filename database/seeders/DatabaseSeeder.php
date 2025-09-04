<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Llamamos a nuestros seeders
        $this->call([
            UserSeeder::class,
            TurnoSeeder::class,
            ReservaSeeder::class,
        ]);
    }
}
