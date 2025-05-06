<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'wallets',
            static function (Blueprint $table) {
                $table->string('id', 36);
                $table->string('description', 128)->nullable();
                $table->string('currency', 4);
                $table->integer('balance');
                $table->bigInteger('user_id');

                $table->primary('id');
                $table->index('user_id');

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('wallets');
    }
}
