<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletSettings extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'wallet_settings',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('wallet_id');
                $table->string('key');
                $table->string('value');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
                $table->unique(['wallet_id', 'key']);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('wallet_settings');
    }
}
