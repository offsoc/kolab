<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class SignupCodeHeaders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'signup_codes',
            function (Blueprint $table) {
                $table->text('headers')->nullable();
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
            'signup_codes',
            function (Blueprint $table) {
                $table->dropColumn('headers');
            }
        );
    }
}
