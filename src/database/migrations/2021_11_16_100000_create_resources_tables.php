<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResourcesTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'resources',
            static function (Blueprint $table) {
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
            static function (Blueprint $table) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('resource_settings');
        Schema::dropIfExists('resources');

        // there's no need to remove the SKU
    }
}
