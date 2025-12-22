<?php

namespace App\Http\Controllers;

use App\Services\InventoryIntegrationService;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    protected InventoryIntegrationService $inventoryService;

    public function __construct(InventoryIntegrationService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * GET /items/scan/{qrCode} - Lookup item by QR code
     */
    public function scan(string $qrCode)
    {
        $item = $this->inventoryService->scanQrCode($qrCode);

        if (!$item) {
            return response()->json([
                'message' => 'Item not found',
            ], 404);
        }

        return response()->json([
            'data' => $item->load(['currentLocation', 'type', 'category']),
        ]);
    }

    /**
     * GET /items/{id}/movement-history - View full movement trail
     */
    public function movementHistory(int $id)
    {
        $movements = $this->inventoryService->getItemMovementHistory($id);

        return response()->json([
            'data' => $movements,
        ]);
    }

    /**
     * GET /items/{id}/current-request - Find active request for this item
     */
    public function currentRequest(int $id)
    {
        $request = $this->inventoryService->getCurrentRequest($id);

        if (!$request) {
            return response()->json([
                'message' => 'No active request found for this item',
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $request->load([
                'requestType',
                'status',
                'urgency',
                'originLocation',
                'receivingLocation',
                'runInstance.route',
                'assignedRunner',
            ]),
        ]);
    }
}
