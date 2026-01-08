<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" persistent>
    <q-card style="min-width: 400px; max-width: 95vw; width: 600px;">
      <q-card-section class="row items-center">
        <div class="text-h6">Import Customers from CSV</div>
        <q-space />
        <q-btn icon="close" flat round dense @click="closeDialog" />
      </q-card-section>

      <!-- Step 1: Upload -->
      <q-card-section v-if="step === 'upload'" class="q-pt-none">
        <div class="text-body2 q-mb-md">
          Upload a FullBay customer export CSV file. The system will automatically map fields
          and detect potential duplicates with existing customers.
        </div>

        <q-file
          v-model="selectedFile"
          label="Select CSV File"
          filled
          accept=".csv"
          class="q-mb-md"
        >
          <template v-slot:prepend>
            <q-icon name="attach_file" />
          </template>
        </q-file>

        <q-checkbox
          v-model="processSync"
          label="Process synchronously (wait for completion)"
          class="q-mb-md"
        />

        <div class="text-caption text-grey-7">
          <q-icon name="info" size="xs" class="q-mr-xs" />
          For large files (1000+ rows), we recommend unchecking synchronous processing.
        </div>
      </q-card-section>

      <!-- Step 2: Processing -->
      <q-card-section v-else-if="step === 'processing'" class="q-pt-none">
        <div class="text-center q-py-lg">
          <q-spinner-dots color="primary" size="50px" />
          <div class="text-body1 q-mt-md">Processing import...</div>
          <div v-if="currentImport" class="text-caption text-grey-7 q-mt-sm">
            {{ currentImport.original_filename }}
          </div>
        </div>

        <div v-if="currentImport && currentImport.status === 'processing'" class="q-mt-md">
          <q-linear-progress
            :value="progressValue"
            color="primary"
            class="q-mb-sm"
          />
          <div class="text-caption text-center">
            Processing... (Refresh to see progress)
          </div>
        </div>
      </q-card-section>

      <!-- Step 3: Results -->
      <q-card-section v-else-if="step === 'results'" class="q-pt-none">
        <div class="text-center q-mb-md">
          <q-icon
            :name="currentImport?.status === 'completed' ? 'check_circle' : 'error'"
            :color="currentImport?.status === 'completed' ? 'positive' : 'negative'"
            size="64px"
          />
          <div class="text-h6 q-mt-sm">
            {{ currentImport?.status === 'completed' ? 'Import Complete' : 'Import Failed' }}
          </div>
        </div>

        <div v-if="currentImport?.status === 'completed'" class="q-gutter-sm">
          <q-card flat bordered>
            <q-card-section class="q-py-sm">
              <div class="row q-col-gutter-md text-center">
                <div class="col-4">
                  <div class="text-h5 text-positive">{{ currentImport.created_count }}</div>
                  <div class="text-caption">Created</div>
                </div>
                <div class="col-4">
                  <div class="text-h5 text-info">{{ currentImport.updated_count }}</div>
                  <div class="text-caption">Updated</div>
                </div>
                <div class="col-4">
                  <div class="text-h5 text-warning">{{ currentImport.merge_needed_count }}</div>
                  <div class="text-caption">Need Review</div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <q-card v-if="currentImport.skipped_count > 0 || currentImport.error_count > 0" flat bordered>
            <q-card-section class="q-py-sm">
              <div class="row q-col-gutter-md text-center">
                <div class="col-6">
                  <div class="text-h5 text-grey">{{ currentImport.skipped_count }}</div>
                  <div class="text-caption">Skipped</div>
                </div>
                <div class="col-6">
                  <div class="text-h5 text-negative">{{ currentImport.error_count }}</div>
                  <div class="text-caption">Errors</div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <div v-if="currentImport.merge_needed_count > 0" class="text-center q-mt-md">
            <q-btn
              color="warning"
              label="Review Merge Candidates"
              icon="merge_type"
              @click="openMergeReview"
            />
          </div>
        </div>

        <div v-else-if="currentImport?.error_message" class="text-negative">
          <strong>Error:</strong> {{ currentImport.error_message }}
        </div>
      </q-card-section>

      <!-- Import History -->
      <q-card-section v-if="step === 'upload' && imports.length > 0" class="q-pt-none">
        <q-separator class="q-mb-md" />
        <div class="text-subtitle2 q-mb-sm">Recent Imports</div>
        <q-list bordered separator class="rounded-borders">
          <q-item
            v-for="imp in imports.slice(0, 5)"
            :key="imp.id"
            clickable
            @click="viewImport(imp)"
          >
            <q-item-section>
              <q-item-label>{{ imp.original_filename }}</q-item-label>
              <q-item-label caption>
                {{ formatDate(imp.created_at) }}
                <span v-if="imp.uploader"> by {{ imp.uploader.name }}</span>
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-badge
                :color="getStatusColor(imp.status)"
              >
                {{ imp.status }}
              </q-badge>
            </q-item-section>
            <q-item-section side>
              <div class="text-caption">
                {{ imp.created_count + imp.updated_count }} / {{ imp.total_rows }}
              </div>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn
          v-if="step === 'upload'"
          flat
          label="Cancel"
          @click="closeDialog"
        />
        <q-btn
          v-if="step === 'upload'"
          color="primary"
          label="Upload & Process"
          :loading="uploading"
          :disable="!selectedFile"
          @click="uploadFile"
        />
        <q-btn
          v-if="step === 'processing'"
          flat
          label="Check Status"
          @click="refreshStatus"
        />
        <q-btn
          v-if="step === 'results'"
          flat
          label="Close"
          @click="closeDialog"
        />
        <q-btn
          v-if="step === 'results'"
          color="primary"
          label="Import Another"
          @click="resetForm"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useCustomersStore } from 'src/stores/customers'
