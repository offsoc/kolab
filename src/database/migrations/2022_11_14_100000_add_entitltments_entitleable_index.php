<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'entitlements',
            function (Blueprint $table) {
                $table->index(['entitleable_id', 'entitleable_type']);
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
            'entitlements',
            function (Blueprint $table) {
                $table->dropIndex(['entitleable_id', 'entitleable_type']);
            }
        );
    }
};
