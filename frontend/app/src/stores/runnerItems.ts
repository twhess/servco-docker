import { defineStore } from 'pinia';
import { api } from 'boot/axios';
import type { AxiosError } from 'axios';

interface ItemStatus {
  id: number;
  name: string;
  display_name: string;
  color: string;
}

interface ItemUrgency {
  id: number;
  name: string;
  color: string;
}

interface ItemLocation {
  location_id: number | null;
  location_name: string | null;
  stop_id: number | null;
  stop_name: string | null;
}

interface ItemPhoto {
  id: number;
  type: 'pickup' | 'delivery' | 'exception' | 'other';
  url: string;
  taken_at: string;
}

interface LineItem {
  id: number;
  description: string | null;
  quantity: number;
  part_number: string | null;
  notes: string | null;
  is_verified: boolean;
}

interface Document {
  id: number;
  original_filename: string;
  description: string | null;
  url: string;
  mime_type: string;
  formatted_file_size: string;
  icon: string;
  is_previewable: boolean;
}

interface Note {
  id: number;
  content: string;
  user_name: string;
  created_at: string;
  is_edited: boolean;
}

interface RunnerItem {
  id: number;
  reference_number: string;
  status: ItemStatus;
  urgency: ItemUrgency | null;
  details: string | null;
  special_instructions: string | null;
  origin: ItemLocation & { vendor_id?: number | null; vendor_name?: string | null };
  destination: ItemLocation;
  action_at_stop: 'pickup' | 'dropoff' | null;
  is_completed: boolean;
  has_pickup_photo: boolean;
  has_delivery_photo: boolean;
  photos: ItemPhoto[];
  valid_transitions: string[];
  // Additional detail fields (loaded when viewing single item)
  line_items?: LineItem[] | null;
  documents?: Document[] | null;
  notes?: Note[] | null;
  line_items_count?: number;
  documents_count?: number;
  notes_count?: number;
}

interface RunnerItemsState {
  items: RunnerItem[];
  currentItem: RunnerItem | null;
  loading: boolean;
  error: string | null;
  filters: {
    stopId: number | null;
    showCompleted: boolean;
  };
}

interface ApiErrorResponse {
  message?: string;
  requires_photo?: string;
  valid_transitions?: string[];
}

export const useRunnerItemsStore = defineStore('runnerItems', {
  state: (): RunnerItemsState => ({
    items: [],
    currentItem: null,
    loading: false,
    error: null,
    filters: {
      stopId: null,
      showCompleted: true,
    },
  }),

  getters: {
    openItems: (state) => state.items.filter((i) => !i.is_completed),
    completedItems: (state) => state.items.filter((i) => i.is_completed),
    pickupItems: (state) =>
      state.items.filter((i) => i.action_at_stop === 'pickup'),
    dropoffItems: (state) =>
      state.items.filter((i) => i.action_at_stop === 'dropoff'),
    openItemCount: (state) =>
      state.items.filter((i) => !i.is_completed).length,
  },

  actions: {
    /**
     * Fetch items for a run, optionally filtered by stop.
     */
    async fetchItems(runId: number, stopId?: number | null) {
      this.loading = true;
      this.error = null;

      try {
        const params: Record<string, unknown> = {
          show_completed: this.filters.showCompleted,
        };

        if (stopId !== undefined) {
          this.filters.stopId = stopId;
        }

        if (this.filters.stopId) {
          params.stop_id = this.filters.stopId;
        }

        const response = await api.get(`/runner/runs/${runId}/items`, {
          params,
        });

        this.items = response.data.data || [];
        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to load items';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Fetch a single item's details.
     */
    async fetchItem(itemId: number) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get(`/runner/parts-requests/${itemId}`);
        this.currentItem = response.data.data;
        return { success: true, item: this.currentItem };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to load item';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Update an item's status.
     */
    async updateStatus(itemId: number, status: string, notes?: string) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post(
          `/runner/parts-requests/${itemId}/status`,
          { status, notes }
        );

        // Update local state
        const updatedItem = response.data.data;
        const index = this.items.findIndex((i) => i.id === itemId);
        if (index !== -1) {
          this.items[index] = updatedItem;
        }
        if (this.currentItem?.id === itemId) {
          this.currentItem = updatedItem;
        }

        return { success: true, item: updatedItem };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        const data = axiosError.response?.data;

        if (data?.requires_photo) {
          return {
            success: false,
            error: data.message,
            requiresPhoto: data.requires_photo,
          };
        }

        this.error = data?.message || 'Failed to update status';
        return {
          success: false,
          error: this.error,
          validTransitions: data?.valid_transitions,
        };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Upload a photo for an item.
     */
    async uploadPhoto(
      itemId: number,
      photo: File,
      type: 'pickup' | 'delivery' | 'exception' | 'other'
    ) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append('photo', photo);
        formData.append('type', type);

        const response = await api.post(
          `/runner/parts-requests/${itemId}/photos`,
          formData,
          {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
          }
        );

        // Update local state with new photo
        const newPhoto = response.data.photo;
        const item = this.items.find((i) => i.id === itemId);
        if (item) {
          item.photos.push(newPhoto);
          if (type === 'pickup') item.has_pickup_photo = true;
          if (type === 'delivery') item.has_delivery_photo = true;
        }
        if (this.currentItem?.id === itemId) {
          this.currentItem.photos.push(newPhoto);
          if (type === 'pickup') this.currentItem.has_pickup_photo = true;
          if (type === 'delivery') this.currentItem.has_delivery_photo = true;
        }

        return { success: true, photo: newPhoto };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to upload photo';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Mark an item as exception.
     */
    async markException(itemId: number, reason: string, photo?: File) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append('reason', reason);
        if (photo) {
          formData.append('photo', photo);
        }

        const response = await api.post(
          `/runner/parts-requests/${itemId}/exception`,
          formData,
          {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
          }
        );

        // Update local state
        const updatedItem = response.data.data;
        const index = this.items.findIndex((i) => i.id === itemId);
        if (index !== -1) {
          this.items[index] = updatedItem;
        }
        if (this.currentItem?.id === itemId) {
          this.currentItem = updatedItem;
        }

        return { success: true, item: updatedItem };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to mark exception';
        return { success: false, error: this.error };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Add a note to an item.
     */
    async addNote(itemId: number, content: string) {
      this.error = null;

      try {
        const response = await api.post(
          `/runner/parts-requests/${itemId}/notes`,
          { content }
        );

        // Update local state with new note
        const newNote = response.data.note;
        if (this.currentItem?.id === itemId) {
          if (!this.currentItem.notes) {
            this.currentItem.notes = [];
          }
          this.currentItem.notes.push(newNote);
          this.currentItem.notes_count = (this.currentItem.notes_count || 0) + 1;
        }

        return { success: true, note: newNote };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        this.error =
          axiosError.response?.data?.message || 'Failed to add note';
        return { success: false, error: this.error };
      }
    },

    /**
     * Set filter for stop ID.
     */
    setStopFilter(stopId: number | null) {
      this.filters.stopId = stopId;
    },

    /**
     * Toggle show completed filter.
     */
    toggleShowCompleted() {
      this.filters.showCompleted = !this.filters.showCompleted;
    },

    /**
     * Clear items and filters.
     */
    clearItems() {
      this.items = [];
      this.currentItem = null;
      this.filters.stopId = null;
    },
  },
});
