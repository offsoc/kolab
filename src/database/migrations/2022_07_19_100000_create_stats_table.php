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
        Schema::create('stats', static function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->integer('type')->unsigned();
            $table->bigInteger('value');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at', 'tenant_id']);
            $table->index('tenant_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('stats');
    }
};
