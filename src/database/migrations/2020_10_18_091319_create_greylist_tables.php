<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore
class CreateGreylistTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'greylist_connect',
            function (Blueprint $table) {
                $table->charset = 'latin1';
                $table->collation = 'latin1_general_ci';
                $table->bigIncrements('id');
                $table->string('sender_local', 256);
                $table->string('sender_domain', 256);
                $table->string('recipient_hash', 64);
                $table->bigInteger('recipient_id')->unsigned()->nullable();
                $table->string('recipient_type', 16)->nullable();
                $table->bigInteger('net_id');
                $table->string('net_type', 16);
                $table->boolean('greylisting')->default(true);
                $table->bigInteger('connect_count')->unsigned()->default(1);
                $table->timestamps();

                /**
                 * Index for recipient request.
                 */
                $table->index(
                    [
                        'sender_local',
                        'sender_domain',
                        'recipient_hash',
                        'net_id',
                        'net_type'
                    ],
                    'ssrnn_idx'
                );

                /**
                 * Index for domain whitelist query.
                 */
                $table->index(
                    [
                        'sender_domain',
                        'net_id',
                        'net_type'
                    ],
                    'snn_idx'
                );

                /**
                 * Index for updated_at
                 */
                $table->index('updated_at');

                $table->unique(
                    ['sender_local', 'sender_domain', 'recipient_hash', 'net_id', 'net_type'],
                    'ssrnn_unq'
                );
            }
        );

        Schema::create(
            'greylist_penpals',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('local_id');
                $table->string('local_type', 16);
                $table->string('remote_local', 128);
                $table->string('remote_domain', 256);
                $table->timestamps();
            }
        );

        Schema::create(
            'greylist_settings',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('object_id');
                $table->string('object_type', 16);
                $table->string('key', 64);
                $table->text('value');
                $table->timestamps();

                $table->index(['object_id', 'object_type', 'key'], 'ook_idx');
            }
        );

        Schema::create(
            'greylist_whitelist',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('sender_local', 128)->nullable();
                $table->string('sender_domain', 256);
                $table->bigInteger('net_id');
                $table->string('net_type', 16);
                $table->timestamps();

                $table->index(['sender_local', 'sender_domain', 'net_id', 'net_type'], 'ssnn_idx');
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
        Schema::dropIfExists('greylist_connect');
        Schema::dropIfExists('greylist_penpals');
        Schema::dropIfExists('greylist_settings');
        Schema::dropIfExists('greylist_whitelist');
    }
}
