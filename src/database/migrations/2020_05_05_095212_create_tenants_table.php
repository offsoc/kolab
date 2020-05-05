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

        Schema::table(
            'users',
            function (Blueprint $table) {
                $table->bigInteger('tenant_id')->unsigned()->nullable();

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
            'users',
            function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        );

        Schema::dropIfExists('tenants');
    }
}
