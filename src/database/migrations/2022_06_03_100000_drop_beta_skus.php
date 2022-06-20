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
        $skus = ['beta-distlists', 'beta-resources', 'beta-shared-folders', 'files'];

        \App\Sku::whereIn('title', $skus)->delete();

        \App\Sku::where('title', 'beta')->update(['description' => 'Access to the private beta program features']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Sku::create([
                'title' => 'beta-distlists',
                'name' => 'Distribution lists',
                'description' => 'Access to mail distribution lists',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta\Distlists',
                'active' => true,
        ]);
        \App\Sku::create([
                'title' => 'beta-resources',
                'name' => 'Calendaring resources',
                'description' => 'Access to calendaring resources',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta\Resources',
                'active' => true,
        ]);
        \App\Sku::create([
                'title' => 'beta-shared-folders',
                'name' => 'Shared folders',
                'description' => 'Access to shared folders',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Beta\SharedFolders',
                'active' => true,
        ]);
        \App\Sku::create([
                'title' => 'files',
                'name' => 'File storage',
                'description' => 'Access to file storage',
                'cost' => 0,
                'units_free' => 0,
                'period' => 'monthly',
                'handler_class' => 'App\Handlers\Files',
                'active' => true,
        ]);
    }
};
