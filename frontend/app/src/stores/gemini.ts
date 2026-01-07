import { defineStore } from 'pinia';
import { api } from 'src/boot/axios';
import { Notify } from 'quasar';

export interface DriveFolder {
  id: string;
  name: string;
  parents: string[] | null;
}

export interface CsvFile {
  id: string;
  name: string;
  mime_type: string;
  size: number | null;
  modified_time: string;
  web_view_link: string | null;
}

export interface CsvContent {
  file_id: string;
  file_name: string;
  headers: string[];
  rows: Record<string, string>[] | string[][];
  row_count: number;
}

export interface GeminiStatus {
  gemini_configured: boolean;
  gemini_model: string;
  drive_configured: boolean;
}

export interface QueryResponse {
  file_name?: string;
  file_count?: number;
  query: string;
  response: string;
  tokens_used: number | null;
}

export interface ChatResponse {
  response: string;
  tokens_used: number | null;
}

export interface ShopRevenue {
  shop: string;
  total: number;
  total_formatted: string;
  transaction_count: number;
}

export interface RevenueByShopResponse {
  file_name: string;
  shop_column: string;
  amount_column: string;
  shops: ShopRevenue[];
  grand_total: number;
  grand_total_formatted: string;
  total_rows: number;
  shop_count: number;
}

