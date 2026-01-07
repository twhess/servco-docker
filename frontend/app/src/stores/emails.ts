import { defineStore } from 'pinia';
import { api } from 'src/boot/axios';
import { Notify } from 'quasar';

export interface EmailAttachment {
  id: string;
  filename: string;
  mime_type: string;
  size: number;
  formatted_file_size?: string;
  drive_file_id?: string;
  drive_web_view_link?: string;
  drive_download_link?: string;
  status?: 'pending' | 'downloaded' | 'error';
}

export interface Email {
  id: string;
  thread_id: string;
  subject: string;
  from: string;
  to: string;
  date: string;
  snippet: string;
  internal_date: string;
  body?: {
    text: string;
    html: string;
  };
  label_ids: string[];
  has_attachments: boolean;
  attachments: EmailAttachment[];
}

export interface LocalEmail {
  id: number;
  gmail_message_id: string;
  gmail_thread_id: string;
  subject: string | null;
  from_email: string;
  from_name: string | null;
  to_emails: string[];
  email_date: string;
  snippet: string | null;
  body_text: string | null;
  body_html: string | null;
  has_attachments: boolean;
  attachment_count: number;
  status: 'unprocessed' | 'processing' | 'processed' | 'error';
  processing_notes: string | null;
  processed_at: string | null;
  attachments: EmailAttachment[];
}

export interface EmailSyncStatus {
  configured: boolean;
  shared_mailbox: string | null;
  attachments_folder_configured: boolean;
}

export interface GmailLabel {
  id: string;
  name: string;
  type: string;
}

