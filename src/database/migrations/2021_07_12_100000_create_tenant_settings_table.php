<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateTenantSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'tenant_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id');
                $table->string('key');
                $table->text('value');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->foreign('tenant_id')->references('id')->on('tenants')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->unique(['tenant_id', 'key']);
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
        Schema::dropIfExists('tenant_settings');
    }
}
