<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateCompanionAppsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companion_apps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            // Seems to grow over time, no clear specification.
            // Typically below 200 bytes, but some mention up to 350 bytes.
            $table->string('notification_token', 512)->nullable();
            // 16 byte for android, 36 for ios. May change over tyme
            $table->string('device_id', 64);
            $table->string('name')->nullable();
            $table->boolean('mfa_enabled');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('companion_apps');
    }
}
