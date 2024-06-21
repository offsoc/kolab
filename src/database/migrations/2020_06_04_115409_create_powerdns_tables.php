<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreatePowerDNSTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Drop the tables from the mysql initialization
        Schema::dropIfExists('powerdns_domains');
        Schema::dropIfExists('powerdns_records');
        Schema::dropIfExists('powerdns_masters');
        Schema::dropIfExists('powerdns_comments');
        Schema::dropIfExists('powerdns_domain_settings');
        Schema::dropIfExists('powerdns_cryptokeys');
        Schema::dropIfExists('powerdns_tsigkeys');

        Schema::create(
            'powerdns_domains',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255)->unique()->index();
                $table->string('master', 128)->nullable();
                $table->datetime('last_check')->nullable();
                $table->string('type', 6)->default('NATIVE');
                $table->integer('notified_serial')->unsigned()->nullable();
                $table->string('account', 40)->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'powerdns_records',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('domain_id')->unsigned();
                $table->string('name', 255)->nullable();
                $table->string('type', 10)->nullable();
                $table->longtext('content')->nullable();
                $table->integer('ttl')->unsigned()->nullable();
                $table->integer('prio')->unsigned()->nullable();
                $table->boolean('disabled')->default(false);
                $table->binary('ordername')->nullable();
                $table->boolean('auth')->default(true);
                $table->timestamps();

                $table->foreign('domain_id')->references('id')->on('powerdns_domains')
                    ->onDelete('cascade');

                $table->index('domain_id');
                $table->index(['name', 'type']);
                //$table->index('ordername');
            }
        );

        Schema::create(
            'powerdns_masters',
            function (Blueprint $table) {
                $table->string('ip', 64);
                $table->string('nameserver', 255);
                $table->string('account', 40);

                $table->primary(['ip', 'nameserver']);

                $table->timestamps();
            }
        );

        Schema::create(
            'powerdns_comments',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('domain_id')->unsigned();
                $table->string('name', 255);
                $table->string('type', 10);
                $table->string('account', 40)->nullable();
                $table->text('comment');
                $table->timestamps();

                $table->index(['name', 'type']);
                $table->index(['domain_id', 'updated_at']);

                $table->foreign('domain_id')->references('id')->on('powerdns_domains')
                    ->onDelete('cascade');
            }
        );

        Schema::create(
            'powerdns_domain_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('domain_id')->unsigned();
                $table->string('kind', 32);
                $table->text('content');
                $table->timestamps();

                $table->foreign('domain_id')->references('id')->on('powerdns_domains')
                    ->onDelete('cascade');
            }
        );

        Schema::create(
            'powerdns_cryptokeys',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('domain_id')->unsigned();
                $table->integer('flags');
                $table->boolean('active');
                $table->text('content');
                $table->timestamps();

                $table->index('domain_id');

                $table->foreign('domain_id')->references('id')->on('powerdns_domains')
                    ->onDelete('cascade');
            }
        );

        Schema::create(
            'powerdns_tsigkeys',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255);
                $table->string('algorithm', 50);
                $table->string('secret', 255);
                $table->timestamps();

                $table->index(['name', 'algorithm']);
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
        Schema::dropIfExists('powerdns_tsigkeys');
        Schema::dropIfExists('powerdns_cryptokeys');
        Schema::dropIfExists('powerdns_domain_settings');
        Schema::dropIfExists('powerdns_comments');
        Schema::dropIfExists('powerdns_masters');
        Schema::dropIfExists('powerdns_records');
        Schema::dropIfExists('powerdns_domains');
    }
}
