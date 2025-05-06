<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserTableAddRoleColumn extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'users',
            static function (Blueprint $table) {
                $table->string('role')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'users',
            static function (Blueprint $table) {
                $table->dropColumn('role');
            }
        );
    }
}
