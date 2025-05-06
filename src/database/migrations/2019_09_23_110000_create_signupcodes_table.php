<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSignupCodesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('signup_codes', static function (Blueprint $table) {
            $table->string('code', 32);
            $table->string('short_code', 8);
            $table->text('data');
            $table->timestamp('expires_at');

            $table->primary('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('signup_codes');
    }
}
