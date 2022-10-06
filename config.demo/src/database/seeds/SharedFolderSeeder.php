<?php

namespace Database\Seeds;

use App\SharedFolder;
use App\User;
use Illuminate\Database\Seeder;

class SharedFolderSeeder extends Seeder
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

        $folder = SharedFolder::create([
                'name' => 'Calendar',
                'email' => 'folder-event@kolab.org',
                'type' => 'event',
        ]);
        $folder->assignToWallet($wallet);

        $folder = SharedFolder::create([
                'name' => 'Contacts',
                'email' => 'folder-contact@kolab.org',
                'type' => 'contact',
        ]);
        $folder->assignToWallet($wallet);
    }
}
