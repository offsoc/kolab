<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'licenses',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('user_id')->nullable()->index();
                $table->bigInteger('tenant_id')->unsigned()->nullable()->index();
                $table->string('type', 16);
                $table->string('key', 255);
                $table->timestamps();

                $table->unique(['type', 'key']);

                $table->foreign('user_id')->references('id')->on('users')
                    ->onDelete('cascade')->onUpdate('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')
                    ->onDelete('set null')->onUpdate('cascade');
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
        Schema::dropIfExists('licenses');
    }
};
