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
  vendor_id: number | null;
  vendor?: { id: number; name: string; phone?: string | null } | null;
  vendor_address_id: number | null;
  vendor_address?: { id: number; line1: string; city: string; state: string; one_line_address?: string; instructions?: string | null } | null;
  customer_id: number | null;
  customer?: { id: number; formatted_name: string; company_name: string; phone?: string | null } | null;
  customer_address_id: number | null;
  customer_address_obj?: { id: number; line1: string; city: string; state: string; one_line_address?: string; phone?: string | null } | null;
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
  // Parts Runner routing fields
  run_instance_id: number | null;
  run_instance?: any;
  pickup_stop_id: number | null;
  pickup_stop?: any;
  dropoff_stop_id: number | null;
  dropoff_stop?: any;
  parent_request_id: number | null;
  segment_order: number | null;
  is_segment: boolean;
  item_id: number | null;
  item?: any;
  scheduled_for_date: string | null;
  not_before_datetime: string | null;
  override_run_instance_id: number | null;
  override_reason: string | null;
  override_by_user_id: number | null;
  override_at: string | null;
  is_archived: boolean;
  archived_at: string | null;
  // Line items
  items?: PartsRequestItem[];
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

export interface PartsRequestItem {
  id: number;
  parts_request_id: number;
  description: string;
  quantity: number;
  part_number: string | null;
  notes: string | null;
  is_verified: boolean;
  verified_by_user_id: number | null;
  verified_by?: { id: number; name: string } | null;
  verified_at: string | null;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export interface PartsRequestDocument {
  id: number;
  parts_request_id: number;
  original_filename: string;
  stored_filename: string;
  file_path: string;
  mime_type: string;
  file_size: number;
  formatted_file_size: string;
  description: string | null;
  uploaded_by_user_id: number | null;
  uploaded_by?: { id: number; name: string } | null;
  uploaded_at: string;
  url: string;
  created_at: string;
  updated_at: string;
}

export interface PartsRequestNote {
  id: number;
  parts_request_id: number;
  content: string;
  user_id: number;
  user?: { id: number; name: string } | null;
  is_edited: boolean;
  edited_at: string | null;
  can_edit: boolean;
  can_delete: boolean;
  created_at: string;
  updated_at: string;
}

export type ImageSource = 'requester' | 'pickup' | 'delivery';

export interface PartsRequestImage {
  id: number;
  parts_request_id: number;
  source: ImageSource;
  original_filename: string;
  stored_filename: string;
  file_path: string;
  thumbnail_path: string | null;
  mime_type: string;
  file_size: number;
  original_size: number | null;
  formatted_file_size: string;
  width: number | null;
  height: number | null;
  caption: string | null;
  latitude: number | null;
  longitude: number | null;
  taken_at: string | null;
  uploaded_by_user_id: number | null;
  uploaded_by?: { id: number; name: string } | null;
  uploaded_at: string;
  url: string;
  thumbnail_url: string | null;
  created_at: string;
  updated_at: string;
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

    // ==========================================
    // PARTS RUNNER ROUTING ACTIONS (NEW)
    // ==========================================

