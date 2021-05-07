<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class AddDistlistBetaSku extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!\App\Sku::where('title', 'distlist')->first()) {
            \App\Sku::create([
                'title' => 'distlist',
                'name' => 'Distribution lists',
                'description' => 'Access to mail distribution lists',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Distlist',
                'active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // there's no need to remove this SKU
    }
}
