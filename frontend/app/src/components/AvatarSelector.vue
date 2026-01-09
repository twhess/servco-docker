<template>
  <div class="avatar-selector">
    <!-- Current Avatar Preview -->
    <div class="text-center q-mb-md">
      <q-avatar size="100px" class="shadow-2">
        <!-- Custom uploaded image -->
        <img v-if="displayAvatarUrl" :src="displayAvatarUrl" />

        <!-- Preset: Initials with color -->
        <span
          v-else-if="presetAvatarData?.type === 'initials'"
          :class="`bg-${presetAvatarData.color} text-white flex flex-center`"
          style="width: 100%; height: 100%; font-size: 36px"
        >
          {{ initials }}
        </span>

        <!-- Preset: Icon -->
        <div
          v-else-if="presetAvatarData?.type === 'icon'"
          :class="`bg-${presetAvatarData.color} text-white flex flex-center`"
          style="width: 100%; height: 100%"
        >
          <q-icon :name="presetAvatarData.name" size="48px" />
        </div>

        <!-- Preset: Solid color -->
        <div
          v-else-if="presetAvatarData?.type === 'solid'"
          :class="`bg-${presetAvatarData.color}`"
          style="width: 100%; height: 100%"
        />

        <!-- Default: Initials fallback -->
        <span
          v-else
          class="bg-primary text-white flex flex-center"
          style="width: 100%; height: 100%; font-size: 36px"
        >
          {{ initials }}
        </span>
      </q-avatar>
    </div>

    <!-- Action Links -->
    <div class="row justify-center q-gutter-md q-mb-md">
      <a
        href="#"
        class="avatar-link text-primary"
        @click.prevent="triggerFileInput"
      >
        <q-icon name="upload" size="xs" class="q-mr-xs" />
        <span>{{ uploading ? 'Uploading...' : 'Upload Photo' }}</span>
        <q-spinner-dots v-if="uploading" size="14px" class="q-ml-xs" />
      </a>
      <a
        href="#"
        class="avatar-link text-secondary"
        @click.prevent="showPresetDialog = true"
      >
        <q-icon name="face" size="xs" class="q-mr-xs" />
        <span>Choose Avatar</span>
      </a>
      <a
        v-if="currentAvatar"
        href="#"
        class="avatar-link text-negative"
        @click.prevent="handleRemoveAvatar"
      >
        <q-icon name="delete" size="xs" class="q-mr-xs" />
        <span>{{ removing ? 'Removing...' : 'Remove' }}</span>
        <q-spinner-dots v-if="removing" size="14px" class="q-ml-xs" />
      </a>
    </div>

    <!-- Hidden File Input -->
    <input
      ref="fileInput"
      type="file"
      accept="image/*"
      style="display: none"
      @change="handleFileSelect"
    />

    <!-- Preset Avatar Selection Dialog -->
    <q-dialog v-model="showPresetDialog">
      <q-card style="min-width: 350px; max-width: 500px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Choose an Avatar</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-tabs v-model="presetTab" dense class="q-mb-md">
            <q-tab name="initials" label="Initials" />
            <q-tab name="icons" label="Icons" />
            <q-tab name="colors" label="Colors" />
          </q-tabs>

          <q-tab-panels v-model="presetTab" animated>
            <!-- Initials Tab -->
            <q-tab-panel name="initials" class="q-pa-none">
              <div class="text-caption text-grey q-mb-sm">
                Choose a background color for your initials
              </div>
              <div class="row q-gutter-sm justify-center">
                <q-avatar
                  v-for="color in initialsColors"
                  :key="color"
                  size="60px"
                  :color="color"
                  text-color="white"
                  class="cursor-pointer avatar-option"
                  :class="{ 'avatar-selected': selectedPreset === `initials:${color}` }"
                  @click="selectPreset(`initials:${color}`)"
                >
                  {{ initials }}
                </q-avatar>
              </div>
            </q-tab-panel>

            <!-- Icons Tab -->
            <q-tab-panel name="icons" class="q-pa-none">
              <div class="text-caption text-grey q-mb-sm">
                Choose an icon avatar
              </div>
              <div class="row q-gutter-sm justify-center">
                <q-avatar
                  v-for="icon in avatarIcons"
                  :key="icon.name"
                  size="60px"
                  :color="icon.color"
                  text-color="white"
                  class="cursor-pointer avatar-option"
                  :class="{ 'avatar-selected': selectedPreset === `icon:${icon.name}:${icon.color}` }"
                  @click="selectPreset(`icon:${icon.name}:${icon.color}`)"
                >
                  <q-icon :name="icon.name" size="32px" />
                </q-avatar>
              </div>
            </q-tab-panel>

            <!-- Colors Tab -->
            <q-tab-panel name="colors" class="q-pa-none">
              <div class="text-caption text-grey q-mb-sm">
                Choose a solid color avatar
              </div>
              <div class="row q-gutter-sm justify-center">
                <q-avatar
                  v-for="color in solidColors"
                  :key="color"
                  size="60px"
                  :color="color"
                  class="cursor-pointer avatar-option"
                  :class="{ 'avatar-selected': selectedPreset === `solid:${color}` }"
                  @click="selectPreset(`solid:${color}`)"
                >
                  <q-icon name="check" v-if="selectedPreset === `solid:${color}`" color="white" />
                </q-avatar>
              </div>
            </q-tab-panel>
          </q-tab-panels>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" color="grey" v-close-popup />
          <q-btn
            label="Apply"
            color="primary"
            :loading="settingPreset"
            :disable="!selectedPreset"
            @click="applyPreset"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Image Cropper Dialog -->
    <q-dialog v-model="showCropperDialog" persistent>
      <q-card style="min-width: 350px; max-width: 500px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Adjust Photo</div>
          <q-space />
          <q-btn icon="close" flat round dense @click="cancelCrop" />
        </q-card-section>

        <q-card-section class="text-center">
          <div class="text-caption text-grey q-mb-md">
            Drag to reposition, use slider to zoom
          </div>
          <div class="cropper-container">
            <div
              ref="cropperArea"
              class="cropper-area"
              @mousedown="startDrag"
              @touchstart="startDrag"
            >
              <img
                v-if="cropperImageSrc"
                ref="cropperImage"
                :src="cropperImageSrc"
                :style="cropperImageStyle"
                class="cropper-image"
                draggable="false"
              />
              <div class="cropper-overlay" />
            </div>
          </div>
          <div class="row items-center q-mt-md q-px-md">
            <q-icon name="zoom_out" size="sm" class="text-grey" />
            <q-slider
              v-model="cropperZoom"
              :min="1"
              :max="3"
              :step="0.1"
              class="q-mx-md"
              color="primary"
            />
            <q-icon name="zoom_in" size="sm" class="text-grey" />
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" color="grey" @click="cancelCrop" />
          <q-btn
            label="Apply"
            color="primary"
            :loading="uploading"
            @click="applyCrop"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';

