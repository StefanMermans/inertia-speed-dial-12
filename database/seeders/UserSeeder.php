<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var User $user */
        $user = User::firstOrCreate([
            'name' => 'Admin',
            'email' => config('app.admin.email'),
        ], [
            'password' => bcrypt(config('app.admin.password')),
        ]);
        $user->markEmailAsVerified();
    }
}
