<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class VerificationCodeActiveColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'verification_codes',
            function (Blueprint $table) {
                $table->boolean('active')->default(true);
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
        if (!Schema::hasTable('verification_codes')) {
            return;
        }

        Schema::table(
            'verification_codes',
            function (Blueprint $table) {
                $table->dropColumn('active');
            }
        );
    }
}
