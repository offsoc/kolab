<?php

namespace Database\Seeds;

use Laravel\Passport\Passport;
use Illuminate\Database\Seeder;

class PassportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This emulates:
     * './artisan passport:client --password --name="Kolab Password Grant Client" --provider=users'
     *
     * @return void
     */
    public function run()
    {
        //Create a password grant client for the webapp
        $client = Passport::client()->forceFill([
            'user_id' => null,
            'name' => "Kolab Password Grant Client",
            'secret' => \config('auth.proxy.client_secret'),
            'provider' => 'users',
            'redirect' => 'https://' . \config('app.website_domain'),
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => false,
        ]);
        $client->id = \config('auth.proxy.client_id');
        $client->save();

        // Create a client for synapse oauth
        $client = Passport::client()->forceFill([
            'user_id' => null,
            'name' => "Synapse oauth client",
            'secret' => \config('auth.synapse.client_secret'),
            'provider' => 'users',
            'redirect' => 'https://' . \config('app.website_domain') . "/_synapse/client/oidc/callback",
            'personal_access_client' => 0,
            'password_client' => 0,
            'revoked' => false,
            'allowed_scopes' => ['email'],
        ]);
        $client->id = \config('auth.synapse.client_id');
        $client->save();
    }
}
