import { defineStore } from 'pinia';
import { api } from 'boot/axios';
import { Notify } from 'quasar';

export interface PartsRequest {
  id: number;
  reference_number: string;
  request_type: { id: number; name: string };
  status: { id: number; name: string };
  urgency: { id: number; name: string };
  vendor_name: string | null;
  customer_name: string | null;
  customer_phone: string | null;
  customer_address: string | null;
  origin_location: any;
  origin_area: any;
  origin_address: string | null;
  receiving_location: any;
  receiving_area: any;
  requested_at: string;
  requested_by: { id: number; name: string };
  assigned_runner: { id: number; name: string } | null;
  details: string;
  special_instructions: string | null;
  pickup_run: boolean;
  slack_notify_pickup: boolean;
  slack_notify_delivery: boolean;
  created_at: string;
  updated_at: string;
}

export interface PartsRequestEvent {
  id: number;
  event_type: string;
  event_at: string;
  user: { id: number; name: string } | null;
  notes: string | null;
}

export interface PartsRequestPhoto {
  id: number;
  stage: 'pickup' | 'delivery' | 'other';
  url: string;
  taken_at: string;
  taken_by: string;
  lat: number | null;
  lng: number | null;
  notes: string | null;
}

export const usePartsRequestsStore = defineStore('partsRequests', {
  state: () => ({
    requests: [] as PartsRequest[],
    myJobs: [] as PartsRequest[],
    currentRequest: null as PartsRequest | null,
    loading: false,
    lookups: {
      request_types: [] as any[],
      statuses: [] as any[],
      urgency_levels: [] as any[],
    },
  }),

  actions: {
    async fetchLookups() {
      try {
        const response = await api.get('/parts-requests/lookups');
        this.lookups = response.data;
      } catch (error: any) {
        console.error('Failed to load lookups', error);
      }
    },

    async fetchRequests(params: any = {}) {
      this.loading = true;
      try {
        const response = await api.get('/parts-requests', { params });
        this.requests = response.data.data;
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load parts requests',
        });
      } finally {
        this.loading = false;
      }
    },

    async fetchMyJobs() {
      this.loading = true;
      try {
        const response = await api.get('/parts-requests/my-jobs');
        this.myJobs = response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load jobs',
        });
      } finally {
        this.loading = false;
      }
    },

    async fetchRequest(id: number) {
      this.loading = true;
      try {
        const response = await api.get(`/parts-requests/${id}`);
        this.currentRequest = response.data;
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load request',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createRequest(data: any) {
      this.loading = true;
      try {
        const response = await api.post('/parts-requests', data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Request created successfully',
        });
        return response.data.parts_request;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to create request',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateRequest(id: number, data: any) {
      this.loading = true;
      try {
        const response = await api.put(`/parts-requests/${id}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Request updated successfully',
        });
        if (this.currentRequest?.id === id) {
          this.currentRequest = response.data.parts_request;
        }
        return response.data.parts_request;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update request',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async assignRunner(requestId: number, runnerId: number) {
      try {
        const response = await api.post(`/parts-requests/${requestId}/assign`, {
          assigned_runner_user_id: runnerId,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Runner assigned successfully',
        });
        return response.data.parts_request;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to assign runner',
        });
        throw error;
      }
    },

    async unassignRunner(requestId: number) {
      try {
        const response = await api.post(`/parts-requests/${requestId}/unassign`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Runner unassigned successfully',
        });
        return response.data.parts_request;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to unassign runner',
        });
        throw error;
      }
    },

    async addEvent(requestId: number, eventType: string, notes?: string) {
      try {
        const response = await api.post(`/parts-requests/${requestId}/events`, {
          event_type: eventType,
          notes,
        });
        Notify.create({
          type: 'positive',
          message: 'Event added successfully',
        });
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to add event',
        });
        throw error;
      }
    },

    async fetchTimeline(requestId: number): Promise<PartsRequestEvent[]> {
      try {
        const response = await api.get(`/parts-requests/${requestId}/timeline`);
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load timeline',
        });
        throw error;
      }
    },

    async uploadPhoto(requestId: number, formData: FormData) {
      try {
        const response = await api.post(`/parts-requests/${requestId}/photos`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Photo uploaded successfully',
        });
        return response.data.photo;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to upload photo',
        });
        throw error;
      }
    },

    async fetchPhotos(requestId: number): Promise<PartsRequestPhoto[]> {
      try {
        const response = await api.get(`/parts-requests/${requestId}/photos`);
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load photos',
        });
        throw error;
      }
    },

    async postLocation(requestId: number, location: any) {
      try {
        const response = await api.post(`/parts-requests/${requestId}/location`, location);
        return response.data.location;
      } catch (error: any) {
        console.error('Failed to post location', error);
        throw error;
      }
    },

    async fetchTracking(requestId: number) {
      try {
        const response = await api.get(`/parts-requests/${requestId}/tracking`);
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load tracking',
        });
        throw error;
      }
    },

    async deleteRequest(id: number) {
      try {
        const response = await api.delete(`/parts-requests/${id}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Request deleted successfully',
        });
        this.requests = this.requests.filter(r => r.id !== id);
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete request',
        });
        throw error;
      }
    },
  },
});
