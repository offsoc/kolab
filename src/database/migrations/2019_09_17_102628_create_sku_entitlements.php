<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSkuEntitlements extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'skus',
            static function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('title', 64);
                // were json, but mariadb
                $table->text('name');
                $table->text('description');
                // end of
                $table->integer('cost');
                $table->smallinteger('units_free')->default('0');
                $table->string('period', strlen('monthly'))->default('monthly');
                $table->string('handler_class')->nullable();
                $table->boolean('active')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            }
        );

        Schema::create(
            'entitlements',
            static function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->bigInteger('entitleable_id');
                $table->string('entitleable_type');
                $table->integer('cost')->default(0)->nullable();
                $table->string('wallet_id', 36);
                $table->string('sku_id', 36);
                $table->string('description')->nullable();
                $table->timestamps();

                $table->foreign('sku_id')->references('id')->on('skus')->onDelete('cascade');
                $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // TODO: drop foreign keys first
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('skus');
    }
}