    async executeAction(requestId: number, action: string, data: any) {
      this.loading = true;
      try {
        const response = await api.post(`/parts-requests/${requestId}/actions/${action}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Action executed successfully',
        });
        if (this.currentRequest?.id === requestId) {
          this.currentRequest = response.data.data;
        }
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to execute action',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchAvailableActions(requestId: number) {
      try {
        const response = await api.get(`/parts-requests/${requestId}/available-actions`);
        return response.data.data;
      } catch (error: any) {
        console.error('Failed to load available actions', error);
        return [];
      }
    },

    async assignToRun(requestId: number, runId: number, pickupStopId: number, dropoffStopId: number, overrideReason?: string) {
      this.loading = true;
      try {
        const response = await api.post(`/parts-requests/${requestId}/assign-to-run`, {
          run_instance_id: runId,
          pickup_stop_id: pickupStopId,
          dropoff_stop_id: dropoffStopId,
          override_reason: overrideReason,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Assigned to run successfully',
        });
        if (this.currentRequest?.id === requestId) {
          this.currentRequest = response.data.data;
        }
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to assign to run',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchSegments(requestId: number) {
      try {
        const response = await api.get(`/parts-requests/${requestId}/segments`);
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load segments',
        });
        throw error;
      }
    },

    async fetchNeedsStaging(locationId?: number) {
      this.loading = true;
      try {
        const params = locationId ? { location_id: locationId } : {};
        const response = await api.get('/parts-requests/needs-staging', { params });
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load staging requests',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchFeed(filters: any = {}) {
      this.loading = true;
      try {
        const response = await api.get('/parts-requests/feed', { params: filters });
        this.requests = response.data.data;
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load feed',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async linkItem(requestId: number, itemId: number) {
      this.loading = true;
      try {
        const response = await api.post(`/parts-requests/${requestId}/link-item`, {
          item_id: itemId,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Item linked successfully',
        });
        if (this.currentRequest?.id === requestId) {
          this.currentRequest = response.data.data;
        }
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to link item',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchScheduled() {
      this.loading = true;
      try {
        const response = await api.get('/parts-requests/scheduled');
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load scheduled requests',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async bulkSchedule(requests: any[]) {
      this.loading = true;
      try {
        const response = await api.post('/parts-requests/bulk-schedule', {
          requests,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Requests scheduled successfully',
        });
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to schedule requests',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    // ==========================================
    // LINE ITEMS ACTIONS
    // ==========================================

    async fetchItems(requestId: number): Promise<PartsRequestItem[]> {
      try {
        const response = await api.get(`/parts-requests/${requestId}/items`);
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load items',
        });
        throw error;
      }
    },

    async addItem(requestId: number, item: { description: string; quantity: number; part_number?: string | undefined; notes?: string | undefined }) {
      try {
        const response = await api.post(`/parts-requests/${requestId}/items`, item);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Item added successfully',
        });
        return response.data.data as PartsRequestItem;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to add item',
        });
        throw error;
      }
    },

    async updateItem(requestId: number, itemId: number, data: Partial<PartsRequestItem>) {
      try {
        const response = await api.put(`/parts-requests/${requestId}/items/${itemId}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Item updated successfully',
        });
        return response.data.data as PartsRequestItem;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update item',
        });
        throw error;
      }
    },

    async removeItem(requestId: number, itemId: number) {
      try {
        const response = await api.delete(`/parts-requests/${requestId}/items/${itemId}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Item removed successfully',
        });
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to remove item',
        });
        throw error;
      }
    },

    async verifyItem(requestId: number, itemId: number): Promise<PartsRequestItem> {
      try {
        const response = await api.post(`/parts-requests/${requestId}/items/${itemId}/verify`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Item verified successfully',
        });
        return response.data.data as PartsRequestItem;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to verify item',
        });
        throw error;
      }
    },

    async unverifyItem(requestId: number, itemId: number): Promise<PartsRequestItem> {
      try {
        const response = await api.post(`/parts-requests/${requestId}/items/${itemId}/unverify`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Item unverified',
        });
        return response.data.data as PartsRequestItem;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to unverify item',
        });
        throw error;
      }
    },

    async reorderItems(requestId: number, itemIds: number[]) {
      try {
        const response = await api.put(`/parts-requests/${requestId}/items/reorder`, {
          item_ids: itemIds,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Items reordered successfully',
        });
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to reorder items',
        });
        throw error;
      }
    },

    // ==========================================
    // DOCUMENT ACTIONS
    // ==========================================

    async fetchDocuments(requestId: number): Promise<PartsRequestDocument[]> {
      try {
        const response = await api.get(`/parts-requests/${requestId}/documents`);
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load documents',
        });
        throw error;
      }
    },

    async uploadDocument(requestId: number, file: File, description?: string): Promise<PartsRequestDocument> {
      const formData = new FormData();
      formData.append('file', file);
      if (description) {
        formData.append('description', description);
      }

      try {
        const response = await api.post(`/parts-requests/${requestId}/documents`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Document uploaded successfully',
        });
        return response.data.data as PartsRequestDocument;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to upload document',
        });
        throw error;
      }
    },

    async updateDocument(requestId: number, documentId: number, description: string | null): Promise<PartsRequestDocument> {
      try {
        const response = await api.put(`/parts-requests/${requestId}/documents/${documentId}`, {
          description,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Document updated successfully',
        });
        return response.data.data as PartsRequestDocument;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update document',
        });
        throw error;
      }
    },

    async deleteDocument(requestId: number, documentId: number): Promise<void> {
      try {
        const response = await api.delete(`/parts-requests/${requestId}/documents/${documentId}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Document deleted successfully',
        });
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete document',
        });
        throw error;
      }
    },

    getDocumentDownloadUrl(requestId: number, documentId: number): string {
      return `${api.defaults.baseURL}/parts-requests/${requestId}/documents/${documentId}/download`;
    },

    // ==========================================
    // IMAGE ACTIONS
    // ==========================================

    async fetchImages(requestId: number, source?: ImageSource): Promise<PartsRequestImage[]> {
      try {
        const params = source ? { source } : {};
        const response = await api.get(`/parts-requests/${requestId}/images`, { params });
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load images',
        });
        throw error;
      }
    },

    async uploadImage(
      requestId: number,
      file: File,
      options?: { source?: ImageSource; caption?: string; latitude?: number; longitude?: number }
    ): Promise<PartsRequestImage> {
      const formData = new FormData();
      formData.append('image', file);
      if (options?.source) {
        formData.append('source', options.source);
      }
      if (options?.caption) {
        formData.append('caption', options.caption);
      }
      if (options?.latitude !== undefined) {
        formData.append('latitude', String(options.latitude));
      }
      if (options?.longitude !== undefined) {
        formData.append('longitude', String(options.longitude));
      }

      try {
        const response = await api.post(`/parts-requests/${requestId}/images`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Image uploaded successfully',
        });
        return response.data.data as PartsRequestImage;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to upload image',
        });
        throw error;
      }
    },

    async updateImage(requestId: number, imageId: number, caption: string | null): Promise<PartsRequestImage> {
      try {
        const response = await api.put(`/parts-requests/${requestId}/images/${imageId}`, {
          caption,
        });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Image updated successfully',
        });
        return response.data.data as PartsRequestImage;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update image',
        });
        throw error;
      }
    },

    async deleteImage(requestId: number, imageId: number): Promise<void> {
      try {
        const response = await api.delete(`/parts-requests/${requestId}/images/${imageId}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Image deleted successfully',
        });
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete image',
        });
        throw error;
      }
    },

    getImageUrl(requestId: number, imageId: number): string {
      return `${api.defaults.baseURL}/parts-requests/${requestId}/images/${imageId}`;
    },

    getImageThumbnailUrl(requestId: number, imageId: number): string {
      return `${api.defaults.baseURL}/parts-requests/${requestId}/images/${imageId}/thumbnail`;
    },

    // ==========================================
    // NOTES ACTIONS
    // ==========================================

    async fetchNotes(requestId: number): Promise<PartsRequestNote[]> {
      try {
        const response = await api.get(`/parts-requests/${requestId}/notes`);
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load notes',
        });
        throw error;
      }
    },

    async createNote(requestId: number, content: string): Promise<PartsRequestNote> {
      try {
        const response = await api.post(`/parts-requests/${requestId}/notes`, { content });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Note added successfully',
        });
        return response.data.data as PartsRequestNote;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to add note',
        });
        throw error;
      }
    },

    async updateNote(requestId: number, noteId: number, content: string): Promise<PartsRequestNote> {
      try {
        const response = await api.put(`/parts-requests/${requestId}/notes/${noteId}`, { content });
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Note updated successfully',
        });
        return response.data.data as PartsRequestNote;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update note',
        });
        throw error;
      }
    },

    async deleteNote(requestId: number, noteId: number): Promise<void> {
      try {
        const response = await api.delete(`/parts-requests/${requestId}/notes/${noteId}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Note deleted successfully',
        });
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete note',
        });
        throw error;
      }
    },
  },
});
