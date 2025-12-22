<template>
  <div class="request-action-buttons">
    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="40px" />
    </div>

    <div v-else-if="actions.length === 0" class="text-center text-grey-6 q-py-md">
      No actions available
    </div>

    <div v-else class="q-gutter-sm">
      <q-btn
        v-for="action in actions"
        :key="action.id"
        :label="action.display_label"
        :color="getActionColor(action)"
        :icon="action.display_icon"
        size="lg"
        class="full-width"
        style="min-height: 56px"
        @click="handleAction(action)"
      />
    </div>

    <!-- Action Dialog -->
    <q-dialog v-model="showActionDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">{{ selectedAction?.display_label }}</div>
        </q-card-section>

        <q-separator />

        <q-card-section class="q-gutter-md">
          <q-input
            v-if="selectedAction?.requires_note"
            v-model="actionData.note"
            type="textarea"
            label="Notes"
            filled
            rows="3"
            :rules="[val => !!val || 'Note is required']"
          />

          <div v-if="selectedAction?.requires_photo">
            <div class="text-subtitle2 q-mb-sm">Photo {{ selectedAction.requires_photo ? '(Required)' : '' }}</div>
            <q-file
              v-model="actionData.photoFile"
              label="Take or upload photo"
              filled
              accept="image/*"
              capture="environment"
              @update:model-value="onPhotoSelected"
            >
              <template v-slot:prepend>
                <q-icon name="photo_camera" />
              </template>
            </q-file>

            <div v-if="photoPreview" class="q-mt-sm">
              <q-img :src="photoPreview" style="max-width: 200px" />
            </div>
          </div>

          <q-checkbox
            v-model="actionData.captureLocation"
            label="Capture GPS location"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeActionDialog" />
          <q-btn
            flat
            label="Confirm"
            color="primary"
            @click="executeAction"
            :loading="executing"
            :disable="!canExecute"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePartsRequestsStore } from 'src/stores/partsRequests'
import { useQuasar } from 'quasar'

interface Props {
  requestId: number
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'action-executed'): void
}>()

const partsRequestsStore = usePartsRequestsStore()
const $q = useQuasar()

const actions = ref<any[]>([])
const loading = ref(false)
const showActionDialog = ref(false)
const selectedAction = ref<any>(null)
const executing = ref(false)

const actionData = ref({
  note: '',
  photoFile: null as File | null,
  captureLocation: true,
  lat: null as number | null,
  lng: null as number | null,
})

const photoPreview = ref<string | null>(null)

const canExecute = computed(() => {
  if (selectedAction.value?.requires_note && !actionData.value.note) {
    return false
  }
  if (selectedAction.value?.requires_photo && !actionData.value.photoFile) {
    return false
  }
  return true
})

onMounted(async () => {
  await loadActions()
})

async function loadActions() {
  loading.value = true
  try {
    actions.value = await partsRequestsStore.fetchAvailableActions(props.requestId)
  } finally {
    loading.value = false
  }
}

function getActionColor(action: any) {
  if (action.display_color) {
    return action.display_color
  }

  // Default colors based on action name patterns
  const actionName = action.action_name.toLowerCase()
  if (actionName.includes('ready') || actionName.includes('deliver')) return 'primary'
  if (actionName.includes('not_ready') || actionName.includes('unable')) return 'warning'
  if (actionName.includes('not_available')) return 'negative'
  return 'secondary'
}

function handleAction(action: any) {
  selectedAction.value = action
  showActionDialog.value = true
}

function onPhotoSelected(file: File | null) {
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      photoPreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  } else {
    photoPreview.value = null
  }
}

async function executeAction() {
  if (!selectedAction.value) return

  executing.value = true
  try {
    // Capture GPS if requested
    if (actionData.value.captureLocation) {
      try {
        const position = await getCurrentPosition()
        actionData.value.lat = position.coords.latitude
        actionData.value.lng = position.coords.longitude
      } catch (error) {
        console.error('Failed to get location', error)
        // Continue without location if user denies
      }
    }

    // Prepare form data
    const formData = new FormData()
    if (actionData.value.note) {
      formData.append('note', actionData.value.note)
    }
    if (actionData.value.photoFile) {
      formData.append('photo', actionData.value.photoFile)
    }
    if (actionData.value.lat) {
      formData.append('lat', actionData.value.lat.toString())
    }
    if (actionData.value.lng) {
      formData.append('lng', actionData.value.lng.toString())
    }

    await partsRequestsStore.executeAction(
      props.requestId,
      selectedAction.value.action_name,
      formData
    )

    $q.notify({
      type: 'positive',
      message: 'Action completed successfully',
    })

    emit('action-executed')
    closeActionDialog()
    await loadActions() // Refresh available actions
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.message || 'Failed to execute action',
    })
  } finally {
    executing.value = false
  }
}

function getCurrentPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocation not supported'))
      return
    }
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    })
  })
}

function closeActionDialog() {
  showActionDialog.value = false
  selectedAction.value = null
  actionData.value = {
    note: '',
    photoFile: null,
    captureLocation: true,
    lat: null,
    lng: null,
  }
  photoPreview.value = null
}
</script>
