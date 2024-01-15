<?php

namespace Database\Seeds;

use App\User;
use Illuminate\Database\Seeder;

class NoreplySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Create imap admin service account, which is required for sasl httpauth to work
     *
     * @return void
     */
    public function run()
    {
        if (!empty(\config('mail.mailers.smtp.username'))) {
            User::create(
                [
                    'email' => \config('mail.mailers.smtp.username'),
                    'password' => \config('mail.mailers.smtp.password')
                ]
            );
        }
    }
}
