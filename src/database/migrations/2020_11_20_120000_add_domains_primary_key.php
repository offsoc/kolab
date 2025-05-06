<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDomainsPrimaryKey extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'domains',
            static function (Blueprint $table) {
                $table->primary('id');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable('domains')) {
            Schema::table(
                'domains',
                static function (Blueprint $table) {
                    $table->dropPrimary('id');
                }
            );
        }
    }
}
