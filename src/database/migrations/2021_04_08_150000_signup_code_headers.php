<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SignupCodeHeaders extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->text('headers')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->dropColumn('headers');
            }
        );
    }
}
