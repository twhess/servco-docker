<template>
  <q-page padding>
    <div class="q-pb-md">
      <!-- Header -->
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h5">Email Inbox</div>
        <div class="row q-gutter-sm">
          <q-btn
            flat
            :loading="emailsStore.syncing"
            @click="syncEmails"
          >
            <q-icon name="sync" color="primary" size="sm" class="q-mr-xs" />
            <span class="text-primary">Sync Emails</span>
          </q-btn>
          <q-btn flat @click="showAttachmentsOnly = !showAttachmentsOnly">
            <q-icon
              :name="showAttachmentsOnly ? 'attachment' : 'mail'"
              :color="showAttachmentsOnly ? 'primary' : 'grey'"
              size="sm"
              class="q-mr-xs"
            />
            <span :class="showAttachmentsOnly ? 'text-primary' : 'text-grey'">
              {{ showAttachmentsOnly ? 'With Attachments' : 'All Emails' }}
            </span>
          </q-btn>
        </div>
      </div>

      <!-- Sync Status Banner -->
      <q-banner v-if="syncStatus && !syncStatus.configured" class="bg-warning q-mb-md" rounded>
        <template v-slot:avatar>
          <q-icon name="warning" color="dark" />
        </template>
        Gmail integration is not configured. Please contact your administrator to set up Gmail API access.
      </q-banner>

      <!-- Search & Filters -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section class="q-pa-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-8">
              <q-input
                v-model="searchQuery"
                dense
                outlined
                placeholder="Search emails (subject, from, to)..."
                @keyup.enter="doSearch"
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
                <template v-slot:append>
                  <q-icon
                    v-if="searchQuery"
                    name="close"
                    class="cursor-pointer"
                    @click="searchQuery = ''; fetchEmails()"
                  />
                  <q-btn
                    flat
                    dense
                    label="Search"
                    color="primary"
                    @click="doSearch"
                  />
                </template>
              </q-input>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Email List -->
      <q-card flat bordered>
        <q-list separator>
          <q-item
            v-for="email in emails"
            :key="email.id"
            clickable
            @click="openEmailDialog(email)"
          >
            <q-item-section avatar>
              <q-icon
                :name="isUnread(email) ? 'mark_email_unread' : 'email'"
                :color="isUnread(email) ? 'primary' : 'grey'"
              />
            </q-item-section>

            <q-item-section>
              <q-item-label :class="{ 'text-weight-bold': isUnread(email) }">
                {{ email.subject || '(no subject)' }}
              </q-item-label>
              <q-item-label caption>
                {{ email.from }}
              </q-item-label>
              <q-item-label caption class="text-grey-7 ellipsis-2-lines">
                {{ email.snippet }}
              </q-item-label>
            </q-item-section>

            <q-item-section side top>
              <q-item-label caption>{{ formatDate(email.date || email.internal_date) }}</q-item-label>
              <div class="row q-gutter-xs q-mt-xs">
                <q-badge
                  v-if="email.has_attachments"
                  color="primary"
                  outline
                >
                  <q-icon name="attachment" size="xs" class="q-mr-xs" />
                  {{ email.attachments?.length || 0 }}
                </q-badge>
              </div>
            </q-item-section>
          </q-item>

          <q-item v-if="loading">
            <q-item-section class="text-center q-pa-md">
              <q-spinner color="primary" size="30px" />
            </q-item-section>
          </q-item>

          <q-item v-else-if="emails.length === 0">
            <q-item-section class="text-center text-grey q-pa-md">
              No emails found
            </q-item-section>
          </q-item>
        </q-list>

        <!-- Load More -->
        <q-card-section v-if="nextPageToken && !loading" class="text-center">
          <q-btn
            flat
            color="primary"
            label="Load More"
            @click="loadMore"
          />
        </q-card-section>
      </q-card>
    </div>

    <!-- Email Detail Dialog -->
    <q-dialog v-model="showEmailDialog" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card v-if="selectedEmail">
        <q-card-section class="row items-center q-pb-none bg-primary text-white">
          <div class="text-h6 ellipsis" style="max-width: 80%;">
            {{ selectedEmail.subject || '(no subject)' }}
          </div>
          <q-space />
          <q-btn icon="close" flat round dense color="white" v-close-popup />
        </q-card-section>

        <q-card-section>
          <div class="row q-col-gutter-sm q-mb-md">
            <div class="col-12">
              <strong>From:</strong> {{ selectedEmail.from }}
            </div>
            <div class="col-12">
              <strong>To:</strong> {{ selectedEmail.to }}
            </div>
            <div class="col-12">
              <strong>Date:</strong> {{ formatDate(selectedEmail.date || selectedEmail.internal_date) }}
            </div>
          </div>

          <!-- Attachments Section -->
          <div v-if="selectedEmail.has_attachments" class="q-mb-md">
            <div class="row items-center justify-between q-mb-sm">
              <div class="text-subtitle1 text-weight-medium">
                Attachments ({{ selectedEmail.attachments?.length || 0 }})
              </div>
              <q-btn
                v-if="selectedEmail.attachments?.length"
                flat
                dense
                color="primary"
                label="Download All to Drive"
                icon="cloud_download"
                :loading="downloadingAll"
                @click="downloadAllToDrive"
              />
            </div>
            <q-list bordered separator>
              <q-item
                v-for="attachment in selectedEmail.attachments"
                :key="attachment.id"
              >
                <q-item-section avatar>
                  <q-icon :name="getAttachmentIcon(attachment.mime_type)" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ attachment.filename }}</q-item-label>
                  <q-item-label caption>
                    {{ formatFileSize(attachment.size) }}
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-btn
                    flat
                    dense
                    color="primary"
                    icon="cloud_download"
                    label="To Drive"
                    :loading="downloadingAttachment === attachment.id"
                    @click="downloadToDrive(attachment)"
                  />
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <q-separator class="q-my-md" />

          <!-- Email Body -->
          <div class="email-body">
            <div
              v-if="selectedEmail.body?.html"
              v-html="selectedEmail.body.html"
              class="email-html-content"
            />
            <pre
              v-else-if="selectedEmail.body?.text"
              class="email-text-content"
            >{{ selectedEmail.body.text }}</pre>
            <div v-else class="text-grey text-center q-pa-md">
              No email body available
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useEmailsStore, type Email, type EmailAttachment } from 'src/stores/emails';
import { date } from 'quasar';

