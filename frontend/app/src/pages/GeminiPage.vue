<template>
  <q-page padding>
    <div class="q-pb-md">
      <!-- Header -->
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h5">AI Data Query</div>
        <div class="row q-gutter-sm items-center">
          <q-chip
            v-if="geminiStore.status"
            :color="geminiStore.isConfigured ? 'positive' : 'negative'"
            text-color="white"
            dense
          >
            {{ geminiStore.isConfigured ? 'Gemini Ready' : 'Not Configured' }}
          </q-chip>
          <q-chip
            v-if="geminiStore.status"
            color="grey-7"
            text-color="white"
            dense
          >
            {{ geminiStore.currentModel }}
          </q-chip>
        </div>
      </div>

      <!-- Configuration Banner -->
      <q-banner v-if="geminiStore.status && !geminiStore.isConfigured" class="bg-warning q-mb-md" rounded>
        <template v-slot:avatar>
          <q-icon name="warning" color="dark" />
        </template>
        Gemini AI is not configured. Please contact your administrator to set up the API key.
      </q-banner>

      <!-- Main Layout -->
      <div class="row q-col-gutter-md">
        <!-- Left Panel: Folder Browser -->
        <div class="col-12 col-md-5">
          <q-card flat bordered>
            <q-card-section class="q-pb-sm">
              <div class="row items-center justify-between">
                <div class="text-subtitle1 text-weight-medium">Google Drive</div>
                <q-btn
                  flat
                  dense
                  icon="refresh"
                  color="primary"
                  :loading="geminiStore.loading"
                  @click="refreshFolders"
                />
              </div>

              <!-- Folder Search -->
              <q-input
                v-model="folderSearch"
                dense
                outlined
                placeholder="Search folders..."
                class="q-mt-sm"
                :loading="geminiStore.searching"
                @update:model-value="onSearchInput"
                @keyup.enter="doFolderSearch"
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
                <template v-slot:append>
                  <q-icon
                    v-if="folderSearch"
                    name="close"
                    class="cursor-pointer"
                    @click="clearFolderSearch"
                  />
                </template>
              </q-input>

              <!-- Search Results -->
              <template v-if="geminiStore.searchResults.length > 0">
                <div class="text-caption text-grey-7 q-mt-sm">
                  Search Results ({{ geminiStore.searchResults.length }})
                </div>
                <q-list dense bordered separator class="q-mt-xs rounded-borders" style="max-height: 200px; overflow-y: auto;">
                  <q-item
                    v-for="folder in geminiStore.searchResults"
                    :key="folder.id"
                    clickable
                    @click="selectSearchResult(folder)"
                  >
                    <q-item-section avatar>
                      <q-icon name="folder" color="amber-8" size="sm" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>{{ folder.name }}</q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-icon name="arrow_forward" color="primary" size="xs" />
                    </q-item-section>
                  </q-item>
                </q-list>
              </template>

              <!-- Breadcrumb -->
              <q-breadcrumbs class="q-mt-sm">
                <q-breadcrumbs-el
                  label="Root"
                  icon="folder"
                  class="cursor-pointer"
                  @click="geminiStore.navigateToRoot()"
                />
                <q-breadcrumbs-el
                  v-for="folder in geminiStore.breadcrumbPath"
                  :key="folder.id"
                  :label="folder.name"
                  class="cursor-pointer"
                  @click="navigateToBreadcrumb(folder)"
                />
              </q-breadcrumbs>
            </q-card-section>

            <q-separator />

            <!-- Folders List -->
            <q-list separator style="max-height: 300px; overflow-y: auto;">
              <q-item v-if="geminiStore.loading" class="text-center">
                <q-item-section>
                  <q-spinner color="primary" size="24px" />
                </q-item-section>
              </q-item>

              <q-item
                v-else-if="geminiStore.folders.length === 0 && !geminiStore.currentFolderId"
                class="text-center text-grey"
              >
                <q-item-section>No folders found in root</q-item-section>
              </q-item>

              <q-item
                v-for="folder in geminiStore.folders"
                :key="folder.id"
                clickable
                @click="navigateToFolder(folder)"
              >
                <q-item-section avatar>
                  <q-icon name="folder" color="amber-8" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ folder.name }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon name="chevron_right" color="grey" />
                </q-item-section>
              </q-item>
            </q-list>

            <q-separator v-if="geminiStore.currentFolderId" />

            <!-- Load CSV Files Button -->
            <q-card-section v-if="geminiStore.currentFolderId" class="q-pt-sm q-pb-sm">
              <q-btn
                color="primary"
                :label="geminiStore.loadingFiles ? 'Loading...' : 'Load CSV Files'"
                :loading="geminiStore.loadingFiles"
                icon="table_chart"
                class="full-width"
                @click="loadCsvFiles"
              />
            </q-card-section>

            <!-- CSV Files List -->
            <template v-if="geminiStore.csvFiles.length > 0">
              <q-separator />
              <q-card-section class="q-pb-sm">
                <div class="row items-center justify-between">
                  <div class="text-subtitle2">CSV Files ({{ geminiStore.csvFiles.length }})</div>
                  <q-badge v-if="geminiStore.selectedFiles.length > 0" color="primary">
                    {{ geminiStore.selectedFiles.length }} selected
                  </q-badge>
                </div>
              </q-card-section>

              <q-list separator style="max-height: 250px; overflow-y: auto;">
                <q-item
                  v-for="file in geminiStore.csvFiles"
                  :key="file.id"
                  clickable
                  :active="geminiStore.isFileSelected(file.id)"
                  active-class="bg-primary-1"
                  @click="geminiStore.toggleFileSelection(file)"
                >
                  <q-item-section avatar>
                    <q-checkbox
                      :model-value="geminiStore.isFileSelected(file.id)"
                      color="primary"
                      @click.stop="geminiStore.toggleFileSelection(file)"
                    />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ file.name }}</q-item-label>
                    <q-item-label caption>
                      {{ formatFileSize(file.size) }}
                      <span v-if="file.modified_time">
                        - {{ formatDate(file.modified_time) }}
                      </span>
                    </q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-btn
                      flat
                      dense
                      icon="visibility"
                      color="primary"
                      @click.stop="previewFile(file)"
                    >
                      <q-tooltip>Preview</q-tooltip>
                    </q-btn>
                  </q-item-section>
                </q-item>
              </q-list>
            </template>
          </q-card>
        </div>

        <!-- Right Panel: Query Interface -->
        <div class="col-12 col-md-7">
          <q-card flat bordered>
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium q-mb-md">Query Data with AI</div>

              <!-- Selected Files Summary -->
              <div v-if="geminiStore.hasSelectedFiles" class="q-mb-md">
                <div class="text-caption text-grey-7 q-mb-xs">Selected Files:</div>
                <div class="row q-gutter-xs">
                  <q-chip
                    v-for="file in geminiStore.selectedFiles"
                    :key="file.id"
                    removable
                    color="primary"
                    text-color="white"
                    size="sm"
                    @remove="geminiStore.toggleFileSelection(file)"
                  >
                    {{ file.name }}
                  </q-chip>
                </div>
              </div>

              <!-- Query Input -->
              <q-input
                v-model="queryText"
                type="textarea"
                outlined
                autogrow
                :rows="3"
                placeholder="Ask a question about your data..."
                :disable="!geminiStore.hasSelectedFiles"
              >
                <template v-slot:hint>
                  <span v-if="!geminiStore.hasSelectedFiles" class="text-warning">
                    Select CSV files from the folder browser to query
                  </span>
                </template>
              </q-input>

              <!-- Query Button -->
              <div class="row q-mt-md q-gutter-sm">
                <q-btn
                  color="primary"
                  label="Ask Gemini"
                  icon="auto_awesome"
                  :loading="geminiStore.querying"
                  :disable="!geminiStore.hasSelectedFiles || !queryText.trim()"
                  @click="submitQuery"
                />
                <q-btn
                  color="teal"
                  label="Revenue by Shop"
                  icon="payments"
                  :loading="geminiStore.loadingRevenue"
                  :disable="!hasSingleFileSelected"
                  @click="fetchRevenueByShop"
                >
                  <q-tooltip v-if="!hasSingleFileSelected">Select exactly 1 CSV file</q-tooltip>
                </q-btn>
                <q-btn
                  v-if="geminiStore.queryResponse"
                  flat
                  color="grey"
                  label="Clear"
                  @click="geminiStore.clearQueryResponse(); queryText = ''"
                />
              </div>
            </q-card-section>

            <!-- Query Response -->
            <template v-if="geminiStore.queryResponse">
              <q-separator />
              <q-card-section>
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2 text-weight-medium">Response</div>
                  <div class="row items-center q-gutter-sm">
                    <q-btn
                      flat
                      dense
                      icon="content_copy"
                      color="primary"
                      size="sm"
                      @click="copyQueryResponse"
                    >
                      <q-tooltip>Copy response</q-tooltip>
                    </q-btn>
                    <q-chip v-if="geminiStore.queryResponse.tokens_used" dense size="sm" color="grey-3">
                      {{ geminiStore.queryResponse.tokens_used }} tokens
                    </q-chip>
                  </div>
                </div>
                <div class="response-content q-pa-md bg-grey-1 rounded-borders markdown-body" v-html="renderedQueryResponse"></div>
              </q-card-section>
            </template>

            <!-- Revenue by Shop Response -->
            <template v-if="geminiStore.revenueResponse">
              <q-separator />
              <q-card-section>
                <div class="row items-center justify-between q-mb-md">
                  <div class="text-subtitle2 text-weight-medium">
                    <q-icon name="payments" color="teal" class="q-mr-xs" />
                    Revenue by Shop
                  </div>
                  <q-btn
                    flat
                    dense
                    icon="close"
                    color="grey"
                    size="sm"
                    @click="geminiStore.clearRevenueResponse()"
                  >
                    <q-tooltip>Clear results</q-tooltip>
                  </q-btn>
                </div>

                <div class="text-caption text-grey-7 q-mb-md">
                  File: {{ geminiStore.revenueResponse.file_name }} |
                  {{ geminiStore.revenueResponse.total_rows }} rows |
                  {{ geminiStore.revenueResponse.shop_count }} shops
                </div>

                <!-- Shop Totals Table -->
                <q-table
                  :rows="geminiStore.revenueResponse.shops"
                  :columns="revenueColumns"
                  row-key="shop"
                  flat
                  bordered
                  dense
                  :pagination="{ rowsPerPage: 0 }"
                  hide-pagination
                >
                  <template v-slot:body-cell-total_formatted="props">
                    <q-td :props="props" class="text-right text-weight-medium">
                      {{ props.row.total_formatted }}
                    </q-td>
                  </template>
                  <template v-slot:body-cell-transaction_count="props">
                    <q-td :props="props" class="text-right">
                      {{ props.row.transaction_count.toLocaleString() }}
                    </q-td>
                  </template>
                </q-table>

                <!-- Grand Total -->
                <div class="row justify-end q-mt-md">
                  <q-card flat bordered class="bg-teal-1">
                    <q-card-section class="q-pa-md">
                      <div class="text-caption text-grey-7">Grand Total</div>
                      <div class="text-h5 text-teal text-weight-bold">
                        {{ geminiStore.revenueResponse.grand_total_formatted }}
                      </div>
                      <div class="text-caption text-grey-6">
                        {{ geminiStore.revenueResponse.total_rows.toLocaleString() }} invoices
                      </div>
                    </q-card-section>
                  </q-card>
                </div>
              </q-card-section>
            </template>
          </q-card>

          <!-- Simple Chat Section -->
          <q-card flat bordered class="q-mt-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium q-mb-md">General Chat</div>

              <q-input
                v-model="chatMessage"
                type="textarea"
                outlined
                autogrow
                :rows="2"
                placeholder="Ask Gemini anything..."
              />

              <div class="row q-mt-md q-gutter-sm">
                <q-btn
                  color="secondary"
                  label="Chat"
                  icon="chat"
                  :loading="geminiStore.chatting"
                  :disable="!chatMessage.trim()"
                  @click="submitChat"
                />
                <q-btn
                  v-if="geminiStore.chatResponse"
                  flat
                  color="grey"
                  label="Clear"
                  @click="geminiStore.clearChatResponse(); chatMessage = ''"
                />
              </div>
            </q-card-section>

            <!-- Chat Response -->
            <template v-if="geminiStore.chatResponse">
              <q-separator />
              <q-card-section>
                <div class="row items-center justify-between q-mb-sm">
                  <div class="text-subtitle2 text-weight-medium">Response</div>
                  <div class="row items-center q-gutter-sm">
                    <q-btn
                      flat
                      dense
                      icon="content_copy"
                      color="secondary"
                      size="sm"
                      @click="copyChatResponse"
                    >
                      <q-tooltip>Copy response</q-tooltip>
                    </q-btn>
                    <q-chip v-if="geminiStore.chatResponse.tokens_used" dense size="sm" color="grey-3">
                      {{ geminiStore.chatResponse.tokens_used }} tokens
                    </q-chip>
                  </div>
                </div>
                <div class="response-content q-pa-md bg-grey-1 rounded-borders markdown-body" v-html="renderedChatResponse"></div>
              </q-card-section>
            </template>
          </q-card>
        </div>
      </div>
    </div>

    <!-- CSV Preview Dialog -->
    <q-dialog v-model="showPreviewDialog" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card>
        <q-card-section class="row items-center bg-primary text-white">
          <div class="text-h6">{{ previewFileName }}</div>
          <q-space />
          <q-btn icon="close" flat round dense color="white" v-close-popup />
        </q-card-section>

        <q-card-section v-if="geminiStore.loadingContent" class="text-center q-pa-xl">
          <q-spinner color="primary" size="48px" />
          <div class="q-mt-md text-grey">Loading file content...</div>
        </q-card-section>

        <q-card-section v-else-if="geminiStore.csvContent">
          <div class="text-caption q-mb-sm">
            {{ geminiStore.csvContent.row_count }} rows, {{ geminiStore.csvContent.headers.length }} columns
          </div>
          <div style="overflow-x: auto;">
            <q-table
              :rows="csvPreviewRows"
              :columns="csvPreviewColumns"
              flat
              bordered
              dense
              :rows-per-page-options="[10, 25, 50, 100]"
              :pagination="{ rowsPerPage: 25 }"
            />
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useGeminiStore, type DriveFolder, type CsvFile } from 'src/stores/gemini';
import { date, useQuasar, copyToClipboard } from 'quasar';
import { marked } from 'marked';

