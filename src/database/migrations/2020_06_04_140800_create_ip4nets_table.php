<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateIp4netsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'ip4nets',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('rir_name', 8);
                $table->string('net_number', 15)->index();
                $table->tinyInteger('net_mask')->unsigned();
                $table->string('net_broadcast', 15)->index();
                $table->string('country', 2)->nullable();
                $table->bigInteger('serial')->unsigned();
                $table->timestamps();

                $table->index(['net_number', 'net_mask', 'net_broadcast']);
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
        Schema::dropIfExists('ip4nets');
    }
}
