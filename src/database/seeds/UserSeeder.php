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

        // 10'000 users result in a table size of 11M
        //factory(User::class, 100)->create();
        factory(User::class, 3)->create();
    }
}
