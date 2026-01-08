<template>
  <q-page padding>
    <div class="q-mb-md">
      <div class="text-h5">Transfer Staging</div>
      <div class="text-caption text-grey-6">Prepare parts for pickup</div>
    </div>

    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="40px" />
    </div>

    <div v-else-if="requests.length === 0" class="text-center text-grey-6 q-py-md">
      <q-icon name="inventory_2" size="64px" />
      <div class="q-mt-md">No transfer requests to stage</div>
    </div>

    <div v-else class="q-gutter-md">
      <q-card
        v-for="request in requests"
        :key="request.id"
      >
        <q-card-section>
          <div class="row items-center justify-between q-mb-sm">
            <div class="text-h6">{{ request.reference_number }}</div>
            <q-badge
              :color="getUrgencyColor(request)"
              :label="formatUrgency(request)"
            />
          </div>

          <div class="q-gutter-sm text-body2">
            <div><strong>Destination:</strong> {{ request.receiving_location?.name }}</div>
            <div><strong>Requested By:</strong> {{ request.created_by?.name }}</div>
            <div v-if="request.details"><strong>Details:</strong> {{ request.details }}</div>
            <div v-if="request.vendor_order_number">
              <strong>Order #:</strong> {{ request.vendor_order_number }}
            </div>
            <div v-if="request.special_instructions" class="text-orange-8">
              <q-icon name="warning" />
              <strong>Instructions:</strong> {{ request.special_instructions }}
            </div>
            <div class="text-caption text-grey-6">
              Created: {{ formatDateTime(request.created_at) }}
            </div>
          </div>
        </q-card-section>

        <q-separator />

        <q-card-actions>
          <q-btn
            flat
            color="positive"
            icon="check_circle"
            label="Ready to Transfer"
            @click="markReady(request)"
            class="col"
          />
          <q-btn
            flat
            color="negative"
            icon="cancel"
            label="Not Available"
            @click="markNotAvailable(request)"
            class="col"
          />
        </q-card-actions>
      </q-card>
    </div>

    <!-- Ready to Transfer Dialog -->
    <q-dialog v-model="showReadyDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Ready to Transfer</div>
          <div class="text-caption">Mark part as staged and ready for pickup</div>
        </q-card-section>

        <q-separator />

        <q-card-section>
          <q-input
            v-model="readyNote"
            type="textarea"
            label="Notes (optional)"
            filled
            rows="3"
            hint="e.g., Staged in Bay 3, Special handling required"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeReadyDialog" />
          <q-btn
            flat
            label="Confirm"
            color="positive"
            @click="confirmReady"
            :loading="executing"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Not Available Dialog -->
    <q-dialog v-model="showNotAvailableDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Not Available</div>
          <div class="text-caption">Explain why part cannot be transferred</div>
        </q-card-section>

        <q-separator />

        <q-card-section>
          <q-input
            v-model="notAvailableReason"
            type="textarea"
            label="Reason *"
            filled
            rows="3"
            autofocus
            :rules="[val => !!val || 'Reason is required']"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeNotAvailableDialog" />
          <q-btn
            flat
            label="Confirm"
            color="negative"
            @click="confirmNotAvailable"
            :loading="executing"
            :disable="!notAvailableReason"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePartsRequestsStore, type PartsRequest } from 'src/stores/partsRequests'
import { useAuthStore } from 'src/stores/auth'
import { useQuasar } from 'quasar'

const partsRequestsStore = usePartsRequestsStore()
const authStore = useAuthStore()
const $q = useQuasar()

const loading = ref(false)
const executing = ref(false)
const showReadyDialog = ref(false)
const showNotAvailableDialog = ref(false)
const selectedRequest = ref<PartsRequest | null>(null)
const readyNote = ref('')
const notAvailableReason = ref('')

const requests = computed(() => partsRequestsStore.requests.filter(r =>
  r.request_type.id === 3 && // Transfer type
  r.status.id === 1 && // New status
  r.origin_location_id === authStore.user?.primary_location_id
))

onMounted(async () => {
  await loadRequests()

  // Auto-refresh every 60 seconds
  setInterval(() => {
    loadRequests()
  }, 60000)
})

async function loadRequests() {
  loading.value = true
  try {
    // Fetch all requests, then filter in computed property
    await partsRequestsStore.fetchRequests()
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.message || 'Failed to load requests',
    })
  } finally {
    loading.value = false
  }
}

function markReady(request: PartsRequest) {
  selectedRequest.value = request
  showReadyDialog.value = true
}

function markNotAvailable(request: PartsRequest) {
  selectedRequest.value = request
  showNotAvailableDialog.value = true
}

async function confirmReady() {
  if (!selectedRequest.value) return

  executing.value = true
  try {
    const formData = new FormData()
    if (readyNote.value) {
      formData.append('note', readyNote.value)
    }

    await partsRequestsStore.executeAction(
      selectedRequest.value.id,
      'ready_to_transfer',
      formData
    )

    $q.notify({
      type: 'positive',
      message: 'Part marked as ready to transfer',
    })

    closeReadyDialog()
    await loadRequests()
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.message || 'Failed to mark as ready',
    })
  } finally {
    executing.value = false
  }
}

async function confirmNotAvailable() {
  if (!selectedRequest.value || !notAvailableReason.value) return

  executing.value = true
  try {
    const formData = new FormData()
    formData.append('note', notAvailableReason.value)

    await partsRequestsStore.executeAction(
      selectedRequest.value.id,
      'not_available',
      formData
    )

    $q.notify({
      type: 'positive',
      message: 'Part marked as not available',
    })

    closeNotAvailableDialog()
    await loadRequests()
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.message || 'Failed to mark as not available',
    })
  } finally {
    executing.value = false
  }
}

function closeReadyDialog() {
  showReadyDialog.value = false
  selectedRequest.value = null
  readyNote.value = ''
}

function closeNotAvailableDialog() {
  showNotAvailableDialog.value = false
  selectedRequest.value = null
  notAvailableReason.value = ''
}

function getUrgencyColor(request: PartsRequest): string {
  switch (request.urgency?.name) {
    case 'normal':
      return 'blue'
    case 'today':
      return 'orange'
    case 'asap':
      return 'deep-orange'
    case 'emergency':
      return 'negative'
    default:
      return 'grey'
  }
}

function formatUrgency(request: PartsRequest): string {
  return request.urgency?.name.toUpperCase() || 'NORMAL'
}

function formatDateTime(dateString: string): string {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}
</script>
