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
            'users',
            static function (Blueprint $table) {
                $table->string('password_ldap', 512)->nullable()->change();
            }
        );

        Schema::table(
            'user_passwords',
            static function (Blueprint $table) {
                $table->string('password', 512)->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Note: We can't set the length to a smaller value if there are already entries that are long
    }
};
