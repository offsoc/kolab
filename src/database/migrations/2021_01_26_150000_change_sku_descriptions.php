<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class ChangeSkuDescriptions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $beta_sku = \App\Sku::where('title', 'beta')->first();
        $beta_sku->name = 'Private Beta (invitation only)';
        $beta_sku->description = 'Access to the private beta program subscriptions';
        $beta_sku->save();

        $meet_sku = \App\Sku::where('title', 'meet')->first();
        $meet_sku->name = 'Voice & Video Conferencing (public beta)';
        $meet_sku->handler_class = 'App\Handlers\Meet';
        $meet_sku->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
