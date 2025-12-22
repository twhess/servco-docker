<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemMovement;
use App\Models\PartsRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class InventoryIntegrationService
{
    /**
     * Link a parts request to an existing inventory item
     */
    public function linkRequestToItem(PartsRequest $request, int $itemId): void
    {
        $item = Item::findOrFail($itemId);

        // Validate item is available
        if (!in_array($item->status, ['available', 'in_stock', 'at_shop'])) {
            throw new \Exception("Item #{$itemId} is not available (status: {$item->status})");
        }

        $request->update([
            'item_id' => $itemId,
            'updated_by' => auth()->id() ?? 1,
        ]);

        Log::info("Request #{$request->id} linked to item #{$itemId}");
    }

    /**
     * Create ItemMovement record when runner picks up item
     */
    public function createItemMovementFromPickup(PartsRequest $request, User $runner): ItemMovement
    {
        if (!$request->item_id) {
            throw new \Exception("Request #{$request->id} is not linked to an inventory item");
        }

        $item = $request->item;
        $runnerVehicle = $runner->partsRunnerVehicle ?? null;

        if (!$runnerVehicle) {
            throw new \Exception("Runner #{$runner->id} does not have an assigned vehicle");
        }

        // Create movement record
        $movement = ItemMovement::create([
            'item_id' => $request->item_id,
            'from_location_id' => $request->origin_location_id,
            'to_location_id' => $runnerVehicle->id,
            'moved_by_user_id' => $runner->id,
            'moved_at' => now(),
            'movement_type' => 'pickup',
            'reference_number' => $request->reference_number,
            'notes' => "Picked up for parts request {$request->reference_number}",
            'parts_request_id' => $request->id,
        ]);

        // Update item current location and status
        $item->update([
            'current_location_id' => $runnerVehicle->id,
            'status' => 'in_transit',
            'updated_by' => $runner->id,
        ]);

        Log::info("Item movement created: Item #{$item->id} picked up from location #{$request->origin_location_id} by runner #{$runner->id}");

        return $movement;
    }

    /**
     * Create ItemMovement record when runner delivers item
     */
    public function createItemMovementFromDelivery(PartsRequest $request, User $runner): ItemMovement
    {
        if (!$request->item_id) {
            throw new \Exception("Request #{$request->id} is not linked to an inventory item");
        }

        $item = $request->item;
        $runnerVehicle = $runner->partsRunnerVehicle ?? null;

        if (!$runnerVehicle) {
            throw new \Exception("Runner #{$runner->id} does not have an assigned vehicle");
        }

        // Determine destination location
        $destinationLocationId = $request->receiving_location_id;

        // Create movement record
        $movement = ItemMovement::create([
            'item_id' => $request->item_id,
            'from_location_id' => $runnerVehicle->id,
            'to_location_id' => $destinationLocationId,
            'moved_by_user_id' => $runner->id,
            'moved_at' => now(),
            'movement_type' => 'delivery',
            'reference_number' => $request->reference_number,
            'notes' => "Delivered for parts request {$request->reference_number}",
            'parts_request_id' => $request->id,
        ]);

        // Determine new status based on destination type
        $destinationLocation = $request->receivingLocation;
        $newStatus = 'delivered';

        if ($destinationLocation) {
            switch ($destinationLocation->type) {
                case 'vendor':
                    $newStatus = 'at_vendor';
                    break;
                case 'fixed_shop':
                    $newStatus = 'at_shop';
                    break;
                case 'customer_site':
                    $newStatus = 'delivered';
                    break;
                default:
                    $newStatus = 'delivered';
            }
        }

        // Update item current location and status
        $item->update([
            'current_location_id' => $destinationLocationId,
            'status' => $newStatus,
            'updated_by' => $runner->id,
        ]);

        Log::info("Item movement created: Item #{$item->id} delivered to location #{$destinationLocationId} by runner #{$runner->id}");

        return $movement;
    }

    /**
     * Scan QR code to lookup item
     */
    public function scanQrCode(string $qrCode): ?Item
    {
        $item = Item::where('qr_code', $qrCode)->first();

        if ($item) {
            Log::info("Item found by QR code: {$qrCode} -> Item #{$item->id}");
        } else {
            Log::warning("No item found for QR code: {$qrCode}");
        }

        return $item;
    }

    /**
     * Get full movement history for an item
     */
    public function getItemMovementHistory(int $itemId): \Illuminate\Support\Collection
    {
        return ItemMovement::where('item_id', $itemId)
            ->with(['fromLocation', 'toLocation', 'movedBy', 'partsRequest'])
            ->orderBy('moved_at', 'desc')
            ->get();
    }

    /**
     * Find active parts request for an item
     */
    public function getCurrentRequest(int $itemId): ?PartsRequest
    {
        return PartsRequest::where('item_id', $itemId)
            ->active()
            ->first();
    }
}
