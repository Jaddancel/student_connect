<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            throw new RuntimeException('Historical demo seeding requires an empty database. Use a dedicated demo database.');
        }
        UserSeeder::assertSignatureRuntime();
        SeedData::images(is_dir(config('seeding.assets_path').'/portraits') ? 'portraits' : 'portrait');
        SeedData::images('event_photo');

        DB::transaction(function () {
            $this->call([
                SuperAdminSeeder::class,
                AdminSeeder::class,
                ConfigurationSeeder::class,
                SemesterSeeder::class,
                OrganizationSeeder::class,
                RequestSeeder::class,
                PresidentSeeder::class,
                UserSeeder::class,
            ]);
        });
    }
}
