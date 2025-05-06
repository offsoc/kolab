<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'tenants',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title', 32);
                $table->timestamps();
            }
        );

        foreach (['users', 'discounts', 'domains', 'plans', 'packages', 'skus'] as $tableName) {
            Schema::table(
                $tableName,
                static function (Blueprint $table) {
                    $table->bigInteger('tenant_id')->unsigned()->nullable();
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
                }
            );
        }

        // Add fee column
        foreach (['entitlements', 'skus'] as $table) {
            Schema::table(
                $table,
                static function (Blueprint $table) {
                    $table->integer('fee')->nullable();
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        foreach (['users', 'discounts', 'domains', 'plans', 'packages', 'skus'] as $tableName) {
            Schema::table(
                $tableName,
                static function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                }
            );
        }

        foreach (['entitlements', 'skus'] as $table) {
            Schema::table(
                $table,
                static function (Blueprint $table) {
                    $table->dropColumn('fee');
                }
            );
        }

        Schema::dropIfExists('tenants');
    }
}
