<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateOpenviduTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'openvidu_rooms',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('user_id');
                $table->string('session_id', 16)->unique()->index();
                $table->timestamps();
            }
        );

        Schema::create(
            'openvidu_room_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('room_id');
                $table->string('key', 16);
                $table->string('value');
                $table->timestamps();
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
        Schema::dropIfExists('openvidu_rooms');
        Schema::dropIfExists('openvidu_room_settings');
    }
}
