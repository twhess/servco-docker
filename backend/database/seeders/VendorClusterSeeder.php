<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VendorClusterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding vendor cluster locations...');

        $userId = DB::table('users')->where('email', 'like', '%admin%')->first()->id ?? 1;
        $now = Carbon::now();

        // Get vendor locations
        $vendors = DB::table('service_locations')->where('location_type', 'vendor')->get();

        if ($vendors->isEmpty()) {
            $this->command->warn('No vendor locations found. Please seed locations first.');
            return;
        }

        // Get route stops that are vendor clusters
        $vendorClusterStops = DB::table('route_stops')
            ->where('stop_type', 'VENDOR_CLUSTER')
            ->get();

        if ($vendorClusterStops->isEmpty()) {
            $this->command->warn('No vendor cluster stops found. Please seed routes first.');
            return;
        }

        // For each vendor cluster stop, assign vendors
        foreach ($vendorClusterStops as $clusterStop) {
            $route = DB::table('routes')->where('id', $clusterStop->route_id)->first();

            if (!$route) continue;

            // Determine which vendors to assign based on route name
            $vendorsForCluster = [];

            if (stripos($route->name, 'North') !== false) {
                // Assign first half of vendors to North route
                $vendorsForCluster = $vendors->take(ceil($vendors->count() / 2));
            } elseif (stripos($route->name, 'South') !== false) {
                // Assign second half to South route
                $vendorsForCluster = $vendors->skip(ceil($vendors->count() / 2));
            } elseif (stripos($route->name, 'Express') !== false) {
                // Assign a few nearby vendors for express
                $vendorsForCluster = $vendors->take(min(3, $vendors->count()));
            } else {
                // Default: assign all vendors
                $vendorsForCluster = $vendors;
            }

            // Insert vendor cluster locations
            $order = 1;
            foreach ($vendorsForCluster as $vendor) {
                DB::table('vendor_cluster_locations')->insertOrIgnore([
                    'route_stop_id' => $clusterStop->id,
                    'location_id' => $vendor->id,
                    'location_order' => 0, // 0 = route optimization allowed
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $order++;
            }

            $this->command->info("Assigned {$vendorsForCluster->count()} vendors to cluster: {$route->name}");
        }

        $this->command->info('Vendor cluster locations seeded successfully!');
    }
}
