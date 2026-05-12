<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Koné A.',
                'email' => 'kone@gda.com',
                'password' => Hash::make('1234'),
                'role' => 'chef_chantier',
                'avatar_initials' => 'K',
            ],
            [
                'name' => 'Diallo M.',
                'email' => 'diallo@gda.com',
                'password' => Hash::make('1234'),
                'role' => 'ingenieur',
                'avatar_initials' => 'D',
            ],
            [
                'name' => 'Bah S.',
                'email' => 'bah@gda.com',
                'password' => Hash::make('1234'),
                'role' => 'controle_qualite',
                'avatar_initials' => 'B',
            ],
            [
                'name' => 'Direction GD&A',
                'email' => 'direction@gda.com',
                'password' => Hash::make('1234'),
                'role' => 'direction',
                'avatar_initials' => 'G',
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], $u);
        }
    }
}
