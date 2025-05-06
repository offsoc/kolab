<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'plans',
            static function (Blueprint $table) {
                $table->string('id', 36);
                $table->string('title', 36);
                // were json, but mariadb
                $table->text('name');
                $table->text('description');
                // end of
                $table->datetime('promo_from')->nullable();
                $table->datetime('promo_to')->nullable();
                $table->integer('qty_min')->default(0)->nullable();
                $table->integer('qty_max')->default(0)->nullable();
                $table->integer('discount_qty')->default(0);
                $table->integer('discount_rate')->default(0);

                $table->primary('id');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
