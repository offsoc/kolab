<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUserAliasesUniqueKey extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'user_aliases',
            static function (Blueprint $table) {
                $table->dropUnique(['alias']);
                $table->unique(['alias', 'user_id']);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down() {}
}
