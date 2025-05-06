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
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->string('verify_ip_address')->index()->nullable();
                $table->string('submit_ip_address')->index()->nullable();

                $table->bigInteger('user_id')->index()->nullable();
                $table->foreign('user_id')->references('id')->on('users')
                    ->onUpdate('cascade')->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
                $table->dropColumn('verify_ip_address');
                $table->dropColumn('submit_ip_address');
            }
        );
    }
};
