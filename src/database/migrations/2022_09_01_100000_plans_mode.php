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
            'plans',
            static function (Blueprint $table) {
                $table->string('mode', 32)->default('email');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'plans',
            static function (Blueprint $table) {
                $table->dropColumn('mode');
            }
        );
    }
};
