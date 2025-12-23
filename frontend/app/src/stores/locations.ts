import { defineStore } from 'pinia';
import { api } from 'src/boot/axios';
import { Notify } from 'quasar';

export interface ServiceLocation {
  id: number;
  name: string;
  code: string | null;
  location_type: 'fixed_shop' | 'mobile_service_truck' | 'parts_runner_vehicle' | 'vendor' | 'customer_site';
  status: 'available' | 'on_job' | 'on_run' | 'offline' | 'maintenance' | null;
  is_active: boolean;
  timezone: string | null;
  notes: string | null;
  text_color: string | null;
  background_color: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  latitude: number | null;
  longitude: number | null;
  vehicle_asset_id: number | null;
  home_base_location_id: number | null;
  assigned_user_id: number | null;
  last_known_lat: number | null;
  last_known_lng: number | null;
  last_known_at: string | null;
  is_dispatchable: boolean;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  home_base?: ServiceLocation;
  assigned_user?: {
    id: number;
    username: string;
    first_name: string;
    last_name: string;
  };
  phones?: ServiceLocationPhone[];
  emails?: ServiceLocationEmail[];
}

export interface ServiceLocationPhone {
  id: number;
  service_location_id: number;
  label: string;
  phone_number: string;
  extension: string | null;
  is_primary: boolean;
  is_public: boolean;
  created_at: string;
  updated_at: string;
}

export interface ServiceLocationEmail {
  id: number;
  service_location_id: number;
  label: string;
  email: string;
  is_primary: boolean;
  is_public: boolean;
  created_at: string;
  updated_at: string;
}

export interface LocationPosition {
  id: number;
  service_location_id: number;
  lat: number;
  lng: number;
  accuracy_meters: number | null;
  speed: number | null;
  heading: number | null;
  recorded_at: string;
  source: 'gps' | 'manual' | 'geofence';
  created_at: string;
  updated_at: string;
}

export const useLocationsStore = defineStore('locations', {
  state: () => ({
    locations: [] as ServiceLocation[],
    currentLocation: null as ServiceLocation | null,
    loading: false,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  }),

  actions: {
    async fetchLocations(params: {
      page?: number;
      per_page?: number;
      type?: string | string[];
      status?: string;
      active?: boolean;
      search?: string;
      my_locations_only?: boolean;
    } = {}) {
      this.loading = true;
      try {
        const response = await api.get('/locations', { params });
        this.locations = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
        };
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load locations',
        });
      } finally {
        this.loading = false;
      }
    },

    async fetchLocation(id: number) {
      this.loading = true;
      try {
        const response = await api.get(`/locations/${id}`);
        this.currentLocation = response.data;
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load location',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createLocation(data: Partial<ServiceLocation>) {
      this.loading = true;
      try {
        const response = await api.post('/locations', data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Location created successfully',
        });
        return response.data.location;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to create location',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateLocation(id: number, data: Partial<ServiceLocation>) {
      this.loading = true;
      try {
        const response = await api.put(`/locations/${id}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Location updated successfully',
        });
        if (this.currentLocation?.id === id) {
          this.currentLocation = response.data.location;
        }
        return response.data.location;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update location',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async assignUser(locationId: number, userId: number | null) {
      try {
        const response = await api.post(`/locations/${locationId}/assign-user`, {
          assigned_user_id: userId,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'User assigned successfully',
        });
        if (this.currentLocation?.id === locationId) {
          this.currentLocation = response.data.location;
        }
        return response.data.location;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to assign user',
        });
        throw error;
      }
    },

    async updateStatus(locationId: number, status: string) {
      try {
        const response = await api.post(`/locations/${locationId}/status`, { status });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Status updated successfully',
        });
        if (this.currentLocation?.id === locationId) {
          this.currentLocation.status = status as any;
        }
        return response.data.location;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update status',
        });
        throw error;
      }
    },

    async recordPosition(locationId: number, data: {
      lat: number;
      lng: number;
      accuracy_meters?: number;
      speed?: number;
      heading?: number;
      source?: 'gps' | 'manual' | 'geofence';
    }) {
      try {
        const response = await api.post(`/locations/${locationId}/position`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Position recorded successfully',
        });
        return response.data.position;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to record position',
        });
        throw error;
      }
    },

    async fetchPositionHistory(locationId: number, limit = 50) {
      try {
        const response = await api.get(`/locations/${locationId}/position-history`, {
          params: { limit },
        });
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load position history',
        });
        throw error;
      }
    },

    async addPhone(locationId: number, data: Partial<ServiceLocationPhone>) {
      try {
        const response = await api.post(`/locations/${locationId}/phones`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Phone added successfully',
        });
        if (this.currentLocation?.id === locationId) {
          this.currentLocation.phones?.push(response.data.phone);
        }
        return response.data.phone;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to add phone',
        });
        throw error;
      }
    },

    async updatePhone(locationId: number, phoneId: number, data: Partial<ServiceLocationPhone>) {
      try {
        const response = await api.put(`/locations/${locationId}/phones/${phoneId}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Phone updated successfully',
        });
        if (this.currentLocation?.id === locationId && this.currentLocation.phones) {
          const index = this.currentLocation.phones.findIndex(p => p.id === phoneId);
          if (index !== -1) {
            this.currentLocation.phones[index] = response.data.phone;
          }
        }
        return response.data.phone;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update phone',
        });
        throw error;
      }
    },

    async deletePhone(locationId: number, phoneId: number) {
      try {
        const response = await api.delete(`/locations/${locationId}/phones/${phoneId}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Phone deleted successfully',
        });
        if (this.currentLocation?.id === locationId && this.currentLocation.phones) {
          this.currentLocation.phones = this.currentLocation.phones.filter(p => p.id !== phoneId);
        }
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete phone',
        });
        throw error;
      }
    },

    async addEmail(locationId: number, data: Partial<ServiceLocationEmail>) {
      try {
        const response = await api.post(`/locations/${locationId}/emails`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Email added successfully',
        });
        if (this.currentLocation?.id === locationId) {
          this.currentLocation.emails?.push(response.data.email);
        }
        return response.data.email;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to add email',
        });
        throw error;
      }
    },

    async updateEmail(locationId: number, emailId: number, data: Partial<ServiceLocationEmail>) {
      try {
        const response = await api.put(`/locations/${locationId}/emails/${emailId}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Email updated successfully',
        });
        if (this.currentLocation?.id === locationId && this.currentLocation.emails) {
          const index = this.currentLocation.emails.findIndex(e => e.id === emailId);
          if (index !== -1) {
            this.currentLocation.emails[index] = response.data.email;
          }
        }
        return response.data.email;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update email',
        });
        throw error;
      }
    },

    async deleteEmail(locationId: number, emailId: number) {
      try {
        const response = await api.delete(`/locations/${locationId}/emails/${emailId}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Email deleted successfully',
        });
        if (this.currentLocation?.id === locationId && this.currentLocation.emails) {
          this.currentLocation.emails = this.currentLocation.emails.filter(e => e.id !== emailId);
        }
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete email',
        });
        throw error;
      }
    },

    async deleteLocation(id: number) {
      try {
        const response = await api.delete(`/locations/${id}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Location deleted successfully',
        });
        this.locations = this.locations.filter(l => l.id !== id);
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete location',
        });
        throw error;
      }
    },

    async restoreLocation(id: number) {
      try {
        const response = await api.post(`/locations/${id}/restore`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Location restored successfully',
        });
        return response.data.location;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to restore location',
        });
        throw error;
      }
    },
  },
});
