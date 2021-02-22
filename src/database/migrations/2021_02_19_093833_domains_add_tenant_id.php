<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class DomainsAddTenantId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'domains',
            function (Blueprint $table) {
                $table->bigInteger('tenant_id')->unsigned()->default(\config('app.tenant_id'))->nullable();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
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
            'domains',
            function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        );
    }
}
