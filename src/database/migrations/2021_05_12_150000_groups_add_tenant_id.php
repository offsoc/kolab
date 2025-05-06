<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupsAddTenantId extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'groups',
            static function (Blueprint $table) {
                $table->bigInteger('tenant_id')->unsigned()->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        );

        if ($tenant_id = \config('app.tenant_id')) {
            DB::statement("UPDATE `groups` SET `tenant_id` = {$tenant_id}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'groups',
            static function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        );
    }
}