export const useGeminiStore = defineStore('gemini', {
  state: () => ({
    status: null as GeminiStatus | null,
    folders: [] as DriveFolder[],
    searchResults: [] as DriveFolder[],
    folderStack: [] as DriveFolder[], // For breadcrumb navigation
    currentFolderId: null as string | null,
    csvFiles: [] as CsvFile[],
    selectedFiles: [] as CsvFile[],
    csvContent: null as CsvContent | null,
    queryResponse: null as QueryResponse | null,
    chatResponse: null as ChatResponse | null,
    revenueResponse: null as RevenueByShopResponse | null,
    loading: false,
    loadingRevenue: false,
    searching: false,
    loadingFiles: false,
    loadingContent: false,
    querying: false,
    chatting: false,
  }),

  actions: {
    /**
     * Fetch Gemini and Drive status
     */
    async fetchStatus(): Promise<GeminiStatus> {
      try {
        const response = await api.get('/gemini/status');
        this.status = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to fetch status',
        });
        throw error;
      }
    },

    /**
     * Fetch Drive folders
     */
    async fetchFolders(parentId?: string): Promise<DriveFolder[]> {
      this.loading = true;
      try {
        const response = await api.get('/gemini/folders', {
          params: parentId ? { parent_id: parentId } : {},
        });
        this.folders = response.data.data;
        this.currentFolderId = parentId || null;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load folders',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Search folders by name
     */
    async searchFolders(searchTerm: string): Promise<DriveFolder[]> {
      if (!searchTerm || searchTerm.length < 2) {
        this.searchResults = [];
        return [];
      }

      this.searching = true;
      try {
        const response = await api.get('/gemini/folders/search', {
          params: { q: searchTerm },
        });
        this.searchResults = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to search folders',
        });
        throw error;
      } finally {
        this.searching = false;
      }
    },

    /**
     * Clear search results
     */
    clearSearch(): void {
      this.searchResults = [];
    },

    /**
     * Select a folder from search results and navigate to it
     */
    async selectSearchResult(folder: DriveFolder): Promise<void> {
      // Clear search results
      this.searchResults = [];
      // Reset folder stack and set this as the current folder
      this.folderStack = [folder];
      this.currentFolderId = folder.id;
      // Fetch child folders
      await this.fetchFolders(folder.id);
      // Clear files
      this.csvFiles = [];
      this.selectedFiles = [];
    },

    /**
     * Navigate into a folder
     */
    async navigateToFolder(folder: DriveFolder): Promise<void> {
      this.folderStack.push(folder);
      await this.fetchFolders(folder.id);
      // Clear files when navigating
      this.csvFiles = [];
      this.selectedFiles = [];
    },

    /**
     * Navigate back to parent folder
     */
    async navigateBack(): Promise<void> {
      this.folderStack.pop();
      const parentFolder = this.folderStack[this.folderStack.length - 1];
      await this.fetchFolders(parentFolder?.id);
      // Clear files when navigating
      this.csvFiles = [];
      this.selectedFiles = [];
    },

    /**
     * Navigate to root
     */
    async navigateToRoot(): Promise<void> {
      this.folderStack = [];
      await this.fetchFolders();
      this.csvFiles = [];
      this.selectedFiles = [];
    },

    /**
     * Fetch CSV files in a folder
     */
    async fetchCsvFiles(folderId: string): Promise<CsvFile[]> {
      this.loadingFiles = true;
      try {
        const response = await api.get('/gemini/csv-files', {
          params: { folder_id: folderId },
        });
        this.csvFiles = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load CSV files',
        });
        throw error;
      } finally {
        this.loadingFiles = false;
      }
    },

    /**
     * Get CSV file content
     */
    async getCsvContent(fileId: string): Promise<CsvContent> {
      this.loadingContent = true;
      try {
        const response = await api.get(`/gemini/csv/${fileId}`);
        this.csvContent = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load CSV content',
        });
        throw error;
      } finally {
        this.loadingContent = false;
      }
    },

    /**
     * Query a single CSV file with Gemini
     */
    async queryCsv(fileId: string, query: string): Promise<QueryResponse> {
      this.querying = true;
      try {
        const response = await api.post('/gemini/query-csv', {
          file_id: fileId,
          query,
        });
        this.queryResponse = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Query failed',
        });
        throw error;
      } finally {
        this.querying = false;
      }
    },

    /**
     * Query multiple CSV files with Gemini
     */
    async queryMultipleCsv(fileIds: string[], query: string): Promise<QueryResponse> {
      this.querying = true;
      try {
        const response = await api.post('/gemini/query-multiple-csv', {
          file_ids: fileIds,
          query,
        });
        this.queryResponse = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Query failed',
        });
        throw error;
      } finally {
        this.querying = false;
      }
    },

    /**
     * Simple chat with Gemini
     */
    async chat(message: string, context?: string): Promise<ChatResponse> {
      this.chatting = true;
      try {
        const response = await api.post('/gemini/chat', {
          message,
          context,
        });
        this.chatResponse = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Chat failed',
        });
        throw error;
      } finally {
        this.chatting = false;
      }
    },

    /**
     * Get revenue by shop from a CSV file
     */
    async getRevenueByShop(fileId: string, shopColumn?: string, amountColumn?: string): Promise<RevenueByShopResponse> {
      this.loadingRevenue = true;
      try {
        const params: Record<string, string> = { file_id: fileId };
        if (shopColumn) params.shop_column = shopColumn;
        if (amountColumn) params.amount_column = amountColumn;

        const response = await api.get('/gemini/revenue-by-shop', { params });
        this.revenueResponse = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to calculate revenue',
        });
        throw error;
      } finally {
        this.loadingRevenue = false;
      }
    },

    /**
     * Clear revenue response
     */
    clearRevenueResponse(): void {
      this.revenueResponse = null;
    },

    /**
     * Toggle file selection
     */
    toggleFileSelection(file: CsvFile): void {
      const index = this.selectedFiles.findIndex(f => f.id === file.id);
      if (index === -1) {
        if (this.selectedFiles.length < 10) {
          this.selectedFiles.push(file);
        } else {
          Notify.create({
            type: 'warning',
            message: 'Maximum 10 files can be selected',
          });
        }
      } else {
        this.selectedFiles.splice(index, 1);
      }
    },

    /**
     * Clear file selection
     */
    clearSelection(): void {
      this.selectedFiles = [];
    },

    /**
     * Check if file is selected
     */
    isFileSelected(fileId: string): boolean {
      return this.selectedFiles.some(f => f.id === fileId);
    },

    /**
     * Clear query response
     */
    clearQueryResponse(): void {
      this.queryResponse = null;
    },

    /**
     * Clear chat response
     */
    clearChatResponse(): void {
      this.chatResponse = null;
    },
  },

  getters: {
    /**
     * Is Gemini configured?
     */
    isConfigured: (state): boolean => {
      return state.status?.gemini_configured ?? false;
    },

    /**
     * Is Drive configured?
     */
    isDriveConfigured: (state): boolean => {
      return state.status?.drive_configured ?? false;
    },

    /**
     * Get current model
     */
    currentModel: (state): string => {
      return state.status?.gemini_model ?? 'Unknown';
    },

    /**
     * Get breadcrumb path
     */
    breadcrumbPath: (state): DriveFolder[] => {
      return state.folderStack;
    },

    /**
     * Has selected files?
     */
    hasSelectedFiles: (state): boolean => {
      return state.selectedFiles.length > 0;
    },

    /**
     * Selected file IDs
     */
    selectedFileIds: (state): string[] => {
      return state.selectedFiles.map(f => f.id);
    },
  },
});