const $q = useQuasar();

const geminiStore = useGeminiStore();

const queryText = ref('');
const chatMessage = ref('');
const showPreviewDialog = ref(false);
const previewFileName = ref('');
const folderSearch = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const hasSingleFileSelected = computed(() => geminiStore.selectedFiles.length === 1);

const revenueColumns = [
  { name: 'shop', label: 'Shop', field: 'shop', align: 'left' as const, sortable: true },
  { name: 'total_formatted', label: 'Total Revenue', field: 'total_formatted', align: 'right' as const, sortable: true },
  { name: 'transaction_count', label: 'Invoices', field: 'transaction_count', align: 'right' as const, sortable: true },
];

const csvPreviewColumns = computed(() => {
  if (!geminiStore.csvContent?.headers) return [];
  return geminiStore.csvContent.headers.map((header, index) => ({
    name: `col_${index}`,
    label: header,
    field: header,
    align: 'left' as const,
    sortable: true,
  }));
});

const csvPreviewRows = computed(() => {
  if (!geminiStore.csvContent?.rows) return [];
  // Handle both array and object rows
  return geminiStore.csvContent.rows.map((row, index) => {
    if (Array.isArray(row)) {
      // Convert array to object using headers
      const obj: Record<string, string> = {};
      geminiStore.csvContent?.headers.forEach((header, i) => {
        obj[header] = row[i] || '';
      });
      return obj;
    }
    return row;
  });
});

