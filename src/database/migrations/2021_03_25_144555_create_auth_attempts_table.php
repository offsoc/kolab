<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateAuthAttemptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auth_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('user_id');
            $table->string('ip', 36);
            $table->string('status', 36)->default('NEW');
            $table->string('reason', 36)->nullable();
            $table->datetime('expires_at')->nullable();
            $table->datetime('last_seen')->nullable();
            $table->timestamps();

            $table->index('updated_at');
            $table->unique(['user_id', 'ip']);

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
        Schema::dropIfExists('auth_attempts');
    }
}
