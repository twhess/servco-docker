import { defineStore } from 'pinia';
import { api } from 'boot/axios';
import type { AxiosError } from 'axios';

interface NearestStop {
  stop_id: number;
  location_id: number;
  location_name: string;
  distance_m: number;
  inside: boolean;
  radius_m: number;
}

interface StopDistance {
  stop_id: number;
  location_id: number;
  location_name: string;
  stop_order: number;
  distance_m: number | null;
  inside: boolean;
  radius_m: number;
  has_coordinates: boolean;
}

interface OpenItem {
  id: number;
  reference_number: string;
  status: string;
  action_at_stop: 'pickup' | 'dropoff';
}

interface ExitCheckResult {
  exited: boolean;
  stop_id: number;
  stop_name: string;
  open_items_count: number;
  open_items: OpenItem[];
  alert_sent?: boolean;
}

interface RunnerLocationState {
  currentPosition: { lat: number; lng: number } | null;
  accuracy: number | null;
  nearestStop: NearestStop | null;
  allStops: StopDistance[];
  watching: boolean;
  watchId: number | null;
  lastRecordedAt: string | null;
  loading: boolean;
  error: string | null;
  pollInterval: number | null;
}

interface ApiErrorResponse {
  message?: string;
}

const POLL_INTERVAL_MS = 15000; // 15 seconds

export const useRunnerLocationStore = defineStore('runnerLocation', {
  state: (): RunnerLocationState => ({
    currentPosition: null,
    accuracy: null,
    nearestStop: null,
    allStops: [],
    watching: false,
    watchId: null,
    lastRecordedAt: null,
    loading: false,
    error: null,
    pollInterval: null,
  }),

  getters: {
    isInsideStop: (state) => state.nearestStop?.inside ?? false,
    currentStopId: (state) =>
      state.nearestStop?.inside ? state.nearestStop.stop_id : null,
    currentStopName: (state) =>
      state.nearestStop?.inside ? state.nearestStop.location_name : null,
    distanceToNearestStop: (state) => state.nearestStop?.distance_m ?? null,
  },

  actions: {
    /**
     * Start watching location and reporting to server.
     */
    startWatching(runId: number) {
      if (this.watching) {
        return;
      }

      if (!navigator.geolocation) {
        this.error = 'Geolocation is not supported by your browser';
        return;
      }

      this.watching = true;
      this.error = null;

      // Watch position changes
      this.watchId = navigator.geolocation.watchPosition(
        (position) => {
          this.currentPosition = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          };
          this.accuracy = position.coords.accuracy;
        },
        (error) => {
          console.error('Geolocation error:', error);
          this.error = this.getGeolocationErrorMessage(error);
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 5000,
        }
      );

      // Start polling to report location
      this.pollInterval = window.setInterval(() => {
        if (this.currentPosition) {
          void this.reportLocation(runId);
        }
      }, POLL_INTERVAL_MS);

      // Report immediately if we have a position
      if (this.currentPosition) {
        void this.reportLocation(runId);
      }
    },

    /**
     * Stop watching location.
     */
    stopWatching() {
      if (this.watchId !== null) {
        navigator.geolocation.clearWatch(this.watchId);
        this.watchId = null;
      }

      if (this.pollInterval !== null) {
        clearInterval(this.pollInterval);
        this.pollInterval = null;
      }

      this.watching = false;
    },

    /**
     * Report current location to server.
     */
    async reportLocation(runId: number) {
      if (!this.currentPosition) {
        return { success: false, error: 'No position available' };
      }

      try {
        const response = await api.post('/runner/location', {
          lat: this.currentPosition.lat,
          lng: this.currentPosition.lng,
          accuracy_m: this.accuracy ? Math.round(this.accuracy) : null,
          run_id: runId,
        });

        this.lastRecordedAt = new Date().toISOString();

        if (response.data.nearest_stop) {
          this.nearestStop = response.data.nearest_stop;
        }

        if (response.data.all_stops) {
          this.allStops = response.data.all_stops;
        }

        return {
          success: true,
          stopAutoSelected: response.data.stop_auto_selected ?? false,
        };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        console.error('Failed to report location:', error);
        return {
          success: false,
          error: axiosError.response?.data?.message || 'Failed to report location',
        };
      }
    },

    /**
     * Check for open items when exiting a stop.
     */
    async checkExit(stopId: number, runId: number): Promise<ExitCheckResult | null> {
      if (!this.currentPosition) {
        return null;
      }

      this.loading = true;

      try {
        const response = await api.post(`/runner/stops/${stopId}/exit-check`, {
          lat: this.currentPosition.lat,
          lng: this.currentPosition.lng,
          run_id: runId,
        });

        return response.data as ExitCheckResult;
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to check exit';
        return null;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Get current geofence status for all stops.
     */
    async getGeofenceStatus(runId: number) {
      if (!this.currentPosition) {
        return { success: false, error: 'No position available' };
      }

      this.loading = true;

      try {
        const response = await api.post('/runner/location/geofence-status', {
          lat: this.currentPosition.lat,
          lng: this.currentPosition.lng,
          run_id: runId,
        });

        this.allStops = response.data.stops || [];

        if (response.data.current_stop) {
          // We're inside a stop
          const currentStop = response.data.current_stop;
          const stopData = this.allStops.find(
            (s) => s.stop_id === currentStop.stop_id
          );
          if (stopData) {
            this.nearestStop = {
              stop_id: stopData.stop_id,
              location_id: stopData.location_id,
              location_name: stopData.location_name,
              distance_m: stopData.distance_m ?? 0,
              inside: true,
              radius_m: stopData.radius_m,
            };
          }
        }

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to get geofence status';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Get a single position reading.
     */
    async getCurrentPosition(): Promise<GeolocationPosition | null> {
      return new Promise((resolve) => {
        if (!navigator.geolocation) {
          this.error = 'Geolocation is not supported';
          resolve(null);
          return;
        }

        navigator.geolocation.getCurrentPosition(
          (position) => {
            this.currentPosition = {
              lat: position.coords.latitude,
              lng: position.coords.longitude,
            };
            this.accuracy = position.coords.accuracy;
            resolve(position);
          },
          (error) => {
            this.error = this.getGeolocationErrorMessage(error);
            resolve(null);
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
          }
        );
      });
    },

    /**
     * Get human-readable geolocation error message.
     */
    getGeolocationErrorMessage(error: GeolocationPositionError): string {
      switch (error.code) {
        case error.PERMISSION_DENIED:
          return 'Location permission denied. Please enable location access.';
        case error.POSITION_UNAVAILABLE:
          return 'Location information is unavailable.';
        case error.TIMEOUT:
          return 'Location request timed out.';
        default:
          return 'An unknown location error occurred.';
      }
    },

    /**
     * Clear location state.
     */
    clearLocation() {
      this.stopWatching();
      this.currentPosition = null;
      this.accuracy = null;
      this.nearestStop = null;
      this.allStops = [];
      this.lastRecordedAt = null;
      this.error = null;
    },
  },
});
