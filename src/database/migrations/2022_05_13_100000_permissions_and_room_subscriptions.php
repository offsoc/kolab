<?php

use App\Entitlement;
use App\Meet\Room;
use App\Sku;
use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'permissions',
            static function (Blueprint $table) {
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
            static function (Blueprint $table) {
                $table->bigInteger('tenant_id')->unsigned()->nullable();
                $table->string('description')->nullable();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        );

        // Create the new SKUs
        $sku = Sku::where('title', 'room')->first();

        // Create the entitlement for every existing room
        foreach (Room::get() as $room) {
            $user = User::find($room->user_id); // @phpstan-ignore-line
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

            Entitlement::create([
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => 0,
                'fee' => 0,
                'entitleable_id' => $room->id,
                'entitleable_type' => Room::class,
            ]);
        }

        // Remove 'meet' SKU/entitlements
        Sku::where('title', 'meet')->delete();

        Schema::table(
            'openvidu_rooms',
            static function (Blueprint $table) {
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
            static function (Blueprint $table) {
                $table->dropForeign('openvidu_rooms_tenant_id_foreign');
                $table->dropColumn('tenant_id');
                $table->dropColumn('description');

                $table->bigInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
        );

        // Set user_id back
        foreach (Room::get() as $room) {
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
            static function (Blueprint $table) {
                $table->dropSoftDeletes();
            }
        );

        Entitlement::where('entitleable_type', Room::class)->forceDelete();

        Schema::dropIfExists('permissions');
    }
};
