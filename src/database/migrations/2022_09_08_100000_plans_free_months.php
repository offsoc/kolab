<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'plans',
            function (Blueprint $table) {
                $table->tinyInteger('free_months')->unsigned()->default(0);
            }
        );

        DB::table('plans')->update(['free_months' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'plans',
            function (Blueprint $table) {
                $table->dropColumn('free_months');
            }
        );
    }
};