const emailsStore = useEmailsStore();

const emails = computed(() => emailsStore.emails);
const loading = computed(() => emailsStore.loading);
const nextPageToken = computed(() => emailsStore.nextPageToken);
const syncStatus = computed(() => emailsStore.syncStatus);

const searchQuery = ref('');
const showAttachmentsOnly = ref(true);
const showEmailDialog = ref(false);
const selectedEmail = ref<Email | null>(null);
const downloadingAttachment = ref<string | null>(null);
const downloadingAll = ref(false);

watch(showAttachmentsOnly, () => {
  void fetchEmails();
});

function isUnread(email: Email): boolean {
  return email.label_ids?.includes('UNREAD') ?? false;
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '';

  // Handle Gmail internal_date (milliseconds timestamp)
  let d: Date;
  if (/^\d+$/.test(dateStr)) {
    d = new Date(parseInt(dateStr));
  } else {
    d = new Date(dateStr);
  }

  return date.formatDate(d, 'MMM D, YYYY h:mm A');
}

function formatFileSize(bytes: number): string {
  if (bytes >= 1048576) {
    return (bytes / 1048576).toFixed(2) + ' MB';
  } else if (bytes >= 1024) {
    return (bytes / 1024).toFixed(2) + ' KB';
  }
  return bytes + ' bytes';
}

function getAttachmentIcon(mimeType: string): string {
  if (mimeType?.startsWith('image/')) return 'image';
  if (mimeType === 'application/pdf') return 'picture_as_pdf';
  if (mimeType?.includes('spreadsheet') || mimeType?.includes('excel')) return 'table_chart';
  if (mimeType?.includes('document') || mimeType?.includes('word')) return 'description';
  return 'insert_drive_file';
}

async function fetchEmails() {
  const params: { hasAttachments?: boolean; search?: string } = {
    hasAttachments: showAttachmentsOnly.value,
  };
  if (searchQuery.value) {
    params.search = searchQuery.value;
  }
  await emailsStore.fetchEmails(params);
}

async function doSearch() {
  if (searchQuery.value) {
    await emailsStore.searchEmails(searchQuery.value);
  } else {
    await fetchEmails();
  }
}

async function loadMore() {
  if (nextPageToken.value) {
    await emailsStore.fetchEmails({
      hasAttachments: showAttachmentsOnly.value,
      pageToken: nextPageToken.value,
    });
  }
}

async function syncEmails() {
  await emailsStore.syncEmails(showAttachmentsOnly.value);
  await fetchEmails();
}

async function openEmailDialog(email: Email) {
  // Fetch full email details
  selectedEmail.value = await emailsStore.fetchEmail(email.id);
  showEmailDialog.value = true;

  // Mark as read
  if (isUnread(email)) {
    void emailsStore.markAsRead(email.id);
  }
}

async function downloadToDrive(attachment: EmailAttachment) {
  if (!selectedEmail.value) return;

  downloadingAttachment.value = attachment.id;
  try {
    await emailsStore.downloadAttachmentToDrive(
      selectedEmail.value.id,
      attachment.id,
      attachment.filename
    );
  } finally {
    downloadingAttachment.value = null;
  }
}

async function downloadAllToDrive() {
  if (!selectedEmail.value) return;

  downloadingAll.value = true;
  try {
    await emailsStore.downloadAllAttachments(selectedEmail.value.id);
  } finally {
    downloadingAll.value = false;
  }
}

onMounted(async () => {
  await emailsStore.fetchSyncStatus();
  if (syncStatus.value?.configured) {
    await fetchEmails();
  }
});
</script>

<style scoped>
.email-body {
  max-height: 60vh;
  overflow-y: auto;
}

.email-html-content {
  font-family: inherit;
}

.email-html-content :deep(img) {
  max-width: 100%;
  height: auto;
}

.email-text-content {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: inherit;
  margin: 0;
}

.ellipsis-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
