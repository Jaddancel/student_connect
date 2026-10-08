<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 3) as $index) {
            SeedData::at('2023-06-01 08:0'.$index.':00', fn () => SeedData::user(User::TYPE_ADMIN, "admin{$index}@example.com"));
        }
    }
}
