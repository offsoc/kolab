<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'package_skus',
            function (Blueprint $table) {
                $table->integer('cost')->default(null)->nullable()->change();
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
        Schema::table(
            'package_skus',
            function (Blueprint $table) {
                $table->integer('cost')->default(0)->nullable()->change();
            }
        );
    }
};
