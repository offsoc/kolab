<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'plans',
            function (Blueprint $table) {
                $table->string('id', 36);
                $table->string('title', 36);
                $table->json('name');
                $table->json('description');
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
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
