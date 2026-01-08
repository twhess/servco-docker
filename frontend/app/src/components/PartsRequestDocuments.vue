<template>
  <div class="parts-request-documents">
    <!-- Header -->
    <div class="row items-center no-wrap q-mb-xs">
      <div class="text-caption text-weight-medium text-grey-8">Documents</div>
      <q-space />
      <q-btn
        v-if="!readonly && requestId"
        flat
        dense
        size="xs"
        color="primary"
        icon="attach_file"
        label="Attach"
        @click="triggerFileInput"
      />
      <input
        ref="fileInputRef"
        type="file"
        class="hidden"
        accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.txt,.csv"
        @change="handleFileSelect"
      />
    </div>

    <!-- Documents List -->
    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="24px" />
    </div>

    <div v-else-if="documents.length === 0" class="text-grey-6 text-center q-py-md">
      No documents attached
    </div>

    <q-list v-else separator class="rounded-borders bg-grey-1">
      <q-item
        v-for="doc in documents"
        :key="doc.id"
        clickable
        @click="previewDocument(doc)"
        class="q-py-sm"
      >
        <q-item-section avatar>
          <q-icon :name="getDocIcon(doc)" color="grey-7" />
        </q-item-section>

        <q-item-section>
          <q-item-label class="text-weight-medium ellipsis">
            {{ doc.original_filename }}
          </q-item-label>
          <q-item-label caption>
            {{ doc.formatted_file_size }}
            <template v-if="doc.uploaded_by">
              - by {{ doc.uploaded_by.name }}
            </template>
          </q-item-label>
          <q-item-label v-if="doc.description" caption class="text-grey-7">
            {{ doc.description }}
          </q-item-label>
        </q-item-section>

        <q-item-section side>
          <div class="row no-wrap q-gutter-xs">
            <q-btn
              flat
              dense
              round
              size="sm"
              icon="download"
              color="grey-7"
              @click.stop="downloadDocument(doc)"
            />
            <q-btn
              v-if="!readonly"
              flat
              dense
              round
              size="sm"
              icon="delete"
              color="negative"
              @click.stop="confirmDelete(doc)"
            />
          </div>
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Upload Progress Dialog -->
    <q-dialog v-model="showUploadDialog" persistent>
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">Upload Document</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <div class="text-body2 q-mb-sm">{{ uploadFileName }}</div>
          <q-input
            v-model="uploadDescription"
            label="Description (optional)"
            outlined
            dense
          />
        </q-card-section>

        <q-card-section v-if="uploading" class="q-pt-none">
          <q-linear-progress indeterminate color="primary" />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" color="grey-7" @click="cancelUpload" :disable="uploading" />
          <q-btn flat label="Upload" color="primary" @click="uploadFile" :loading="uploading" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Preview Dialog -->
    <q-dialog v-model="showPreviewDialog" maximized>
      <q-card class="column full-height">
        <q-card-section class="row items-center q-pb-none bg-grey-2">
          <div class="text-h6 ellipsis" style="max-width: 70%">
            {{ previewDoc?.original_filename }}
          </div>
          <q-space />
          <q-btn
            flat
            dense
            round
            icon="download"
            @click="downloadDocument(previewDoc!)"
          />
          <q-btn
            flat
            dense
            round
            icon="close"
            v-close-popup
          />
        </q-card-section>

        <q-card-section class="col q-pa-none" style="overflow: auto">
          <!-- Image Preview -->
          <template v-if="previewDoc && isImage(previewDoc)">
            <div class="flex flex-center full-height bg-grey-10 q-pa-md">
              <img
                :src="getInlineUrl(previewDoc)"
                :alt="previewDoc.original_filename"
                style="max-width: 100%; max-height: 100%; object-fit: contain"
              />
            </div>
          </template>

          <!-- PDF Preview -->
          <template v-else-if="previewDoc && isPdf(previewDoc)">
            <iframe
              :src="getInlineUrl(previewDoc)"
              class="full-width full-height"
              style="border: none"
            />
          </template>

          <!-- Non-previewable -->
          <template v-else>
            <div class="flex flex-center full-height column q-pa-lg text-center">
              <q-icon name="insert_drive_file" size="64px" color="grey-5" />
              <div class="text-h6 q-mt-md">{{ previewDoc?.original_filename }}</div>
              <div class="text-body2 text-grey-7 q-mb-md">
                {{ previewDoc?.formatted_file_size }}
              </div>
              <q-btn
                color="primary"
                icon="download"
                label="Download File"
                @click="downloadDocument(previewDoc!)"
              />
            </div>
          </template>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import { usePartsRequestsStore, type PartsRequestDocument } from 'src/stores/partsRequests';

