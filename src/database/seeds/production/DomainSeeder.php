<?php

namespace Database\Seeds\Production;

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
            "attorneymail.ch",
            "barmail.ch",
            "collaborative.li",
            "diplomail.ch",
            "freedommail.ch",
            "groupoffice.ch",
            "journalistmail.ch",
            "legalprivilege.ch",
            "libertymail.co",
            "libertymail.net",
            "kolabnow.com",
            "kolabnow.ch",
            "mailatlaw.ch",
            "medmail.ch",
            "mykolab.ch",
            "mykolab.com",
            "myswissmail.ch",
            "opengroupware.ch",
            "pressmail.ch",
            "swissgroupware.ch",
            "switzerlandmail.ch",
            "trusted-legal-mail.ch",
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
    }
}