// Markdown rendering for query response
const renderedQueryResponse = computed(() => {
  if (!geminiStore.queryResponse?.response) return '';
  return marked(geminiStore.queryResponse.response) as string;
});

// Markdown rendering for chat response
const renderedChatResponse = computed(() => {
  if (!geminiStore.chatResponse?.response) return '';
  return marked(geminiStore.chatResponse.response) as string;
});

// Copy response to clipboard
async function copyQueryResponse() {
  if (!geminiStore.queryResponse?.response) return;
  try {
    await copyToClipboard(geminiStore.queryResponse.response);
    $q.notify({
      type: 'positive',
      message: 'Response copied to clipboard',
      timeout: 2000,
    });
  } catch {
    $q.notify({
      type: 'negative',
      message: 'Failed to copy to clipboard',
      timeout: 2000,
    });
  }
}

async function copyChatResponse() {
  if (!geminiStore.chatResponse?.response) return;
  try {
    await copyToClipboard(geminiStore.chatResponse.response);
    $q.notify({
      type: 'positive',
      message: 'Response copied to clipboard',
      timeout: 2000,
    });
  } catch {
    $q.notify({
      type: 'negative',
      message: 'Failed to copy to clipboard',
      timeout: 2000,
    });
  }
}

function formatFileSize(bytes: number | null): string {
  if (bytes === null || bytes === undefined) return 'Unknown size';
  if (bytes >= 1048576) {
    return (bytes / 1048576).toFixed(2) + ' MB';
  } else if (bytes >= 1024) {
    return (bytes / 1024).toFixed(2) + ' KB';
  }
  return bytes + ' bytes';
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return date.formatDate(d, 'MMM D, YYYY');
}