import type { CustomerImport } from 'src/types/customers'
import { date } from 'quasar'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'imported': []
}>()

const customersStore = useCustomersStore()

const step = ref<'upload' | 'processing' | 'results'>('upload')
const selectedFile = ref<File | null>(null)
const processSync = ref(true)
const uploading = ref(false)
const currentImport = ref<CustomerImport | null>(null)
const pollInterval = ref<number | null>(null)

const imports = computed(() => customersStore.imports)

const progressValue = computed(() => {
  if (!currentImport.value || currentImport.value.total_rows === 0) return 0
  const processed = currentImport.value.created_count +
    currentImport.value.updated_count +
    currentImport.value.skipped_count +
    currentImport.value.merge_needed_count +
    currentImport.value.error_count
  return processed / currentImport.value.total_rows
})

watch(() => props.modelValue, (val) => {
  if (val) {
    void loadImports()
  } else {
    stopPolling()
  }
})

async function loadImports() {
  await customersStore.fetchImports({ per_page: 10 })
}

async function uploadFile() {
  if (!selectedFile.value) return

  uploading.value = true
  try {
    const result = await customersStore.uploadImport(selectedFile.value, processSync.value)
    currentImport.value = result

    if (processSync.value) {
      // If sync processing, immediately show results
      step.value = 'results'
      emit('imported')
    } else {
      // If async, show processing and start polling
      step.value = 'processing'
      startPolling()
    }
  } catch (error) {
    console.error('Upload failed:', error)
  } finally {
    uploading.value = false
  }
}

function startPolling() {
  pollInterval.value = window.setInterval(() => {
    void (async () => {
      if (currentImport.value) {
        const updated = await customersStore.fetchImport(currentImport.value.id)
        currentImport.value = updated

        if (updated.status === 'completed' || updated.status === 'failed') {
          stopPolling()
          step.value = 'results'
          emit('imported')
        }
      }
    })()
  }, 3000)
}

function stopPolling() {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
    pollInterval.value = null
  }
}

async function refreshStatus() {
  if (currentImport.value) {
    const updated = await customersStore.fetchImport(currentImport.value.id)
    currentImport.value = updated

    if (updated.status === 'completed' || updated.status === 'failed') {
      step.value = 'results'
      emit('imported')
    }
  }
}

async function viewImport(imp: CustomerImport) {
  currentImport.value = await customersStore.fetchImport(imp.id)
  if (imp.status === 'processing' || imp.status === 'pending') {
    step.value = 'processing'
    startPolling()
  } else {
    step.value = 'results'
  }
}

function openMergeReview() {
  closeDialog()
  emit('imported')
}

function resetForm() {
  step.value = 'upload'
  selectedFile.value = null
  currentImport.value = null
  void loadImports()
}

function closeDialog() {
  stopPolling()
  emit('update:modelValue', false)
  // Reset after animation
  setTimeout(() => {
    step.value = 'upload'
    selectedFile.value = null
    currentImport.value = null
  }, 300)
}

function formatDate(dateString: string): string {
  return date.formatDate(dateString, 'MMM D, YYYY h:mm A')
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'completed': return 'positive'
    case 'processing': return 'info'
    case 'pending': return 'grey'
    case 'failed': return 'negative'
    default: return 'grey'
  }
}

onMounted(() => {
  if (props.modelValue) {
    void loadImports()
  }
})
</script>
