<?php

namespace App\Http\Controllers;

use App\Models\ClosedDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClosedDateController extends Controller
{
    /**
     * List closed dates with optional year filter
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClosedDate::with(['createdByUser', 'updatedByUser'])
            ->orderBy('date');

        if ($request->has('year')) {
            $query->forYear((int) $request->year);
        }

        if ($request->has('upcoming') && $request->upcoming === 'true') {
            $query->upcoming();
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Create a new closed date
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:closed_dates,date',
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $closedDate = ClosedDate::create($validated);

        return response()->json([
            'message' => 'Closed date created successfully',
            'data' => $closedDate->load(['createdByUser', 'updatedByUser']),
        ], 201);
    }

    /**
     * Get a single closed date
     */
    public function show(int $id): JsonResponse
    {
        $closedDate = ClosedDate::with(['createdByUser', 'updatedByUser'])
            ->findOrFail($id);

        return response()->json([
            'data' => $closedDate,
        ]);
    }

    /**
     * Update a closed date
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $closedDate = ClosedDate::findOrFail($id);

        $validated = $request->validate([
            'date' => 'sometimes|required|date|unique:closed_dates,date,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $closedDate->update($validated);

        return response()->json([
            'message' => 'Closed date updated successfully',
            'data' => $closedDate->load(['createdByUser', 'updatedByUser']),
        ]);
    }

    /**
     * Delete a closed date
     */
    public function destroy(int $id): JsonResponse
    {
        $closedDate = ClosedDate::findOrFail($id);
        $closedDate->delete();

        return response()->json([
            'message' => 'Closed date deleted successfully',
        ]);
    }

    /**
     * Check if a specific date is closed
     */
    public function checkDate(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = \Carbon\Carbon::parse($request->date);
        $closedDate = ClosedDate::getForDate($date);

        return response()->json([
            'is_closed' => $closedDate !== null,
            'closed_date' => $closedDate,
        ]);
    }
}
