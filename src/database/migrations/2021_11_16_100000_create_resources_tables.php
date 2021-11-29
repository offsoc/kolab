<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore
class CreateResourcesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'resources',
            function (Blueprint $table) {
                $table->unsignedBigInteger('id');
                $table->string('email')->unique();
                $table->string('name');
                $table->smallInteger('status');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->primary('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        );

        Schema::create(
            'resource_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('resource_id');
                $table->string('key');
                $table->text('value');
                $table->timestamps();

                $table->foreign('resource_id')->references('id')->on('resources')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->unique(['resource_id', 'key']);
            }
        );

        \App\Sku::where('title', 'resource')->update([
                'active' => true,
                'cost' => 0,
        ]);

        if (!\App\Sku::where('title', 'beta-resources')->first()) {
            \App\Sku::create([
                'title' => 'beta-resources',
                'name' => 'Calendaring resources',
                'description' => 'Access to calendaring resources',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta\Resources',
                'active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('resource_settings');
        Schema::dropIfExists('resources');

        // there's no need to remove the SKU
    }
}
