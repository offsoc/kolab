<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CompanionAppUuidsOauthClient extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('companion_apps');
        Schema::create('companion_apps', function (Blueprint $table) {
            $table->string('id', 36);
            $table->string('oauth_client_id', 36)->nullable();
            $table->bigInteger('user_id');
            // Seems to grow over time, no clear specification.
            // Typically below 200 bytes, but some mention up to 350 bytes.
            $table->string('notification_token', 512)->nullable();
            // 16 byte for android, 36 for ios. May change over tyme
            $table->string('device_id', 64)->default("");
            $table->string('name')->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->timestamps();

            $table->primary('id');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('oauth_client_id')
                  ->references('id')->on('oauth_clients')
                  ->onDelete('set null');
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
}
