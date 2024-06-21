<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateOpenviduConnectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'openvidu_connections',
            function (Blueprint $table) {
                // I'm not sure about the max. length of the OpenVidu identifiers
                // In examples they have 14 characters, so 16 should be enough, but
                // let's be on the safe side with 24.
                $table->string('id', 24);
                $table->string('session_id', 24);
                $table->bigInteger('room_id')->unsigned();
                $table->smallInteger('role')->default(0);
                $table->text('metadata')->nullable(); // should be json, but mariadb
                $table->timestamps();

                $table->primary('id');
                $table->index('session_id');
                $table->foreign('room_id')->references('id')->on('openvidu_rooms')->onDelete('cascade');
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
        Schema::dropIfExists('openvidu_connections');
    }
}
