<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class CreateWalletSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'wallet_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('wallet_id');
                $table->string('key');
                $table->string('value');
                $table->timestamps();

                $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
                $table->unique(['wallet_id', 'key']);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wallet_settings');
    }
}
