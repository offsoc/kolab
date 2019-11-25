<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDataMigratorQueuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_migrator_queues', function (Blueprint $table) {
            $table->string('id', 32);
            $table->integer('jobs_started');
            $table->integer('jobs_finished');
            $table->text('data');
            $table->timestamps();

            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('data_migrator_queues');
    }
}