interface Props {
  userId: number;
  currentAvatar?: string | null;
  firstName?: string;
  lastName?: string;
  username?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'avatar-updated', avatar: string | null): void;
}>();

const $q = useQuasar();

const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const removing = ref(false);
const settingPreset = ref(false);
const showPresetDialog = ref(false);
const presetTab = ref('initials');
const selectedPreset = ref<string | null>(null);

// Cropper state
const showCropperDialog = ref(false);
const cropperImageSrc = ref<string | null>(null);
const cropperZoom = ref(1);
const cropperX = ref(0);
const cropperY = ref(0);
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const selectedFile = ref<File | null>(null);
const cropperArea = ref<HTMLElement | null>(null);
const cropperImage = ref<HTMLImageElement | null>(null);

const cropperImageStyle = computed(() => ({
  transform: `translate(calc(-50% + ${cropperX.value}px), calc(-50% + ${cropperY.value}px)) scale(${cropperZoom.value})`,
}));

function startDrag(event: MouseEvent | TouchEvent) {
  event.preventDefault();
  isDragging.value = true;

  const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX;
  const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY;

  dragStart.value = {
    x: clientX - cropperX.value,
    y: clientY - cropperY.value,
  };

  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', stopDrag);
  document.addEventListener('touchmove', onDrag);
  document.addEventListener('touchend', stopDrag);
}

function onDrag(event: MouseEvent | TouchEvent) {
  if (!isDragging.value) return;

  const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX;
  const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY;

  cropperX.value = clientX - dragStart.value.x;
  cropperY.value = clientY - dragStart.value.y;
}

function stopDrag() {
  isDragging.value = false;
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', stopDrag);
  document.removeEventListener('touchmove', onDrag);
  document.removeEventListener('touchend', stopDrag);
}

function cancelCrop() {
  showCropperDialog.value = false;
  cropperImageSrc.value = null;
  selectedFile.value = null;
  cropperZoom.value = 1;
  cropperX.value = 0;
  cropperY.value = 0;
}

