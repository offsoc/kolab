<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyRatelimitTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'policy_ratelimit',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('user_id');
                $table->bigInteger('owner_id');
                $table->string('recipient_hash', 128);
                $table->tinyInteger('recipient_count')->unsigned();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');

                $table->index(['user_id', 'updated_at']);
                $table->index(['owner_id', 'updated_at']);
                $table->index(['user_id', 'recipient_hash', 'updated_at']);
            }
        );

        Schema::create(
            'policy_ratelimit_wl',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('whitelistable_id');
                $table->string('whitelistable_type');
                $table->timestamps();

                $table->index(['whitelistable_id', 'whitelistable_type']);
                $table->unique(['whitelistable_id', 'whitelistable_type']);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('policy_ratelimit');
        Schema::dropIfExists('policy_ratelimit_wl');
    }
}
