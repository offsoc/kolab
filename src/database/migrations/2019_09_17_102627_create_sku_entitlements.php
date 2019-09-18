<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSkuEntitlements extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'entitlements',
            function (Blueprint $table) {
                $table->string('id', 36);
                $table->bigInteger('owner_id');
                $table->bigInteger('user_id');
                $table->string('wallet_id', 36);
                $table->string('sku_id', 36);
                $table->string('description');
                $table->timestamps();
            }
        );

        Schema::create(
            'skus',
            function (Blueprint $table) {
                $table->string('id', 36);
                $table->string('title', 64);
                $table->text('description');
                $table->decimal('cost', 8, 2);
                $table->timestamps();
            }
        );

        Schema::table(
            'entitlements',
            function (Blueprint $table) {
                $table->primary('id');
                $table->unique(['owner_id', 'user_id']);
            }
        );

        Schema::table(
            'skus',
            function (Blueprint $table) {
                $table->primary('id');
            }
        );

        Schema::table(
            'entitlements',
            function (Blueprint $table) {
                $table->foreign('sku_id')
                    ->references('id')->on('skus')
                    ->onDelete('cascade');

                $table->foreign('owner_id')
                    ->references('id')->on('users')
                    ->onDelete('cascade');

                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->onDelete('cascade');

                $table->foreign('wallet_id')
                    ->references('id')->on('wallets')
                    ->onDelete('cascade');
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
        // TODO: drop foreign keys first
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('skus');
    }
}
