<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :maximized="$q.screen.lt.md"
    :full-width="$q.screen.lt.md"
  >
    <q-card v-if="item" class="column" :class="dialogCardClass">
      <!-- Header -->
      <q-card-section class="row items-center q-py-sm bg-grey-2">
        <div class="text-subtitle1 text-weight-medium">{{ item.reference_number }}</div>
        <q-chip
          dense
          size="sm"
          :color="item.status.color || 'grey'"
          text-color="white"
          class="q-ml-sm"
        >
          {{ item.status.display_name }}
        </q-chip>
        <q-space />
        <q-btn icon="close" flat round dense size="sm" @click="close" />
      </q-card-section>

      <!-- Scrollable Content -->
      <q-card-section class="col q-pa-md" style="overflow-y: auto;">
        <div class="view-dialog-content">
          <!-- Status & Urgency Chips -->
          <div class="row items-center chip-row">
            <q-chip
              dense
              size="sm"
              :color="item.status.color || 'grey'"
              text-color="white"
            >
              {{ item.status.display_name }}
            </q-chip>
            <q-chip
              v-if="item.urgency"
              dense
              size="sm"
              :color="item.urgency.color || 'orange'"
              text-color="white"
            >
              {{ item.urgency.name }}
            </q-chip>
          </div>

          <!-- From → To Side by Side -->
          <div class="row items-start from-to-row bg-grey-1 q-pa-sm rounded-borders">
            <!-- From -->
            <div class="col">
              <div class="text-caption text-weight-bold text-grey-8">FROM</div>
              <div class="text-body2 text-weight-medium text-primary">
                {{ item.origin.location_name || item.origin.stop_name || 'N/A' }}
              </div>
              <div v-if="item.origin.vendor_name && item.origin.vendor_name !== item.origin.location_name" class="text-caption text-grey-6">
                {{ item.origin.vendor_name }}
              </div>
            </div>

            <!-- Arrow -->
            <div class="flex items-center q-px-sm" style="padding-top: 16px;">
              <q-icon name="arrow_forward" size="md" color="grey-6" />
            </div>

            <!-- To -->
            <div class="col">
              <div class="text-caption text-weight-bold text-grey-8">TO</div>
              <div class="text-body2 text-weight-medium text-positive">
                {{ item.destination.location_name || item.destination.stop_name || 'N/A' }}
              </div>
            </div>
          </div>

          <!-- Details -->
          <div v-if="item.details">
            <div class="text-caption text-grey-7">Details</div>
            <div class="text-body2" style="word-break: break-word;">{{ item.details }}</div>
          </div>

          <!-- Special Instructions -->
          <div v-if="item.special_instructions" class="bg-orange-1 q-pa-sm rounded-borders">
            <div class="text-caption text-orange-9">
              <q-icon name="warning" size="xs" class="q-mr-xs" />
              {{ item.special_instructions }}
            </div>
          </div>

          <!-- Collapsible Sections -->
          <q-list>
            <!-- Line Items Section -->
            <q-expansion-item
              v-if="lineItemsCount > 0"
              group="runnerItemSections"
              dense
              header-class="bg-grey-2 text-grey-8"
              expand-icon-class="text-grey-7"
            >
              <template v-slot:header>
                <q-item-section>
                  <q-item-label class="text-caption text-weight-medium">
                    Line Items
                    <q-badge color="primary" class="q-ml-sm">
                      {{ lineItemsCount }}
                    </q-badge>
                  </q-item-label>
                </q-item-section>
              </template>
              <div class="q-pa-sm">
                <q-list dense separator>
                  <q-item v-for="lineItem in item.line_items" :key="lineItem.id">
                    <q-item-section>
                      <q-item-label>
                        {{ lineItem.description || 'No description' }}
                      </q-item-label>
                      <q-item-label caption>
                        <span v-if="lineItem.part_number">PN: {{ lineItem.part_number }}</span>
                        <span v-if="lineItem.part_number && lineItem.quantity"> | </span>
                        <span>Qty: {{ lineItem.quantity }}</span>
                      </q-item-label>
                      <q-item-label v-if="lineItem.notes" caption class="text-grey-7">
                        {{ lineItem.notes }}
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-icon
                        v-if="lineItem.is_verified"
                        name="verified"
                        color="positive"
                        size="sm"
                      />
                    </q-item-section>
                  </q-item>
                </q-list>
              </div>
            </q-expansion-item>

            <!-- Notes Section -->
            <q-expansion-item
              group="runnerItemSections"
              dense
              header-class="bg-grey-2 text-grey-8"
              expand-icon-class="text-grey-7"
              default-opened
            >
              <template v-slot:header>
                <q-item-section>
                  <q-item-label class="text-caption text-weight-medium">
                    Notes
                    <q-badge v-if="notesCount > 0" color="orange" class="q-ml-sm">
                      {{ notesCount }}
                    </q-badge>
                  </q-item-label>
                </q-item-section>
              </template>
              <div class="q-pa-sm">
                <!-- Existing Notes -->
                <q-list v-if="item.notes && item.notes.length > 0" dense separator class="q-mb-sm">
                  <q-item v-for="note in item.notes" :key="note.id">
                    <q-item-section>
                      <q-item-label>{{ note.content }}</q-item-label>
                      <q-item-label caption>
                        {{ note.user_name }} - {{ formatNoteDate(note.created_at) }}
                        <span v-if="note.is_edited" class="text-grey-6">(edited)</span>
                      </q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
                <div v-else class="text-grey-6 text-caption q-mb-sm">No notes yet</div>

                <!-- Add Note Form -->
                <div class="q-mt-sm">
                  <q-input
                    v-model="newNoteContent"
                    type="textarea"
                    label="Add a note"
                    outlined
                    dense
                    autogrow
                    :rows="2"
                    class="q-mb-sm"
                  />
                  <q-btn
                    color="primary"
                    label="Add Note"
                    size="sm"
                    :loading="addingNote"
                    :disable="!newNoteContent.trim()"
                    @click="addNote"
                  />
                </div>
              </div>
            </q-expansion-item>

            <!-- Documents Section -->
            <q-expansion-item
              v-if="documentsCount > 0"
              group="runnerItemSections"
              dense
              header-class="bg-grey-2 text-grey-8"
              expand-icon-class="text-grey-7"
            >
              <template v-slot:header>
                <q-item-section>
                  <q-item-label class="text-caption text-weight-medium">
                    Documents
                    <q-badge color="primary" class="q-ml-sm">
                      {{ documentsCount }}
                    </q-badge>
                  </q-item-label>
                </q-item-section>
              </template>
              <div class="q-pa-sm">
                <q-list dense separator>
                  <q-item
                    v-for="doc in item.documents"
                    :key="doc.id"
                    clickable
                    @click="openDocument(doc)"
                  >
                    <q-item-section avatar>
                      <q-icon :name="doc.icon || 'insert_drive_file'" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>{{ doc.original_filename }}</q-item-label>
                      <q-item-label caption>
                        {{ doc.formatted_file_size }}
                        <span v-if="doc.description"> - {{ doc.description }}</span>
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-icon name="open_in_new" size="xs" color="grey-6" />
                    </q-item-section>
                  </q-item>
                </q-list>
              </div>
            </q-expansion-item>

            <!-- Photos Section -->
            <q-expansion-item
              v-if="item.photos.length > 0"
              group="runnerItemSections"
              dense
              header-class="bg-grey-2 text-grey-8"
              expand-icon-class="text-grey-7"
            >
              <template v-slot:header>
                <q-item-section>
                  <q-item-label class="text-caption text-weight-medium">
                    Photos
                    <q-badge color="primary" class="q-ml-sm">
                      {{ item.photos.length }}
                    </q-badge>
                  </q-item-label>
                </q-item-section>
              </template>
              <div class="q-pa-sm">
                <div class="row q-gutter-sm">
                  <q-img
                    v-for="photo in item.photos"
                    :key="photo.id"
                    :src="photo.url"
                    :ratio="1"
                    class="rounded-borders cursor-pointer"
                    style="width: 80px; height: 80px"
                    @click="previewPhoto(photo.url)"
                  >
                    <div class="absolute-bottom text-center text-caption bg-dark-transparent">
                      {{ photo.type }}
                    </div>
                  </q-img>
                </div>
              </div>
            </q-expansion-item>
          </q-list>
        </div>
      </q-card-section>

      <!-- Footer Actions -->
      <q-card-actions class="bg-grey-1 q-pa-sm" :class="footerActionsClass">
        <!-- Left side: Photo buttons -->
        <div class="row items-center q-gutter-sm">
          <q-btn
            v-if="!item.is_completed && item.action_at_stop === 'pickup' && !item.has_pickup_photo"
            flat
            dense
            size="sm"
            color="primary"
            icon="photo_camera"
            label="Pickup Photo"
            @click="takePhoto('pickup')"
            :loading="uploadingPhoto"
          />
          <q-btn
            v-if="!item.is_completed && item.action_at_stop === 'dropoff' && !item.has_delivery_photo"
            flat
            dense
            size="sm"
            color="primary"
            icon="photo_camera"
            label="Delivery Photo"
            @click="takePhoto('delivery')"
            :loading="uploadingPhoto"
          />
        </div>

        <!-- Right side: Status buttons -->
        <div class="row items-center q-gutter-sm">
          <template v-if="!item.is_completed">
            <q-btn
              v-for="status in item.valid_transitions"
              :key="status"
              flat
              dense
              size="sm"
              :color="getStatusButtonColor(status)"
              :label="formatStatusLabel(status)"
              @click="updateStatus(status)"
              :loading="updatingStatus"
              :disable="needsPhotoFirst(status)"
            />
            <q-btn
              v-if="!item.valid_transitions.includes('exception')"
              flat
              dense
              size="sm"
              color="negative"
              label="Exception"
              @click="showExceptionDialog = true"
            />
          </template>
          <div v-else class="text-positive text-weight-medium">
            <q-icon name="check_circle" class="q-mr-xs" />
            Completed
          </div>
        </div>
      </q-card-actions>

      <!-- Photo Required Warning -->
      <div
        v-if="photoRequiredWarning"
        class="bg-orange-1 q-pa-xs text-center text-caption text-orange-9"
      >
        <q-icon name="info" size="xs" class="q-mr-xs" />
        {{ photoRequiredWarning }}
      </div>
    </q-card>

    <!-- Exception Dialog -->
    <q-dialog v-model="showExceptionDialog">
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">Mark as Exception</div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model="exceptionReason"
            type="textarea"
            label="Reason"
            outlined
            autofocus
            :rules="[(v) => !!v || 'Reason is required']"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="negative"
            label="Mark Exception"
            @click="markException"
            :loading="markingException"
            :disable="!exceptionReason.trim()"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Photo Preview -->
    <q-dialog v-model="showPhotoPreview" maximized>
      <q-card class="bg-black">
        <q-btn
          flat
          round
          icon="close"
          color="white"
          class="absolute-top-right q-ma-sm"
          style="z-index: 1"
          @click="showPhotoPreview = false"
        />
        <q-img
          :src="previewPhotoUrl"
          fit="contain"
          class="full-height full-width"
        />
      </q-card>
    </q-dialog>

    <!-- Hidden file input for camera -->
    <input
      ref="fileInput"
      type="file"
      accept="image/*"
      capture="environment"
      style="display: none"
      @change="onFileSelected"
    />
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRunnerItemsStore } from 'stores/runnerItems';
import { useQuasar } from 'quasar';

