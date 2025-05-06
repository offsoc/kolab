<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateVerificationCodesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('verification_codes', static function (Blueprint $table) {
            $table->bigInteger('user_id');
            $table->string('code', 32);
            $table->string('short_code', 16);
            $table->string('mode');
            $table->timestamp('expires_at');

            $table->primary('code');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('verification_codes');
    }
}