async function refreshFolders() {
  await geminiStore.fetchFolders(geminiStore.currentFolderId || undefined);
}

function onSearchInput(value: string | number | null) {
  // Debounce search
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  const searchValue = String(value ?? '');
  if (searchValue && searchValue.length >= 2) {
    searchTimeout = setTimeout(() => {
      doFolderSearch();
    }, 300);
  } else {
    geminiStore.clearSearch();
  }
}

async function doFolderSearch() {
  if (folderSearch.value && folderSearch.value.length >= 2) {
    await geminiStore.searchFolders(folderSearch.value);
  }
}

function clearFolderSearch() {
  folderSearch.value = '';
  geminiStore.clearSearch();
}

async function selectSearchResult(folder: DriveFolder) {
  folderSearch.value = '';
  await geminiStore.selectSearchResult(folder);
}

async function navigateToFolder(folder: DriveFolder) {
  await geminiStore.navigateToFolder(folder);
}

async function navigateToBreadcrumb(folder: DriveFolder) {
  // Find the index of this folder in the stack and truncate
  const index = geminiStore.folderStack.findIndex(f => f.id === folder.id);
  if (index !== -1) {
    // Remove all folders after this one
    geminiStore.folderStack.splice(index + 1);
    await geminiStore.fetchFolders(folder.id);
    geminiStore.csvFiles = [];
    geminiStore.selectedFiles = [];
  }
}

