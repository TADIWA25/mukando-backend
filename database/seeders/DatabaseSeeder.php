<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $mockUsers = [
            [
                'name' => 'Tendai Moyo',
                'phone' => '263771000001',
            ],
            [
                'name' => 'Rudo Chikore',
                'phone' => '263771000002',
            ],
            [
                'name' => 'Farai Ncube',
                'phone' => '263771000003',
            ],
            [
                'name' => 'Nyasha Dube',
                'phone' => '263771000004',
            ],
            [
                'name' => 'Kuda Sibanda',
                'phone' => '263771000005',
            ],
            [
                'name' => 'Tatenda Zhou',
                'phone' => '263771000006',
            ],
        ];

        foreach ($mockUsers as $user) {
            User::updateOrCreate(
                ['phone' => $user['phone']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}