const props = defineProps<{
  requestId: number | null;
  readonly?: boolean;
}>();

const emit = defineEmits<{
  (e: 'count-changed', count: number): void;
}>();

const $q = useQuasar();
const store = usePartsRequestsStore();

const documents = ref<PartsRequestDocument[]>([]);
const loading = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

// Upload state
const showUploadDialog = ref(false);
const uploadFile$ = ref<File | null>(null);
const uploadFileName = ref('');
const uploadDescription = ref('');
const uploading = ref(false);

// Preview state
const showPreviewDialog = ref(false);
const previewDoc = ref<PartsRequestDocument | null>(null);

async function loadDocuments() {
  if (!props.requestId) {
    documents.value = [];
    return;
  }

  loading.value = true;
  try {
    documents.value = await store.fetchDocuments(props.requestId);
    emit('count-changed', documents.value.length);
  } catch {
    // Error handled in store
  } finally {
    loading.value = false;
  }
}

function triggerFileInput() {
  fileInputRef.value?.click();
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (file) {
    uploadFile$.value = file;
    uploadFileName.value = file.name;
    uploadDescription.value = '';
    showUploadDialog.value = true;
  }
  // Reset input so same file can be selected again
  input.value = '';
}

function cancelUpload() {
  showUploadDialog.value = false;
  uploadFile$.value = null;
  uploadFileName.value = '';
  uploadDescription.value = '';
}

async function uploadFile() {
  if (!props.requestId || !uploadFile$.value) return;

  uploading.value = true;
  try {
    const newDoc = await store.uploadDocument(
      props.requestId,
      uploadFile$.value,
      uploadDescription.value || undefined
    );
    documents.value.unshift(newDoc);
    showUploadDialog.value = false;
    uploadFile$.value = null;
    uploadFileName.value = '';
    uploadDescription.value = '';
  } catch {
    // Error handled in store
  } finally {
    uploading.value = false;
  }
}

function previewDocument(doc: PartsRequestDocument) {
  previewDoc.value = doc;
  showPreviewDialog.value = true;
}

function downloadDocument(doc: PartsRequestDocument) {
  // Open download URL in new tab
  window.open(doc.url, '_blank');
}

function confirmDelete(doc: PartsRequestDocument) {
  $q.dialog({
    title: 'Delete Document',
    message: `Are you sure you want to delete "${doc.original_filename}"?`,
    cancel: true,
    persistent: true,
  }).onOk(() => {
    void (async () => {
      if (!props.requestId) return;
      try {
        await store.deleteDocument(props.requestId, doc.id);
        documents.value = documents.value.filter(d => d.id !== doc.id);
      } catch {
        // Error handled in store
      }
    })();
  });
}

function getDocIcon(doc: PartsRequestDocument): string {
  if (isImage(doc)) return 'image';
  if (isPdf(doc)) return 'picture_as_pdf';

  const ext = doc.original_filename.split('.').pop()?.toLowerCase();
  switch (ext) {
    case 'doc':
    case 'docx':
      return 'description';
    case 'xls':
    case 'xlsx':
      return 'table_chart';
    case 'txt':
      return 'article';
    case 'zip':
    case 'rar':
      return 'folder_zip';
    default:
      return 'insert_drive_file';
  }
}

function isImage(doc: PartsRequestDocument): boolean {
  return doc.mime_type.startsWith('image/');
}

function isPdf(doc: PartsRequestDocument): boolean {
  return doc.mime_type === 'application/pdf';
}

function getInlineUrl(doc: PartsRequestDocument): string {
  // Add inline=1 query param for serving content inline (for preview)
  const separator = doc.url.includes('?') ? '&' : '?';
  return `${doc.url}${separator}inline=1`;
}

watch(() => props.requestId, () => {
  void loadDocuments();
}, { immediate: true });

onMounted(() => {
  if (props.requestId) {
    void loadDocuments();
  }
});
</script>

<style scoped>
.parts-request-documents {
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

.hidden {
  display: none;
}

.parts-request-documents :deep(.q-item) {
  min-height: 40px;
  padding: 4px 8px;
}

.parts-request-documents :deep(.q-item__section--avatar) {
  min-width: 32px;
  padding-right: 8px;
}

.parts-request-documents :deep(.q-item__section--main) {
  min-width: 0;
  overflow: hidden;
}

.parts-request-documents :deep(.q-item__label) {
  word-break: break-word;
  overflow-wrap: break-word;
}

.parts-request-documents :deep(.q-item__section--side) {
  padding-left: 4px;
  flex-shrink: 0;
}
</style>
