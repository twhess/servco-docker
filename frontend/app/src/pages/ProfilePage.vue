<template>
  <q-page class="q-pa-md">
    <div class="row justify-center">
      <div class="col-12 col-md-6 col-lg-4">
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-h5">Profile</div>
          </q-card-section>

          <!-- Avatar Section -->
          <q-card-section class="text-center">
            <div class="avatar-container q-mb-md">
              <UserAvatar
                :avatar="authStore.user?.avatar"
                :first-name="authStore.user?.first_name"
                :last-name="authStore.user?.last_name"
                :preferred-name="authStore.user?.preferred_name"
                :username="authStore.user?.username"
                size="120px"
              />
            </div>

            <div class="row q-gutter-md justify-center">
              <a
                href="#"
                class="avatar-link text-primary"
                @click.prevent="triggerFileInput"
              >
                <q-icon name="upload" size="xs" class="q-mr-xs" />
                <span>{{ uploadingAvatar ? 'Uploading...' : 'Upload Photo' }}</span>
                <q-spinner-dots v-if="uploadingAvatar" size="14px" class="q-ml-xs" />
              </a>
              <a
                v-if="authStore.user?.avatar"
                href="#"
                class="avatar-link text-negative"
                @click.prevent="handleDeleteAvatar"
              >
                <q-icon name="delete" size="xs" class="q-mr-xs" />
                <span>{{ deletingAvatar ? 'Removing...' : 'Remove' }}</span>
                <q-spinner-dots v-if="deletingAvatar" size="14px" class="q-ml-xs" />
              </a>
            </div>

            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              style="display: none"
              @change="handleFileSelect"
            />
          </q-card-section>

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
                  :loading="uploadingAvatar"
                  @click="applyCrop"
                />
              </q-card-actions>
            </q-card>
          </q-dialog>
        </q-card>

        <!-- Profile Information Form -->
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-h6 q-mb-md">Profile Information</div>

            <q-form @submit.prevent="handleUpdateProfile">
              <div class="row q-col-gutter-md">
                <div class="col-12">
                  <q-input
                    v-model="profileForm.first_name"
                    label="First Name"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.last_name"
                    label="Last Name"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.preferred_name"
                    label="Preferred Name"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.email"
                    type="email"
                    label="Email"
                    outlined
                    :rules="[
                      (val) => (val && val.length > 0) || 'Email is required',
                      (val) => /.+@.+\..+/.test(val) || 'Invalid email format',
                    ]"
                  />
                </div>
                <div class="col-12">
                  <q-input
                    :model-value="profileForm.phone_number"
                    @update:model-value="(val) => profileForm.phone_number = formatPhoneNumber(val as string)"
                    label="Phone Number"
                    outlined
                    maxlength="14"
                    hint="Format: (xxx)xxx-xxxx"
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.address_line_1"
                    label="Address"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.address_line_2"
                    label="Address 2"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.city"
                    label="City"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.state"
                    label="State"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.zip"
                    label="Zip"
                    outlined
                  />
                </div>
              </div>

              <div v-if="profileError" class="text-negative q-mt-md">
                {{ profileError }}
              </div>

              <q-btn
                type="submit"
                label="Update Profile"
                color="primary"
                class="full-width q-mt-md"
                :loading="updatingProfile"
              />
            </q-form>
          </q-card-section>
        </q-card>

        <!-- Change Password Form -->
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">Change Password</div>

            <q-form @submit.prevent="handleChangePassword">
              <q-input
                v-model="passwordForm.current_password"
                :type="showCurrentPassword ? 'text' : 'password'"
                label="Current Password"
                outlined
                :rules="[
                  (val) =>
                    (val && val.length > 0) || 'Current password is required',
                ]"
                class="q-mb-md"
              >
                <template v-slot:append>
                  <q-icon
                    :name="showCurrentPassword ? 'visibility_off' : 'visibility'"
                    class="cursor-pointer"
                    @click="showCurrentPassword = !showCurrentPassword"
                  />
                </template>
              </q-input>

              <q-input
                v-model="passwordForm.new_password"
                :type="showNewPassword ? 'text' : 'password'"
                label="New Password"
                outlined
                :rules="[
                  (val) =>
                    (val && val.length >= 8) ||
                    'Password must be at least 8 characters',
                ]"
                class="q-mb-md"
              >
                <template v-slot:append>
                  <q-icon
                    :name="showNewPassword ? 'visibility_off' : 'visibility'"
                    class="cursor-pointer"
                    @click="showNewPassword = !showNewPassword"
                  />
                </template>
              </q-input>

              <q-input
                v-model="passwordForm.new_password_confirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                label="Confirm New Password"
                outlined
                :rules="[
                  (val) =>
                    val === passwordForm.new_password || 'Passwords do not match',
                ]"
                class="q-mb-md"
              >
                <template v-slot:append>
                  <q-icon
                    :name="
                      showConfirmPassword ? 'visibility_off' : 'visibility'
                    "
                    class="cursor-pointer"
                    @click="showConfirmPassword = !showConfirmPassword"
                  />
                </template>
              </q-input>

              <div v-if="passwordError" class="text-negative q-mb-md">
                {{ passwordError }}
              </div>

              <q-btn
                type="submit"
                label="Change Password"
                color="primary"
                class="full-width"
                :loading="changingPassword"
              />
            </q-form>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from 'stores/auth';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';