interface ItemStatus {
  id: number;
  name: string;
  display_name: string;
  color: string;
}

interface ItemPhoto {
  id: number;
  type: string;
  url: string;
  taken_at: string;
}

interface LineItem {
  id: number;
  description: string | null;
  quantity: number;
  part_number: string | null;
  notes: string | null;
  is_verified: boolean;
}

interface Document {
  id: number;
  original_filename: string;
  description: string | null;
  url: string;
  mime_type: string;
  formatted_file_size: string;
  icon: string;
  is_previewable: boolean;
}

interface Note {
  id: number;
  content: string;
  user_name: string;
  created_at: string;
  is_edited: boolean;
}

interface RunnerItem {
  id: number;
  reference_number: string;
  status: ItemStatus;
  urgency: { id: number; name: string; color: string } | null;
  details: string | null;
  special_instructions: string | null;
  origin: { location_id: number | null; location_name: string | null; stop_id: number | null; stop_name: string | null; vendor_id?: number | null; vendor_name?: string | null };
  destination: { location_id: number | null; location_name: string | null; stop_id: number | null; stop_name: string | null };
  action_at_stop: 'pickup' | 'dropoff' | null;
  is_completed: boolean;
  has_pickup_photo: boolean;
  has_delivery_photo: boolean;
  photos: ItemPhoto[];
  valid_transitions: string[];
  line_items?: LineItem[] | null;
  documents?: Document[] | null;
  notes?: Note[] | null;
  line_items_count?: number;
  documents_count?: number;
  notes_count?: number;
}

