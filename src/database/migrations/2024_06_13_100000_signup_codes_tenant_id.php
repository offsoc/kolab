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
            'signup_codes',
            static function (Blueprint $table) {
                $table->bigInteger('tenant_id')->unsigned()->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        );

        // We could set tenant_id for old records if there's only one tenant in the DB,
        // but I think nothing will happen if we don't do this.
        // Leave it to the deployment-specific migrations.
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'signup_codes',
            static function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        );
    }
};
