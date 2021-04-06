<?php

namespace Database\Seeds\Local;

use Laravel\Passport\Passport;
use Illuminate\Database\Seeder;

class OauthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This emulates './artisan passport:client --password --name="Kolab Password Grant Client" --provider=users'
     *
     * @return void
     */
    public function run()
    {
        $client = Passport::client()->forceFill([
            'user_id' => null,
            'name' => "Kolab Password Grant Client",
            'secret' => 'JF4pL68ucLuMupaOviTeG8EJeQpjtZtcGLp4f0dq',
            'provider' => 'users',
            'redirect' => 'http://localhost',
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => false,
        ]);

        $client->save();
    }
}
