<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePackageSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'package_skus',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('package_id', 36);
                $table->string('sku_id', 36);
                $table->integer('qty')->default(1);

                $table->foreign('package_id')->references('id')->on('packages')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->foreign('sku_id')->references('id')->on('skus')
                    ->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('package_skus');
    }
}
