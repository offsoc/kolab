<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'tenants',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title', 32);
                $table->timestamps();
            }
        );

        $tenantId = \config('app.tenant_id');

        $tenant = \App\Tenant::create(['id' => $tenantId, 'title' => 'Kolab Now']);

        foreach (['users', 'discounts', 'domains', 'plans', 'packages', 'skus'] as $tableName) {
            Schema::table(
                $tableName,
                function (Blueprint $table) {
                    $table->bigInteger('tenant_id')->unsigned()->nullable();
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
                }
            );

            if ($tenantId) {
                DB::statement("UPDATE `{$tableName}` SET `tenant_id` = {$tenantId}");
            }
        }

        // Add fee column
        foreach (['entitlements', 'skus'] as $table) {
            Schema::table(
                $table,
                function (Blueprint $table) {
                    $table->integer('fee')->nullable();
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (['users', 'discounts', 'domains', 'plans', 'packages', 'skus'] as $tableName) {
            Schema::table(
                $tableName,
                function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                }
            );
        }

        foreach (['entitlements', 'skus'] as $table) {
            Schema::table(
                $table,
                function (Blueprint $table) {
                    $table->dropColumn('fee');
                }
            );
        }

        Schema::dropIfExists('tenants');
    }
}
