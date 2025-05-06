<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackageSkusTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'package_skus',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('package_id', 36);
                $table->string('sku_id', 36);
                $table->integer('qty')->default(1);
                $table->integer('cost')->default(0)->nullable();

                $table->foreign('package_id')->references('id')->on('packages')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->foreign('sku_id')->references('id')->on('skus')
                    ->onDelete('cascade')->onUpdate('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('package_skus');
    }
}
