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
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
            ProcurementSeeder::class,
            SalesSeeder::class,
            ManufacturingSeeder::class,
            DocumentSequenceSeeder::class,
        ]);

        $this->command?->info('Demo admin: admin@abaya.test / password');
    }
}
