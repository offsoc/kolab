<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpenviduTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'openvidu_rooms',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('user_id');
                $table->string('name', 16)->unique();
                $table->string('session_id', 16)->nullable()->unique();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
        );

        Schema::create(
            'openvidu_room_settings',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('room_id')->unsigned();
                $table->string('key', 16);
                $table->string('value');
                $table->timestamps();

                $table->foreign('room_id')->references('id')
                    ->on('openvidu_rooms')->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('openvidu_room_settings');
        Schema::dropIfExists('openvidu_rooms');
    }
}