import { formatPhoneNumber } from 'src/composables/usePhoneFormat';
import UserAvatar from 'src/components/UserAvatar.vue';

const authStore = useAuthStore();
const $q = useQuasar();

const fileInput = ref<HTMLInputElement | null>(null);
const uploadingAvatar = ref(false);
const deletingAvatar = ref(false);

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
  if (!selectedFile.value || !cropperImage.value) return;

  uploadingAvatar.value = true;

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

    const response = await api.post('/profile/avatar', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    // Update the auth store with new avatar
    if (authStore.user) {
      authStore.user.avatar = response.data.user.avatar;
    }

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
    uploadingAvatar.value = false;
  }
}

onUnmounted(() => {
  // Clean up event listeners
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', stopDrag);
  document.removeEventListener('touchmove', onDrag);
  document.removeEventListener('touchend', stopDrag);
});

const profileForm = ref({
  first_name: '',
  last_name: '',
  preferred_name: '',
  email: '',
  phone_number: '',
  address: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  state: '',
  zip: '',
});

const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const updatingProfile = ref(false);
const profileError = ref('');

const changingPassword = ref(false);
const passwordError = ref('');

const loadProfileData = () => {
  if (authStore.user) {
    profileForm.value = {
      first_name: authStore.user.first_name || '',
      last_name: authStore.user.last_name || '',
      preferred_name: authStore.user.preferred_name || '',
      email: authStore.user.email || '',
      phone_number: formatPhoneNumber(authStore.user.phone_number),
      address: authStore.user.address || '',
      address_line_1: authStore.user.address_line_1 || '',
      address_line_2: authStore.user.address_line_2 || '',
      city: authStore.user.city || '',
      state: authStore.user.state || '',
      zip: authStore.user.zip || '',
    };
  }
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
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
};

const handleDeleteAvatar = () => {
  $q.dialog({
    title: 'Confirm',
    message: 'Are you sure you want to delete your avatar?',
    cancel: true,
    persistent: true,
  }).onOk(() => {
    void (async () => {
      deletingAvatar.value = true;

      try {
        const response = await api.delete('/profile/avatar');
        authStore.user = response.data.user;

        $q.notify({
          type: 'positive',
          message: 'Avatar deleted successfully!',
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
          message: errorMessage || 'Failed to delete avatar',
          position: 'top',
        });
      } finally {
        deletingAvatar.value = false;
      }
    })();
  });
};

const handleUpdateProfile = async () => {
  updatingProfile.value = true;
  profileError.value = '';

  try {
    const response = await api.put('/profile', profileForm.value);
    authStore.user = response.data.user;

    $q.notify({
      type: 'positive',
      message: 'Profile updated successfully!',
      position: 'top',
    });
  } catch (error: unknown) {
    const errorMessage =
      error instanceof Error && 'response' in error
        ? (error as { response?: { data?: { message?: string } } }).response
            ?.data?.message
        : undefined;
    profileError.value = errorMessage || 'Failed to update profile';
  } finally {
    updatingProfile.value = false;
  }
};

const handleChangePassword = async () => {
  changingPassword.value = true;
  passwordError.value = '';

  try {
    await api.post('/profile/password', passwordForm.value);

    $q.notify({
      type: 'positive',
      message: 'Password changed successfully!',
      position: 'top',
    });

    // Reset password form
    passwordForm.value = {
      current_password: '',
      new_password: '',
      new_password_confirmation: '',
    };
  } catch (error: unknown) {
    const errorMessage =
      error instanceof Error && 'response' in error
        ? (error as { response?: { data?: { message?: string } } }).response
            ?.data?.message
        : undefined;
    passwordError.value = errorMessage || 'Failed to change password';
  } finally {
    changingPassword.value = false;
  }
};

onMounted(() => {
  loadProfileData();
});
</script>

<style scoped>
.avatar-container {
  display: flex;
  justify-content: center;
  align-items: center;
}

.avatar-link {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  transition: opacity 0.2s;
  background: none;
  border: none;
  padding: 0;
  box-shadow: none;
  min-height: auto;
  line-height: 1.4;
}

.avatar-link:hover {
  text-decoration: underline;
  opacity: 0.8;
}

.avatar-link:focus {
  outline: none;
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
