<?php

namespace Database\Seeds\Local;

use App\Resource;
use App\User;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $john = User::where('email', 'john@kolab.org')->first();
        $wallet = $john->wallets()->first();

        $resource = Resource::create([
                'name' => 'Conference Room #1',
                'email' => 'resource-test1@kolab.org',
        ]);
        $resource->assignToWallet($wallet);

        $resource = Resource::create([
                'name' => 'Conference Room #2',
                'email' => 'resource-test2@kolab.org',
        ]);
        $resource->assignToWallet($wallet);
    }
}
