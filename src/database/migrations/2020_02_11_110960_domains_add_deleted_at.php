<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DomainsAddDeletedAt extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'domains',
            static function (Blueprint $table) {
                $table->softDeletes();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'domains',
            static function (Blueprint $table) {
                $table->dropColumn(['deleted_at']);
            }
        );
    }
}
