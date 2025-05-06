<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddGroupNameColumn extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'groups',
            static function (Blueprint $table) {
                $table->string('name')->nullable()->after('email');
            }
        );

        // Fill the name with the local part of the email address
        DB::table('groups')->update([
            'name' => DB::raw("SUBSTRING_INDEX(`email`, '@', 1)"),
        ]);

        Schema::table(
            'groups',
            static function (Blueprint $table) {
                $table->string('name')->nullable(false)->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'groups',
            static function (Blueprint $table) {
                $table->dropColumn('name');
            }
        );
    }
}
