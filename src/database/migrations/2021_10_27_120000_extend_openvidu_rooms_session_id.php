<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class ExtendOpenviduRoomsSessionId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'openvidu_rooms',
            function (Blueprint $table) {
                $table->string('session_id', 36)->change();
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
            'openvidu_rooms',
            function (Blueprint $table) {
                $table->string('session_id', 16)->change();
            }
        );
    }
}
