<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIp6netsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'ip6nets',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('rir_name', 8);
                $table->string('net_number', 39)->index();
                $table->tinyInteger('net_mask')->unsigned();
                $table->string('net_broadcast', 39)->index();
                $table->string('country', 2)->nullable();
                $table->bigInteger('serial')->unsigned();
                $table->timestamps();

                $table->index(['net_number', 'net_mask', 'net_broadcast']);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('ip6nets');
    }
}
