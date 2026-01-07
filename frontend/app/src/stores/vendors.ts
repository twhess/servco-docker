import { defineStore } from 'pinia';
import { api } from 'src/boot/axios';
import { Notify } from 'quasar';
import type {
  Vendor,
  Address,
  VendorSearchResult,
  VendorDuplicateCandidate,
  VendorCreateRequest,
  VendorAddressAttachRequest,
  AddressCreateRequest,
} from 'src/types/vendors';

interface VendorCreateResponse {
  status: 'created' | 'duplicates_found';
  message: string;
  data?: Vendor;
  candidates?: VendorDuplicateCandidate[];
}

export const useVendorsStore = defineStore('vendors', {
  state: () => ({
    vendors: [] as Vendor[],
    currentVendor: null as Vendor | null,
    searchResults: [] as VendorSearchResult[],
    loading: false,
    searching: false,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  }),

  actions: {
    /**
     * Fetch vendors list with filtering and pagination
     */
    async fetchVendors(params: {
      page?: number;
      per_page?: number;
      status?: 'active' | 'inactive';
      search?: string;
    } = {}) {
      this.loading = true;
      try {
        const response = await api.get('/vendors', { params });
        this.vendors = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
        };
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load vendors',
        });
      } finally {
        this.loading = false;
      }
    },

    /**
     * Search vendors for autocomplete
     */
    async searchVendors(term: string, limit = 20): Promise<VendorSearchResult[]> {
      if (!term || term.length < 1) {
        this.searchResults = [];
        return [];
      }

      this.searching = true;
      try {
        const response = await api.get('/vendors/search', {
          params: { term, limit },
        });
        this.searchResults = response.data.data;
        return this.searchResults;
      } catch (error: any) {
        console.error('Vendor search failed:', error);
        return [];
      } finally {
        this.searching = false;
      }
    },

    /**
     * Check for duplicate vendors
     */
    async checkDuplicate(name: string): Promise<{
      has_duplicates: boolean;
      candidates: VendorDuplicateCandidate[];
    }> {
      try {
        const response = await api.post('/vendors/check-duplicate', { name });
        return response.data;
      } catch (error: any) {
        console.error('Duplicate check failed:', error);
        return { has_duplicates: false, candidates: [] };
      }
    },

    /**
     * Detect if a name is likely an acronym
     */
    async detectAcronym(name: string): Promise<{
      isLikely: boolean;
      reason: string;
      suggestedName: string;
    }> {
      try {
        const response = await api.post('/vendors/detect-acronym', { name });
        return response.data;
      } catch (error: any) {
        console.error('Acronym detection failed:', error);
        return { isLikely: false, reason: 'Detection failed', suggestedName: name };
      }
    },

    /**
     * Create a new vendor with duplicate detection
     */
    async createVendor(data: VendorCreateRequest): Promise<VendorCreateResponse> {
      this.loading = true;
      try {
        const response = await api.post('/vendors', data);

        if (response.data.status === 'duplicates_found') {
          return response.data;
        }

        Notify.create({
          type: 'positive',
          message: response.data.message || 'Vendor created successfully',
        });
        return response.data;
      } catch (error: any) {
        // Handle 409 Conflict (duplicates found)
        if (error.response?.status === 409) {
          return error.response.data;
        }

        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to create vendor',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Get single vendor with addresses
     */
    async fetchVendor(id: number): Promise<Vendor> {
      this.loading = true;
      try {
        const response = await api.get(`/vendors/${id}`);
        this.currentVendor = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load vendor',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Update vendor
     */
    async updateVendor(id: number, data: Partial<Vendor>): Promise<Vendor> {
      this.loading = true;
      try {
        const response = await api.put(`/vendors/${id}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Vendor updated successfully',
        });
        if (this.currentVendor?.id === id) {
          this.currentVendor = response.data.data;
        }
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update vendor',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Delete vendor (soft delete)
     */
    async deleteVendor(id: number): Promise<void> {
      try {
        const response = await api.delete(`/vendors/${id}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Vendor deleted successfully',
        });
        this.vendors = this.vendors.filter(v => v.id !== id);
        if (this.currentVendor?.id === id) {
          this.currentVendor = null;
        }
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete vendor',
        });
        throw error;
      }
    },

    /**
     * Attach a new address to a vendor
     */
    async attachAddress(vendorId: number, data: VendorAddressAttachRequest): Promise<Vendor> {
      try {
        const response = await api.post(`/vendors/${vendorId}/addresses`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Address attached successfully',
        });
        if (this.currentVendor?.id === vendorId) {
          this.currentVendor = response.data.data;
        }
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to attach address',
        });
        throw error;
      }
    },

    /**
     * Update address pivot data (is_primary, address_type)
     */
    async updateAddressPivot(
      vendorId: number,
      addressId: number,
      data: { address_type?: string; is_primary?: boolean; sort_order?: number }
    ): Promise<Vendor> {
      try {
        const response = await api.put(`/vendors/${vendorId}/addresses/${addressId}`, data);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Address updated successfully',
        });
        if (this.currentVendor?.id === vendorId) {
          this.currentVendor = response.data.data;
        }
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update address',
        });
        throw error;
      }
    },

    /**
     * Detach address from vendor
     */
    async detachAddress(vendorId: number, addressId: number): Promise<Vendor> {
      try {
        const response = await api.delete(`/vendors/${vendorId}/addresses/${addressId}`);
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Address removed successfully',
        });
        if (this.currentVendor?.id === vendorId) {
          this.currentVendor = response.data.data;
        }
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to remove address',
        });
        throw error;
      }
    },

    /**
     * Clear search results
     */
    clearSearch() {
      this.searchResults = [];
    },
  },

  getters: {
    /**
     * Get vendor by ID from loaded list
     */
    getVendorById: (state) => (id: number): Vendor | undefined => {
      return state.vendors.find(v => v.id === id);
    },

    /**
     * Active vendors only
     */
    activeVendors: (state): Vendor[] => {
      return state.vendors.filter(v => v.status === 'active');
    },
  },
});
