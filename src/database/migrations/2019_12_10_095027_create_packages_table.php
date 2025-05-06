<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackagesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'packages',
            static function (Blueprint $table) {
                $table->string('id', 36);
                $table->string('title', 36);
                // were json, but mariadb
                $table->text('name');
                $table->text('description');
                // end of
                $table->integer('discount_rate')->default(0)->nullable();

                $table->primary('id');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('packages');
    }
}
