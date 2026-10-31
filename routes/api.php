<?php

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Registry\ComponentRegistry;
use App\Domains\DocumentEngine\Schema\StylePrimitiveCatalog;
use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class)->name('api.health');

Route::get('/components/registry', function (ComponentRegistry $registry) {
    return response()->json([
        'schemaVersion' => 1,
        'components' => $registry->getMetadata(),
        'stylePrimitives' => StylePrimitiveCatalog::all(),
        'aliases' => $registry->getAliases(),
        'responsive' => $registry->getResponsiveContract(),
        'limits' => [
            'maxPayloadBytes' => DocumentLimits::MAX_PAYLOAD_BYTES,
            'maxTreeDepth' => DocumentLimits::MAX_TREE_DEPTH,
            'maxTotalNodes' => DocumentLimits::MAX_TOTAL_NODES,
            'maxChildrenPerNode' => DocumentLimits::MAX_CHILDREN_PER_NODE,
        ],
    ]);
})->name('api.components.registry');
