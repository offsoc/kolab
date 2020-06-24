<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

// phpcs:ignore
class CreateSignupCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('signup_codes', function (Blueprint $table) {
            $table->string('code', 32);
            $table->string('short_code', 8);
            $table->text('data');
            $table->timestamp('expires_at');

            $table->primary('code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('signup_codes');
    }
}
