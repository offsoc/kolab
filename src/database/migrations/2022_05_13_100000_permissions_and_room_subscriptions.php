<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'permissions',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->bigInteger('permissible_id');
                $table->string('permissible_type');
                $table->integer('rights')->default(0);
                $table->string('user');
                $table->timestamps();

                $table->index('user');
                $table->index(['permissible_id', 'permissible_type']);
            }
        );

        Schema::table(
            'openvidu_rooms',
            function (Blueprint $table) {
                $table->bigInteger('tenant_id')->unsigned()->nullable();
                $table->string('description')->nullable();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        );

        // Create the new SKUs
        if (!\App\Sku::where('title', 'room')->first()) {
            $sku = \App\Sku::create([
                    'title' => 'group-room',
                    'name' => 'Group conference room',
                    'description' => 'Shareable audio & video conference room',
                    'cost' => 0,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\GroupRoom',
                    'active' => true,
            ]);

            $sku = \App\Sku::create([
                    'title' => 'room',
                    'name' => 'Standard conference room',
                    'description' => 'Audio & video conference room',
                    'cost' => 0,
                    'units_free' => 0,
                    'period' => 'monthly',
                    'handler_class' => 'App\Handlers\Room',
                    'active' => true,
            ]);

            // Create the entitlement for every existing room
            foreach (\App\Meet\Room::get() as $room) {
                $user = \App\User::find($room->user_id); // @phpstan-ignore-line
                if (!$user) {
                    $room->forceDelete();
                    continue;
                }

                // Set tenant_id
                if ($user->tenant_id) {
                    $room->tenant_id = $user->tenant_id;
                    $room->save();
                }

                $wallet = $user->wallets()->first();

                \App\Entitlement::create([
                        'wallet_id' => $wallet->id,
                        'sku_id' => $sku->id,
                        'cost' => 0,
                        'fee' => 0,
                        'entitleable_id' => $room->id,
                        'entitleable_type' => \App\Meet\Room::class
                ]);
            }
        }

        // Remove 'meet' SKU/entitlements
        \App\Sku::where('title', 'meet')->delete();

        Schema::table(
            'openvidu_rooms',
            function (Blueprint $table) {
                $table->dropForeign('openvidu_rooms_user_id_foreign');
                $table->dropColumn('user_id');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'openvidu_rooms',
            function (Blueprint $table) {
                $table->dropForeign('openvidu_rooms_tenant_id_foreign');
                $table->dropColumn('tenant_id');
                $table->dropColumn('description');

                $table->bigInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
        );

        // Set user_id back
        foreach (\App\Meet\Room::get() as $room) {
            $wallet = $room->wallet();
            if (!$wallet) {
                $room->forceDelete();
                continue;
            }

            $room->user_id = $wallet->user_id; // @phpstan-ignore-line
            $room->save();
        }

        Schema::table(
            'openvidu_rooms',
            function (Blueprint $table) {
                $table->dropSoftDeletes();
            }
        );

        \App\Entitlement::where('entitleable_type', \App\Meet\Room::class)->forceDelete();
        \App\Sku::where('title', 'room')->delete();
        \App\Sku::where('title', 'group-room')->delete();

        \App\Sku::create([
                'title' => 'meet',
                'name' => 'Voice & Video Conferencing (public beta)',
                'description' => 'Video conferencing tool',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Meet',
                'active' => true,
        ]);

        Schema::dropIfExists('permissions');
    }
};
