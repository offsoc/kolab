<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendOpenviduRoomsSessionId extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'openvidu_rooms',
            static function (Blueprint $table) {
                $table->string('session_id', 36)->nullable()->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'openvidu_rooms',
            static function (Blueprint $table) {
                // $table->string('session_id', 16)->change();
            }
        );
    }
}