async function applyCrop() {
  if (!selectedFile.value || !cropperImageSrc.value) return;

  uploading.value = true;

  try {
    // Create a canvas to crop the image
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Could not get canvas context');

    // Output size (avatar size)
    const outputSize = 300;
    canvas.width = outputSize;
    canvas.height = outputSize;

    // Create an image element to draw from
    const img = new Image();
    img.crossOrigin = 'anonymous';

    await new Promise<void>((resolve, reject) => {
      img.onload = () => resolve();
      img.onerror = reject;
      img.src = cropperImageSrc.value!;
    });

    // Container size matches the cropper-area CSS
    const containerSize = 200;
    const zoom = cropperZoom.value;

    // Calculate how object-fit: cover scales the image
    const imgAspect = img.naturalWidth / img.naturalHeight;
    const containerAspect = 1; // Square container

    let displayedWidth: number;
    let displayedHeight: number;

    if (imgAspect > containerAspect) {
      // Image is wider - height fills container, width is cropped
      displayedHeight = containerSize;
      displayedWidth = containerSize * imgAspect;
    } else {
      // Image is taller - width fills container, height is cropped
      displayedWidth = containerSize;
      displayedHeight = containerSize / imgAspect;
    }

    // Apply zoom
    displayedWidth *= zoom;
    displayedHeight *= zoom;

    // Calculate the center offset (image is centered in container)
    // With the pan offset applied
    const imgCenterX = containerSize / 2 + cropperX.value;
    const imgCenterY = containerSize / 2 + cropperY.value;

    // Calculate the visible area in image coordinates
    // The container shows a window into the scaled image
    const visibleLeft = imgCenterX - displayedWidth / 2;
    const visibleTop = imgCenterY - displayedHeight / 2;

    // Convert container coordinates to source image coordinates
    const scaleToSource = img.naturalWidth / displayedWidth;

    const sourceX = -visibleLeft * scaleToSource;
    const sourceY = -visibleTop * scaleToSource;
    const sourceSize = containerSize * scaleToSource;

    // Draw the cropped portion
    ctx.drawImage(
      img,
      Math.max(0, sourceX),
      Math.max(0, sourceY),
      Math.min(sourceSize, img.naturalWidth - Math.max(0, sourceX)),
      Math.min(sourceSize, img.naturalHeight - Math.max(0, sourceY)),
      0,
      0,
      outputSize,
      outputSize
    );

    // Convert canvas to blob
    const blob = await new Promise<Blob>((resolve, reject) => {
      canvas.toBlob(
        (b) => (b ? resolve(b) : reject(new Error('Failed to create blob'))),
        'image/jpeg',
        0.9
      );
    });

    // Create FormData and upload
    const formData = new FormData();
    formData.append('avatar', blob, 'avatar.jpg');

    const response = await api.post(`/users/${props.userId}/avatar`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    emit('avatar-updated', response.data.user.avatar);

    $q.notify({
      type: 'positive',
      message: 'Avatar uploaded successfully!',
      position: 'top',
    });

    cancelCrop();
  } catch (error: unknown) {
    const errorMessage =
      error instanceof Error && 'response' in error
        ? (error as { response?: { data?: { message?: string } } }).response?.data?.message
        : undefined;
    $q.notify({
      type: 'negative',
      message: errorMessage || 'Failed to upload avatar',
      position: 'top',
    });
  } finally {
    uploading.value = false;
  }
}

onUnmounted(() => {
  // Clean up event listeners
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', stopDrag);
  document.removeEventListener('touchmove', onDrag);
  document.removeEventListener('touchend', stopDrag);
});

// Preset options
const initialsColors = [
  'primary',
  'secondary',
  'accent',
  'positive',
  'negative',
  'info',
  'warning',
  'deep-purple',
  'indigo',
  'cyan',
  'teal',
  'orange',
];

const avatarIcons = [
  { name: 'person', color: 'primary' },
  { name: 'person', color: 'indigo' },
  { name: 'engineering', color: 'amber-9' },
  { name: 'build', color: 'blue-grey' },
  { name: 'local_shipping', color: 'teal' },
  { name: 'directions_car', color: 'deep-orange' },
  { name: 'handyman', color: 'brown' },
  { name: 'work', color: 'cyan-9' },
  { name: 'support_agent', color: 'purple' },
  { name: 'admin_panel_settings', color: 'red-9' },
  { name: 'manage_accounts', color: 'deep-purple' },
  { name: 'badge', color: 'green-9' },
];

const solidColors = [
  'red',
  'pink',
  'purple',
  'deep-purple',
  'indigo',
  'blue',
  'cyan',
  'teal',
  'green',
  'light-green',
  'amber',
  'orange',
  'deep-orange',
  'brown',
  'grey',
  'blue-grey',
];

const initials = computed(() => {
  const firstName = props.firstName || '';
  const lastName = props.lastName || '';

  if (firstName && lastName) {
    return `${firstName[0]}${lastName[0]}`.toUpperCase();
  } else if (firstName) {
    return firstName.charAt(0).toUpperCase();
  } else if (props.username) {
    return props.username.charAt(0).toUpperCase();
  }

  return '?';
});

const displayAvatarUrl = computed(() => {
  if (!props.currentAvatar) return null;

  // Handle preset avatars - they're rendered differently
  if (props.currentAvatar.startsWith('preset:')) {
    return null; // We'll handle this in the template
  }

  return `/storage/${props.currentAvatar}`;
});

// Parse preset avatar for display
const presetAvatarData = computed(() => {
  if (!props.currentAvatar || !props.currentAvatar.startsWith('preset:')) {
    return null;
  }

  const preset = props.currentAvatar.replace('preset:', '');
  const parts = preset.split(':');

  if (parts[0] === 'initials') {
    return { type: 'initials', color: parts[1] };
  } else if (parts[0] === 'icon') {
    return { type: 'icon', name: parts[1], color: parts[2] };
  } else if (parts[0] === 'solid') {
    return { type: 'solid', color: parts[1] };
  }

  return null;
});

// Reset selection when dialog opens
watch(showPresetDialog, (isOpen) => {
  if (isOpen) {
    selectedPreset.value = props.currentAvatar?.startsWith('preset:')
      ? props.currentAvatar.replace('preset:', '')
      : null;
  }
});

function triggerFileInput() {
  fileInput.value?.click();
}

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (!file) return;

  // Validate file size (5MB max for original, will be compressed after crop)
  if (file.size > 5 * 1024 * 1024) {
    $q.notify({
      type: 'negative',
      message: 'File size must be less than 5MB',
      position: 'top',
    });
    return;
  }

  // Store the file and open cropper
  selectedFile.value = file;

  // Read file as data URL for preview
  const reader = new FileReader();
  reader.onload = (e) => {
    cropperImageSrc.value = e.target?.result as string;
    cropperZoom.value = 1;
    cropperX.value = 0;
    cropperY.value = 0;
    showCropperDialog.value = true;
  };
  reader.readAsDataURL(file);

  // Clear file input for re-selection
  if (target) target.value = '';
}

