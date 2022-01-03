<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class SignupCodesIndices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'signup_codes',
            function (Blueprint $table) {
                $table->index('email');
                $table->index('ip_address');
                $table->index('expires_at');
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
            'signup_codes',
            function (Blueprint $table) {
                $table->dropIndex('email');
                $table->dropIndex('ip_address');
                $table->dropIndex('expires_at');
            }
        );
    }
}
