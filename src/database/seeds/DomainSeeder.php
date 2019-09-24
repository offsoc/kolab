<?php

use Illuminate\Database\Seeder;
use App\Domain;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Domain::create(['namespace' => 'example.com']);
        Domain::create(['namespace' => 'example.net']);
        Domain::create(['namespace' => 'example.org']);
        Domain::create(['namespace' => 'kolabsystems.com']);
    }
}
