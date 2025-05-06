<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OauthClientScopes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'oauth_clients',
            static function (Blueprint $table) {
                $table->string('allowed_scopes')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'oauth_clients',
            static function (Blueprint $table) {
                $table->dropColumn('allowed_scopes');
            }
        );
    }
}
