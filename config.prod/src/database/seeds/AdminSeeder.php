<?php

namespace Database\Seeds;

use App\Sku;
use App\Package;
use App\Domain;
use App\User;
use Laravel\Passport\Passport;
use Illuminate\Database\Seeder;
use Illuminate\Encryption\Encrypter;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Create a default user with dependencies
     *
     * @return void
     */
    public function run()
    {
        //Create required packages
        $skuDomain = Sku::where(['title' => 'domain-hosting', 'tenant_id' => \config('app.tenant_id')])->first();
        $skuGroupware = Sku::where(['title' => 'groupware', 'tenant_id' => \config('app.tenant_id')])->first();
        $skuMailbox = Sku::where(['title' => 'mailbox', 'tenant_id' => \config('app.tenant_id')])->first();
        $skuStorage = Sku::where(['title' => 'storage', 'tenant_id' => \config('app.tenant_id')])->first();

        $packageKolab = Package::create(
            [
                'title' => 'kolab',
                'name' => 'Groupware Account',
                'description' => 'A fully functional groupware account.',
                'discount_rate' => 0,
            ]
        );
        $packageKolab->skus()->saveMany([
            $skuMailbox,
            $skuGroupware,
            $skuStorage
        ]);


        $packageDomain = Package::create(
            [
                'title' => 'domain-hosting',
                'name' => 'Domain Hosting',
                'description' => 'Use your own, existing domain.',
                'discount_rate' => 0,
            ]
        );
        $packageDomain->skus()->saveMany([
            $skuDomain
        ]);



        //Create primary domain
        $appDomain = Domain::create(
            [
                'namespace' => \config('app.domain'),
                'status' => DOMAIN::STATUS_CONFIRMED + Domain::STATUS_ACTIVE,
                'type' => Domain::TYPE_PUBLIC,
            ]
        );

        //Create admin user
        $admin = User::create(
            [
                'email' => 'admin@' . \config('app.domain'),
                'password' => \App\Utils::generatePassphrase()
            ]
        );

        $admin->setSettings(
            [
                'first_name' => 'Admin',
            ]
        );

        $appDomain->assignPackage($packageDomain, $admin);
        $admin->assignPackage($packageKolab);
    }
}

