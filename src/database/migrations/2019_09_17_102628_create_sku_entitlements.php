<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
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
                $table->string('id', 36)->primary();
                $table->bigInteger('owner_id');
                $table->bigInteger('entitleable_id');
                $table->bigInteger('entitleable_type');
                $table->string('wallet_id', 36);
                $table->string('sku_id', 36);
                $table->string('description')->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'skus',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('title', 64);
                $table->text('description')->nullable();
                $table->integer('cost');
                $table->smallinteger('units_free')->default('0');
                $table->string('period', strlen('monthly'))->default('monthly');
                $table->string('handler_class')->nullable();
                $table->boolean('active')->default(false);
                $table->timestamps();
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