const props = defineProps<{
  modelValue: boolean;
  item: RunnerItem | null;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'status-updated'): void;
  (e: 'photo-uploaded'): void;
}>();

const itemsStore = useRunnerItemsStore();
const $q = useQuasar();

const fileInput = ref<HTMLInputElement | null>(null);
const pendingPhotoType = ref<'pickup' | 'delivery' | 'exception' | 'other'>('pickup');
const uploadingPhoto = ref(false);
const updatingStatus = ref(false);
const showExceptionDialog = ref(false);
const exceptionReason = ref('');
const markingException = ref(false);
const showPhotoPreview = ref(false);
const previewPhotoUrl = ref('');
const newNoteContent = ref('');
const addingNote = ref(false);

// Computed for dialog card class (responsive sizing)
const dialogCardClass = computed(() => {
  if ($q.screen.lt.md) {
    return 'full-height';
  }
  return 'dialog-card-desktop';
});

// Computed for footer actions layout
const footerActionsClass = computed(() => {
  if ($q.screen.lt.sm) {
    return 'column items-stretch';
  }
  return 'justify-between';
});

// Computed counts
const lineItemsCount = computed(() => props.item?.line_items_count ?? props.item?.line_items?.length ?? 0);
const documentsCount = computed(() => props.item?.documents_count ?? props.item?.documents?.length ?? 0);
const notesCount = computed(() => props.item?.notes_count ?? props.item?.notes?.length ?? 0);