async function loadCsvFiles() {
  if (geminiStore.currentFolderId) {
    await geminiStore.fetchCsvFiles(geminiStore.currentFolderId);
  }
}

async function previewFile(file: CsvFile) {
  previewFileName.value = file.name;
  showPreviewDialog.value = true;
  await geminiStore.getCsvContent(file.id);
}

async function submitQuery() {
  if (!geminiStore.hasSelectedFiles || !queryText.value.trim()) return;

  const firstFile = geminiStore.selectedFiles[0];
  if (geminiStore.selectedFiles.length === 1 && firstFile) {
    await geminiStore.queryCsv(firstFile.id, queryText.value);
  } else {
    await geminiStore.queryMultipleCsv(geminiStore.selectedFileIds, queryText.value);
  }
}

async function submitChat() {
  if (!chatMessage.value.trim()) return;
  await geminiStore.chat(chatMessage.value);
}

async function fetchRevenueByShop() {
  if (!hasSingleFileSelected.value) return;
  const file = geminiStore.selectedFiles[0];
  if (file) {
    await geminiStore.getRevenueByShop(file.id);
  }
}

onMounted(async () => {
  await geminiStore.fetchStatus();
  if (geminiStore.isDriveConfigured) {
    await geminiStore.fetchFolders();
  }
});
</script>

<style scoped>
.response-content {
  max-height: 500px;
  overflow-y: auto;
}

.bg-primary-1 {
  background-color: rgba(25, 118, 210, 0.1);
}

/* Markdown styles */
.markdown-body {
  font-size: 14px;
  line-height: 1.6;
}

.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4) {
  margin-top: 1em;
  margin-bottom: 0.5em;
  font-weight: 600;
}

.markdown-body :deep(h1) { font-size: 1.5em; }
.markdown-body :deep(h2) { font-size: 1.3em; }
.markdown-body :deep(h3) { font-size: 1.15em; }
.markdown-body :deep(h4) { font-size: 1em; }

.markdown-body :deep(p) {
  margin: 0.5em 0;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
  margin: 0.5em 0;
  padding-left: 1.5em;
}

.markdown-body :deep(li) {
  margin: 0.25em 0;
}

.markdown-body :deep(table) {
  border-collapse: collapse;
  width: 100%;
  margin: 1em 0;
  font-size: 13px;
}

.markdown-body :deep(th),
.markdown-body :deep(td) {
  border: 1px solid #ddd;
  padding: 8px 12px;
  text-align: left;
}

.markdown-body :deep(th) {
  background-color: #f5f5f5;
  font-weight: 600;
}

.markdown-body :deep(tr:nth-child(even)) {
  background-color: #fafafa;
}

.markdown-body :deep(code) {
  background-color: #f0f0f0;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.9em;
}

.markdown-body :deep(pre) {
  background-color: #2d2d2d;
  color: #f8f8f2;
  padding: 12px;
  border-radius: 6px;
  overflow-x: auto;
  margin: 1em 0;
}

.markdown-body :deep(pre code) {
  background: none;
  padding: 0;
  color: inherit;
}

.markdown-body :deep(blockquote) {
  border-left: 4px solid #ddd;
  margin: 1em 0;
  padding: 0.5em 1em;
  background-color: #f9f9f9;
  color: #666;
}

.markdown-body :deep(hr) {
  border: none;
  border-top: 1px solid #ddd;
  margin: 1.5em 0;
}

.markdown-body :deep(strong) {
  font-weight: 600;
}

.markdown-body :deep(a) {
  color: #1976d2;
  text-decoration: none;
}

.markdown-body :deep(a:hover) {
  text-decoration: underline;
}
</style>
