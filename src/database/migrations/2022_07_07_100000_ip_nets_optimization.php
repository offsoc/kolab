<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('ip4nets');
        Schema::dropIfExists('ip6nets');

        Schema::create(
            'ip4nets',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('rir_name', 8);
                $table->bigInteger('net_number');
                $table->bigInteger('net_broadcast');
                $table->tinyInteger('net_mask')->unsigned();
                $table->string('country', 2)->nullable();
                $table->bigInteger('serial')->unsigned();
                $table->timestamps();

                $table->index(['net_number', 'net_broadcast', 'net_mask']);
            }
        );

        Schema::create(
            'ip6nets',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('rir_name', 8);
                $table->bigInteger('net_number');
                $table->bigInteger('net_broadcast');
                $table->tinyInteger('net_mask')->unsigned();
                $table->string('country', 2)->nullable();
                $table->bigInteger('serial')->unsigned();
                $table->timestamps();

                $table->index(['net_number', 'net_broadcast', 'net_mask']);
            }
        );

        // VARBINARY is MySQL specific and Laravel does not support it natively
        DB::statement("alter table ip4nets change net_number net_number varbinary(4) not null");
        DB::statement("alter table ip4nets change net_broadcast net_broadcast varbinary(4) not null");
        DB::statement("alter table ip6nets change net_number net_number varbinary(16) not null");
        DB::statement("alter table ip6nets change net_broadcast net_broadcast varbinary(16) not null");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip4nets');
        Schema::dropIfExists('ip6nets');

        Schema::create(
            'ip4nets',
            static function (Blueprint $table) {
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
};