export const useEmailsStore = defineStore('emails', {
  state: () => ({
    emails: [] as Email[],
    localEmails: [] as LocalEmail[],
    currentEmail: null as Email | null,
    loading: false,
    syncing: false,
    nextPageToken: null as string | null,
    syncStatus: null as EmailSyncStatus | null,
    labels: [] as GmailLabel[],
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
  }),

  actions: {
    /**
     * Fetch emails from Gmail (live query)
     */
    async fetchEmails(params: {
      hasAttachments?: boolean;
      search?: string;
      pageToken?: string;
      maxResults?: number;
    } = {}) {
      this.loading = true;
      try {
        const response = await api.get('/emails', {
          params: {
            has_attachments: params.hasAttachments,
            search: params.search,
            page_token: params.pageToken,
            max_results: params.maxResults ?? 20,
          },
        });

        if (params.pageToken) {
          // Append to existing
          this.emails = [...this.emails, ...response.data.data];
        } else {
          this.emails = response.data.data;
        }

        this.nextPageToken = response.data.next_page_token;
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load emails',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Fetch single email with full details
     */
    async fetchEmail(messageId: string): Promise<Email> {
      this.loading = true;
      try {
        const response = await api.get(`/emails/${messageId}`);
        this.currentEmail = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load email',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Search emails with Gmail query
     */
    async searchEmails(query: string, maxResults = 50): Promise<Email[]> {
      this.loading = true;
      try {
        const response = await api.get('/emails/search', {
          params: { q: query, max_results: maxResults },
        });
        this.emails = response.data.data;
        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Search failed',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Fetch emails with attachments
     */
    async fetchEmailsWithAttachments(maxResults = 50, pageToken?: string): Promise<{ messages: Email[]; next_page_token: string | null }> {
      this.loading = true;
      try {
        const response = await api.get('/emails/with-attachments', {
          params: { max_results: maxResults, page_token: pageToken },
        });
        this.emails = response.data.data;
        this.nextPageToken = response.data.next_page_token;
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load emails',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Download attachment to Google Drive
     */
    async downloadAttachmentToDrive(
      messageId: string,
      attachmentId: string,
      filename: string,
      folderId?: string
    ): Promise<{ file_id: string; web_view_link: string; download_link: string }> {
      try {
        const response = await api.post(
          `/emails/${messageId}/attachments/${attachmentId}/download-to-drive`,
          { filename, folder_id: folderId }
        );

        Notify.create({
          type: 'positive',
          message: 'Attachment saved to Google Drive',
        });

        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to download attachment',
        });
        throw error;
      }
    },

    /**
     * Download all attachments from a message
     */
    async downloadAllAttachments(messageId: string, folderId?: string): Promise<{
      downloaded: Array<{ filename: string; file_id: string; web_view_link: string }>;
      errors: Array<{ filename: string; error: string }>;
      success_count: number;
      error_count: number;
    }> {
      try {
        const response = await api.post(
          `/emails/${messageId}/download-all-attachments`,
          { folder_id: folderId }
        );

        const data = response.data.data;

        if (data.success_count > 0) {
          Notify.create({
            type: 'positive',
            message: `${data.success_count} attachment(s) saved to Google Drive`,
          });
        }

        if (data.error_count > 0) {
          Notify.create({
            type: 'warning',
            message: `${data.error_count} attachment(s) failed to download`,
          });
        }

        return data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to download attachments',
        });
        throw error;
      }
    },

    /**
     * Mark email as read
     */
    async markAsRead(messageId: string): Promise<void> {
      try {
        await api.post(`/emails/${messageId}/mark-read`);

        // Update local state
        const email = this.emails.find(e => e.id === messageId);
        if (email) {
          email.label_ids = email.label_ids.filter(l => l !== 'UNREAD');
        }
      } catch (error: any) {
        console.error('Failed to mark as read:', error);
      }
    },

    /**
     * Fetch Gmail labels
     */
    async fetchLabels(): Promise<GmailLabel[]> {
      try {
        const response = await api.get('/emails/labels');
        this.labels = response.data.data;
        return response.data.data;
      } catch (error: any) {
        console.error('Failed to fetch labels:', error);
        return [];
      }
    },

    /**
     * Fetch sync status
     */
    async fetchSyncStatus(): Promise<EmailSyncStatus> {
      try {
        const response = await api.get('/emails/sync-status');
        this.syncStatus = response.data.data;
        return response.data.data;
      } catch (error: any) {
        console.error('Failed to fetch sync status:', error);
        throw error;
      }
    },

    /**
     * Sync emails from Gmail to local database
     */
    async syncEmails(hasAttachmentsOnly = true, maxResults = 50): Promise<{
      synced: number;
      skipped: number;
    }> {
      this.syncing = true;
      try {
        const response = await api.post('/emails/sync', {
          has_attachments_only: hasAttachmentsOnly,
          max_results: maxResults,
        });

        Notify.create({
          type: 'positive',
          message: response.data.message,
        });

        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Sync failed',
        });
        throw error;
      } finally {
        this.syncing = false;
      }
    },

    /**
     * Fetch locally cached emails
     */
    async fetchLocalEmails(params: {
      page?: number;
      per_page?: number;
      has_attachments?: boolean;
      status?: string;
      search?: string;
    } = {}) {
      this.loading = true;
      try {
        const response = await api.get('/emails/local', { params });
        this.localEmails = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
        };
        return response.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to load emails',
        });
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Update local email status
     */
    async updateLocalEmailStatus(id: number, status: string, notes?: string): Promise<LocalEmail> {
      try {
        const response = await api.put(`/emails/local/${id}/status`, {
          status,
          processing_notes: notes,
        });

        Notify.create({
          type: 'positive',
          message: 'Status updated',
        });

        // Update local state
        const index = this.localEmails.findIndex(e => e.id === id);
        if (index !== -1) {
          this.localEmails[index] = response.data.data;
        }

        return response.data.data;
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Failed to update status',
        });
        throw error;
      }
    },

    /**
     * Clear emails list
     */
    clearEmails() {
      this.emails = [];
      this.nextPageToken = null;
    },
  },

  getters: {
    /**
     * Check if email is unread
     */
    isUnread: () => (email: Email): boolean => {
      return email.label_ids?.includes('UNREAD') ?? false;
    },

    /**
     * Get emails with attachments
     */
    emailsWithAttachments: (state): Email[] => {
      return state.emails.filter(e => e.has_attachments);
    },

    /**
     * Is Gmail configured?
     */
    isConfigured: (state): boolean => {
      return state.syncStatus?.configured ?? false;
    },
  },
});
