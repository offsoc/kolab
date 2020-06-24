<?php

namespace Database\Seeds\Local;

use App\Domain;
use Illuminate\Database\Seeder;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $domains = [
            "kolabnow.com",
            "mykolab.com",
            "attorneymail.ch",
            "barmail.ch",
            "collaborative.li",
            "diplomail.ch",
            "freedommail.ch",
            "groupoffice.ch",
            "journalistmail.ch",
            "legalprivilege.ch",
            "libertymail.co"
        ];

        foreach ($domains as $domain) {
            Domain::create(
                [
                    'namespace' => $domain,
                    'status' => Domain::STATUS_CONFIRMED + Domain::STATUS_ACTIVE,
                    'type' => Domain::TYPE_PUBLIC
                ]
            );
        }

        if (!in_array(\config('app.domain'), $domains)) {
            Domain::create(
                [
                    'namespace' => \config('app.domain'),
                    'status' => DOMAIN::STATUS_CONFIRMED + Domain::STATUS_ACTIVE,
                    'type' => Domain::TYPE_PUBLIC
                ]
            );
        }

        $domains = [
            'example.com',
            'example.net',
            'example.org'
        ];

        foreach ($domains as $domain) {
            Domain::create(
                [
                    'namespace' => $domain,
                    'status' => Domain::STATUS_CONFIRMED + Domain::STATUS_ACTIVE,
                    'type' => Domain::TYPE_EXTERNAL
                ]
            );
        }
    }
}
