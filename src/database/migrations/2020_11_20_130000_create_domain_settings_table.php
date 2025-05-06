<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainSettingsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'domain_settings',
            static function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('domain_id');
                $table->string('key');
                $table->text('value');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->foreign('domain_id')->references('id')->on('domains')
                    ->onDelete('cascade')->onUpdate('cascade');

                $table->unique(['domain_id', 'key']);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('domain_settings');
    }
}
