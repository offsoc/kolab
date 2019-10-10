<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWalletTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'wallets',
            function (Blueprint $table) {
                $table->string('id', 36);
                $table->string('description', 128)->nullable();
                $table->string('currency', 4);
                $table->integer('balance');
                $table->bigInteger('user_id');
            }
        );

        Schema::table(
            'wallets',
            function (Blueprint $table) {
                $table->primary('id');
                $table->index('user_id');
            }
        );

        Schema::table(
            'wallets',
            function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('wallets');
    }
}
