<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendSettingsValueColumn extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'user_settings',
            static function (Blueprint $table) {
                $table->text('value')->change();
            }
        );

        Schema::table(
            'wallet_settings',
            static function (Blueprint $table) {
                $table->text('value')->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // do nothing
    }
}
