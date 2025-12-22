<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RequestActionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding parts request actions...');

        // Get request type IDs
        $pickupType = DB::table('parts_request_types')->where('name', 'pickup')->first();
        $transferType = DB::table('parts_request_types')->where('name', 'transfer')->first();
        $deliveryType = DB::table('parts_request_types')->where('name', 'delivery')->first();

        // Get status IDs
        $statuses = DB::table('parts_request_statuses')->get()->keyBy('name');

        if (!$pickupType || !$transferType || !$deliveryType || $statuses->isEmpty()) {
            $this->command->warn('Required request types or statuses not found. Please seed those first.');
            return;
        }

        $now = Carbon::now();
        $sortOrder = 1;

        // Helper function to create action
        $createAction = function($typeId, $fromStatusName, $toStatusName, $actionName, $label, $role, $requiresNote = false, $requiresPhoto = false, $color = 'primary', $icon = null) use (&$sortOrder, $statuses, $now) {
            if (!isset($statuses[$fromStatusName]) || !isset($statuses[$toStatusName])) {
                return null;
            }

            $action = [
                'request_type_id' => $typeId,
                'from_status_id' => $statuses[$fromStatusName]->id,
                'to_status_id' => $statuses[$toStatusName]->id,
                'action_name' => $actionName,
                'display_label' => $label,
                'display_color' => $color,
                'display_icon' => $icon,
                'actor_role' => $role,
                'requires_note' => $requiresNote,
                'requires_photo' => $requiresPhoto,
                'sort_order' => $sortOrder++,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            DB::table('parts_request_actions')->insertOrIgnore($action);
            return $action;
        };

        // VENDOR PICKUP ACTIONS
        $createAction($pickupType->id, 'new', 'ready_for_pickup', 'ready_for_pickup', 'Mark Ready for Pickup', 'shop_staff', false, false, 'positive', 'check_circle');
        $createAction($pickupType->id, 'new', 'canceled', 'not_available', 'Not Available', 'shop_staff', true, false, 'negative', 'cancel');
        $createAction($pickupType->id, 'ready_for_pickup', 'picked_up', 'picked_up', 'Mark Picked Up', 'runner', false, true, 'orange', 'shopping_bag');
        $createAction($pickupType->id, 'picked_up', 'delivered', 'delivered', 'Mark Delivered', 'runner', false, true, 'positive', 'local_shipping');

        // SHOP TRANSFER ACTIONS
        $createAction($transferType->id, 'new', 'ready_for_pickup', 'ready_to_transfer', 'Ready to Transfer', 'shop_staff', false, false, 'positive', 'check_circle');
        $createAction($transferType->id, 'new', 'canceled', 'not_available', 'Not Available', 'shop_staff', true, false, 'negative', 'cancel');
        $createAction($transferType->id, 'ready_for_pickup', 'picked_up', 'picked_up', 'Pick Up Part', 'runner', false, true, 'orange', 'shopping_bag');
        $createAction($transferType->id, 'picked_up', 'delivered', 'delivered', 'Deliver Part', 'runner', false, true, 'positive', 'local_shipping');

        // CUSTOMER DELIVERY ACTIONS
        $createAction($deliveryType->id, 'new', 'ready_for_pickup', 'ready_to_deliver', 'Ready to Deliver', 'shop_staff', false, false, 'positive', 'check_circle');
        $createAction($deliveryType->id, 'ready_for_pickup', 'picked_up', 'picked_up', 'Pick Up Part', 'runner', false, true, 'orange', 'shopping_bag');
        $createAction($deliveryType->id, 'picked_up', 'delivered', 'delivered', 'Deliver to Customer', 'runner', false, true, 'positive', 'local_shipping');

        // UNIVERSAL PROBLEM REPORTING
        $problemStatuses = ['new', 'ready_for_pickup', 'picked_up'];
        foreach ([$pickupType, $transferType, $deliveryType] as $type) {
            foreach ($problemStatuses as $statusName) {
                if (isset($statuses[$statusName]) && isset($statuses['problem'])) {
                    $createAction($type->id, $statusName, 'problem', 'report_problem', 'Report Problem', 'runner', true, false, 'negative', 'warning');
                }
            }
        }

        $this->command->info('Parts request actions seeded successfully!');
    }
}
