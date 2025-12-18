<template>
  <q-page class="q-pa-md">
    <div class="row justify-center">
      <div class="col-12 col-md-8 col-lg-6">
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-h5">Profile</div>
          </q-card-section>

          <!-- Avatar Section -->
          <q-card-section class="text-center">
            <div class="avatar-container q-mb-md">
              <q-avatar size="120px" color="primary" text-color="white">
                <img v-if="avatarUrl" :src="avatarUrl" />
                <span v-else class="text-h3">{{ userInitials }}</span>
              </q-avatar>
            </div>

            <div class="row q-gutter-sm justify-center">
              <q-btn
                color="primary"
                label="Upload Avatar"
                icon="upload"
                @click="triggerFileInput"
                :loading="uploadingAvatar"
              />
              <q-btn
                v-if="authStore.user?.avatar"
                color="negative"
                label="Delete Avatar"
                icon="delete"
                outline
                @click="handleDeleteAvatar"
                :loading="deletingAvatar"
              />
            </div>

            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              style="display: none"
              @change="handleFileSelect"
            />
          </q-card-section>
        </q-card>

        <!-- Profile Information Form -->
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-h6 q-mb-md">Profile Information</div>

            <q-form @submit.prevent="handleUpdateProfile">
              <div class="row q-col-gutter-md">
                <div class="col-12 col-sm-6">
                  <q-input
                    v-model="profileForm.first_name"
                    label="First Name"
                    outlined
                  />
                </div>
                <div class="col-12 col-sm-6">
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
                    v-model="profileForm.phone_number"
                    label="Phone Number"
                    outlined
                  />
                </div>
                <div class="col-12">
                  <q-input
                    v-model="profileForm.address"
                    label="Address"
                    type="textarea"
                    outlined
                    rows="3"
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
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from 'stores/auth';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';

const authStore = useAuthStore();
const $q = useQuasar();

const fileInput = ref<HTMLInputElement | null>(null);
const uploadingAvatar = ref(false);
const deletingAvatar = ref(false);

const profileForm = ref({
  first_name: '',
  last_name: '',
  preferred_name: '',
  email: '',
  phone_number: '',
  address: '',
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

const avatarUrl = computed(() => {
  if (authStore.user?.avatar) {
    // Construct the full URL for the avatar
    return `http://localhost:8080/storage/${authStore.user.avatar}`;
  }
  return null;
});

const userInitials = computed(() => {
  const user = authStore.user;
  if (!user) return '?';

  const firstName = user.first_name || user.preferred_name || '';
  const lastName = user.last_name || '';

  if (firstName && lastName) {
    return `${firstName[0]}${lastName[0]}`.toUpperCase();
  } else if (firstName) {
    return firstName[0].toUpperCase();
  } else if (user.username) {
    return user.username[0].toUpperCase();
  }

  return '?';
});

const loadProfileData = () => {
  if (authStore.user) {
    profileForm.value = {
      first_name: authStore.user.first_name || '',
      last_name: authStore.user.last_name || '',
      preferred_name: authStore.user.preferred_name || '',
      email: authStore.user.email || '',
      phone_number: authStore.user.phone_number || '',
      address: authStore.user.address || '',
    };
  }
};

const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleFileSelect = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (!file) return;

  // Validate file size (2MB max)
  if (file.size > 2 * 1024 * 1024) {
    $q.notify({
      type: 'negative',
      message: 'File size must be less than 2MB',
      position: 'top',
    });
    return;
  }

  uploadingAvatar.value = true;

  try {
    const formData = new FormData();
    formData.append('avatar', file);

    const response = await api.post('/profile/avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    authStore.user = response.data.user;

    $q.notify({
      type: 'positive',
      message: 'Avatar uploaded successfully!',
      position: 'top',
    });
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Failed to upload avatar',
      position: 'top',
    });
  } finally {
    uploadingAvatar.value = false;
    if (target) target.value = '';
  }
};

const handleDeleteAvatar = async () => {
  $q.dialog({
    title: 'Confirm',
    message: 'Are you sure you want to delete your avatar?',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    deletingAvatar.value = true;

    try {
      const response = await api.delete('/profile/avatar');
      authStore.user = response.data.user;

      $q.notify({
        type: 'positive',
        message: 'Avatar deleted successfully!',
        position: 'top',
      });
    } catch (error: any) {
      $q.notify({
        type: 'negative',
        message: error.response?.data?.message || 'Failed to delete avatar',
        position: 'top',
      });
    } finally {
      deletingAvatar.value = false;
    }
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
  } catch (error: any) {
    profileError.value =
      error.response?.data?.message || 'Failed to update profile';
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
  } catch (error: any) {
    passwordError.value =
      error.response?.data?.message || 'Failed to change password';
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
</style>
