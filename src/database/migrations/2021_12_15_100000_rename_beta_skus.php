<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class RenameBetaSkus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \App\Sku::where('title', 'distlist')->get()->each(function ($sku) {
            $sku->title = 'beta-distlists';
            $sku->handler_class = 'App\Handlers\Beta\Distlists';
            $sku->save();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \App\Sku::where('title', 'beta-distlists')->get()->each(function ($sku) {
            $sku->title = 'distlist';
            $sku->handler_class = 'App\Handlers\Distlist';
            $sku->save();
        });
    }
}
