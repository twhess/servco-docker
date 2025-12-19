<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ServiceLocation;
use App\Models\PartsRequest;
use App\Models\PartsRequestType;
use App\Models\PartsRequestStatus;
use App\Models\UrgencyLevel;
use App\Models\PartsRequestEvent;
use Illuminate\Database\Seeder;

class PartsRunnerSeeder extends Seeder
{
    public function run(): void
    {
        // Create sample users if they don't exist
        $dispatcher = User::firstOrCreate(
            ['email' => 'dispatcher@example.com'],
            [
                'username' => 'dispatcher',
                'password' => bcrypt('password'),
                'first_name' => 'John',
                'last_name' => 'Dispatcher',
                'role' => 'dispatcher',
                'active' => true,
            ]
        );

        $runner1 = User::firstOrCreate(
            ['email' => 'runner1@example.com'],
            [
                'username' => 'runner1',
                'password' => bcrypt('password'),
                'first_name' => 'Mike',
                'last_name' => 'Runner',
                'role' => 'runner_driver',
                'active' => true,
            ]
        );

        $runner2 = User::firstOrCreate(
            ['email' => 'runner2@example.com'],
            [
                'username' => 'runner2',
                'password' => bcrypt('password'),
                'first_name' => 'Sarah',
                'last_name' => 'Driver',
                'role' => 'runner_driver',
                'active' => true,
            ]
        );

        // Get lookup data
        $pickupType = PartsRequestType::where('name', 'pickup')->first();
        $transferType = PartsRequestType::where('name', 'transfer')->first();
        $deliveryType = PartsRequestType::where('name', 'delivery')->first();

        $newStatus = PartsRequestStatus::where('name', 'new')->first();
        $assignedStatus = PartsRequestStatus::where('name', 'assigned')->first();
        $pickedUpStatus = PartsRequestStatus::where('name', 'picked_up')->first();

        $normalUrgency = UrgencyLevel::where('name', 'normal')->first();
        $todayUrgency = UrgencyLevel::where('name', 'today')->first();
        $asapUrgency = UrgencyLevel::where('name', 'asap')->first();
        $emergencyUrgency = UrgencyLevel::where('name', 'emergency')->first();

        // Get locations
        $locations = ServiceLocation::limit(3)->get();
        if ($locations->count() < 2) {
            // Create sample locations if none exist
            $locations = collect([
                ServiceLocation::create([
                    'name' => 'Main Shop',
                    'code' => 'MAIN',
                    'location_type' => 'fixed_shop',
                    'status' => 'available',
                    'is_active' => true,
                    'city' => 'Lima',
                    'state' => 'OH',
                    'postal_code' => '45801',
                    'latitude' => 40.7425,
                    'longitude' => -84.1052,
                    'is_dispatchable' => false,
                ]),
                ServiceLocation::create([
                    'name' => 'West Shop',
                    'code' => 'WEST',
                    'location_type' => 'fixed_shop',
                    'status' => 'available',
                    'is_active' => true,
                    'city' => 'Wapakoneta',
                    'state' => 'OH',
                    'postal_code' => '45895',
                    'latitude' => 40.5678,
                    'longitude' => -84.1936,
                    'is_dispatchable' => false,
                ]),
            ]);
        }

        // Create sample parts requests

        // Request 1: Pickup from NAPA (new, unassigned, emergency)
        $request1 = PartsRequest::create([
            'reference_number' => 'PR-20241219-0001',
            'request_type_id' => $pickupType->id,
            'vendor_name' => 'NAPA Auto Parts',
            'origin_address' => '123 Main St, Lima, OH 45801',
            'origin_lat' => 40.7425,
            'origin_lng' => -84.1052,
            'receiving_location_id' => $locations[0]->id,
            'requested_at' => now()->subHours(2),
            'requested_by_user_id' => $dispatcher->id,
            'pickup_run' => true,
            'urgency_id' => $emergencyUrgency->id,
            'status_id' => $newStatus->id,
            'details' => 'Brake pads for F-250, part #12345. URGENT - Truck down!',
            'special_instructions' => 'Call ahead, ask for Mike at ext. 23',
            'slack_notify_pickup' => true,
            'slack_notify_delivery' => true,
            'slack_channel' => '#parts-alerts',
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request1->id,
            'event_type' => 'created',
            'event_at' => now()->subHours(2),
            'user_id' => $dispatcher->id,
        ]);

        // Request 2: Transfer between shops (assigned to runner1, picked up)
        $request2 = PartsRequest::create([
            'reference_number' => 'PR-20241219-0002',
            'request_type_id' => $transferType->id,
            'origin_location_id' => $locations[0]->id,
            'receiving_location_id' => $locations[1]->id,
            'requested_at' => now()->subHours(4),
            'requested_by_user_id' => $dispatcher->id,
            'pickup_run' => false,
            'urgency_id' => $todayUrgency->id,
            'status_id' => $pickedUpStatus->id,
            'details' => 'Transfer hydraulic fluid (5 gallons) and misc tools',
            'special_instructions' => 'Handle with care, fragile items included',
            'slack_notify_pickup' => false,
            'slack_notify_delivery' => true,
            'assigned_runner_user_id' => $runner1->id,
            'assigned_at' => now()->subHours(3),
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request2->id,
            'event_type' => 'created',
            'event_at' => now()->subHours(4),
            'user_id' => $dispatcher->id,
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request2->id,
            'event_type' => 'assigned',
            'event_at' => now()->subHours(3),
            'user_id' => $dispatcher->id,
            'notes' => 'Assigned to Mike Runner',
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request2->id,
            'event_type' => 'started',
            'event_at' => now()->subHours(2)->subMinutes(30),
            'user_id' => $runner1->id,
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request2->id,
            'event_type' => 'picked_up',
            'event_at' => now()->subHours(2),
            'user_id' => $runner1->id,
            'notes' => 'All items loaded',
        ]);

        // Request 3: Customer delivery (assigned to runner2, normal priority)
        $request3 = PartsRequest::create([
            'reference_number' => 'PR-20241219-0003',
            'request_type_id' => $deliveryType->id,
            'customer_name' => 'Johnson Farm Equipment',
            'customer_phone' => '(419) 555-0123',
            'customer_address' => '789 County Road 25A, St Marys, OH 45885',
            'customer_lat' => 40.5420,
            'customer_lng' => -84.3897,
            'origin_location_id' => $locations[0]->id,
            'requested_at' => now()->subHours(6),
            'requested_by_user_id' => $dispatcher->id,
            'pickup_run' => false,
            'urgency_id' => $normalUrgency->id,
            'status_id' => $assignedStatus->id,
            'details' => 'Replacement alternator for John Deere tractor',
            'special_instructions' => 'Get signature from owner',
            'slack_notify_pickup' => false,
            'slack_notify_delivery' => true,
            'slack_channel' => '#deliveries',
            'assigned_runner_user_id' => $runner2->id,
            'assigned_at' => now()->subHours(5),
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request3->id,
            'event_type' => 'created',
            'event_at' => now()->subHours(6),
            'user_id' => $dispatcher->id,
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request3->id,
            'event_type' => 'assigned',
            'event_at' => now()->subHours(5),
            'user_id' => $dispatcher->id,
            'notes' => 'Assigned to Sarah Driver',
        ]);

        // Request 4: Another pickup (ASAP)
        $request4 = PartsRequest::create([
            'reference_number' => 'PR-20241219-0004',
            'request_type_id' => $pickupType->id,
            'vendor_name' => 'Bearing Supply Inc',
            'origin_address' => '456 Industrial Pkwy, Celina, OH 45822',
            'origin_lat' => 40.5548,
            'origin_lng' => -84.5777,
            'receiving_location_id' => $locations[1]->id,
            'requested_at' => now()->subMinutes(30),
            'requested_by_user_id' => $dispatcher->id,
            'pickup_run' => true,
            'urgency_id' => $asapUrgency->id,
            'status_id' => $newStatus->id,
            'details' => 'Wheel bearings - order #78945',
            'special_instructions' => 'Already paid, just pick up',
            'slack_notify_pickup' => true,
            'slack_notify_delivery' => true,
        ]);

        PartsRequestEvent::create([
            'parts_request_id' => $request4->id,
            'event_type' => 'created',
            'event_at' => now()->subMinutes(30),
            'user_id' => $dispatcher->id,
        ]);

        $this->command->info('Sample parts requests created successfully!');
        $this->command->info('Dispatcher: dispatcher@example.com / password');
        $this->command->info('Runner 1: runner1@example.com / password');
        $this->command->info('Runner 2: runner2@example.com / password');
    }
}
