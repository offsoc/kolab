<?php

use Illuminate\Database\Seeder;
use App\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create(
            [
                'name' => "John Doe",
                'email' => 'jdoe@example.org',
                'password' => 'simple123',
                'email_verified_at' => now()
            ]
        );
    }
}
