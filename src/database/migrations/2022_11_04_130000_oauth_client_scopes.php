<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class OauthClientScopes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'oauth_clients',
            function (Blueprint $table) {
                $table->string('allowed_scopes')->nullable();
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
            'oauth_clients',
            function (Blueprint $table) {
                $table->dropColumn('allowed_scopes');
            }
        );
    }
}