const photoRequiredWarning = computed(() => {
  if (!props.item) return null;

  if (
    props.item.action_at_stop === 'pickup' &&
    !props.item.has_pickup_photo &&
    props.item.valid_transitions.includes('picked_up')
  ) {
    return 'Take pickup photo before marking as picked up';
  }

  if (
    props.item.action_at_stop === 'dropoff' &&
    !props.item.has_delivery_photo &&
    props.item.valid_transitions.includes('delivered')
  ) {
    return 'Take delivery photo before marking as delivered';
  }

  return null;
});

// Reset new note when dialog opens/closes
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    newNoteContent.value = '';
  }
});

const close = () => {
  emit('update:modelValue', false);
};

const getStatusButtonColor = (status: string) => {
  switch (status) {
    case 'picked_up':
      return 'primary';
    case 'delivered':
      return 'positive';
    case 'in_transit':
      return 'info';
    case 'exception':
      return 'negative';
    default:
      return 'grey';
  }
};

const formatStatusLabel = (status: string) => {
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

const formatNoteDate = (dateStr: string) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const needsPhotoFirst = (status: string) => {
  if (!props.item) return false;

  if (status === 'picked_up' && !props.item.has_pickup_photo) {
    return true;
  }
  if (status === 'delivered' && !props.item.has_delivery_photo) {
    return true;
  }
  return false;
};

const takePhoto = (type: 'pickup' | 'delivery' | 'exception' | 'other') => {
  pendingPhotoType.value = type;
  fileInput.value?.click();
};

const onFileSelected = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (!file || !props.item) return;

  uploadingPhoto.value = true;

  const result = await itemsStore.uploadPhoto(
    props.item.id,
    file,
    pendingPhotoType.value
  );

  uploadingPhoto.value = false;

  // Clear the input
  if (fileInput.value) {
    fileInput.value.value = '';
  }

  if (result.success) {
    $q.notify({
      type: 'positive',
      message: 'Photo uploaded',
      position: 'top',
    });
    emit('photo-uploaded');
  } else {
    $q.notify({
      type: 'negative',
      message: result.error || 'Failed to upload photo',
      position: 'top',
    });
  }
};

