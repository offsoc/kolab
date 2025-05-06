<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SignupCodesIndices extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->index('email');
                $table->index('ip_address');
                $table->index('expires_at');
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
                $table->dropIndex('signup_codes_email_index');
                $table->dropIndex('signup_codes_ip_address_index');
                $table->dropIndex('signup_codes_expires_at_index');
            }
        );
    }
}
