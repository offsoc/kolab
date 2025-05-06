<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanPackagesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'plan_packages',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('plan_id', 36);
                $table->string('package_id', 36);

                $table->integer('qty')->default(1);
                $table->integer('qty_min')->default(0);
                $table->integer('qty_max')->default(0);

                $table->integer('discount_qty')->default(0);
                $table->integer('discount_rate')->default(0);

                $table->foreign('plan_id')->references('id')->on('plans')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->foreign('package_id')->references('id')->on('packages')
                    ->onDelete('cascade')->onUpdate('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('plan_packages');
    }
}