const updateStatus = async (status: string) => {
  if (!props.item) return;

  updatingStatus.value = true;

  const result = await itemsStore.updateStatus(props.item.id, status);

  updatingStatus.value = false;

  if (result.success) {
    $q.notify({
      type: 'positive',
      message: `Status updated to ${formatStatusLabel(status)}`,
      position: 'top',
    });
    emit('status-updated');
    close();
  } else if ((result as { requiresPhoto?: string }).requiresPhoto) {
    $q.notify({
      type: 'warning',
      message: result.error || 'Photo required',
      position: 'top',
    });
  } else {
    $q.notify({
      type: 'negative',
      message: result.error || 'Failed to update status',
      position: 'top',
    });
  }
};

const markException = async () => {
  if (!props.item || !exceptionReason.value.trim()) return;

  markingException.value = true;

  const result = await itemsStore.markException(
    props.item.id,
    exceptionReason.value.trim()
  );

  markingException.value = false;

  if (result.success) {
    showExceptionDialog.value = false;
    exceptionReason.value = '';
    $q.notify({
      type: 'info',
      message: 'Marked as exception',
      position: 'top',
    });
    emit('status-updated');
    close();
  } else {
    $q.notify({
      type: 'negative',
      message: result.error || 'Failed to mark exception',
      position: 'top',
    });
  }
};

const addNote = async () => {
  if (!props.item || !newNoteContent.value.trim()) return;

  addingNote.value = true;

  const result = await itemsStore.addNote(
    props.item.id,
    newNoteContent.value.trim()
  );

  addingNote.value = false;

  if (result.success) {
    newNoteContent.value = '';
    $q.notify({
      type: 'positive',
      message: 'Note added',
      position: 'top',
    });
  } else {
    $q.notify({
      type: 'negative',
      message: result.error || 'Failed to add note',
      position: 'top',
    });
  }
};

const previewPhoto = (url: string) => {
  previewPhotoUrl.value = url;
  showPhotoPreview.value = true;
};

const openDocument = (doc: Document) => {
  window.open(doc.url, '_blank');
};
</script>

<style scoped>
.dialog-card-desktop {
  width: 100%;
  max-width: 550px;
  max-height: 90vh;
}

.view-dialog-content > * {
  margin-bottom: 16px;
}

.view-dialog-content > *:last-child {
  margin-bottom: 0;
}

.chip-row {
  gap: 4px;
}

.from-to-row {
  gap: 8px;
}

.bg-dark-transparent {
  background-color: rgba(0, 0, 0, 0.5);
}
</style>
