<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'package_skus',
            static function (Blueprint $table) {
                $table->integer('cost')->default(null)->nullable()->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'package_skus',
            static function (Blueprint $table) {
                $table->integer('cost')->default(0)->nullable()->change();
            }
        );
    }
};
