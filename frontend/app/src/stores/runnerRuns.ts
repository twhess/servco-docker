import { defineStore } from 'pinia';
import { api } from 'boot/axios';
import type { AxiosError } from 'axios';

interface RunVehicle {
  id: number;
  name: string;
}

interface RunStop {
  id: number;
  location_id: number;
  location_name: string;
  stop_order: number;
  latitude: number | null;
  longitude: number | null;
  geofence_radius_m: number;
  total_items: number;
  open_items: number;
  completed_items: number;
}

interface Run {
  id: number;
  display_name: string;
  route_id: number;
  route_name: string;
  scheduled_date: string;
  scheduled_time: string | null;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  stop_count: number;
  total_items: number;
  open_items: number;
  current_stop_id: number | null;
  vehicle: RunVehicle | null;
}

interface RunDetail extends Run {
  actual_start_at: string | null;
}

interface RunnerRunsState {
  assignedRuns: Run[];
  availableRuns: Run[];
  currentRun: RunDetail | null;
  currentRunStops: RunStop[];
  loading: boolean;
  error: string | null;
  selectedDate: string;
}

interface ApiErrorResponse {
  message?: string;
}

export const useRunnerRunsStore = defineStore('runnerRuns', {
  state: (): RunnerRunsState => ({
    assignedRuns: [],
    availableRuns: [],
    currentRun: null,
    currentRunStops: [],
    loading: false,
    error: null,
    selectedDate: new Date().toISOString().split('T')[0],
  }),

  getters: {
    hasAssignedRuns: (state) => state.assignedRuns.length > 0,
    hasAvailableRuns: (state) => state.availableRuns.length > 0,
    activeRun: (state) =>
      state.assignedRuns.find((r) => r.status === 'in_progress') ?? null,
    pendingRuns: (state) =>
      state.assignedRuns.filter((r) => r.status === 'pending'),
    totalOpenItems: (state) =>
      state.assignedRuns.reduce((sum, r) => sum + r.open_items, 0),
  },

  actions: {
    /**
     * Fetch runs for the selected date.
     */
    async fetchRuns(date?: string) {
      this.loading = true;
      this.error = null;

      try {
        const targetDate = date || this.selectedDate;
        const response = await api.get('/runner/runs', {
          params: { date: targetDate },
        });

        this.assignedRuns = response.data.assigned || [];
        this.availableRuns = response.data.available || [];
        this.selectedDate = response.data.date;

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error = axiosError.response?.data?.message || 'Failed to load runs';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Fetch details of a specific run.
     */
    async fetchRunDetails(runId: number) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get(`/runner/runs/${runId}`);

        this.currentRun = response.data.run;
        this.currentRunStops = response.data.stops || [];

        return { success: true, data: response.data };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to load run details';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Claim an available run.
     */
    async claimRun(runId: number) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post(`/runner/runs/${runId}/claim`);

        // Move from available to assigned
        const claimedRun = response.data.run;
        this.availableRuns = this.availableRuns.filter((r) => r.id !== runId);
        this.assignedRuns.push(claimedRun);

        return { success: true, run: claimedRun };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error = axiosError.response?.data?.message || 'Failed to claim run';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Start a run.
     */
    async startRun(runId: number) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post(`/runner/runs/${runId}/start`);

        // Update local state
        const run = this.assignedRuns.find((r) => r.id === runId);
        if (run) {
          run.status = 'in_progress';
        }
        if (this.currentRun?.id === runId) {
          this.currentRun.status = 'in_progress';
          this.currentRun.actual_start_at = response.data.run.actual_start_at;
        }

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error = axiosError.response?.data?.message || 'Failed to start run';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Complete a run.
     */
    async completeRun(runId: number, force = false) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post(`/runner/runs/${runId}/complete`, {
          force,
        });

        // Update local state
        const run = this.assignedRuns.find((r) => r.id === runId);
        if (run) {
          run.status = 'completed';
        }
        if (this.currentRun?.id === runId) {
          this.currentRun.status = 'completed';
        }

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<{ message: string; open_items?: number; force_available?: boolean }>;
        const data = axiosError.response?.data;

        if (data?.force_available) {
          return {
            success: false,
            error: data.message,
            openItems: data.open_items,
            canForce: true,
          };
        }

        this.error = data?.message || 'Failed to complete run';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Update current stop (manual selection).
     */
    async updateCurrentStop(runId: number, stopId: number) {
      try {
        await api.put(`/runner/runs/${runId}/current-stop`, {
          stop_id: stopId,
        });

        // Update local state
        const run = this.assignedRuns.find((r) => r.id === runId);
        if (run) {
          run.current_stop_id = stopId;
        }
        if (this.currentRun?.id === runId) {
          this.currentRun.current_stop_id = stopId;
        }

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        return {
          success: false,
          error: axiosError.response?.data?.message || 'Failed to update stop',
        };
      }
    },

    /**
     * Clear current run selection.
     */
    clearCurrentRun() {
      this.currentRun = null;
      this.currentRunStops = [];
    },

    /**
     * Set selected date.
     */
    setDate(date: string) {
      this.selectedDate = date;
    },
  },
});
