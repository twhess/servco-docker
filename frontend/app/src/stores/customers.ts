import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'
import { Notify } from 'quasar'
import type {
  Customer,
  CustomerSearchResult,
  CustomerDuplicateCandidate,
  CustomerCreateRequest,
  CustomerImport,
  CustomerImportRow,
  CustomerMergeCandidate,
  MergeComparisonData,
  MergeSummary,
} from 'src/types/customers'
import type { Address, VendorAddressAttachRequest } from 'src/types/vendors'

interface CustomerCreateResponse {
  status: 'created' | 'duplicates_found'
  message: string
  data?: Customer
  candidates?: CustomerDuplicateCandidate[]
}

export const useCustomersStore = defineStore('customers', {
  state: () => ({
    customers: [] as Customer[],
    currentCustomer: null as Customer | null,
    searchResults: [] as CustomerSearchResult[],
    loading: false,
    searching: false,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },

    // Import state
    imports: [] as CustomerImport[],
    currentImport: null as CustomerImport | null,
    importRows: [] as CustomerImportRow[],
    importLoading: false,
    importPagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },

    // Merge state
    mergeCandidates: [] as CustomerMergeCandidate[],
    currentMergeCandidate: null as CustomerMergeCandidate | null,
    mergeComparison: null as MergeComparisonData | null,
    mergeSummary: null as MergeSummary | null,
    mergeLoading: false,
  }),

  actions: {
    // ==========================================
    // CUSTOMER CRUD
    // ==========================================

    async fetchCustomers(params: {
      page?: number
      per_page?: number
      status?: 'active' | 'inactive'
      source?: 'manual' | 'import'
      search?: string
    } = {}) {
      this.loading = true
      try {
        const response = await api.get('/customers', { params })
        this.customers = response.data.data
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
        }
        return this.pagination
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load customers',
        })
        return null
      } finally {
        this.loading = false
      }
    },

    async searchCustomers(term: string, limit = 20): Promise<CustomerSearchResult[]> {
      if (!term || term.length < 1) {
        this.searchResults = []
        return []
      }

      this.searching = true
      try {
        const response = await api.get('/customers/search', {
          params: { term, limit },
        })
        this.searchResults = response.data.data
        return this.searchResults
      } catch (error: any) {
        console.error('Customer search failed:', error)
        return []
      } finally {
        this.searching = false
      }
    },

    async checkDuplicate(data: {
      formatted_name: string
      dot_number?: string
      phone?: string
    }): Promise<CustomerDuplicateCandidate[]> {
      try {
        const response = await api.post('/customers/check-duplicate', data)
        return response.data.candidates || []
      } catch (error: any) {
        console.error('Duplicate check failed:', error)
        return []
      }
    },

    async createCustomer(data: CustomerCreateRequest): Promise<CustomerCreateResponse> {
      this.loading = true
      try {
        const response = await api.post('/customers', data)

        if (response.data.status === 'duplicates_found') {
          return response.data
        }

        Notify.create({
          type: 'positive',
          message: response.data.message || 'Customer created successfully',
        })
        return response.data
      } catch (error: any) {
        if (error.response?.status === 409) {
          return error.response.data
        }

        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to create customer',
        })
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchCustomer(id: number): Promise<Customer> {
      this.loading = true
      try {
        const response = await api.get(`/customers/${id}`)
        this.currentCustomer = response.data.data
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load customer',
        })
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateCustomer(id: number, data: Partial<Customer>): Promise<Customer> {
      this.loading = true
      try {
        const response = await api.put(`/customers/${id}`, data)
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Customer updated successfully',
        })
        if (this.currentCustomer?.id === id) {
          this.currentCustomer = response.data.data
        }
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update customer',
        })
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteCustomer(id: number): Promise<void> {
      try {
        const response = await api.delete(`/customers/${id}`)
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Customer deleted successfully',
        })
        this.customers = this.customers.filter(c => c.id !== id)
        if (this.currentCustomer?.id === id) {
          this.currentCustomer = null
        }
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete customer',
        })
        throw error
      }
    },

    // ==========================================
    // CUSTOMER ADDRESSES
    // ==========================================

    async attachAddress(customerId: number, data: VendorAddressAttachRequest): Promise<Customer> {
      try {
        const response = await api.post(`/customers/${customerId}/addresses`, data)
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Address attached successfully',
        })
        if (this.currentCustomer?.id === customerId) {
          this.currentCustomer = response.data.data
        }
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to attach address',
        })
        throw error
      }
    },

    async updateAddressPivot(
      customerId: number,
      addressId: number,
      data: { address_type?: string; is_primary?: boolean; sort_order?: number }
    ): Promise<Customer> {
      try {
        const response = await api.put(`/customers/${customerId}/addresses/${addressId}`, data)
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Address updated successfully',
        })
        if (this.currentCustomer?.id === customerId) {
          this.currentCustomer = response.data.data
        }
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update address',
        })
        throw error
      }
    },

    async detachAddress(customerId: number, addressId: number): Promise<Customer> {
      try {
        const response = await api.delete(`/customers/${customerId}/addresses/${addressId}`)
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Address removed successfully',
        })
        if (this.currentCustomer?.id === customerId) {
          this.currentCustomer = response.data.data
        }
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to remove address',
        })
        throw error
      }
    },

    // ==========================================
    // CUSTOMER IMPORTS
    // ==========================================

    async fetchImports(params: {
      page?: number
      per_page?: number
      status?: string
    } = {}) {
      this.importLoading = true
      try {
        const response = await api.get('/customer-imports', { params })
        this.imports = response.data.data
        this.importPagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
        }
        return this.importPagination
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load imports',
        })
        return null
      } finally {
        this.importLoading = false
      }
    },

    async uploadImport(file: File, processSync = false): Promise<CustomerImport> {
      this.importLoading = true
      try {
        const formData = new FormData()
        formData.append('file', file)
        if (processSync) {
          formData.append('process_sync', 'true')
        }

        const response = await api.post('/customer-imports', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        })

        Notify.create({
          type: 'positive',
          message: response.data.message || 'Import uploaded successfully',
        })

        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to upload import',
        })
        throw error
      } finally {
        this.importLoading = false
      }
    },

    async fetchImport(id: number): Promise<CustomerImport> {
      this.importLoading = true
      try {
        const response = await api.get(`/customer-imports/${id}`)
        this.currentImport = response.data.data
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load import',
        })
        throw error
      } finally {
        this.importLoading = false
      }
    },

    async fetchImportRows(importId: number, params: {
      page?: number
      per_page?: number
      action?: string
    } = {}): Promise<{ data: CustomerImportRow[]; pagination: any }> {
      try {
        const response = await api.get(`/customer-imports/${importId}/rows`, { params })
        this.importRows = response.data.data
        return {
          data: response.data.data,
          pagination: {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            per_page: response.data.per_page,
            total: response.data.total,
          },
        }
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load import rows',
        })
        throw error
      }
    },

    async processImport(id: number): Promise<CustomerImport> {
      this.importLoading = true
      try {
        const response = await api.post(`/customer-imports/${id}/process`)
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Import processing started',
        })
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to start processing',
        })
        throw error
      } finally {
        this.importLoading = false
      }
    },

    async deleteImport(id: number): Promise<void> {
      try {
        const response = await api.delete(`/customer-imports/${id}`)
        Notify.create({
          type: 'positive',
          message: response.data.message || 'Import deleted successfully',
        })
        this.imports = this.imports.filter(i => i.id !== id)
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to delete import',
        })
        throw error
      }
    },

    // ==========================================
    // CUSTOMER MERGES
    // ==========================================

    async fetchMergeCandidates(params: {
      import_id?: number
      status?: string
      limit?: number
    } = {}): Promise<CustomerMergeCandidate[]> {
      this.mergeLoading = true
      try {
        const response = await api.get('/customer-merges', { params })
        this.mergeCandidates = response.data.data
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load merge candidates',
        })
        return []
      } finally {
        this.mergeLoading = false
      }
    },

    async fetchMergeSummary(importId?: number): Promise<MergeSummary> {
      try {
        const params = importId ? { import_id: importId } : {}
        const response = await api.get('/customer-merges/summary', { params })
        this.mergeSummary = response.data.data
        return response.data.data
      } catch (error: any) {
        console.error('Failed to load merge summary:', error)
        return { pending: 0, merged: 0, created_new: 0, skipped: 0, total: 0 }
      }
    },

    async fetchMergeCandidate(id: number): Promise<{ candidate: CustomerMergeCandidate; comparison: MergeComparisonData }> {
      this.mergeLoading = true
      try {
        const response = await api.get(`/customer-merges/${id}`)
        this.currentMergeCandidate = response.data.data.candidate
        this.mergeComparison = response.data.data.comparison
        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load merge candidate',
        })
        throw error
      } finally {
        this.mergeLoading = false
      }
    },

    async resolveMerge(
      id: number,
      action: 'merge' | 'create_new' | 'skip',
      fieldSelections: Record<string, 'existing' | 'incoming'> = {}
    ): Promise<{ success: boolean; message: string; customer?: Customer }> {
      this.mergeLoading = true
      try {
        const response = await api.post(`/customer-merges/${id}/resolve`, {
          action,
          field_selections: fieldSelections,
        })

        Notify.create({
          type: 'positive',
          message: response.data.message || 'Merge resolved successfully',
        })

        // Remove from local list
        this.mergeCandidates = this.mergeCandidates.filter(c => c.id !== id)

        return {
          success: true,
          message: response.data.message,
          customer: response.data.data,
        }
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to resolve merge',
        })
        return {
          success: false,
          message: error.response?.data?.message || 'Failed to resolve merge',
        }
      } finally {
        this.mergeLoading = false
      }
    },

    async batchResolveMerge(
      ids: number[],
      action: 'create_new' | 'skip'
    ): Promise<{ success: number; failed: number; errors: string[] }> {
      this.mergeLoading = true
      try {
        const response = await api.post('/customer-merges/batch-resolve', {
          ids,
          action,
        })

        Notify.create({
          type: 'positive',
          message: response.data.message,
        })

        // Remove resolved from local list
        this.mergeCandidates = this.mergeCandidates.filter(c => !ids.includes(c.id))

        return response.data.data
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to batch resolve',
        })
        throw error
      } finally {
        this.mergeLoading = false
      }
    },

    // ==========================================
    // UTILITIES
    // ==========================================

    clearSearch() {
      this.searchResults = []
    },
  },

  getters: {
    getCustomerById: (state) => (id: number): Customer | undefined => {
      return state.customers.find(c => c.id === id)
    },

    activeCustomers: (state): Customer[] => {
      return state.customers.filter(c => c.is_active)
    },

    pendingMergeCount: (state): number => {
      return state.mergeSummary?.pending || 0
    },
  },
})
