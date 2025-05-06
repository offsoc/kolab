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
            'entitlements',
            static function (Blueprint $table) {
                $table->index(['entitleable_id', 'entitleable_type']);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'entitlements',
            static function (Blueprint $table) {
                $table->dropIndex(['entitleable_id', 'entitleable_type']);
            }
        );
    }
};
