<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['username' => 'diallo', 'name' => 'Diallo', 'password' => 'Yaya@daily26', 'role' => User::ROLE_ADMIN, 'initials' => 'DI'],
            ['username' => 'sacko2', 'name' => 'Sacko2', 'password' => 'Const@daily26', 'role' => User::ROLE_ADMIN, 'initials' => 'SA'],
            ['username' => 'admingda', 'name' => 'Admingda', 'password' => 'Dymo@daily', 'role' => User::ROLE_ADMIN, 'initials' => 'DY'],
            ['username' => 'b2gold', 'name' => 'B2gold', 'password' => 'Partner@26daily', 'role' => User::ROLE_PARTNER, 'initials' => 'B2'],
        ];

        User::query()->where('username', 'sacko')->update([
            'username' => 'sacko2',
            'name' => 'Sacko2',
        ]);

        foreach ($accounts as $row) {
            User::updateOrCreate(
                ['username' => $row['username']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make($row['password']),
                    'role' => $row['role'],
                    'avatar_initials' => $row['initials'],
                    'is_active' => true,
                ]
            );
        }

        if ($project = Project::query()->first()) {
            $project->users()->sync(User::query()->pluck('id')->all());
        }
    }
}
