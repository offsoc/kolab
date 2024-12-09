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
        $user = new \App\User();
        $user->email = \config('services.imap.admin_login');
        $user->password = \config('services.imap.admin_password');
        $user->role = \App\User::ROLE_SERVICE;
        $user->save();
    }
}
