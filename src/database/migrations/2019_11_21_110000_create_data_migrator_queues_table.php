<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('data_migrator_queues', static function (Blueprint $table) {
            $table->string('id', 32);
            $table->integer('jobs_started');
            $table->integer('jobs_finished');
            $table->mediumText('data');
            $table->timestamps();

            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('data_migrator_queues');
    }
};
