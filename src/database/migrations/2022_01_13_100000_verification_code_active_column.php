<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VerificationCodeActiveColumn extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'verification_codes',
            static function (Blueprint $table) {
                $table->boolean('active')->default(true);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (!Schema::hasTable('verification_codes')) {
            return;
        }

        Schema::table(
            'verification_codes',
            static function (Blueprint $table) {
                $table->dropColumn('active');
            }
        );
    }
}