function handleRemoveAvatar() {
  $q.dialog({
    title: 'Confirm',
    message: 'Are you sure you want to remove the avatar?',
    cancel: true,
    persistent: true,
  }).onOk(() => {
    void (async () => {
      removing.value = true;

      try {
        await api.delete(`/users/${props.userId}/avatar`);

        emit('avatar-updated', null);

        $q.notify({
          type: 'positive',
          message: 'Avatar removed successfully!',
          position: 'top',
        });
      } catch (error: unknown) {
        const errorMessage =
          error instanceof Error && 'response' in error
            ? (error as { response?: { data?: { message?: string } } }).response
                ?.data?.message
            : undefined;
        $q.notify({
          type: 'negative',
          message: errorMessage || 'Failed to remove avatar',
          position: 'top',
        });
      } finally {
        removing.value = false;
      }
    })();
  });
}

function selectPreset(preset: string) {
  selectedPreset.value = preset;
}

async function applyPreset() {
  if (!selectedPreset.value) return;

  settingPreset.value = true;

  try {
    const response = await api.post(`/users/${props.userId}/avatar/preset`, {
      preset: selectedPreset.value,
    });

    emit('avatar-updated', response.data.user.avatar);
    showPresetDialog.value = false;

    $q.notify({
      type: 'positive',
      message: 'Avatar updated successfully!',
      position: 'top',
    });
  } catch (error: unknown) {
    const errorMessage =
      error instanceof Error && 'response' in error
        ? (error as { response?: { data?: { message?: string } } }).response
            ?.data?.message
        : undefined;
    $q.notify({
      type: 'negative',
      message: errorMessage || 'Failed to update avatar',
      position: 'top',
    });
  } finally {
    settingPreset.value = false;
  }
}

// Expose preset data for parent component
defineExpose({
  presetAvatarData,
});
</script>

<style scoped>
.avatar-selector {
  width: 100%;
}

.avatar-link {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  transition: opacity 0.2s;
  background: none !important;
  border: none !important;
  padding: 0 !important;
  box-shadow: none !important;
  min-height: auto !important;
  line-height: 1.4;
}

.avatar-link:hover {
  text-decoration: underline;
  opacity: 0.8;
}

.avatar-link:focus {
  outline: none;
}

.avatar-option {
  transition: transform 0.2s, box-shadow 0.2s;
}

.avatar-option:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.avatar-selected {
  outline: 3px solid var(--q-primary);
  outline-offset: 2px;
}

/* Image Cropper Styles */
.cropper-container {
  display: flex;
  justify-content: center;
  align-items: center;
}

.cropper-area {
  width: 200px;
  height: 200px;
  border-radius: 50%;
  overflow: hidden;
  position: relative;
  cursor: grab;
  background: #f0f0f0;
  border: 2px solid #ddd;
}

.cropper-area:active {
  cursor: grabbing;
}

.cropper-image {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform-origin: center center;
  pointer-events: none;
  user-select: none;
}

.cropper-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: 50%;
  box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.5);
  pointer-events: none;
}
</style>
