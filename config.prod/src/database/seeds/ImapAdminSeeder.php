<?php

namespace Database\Seeds;

use App\User;
use Illuminate\Database\Seeder;

class ImapAdminSeeder extends Seeder
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
        User::create(
            [
                'email' => \config('services.imap.admin_login'),
                'password' => \config('services.imap.admin_password')
            ]
        );
    }
}
