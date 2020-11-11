<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class AddBetaSkus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!\App\Sku::where('title', 'beta')->first()) {
            \App\Sku::create([
                'title' => 'beta',
                'name' => 'Beta program',
                'description' => 'Access to beta program subscriptions',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta',
                'active' => false,
            ]);
        }

        if (!\App\Sku::where('title', 'meet')->first()) {
            \App\Sku::create([
                'title' => 'meet',
                'name' => 'Video chat',
                'description' => 'Video conferencing tool',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta\Meet',
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
        // there's no need to remove these SKUs
    }
}
