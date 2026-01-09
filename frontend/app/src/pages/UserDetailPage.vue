<template>
  <q-page padding>
    <div class="row q-col-gutter-md">
      <!-- Header with Back Button and Edit Icon -->
      <div class="col-12">
        <div class="row items-center justify-between q-mb-md">
          <div class="row items-center">
            <q-btn
              flat
              round
              icon="arrow_back"
              @click="goBack"
              class="q-mr-sm"
            />
            <div class="text-h4">User Details</div>
          </div>
          <q-btn
            round
            color="primary"
            icon="edit"
            @click="editUser"
          >
            <q-tooltip>Edit User</q-tooltip>
          </q-btn>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="col-12 text-center q-pa-xl">
        <q-spinner-dots color="primary" size="50px" />
        <div class="text-grey q-mt-md">Loading user details...</div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="col-12 text-center q-pa-xl">
        <q-icon name="error" color="negative" size="50px" />
        <div class="text-h6 q-mt-md">Error loading user</div>
        <div class="text-grey q-mb-md">{{ error }}</div>
        <q-btn color="primary" label="Go Back" @click="goBack" />
      </div>

      <!-- User Details -->
      <template v-else-if="user">
        <!-- Profile Header Card -->
        <div class="col-12">
          <q-card>
            <q-card-section>
              <div class="row items-center q-gutter-md">
                <UserAvatar
                  :avatar="user.avatar"
                  :first-name="user.first_name"
                  :last-name="user.last_name"
                  :preferred-name="user.preferred_name"
                  :username="user.username"
                  size="100px"
                  avatar-class="shadow-2"
                />
                <div>
                  <div class="text-h5">{{ user.username }}</div>
                  <div class="text-subtitle1 text-grey">
                    {{ getDisplayName(user) }}
                  </div>
                  <div class="q-mt-sm">
                    <q-chip
                      :color="user.active ? 'positive' : 'grey'"
                      text-color="white"
                      size="sm"
                    >
                      {{ user.active ? 'Active' : 'Inactive' }}
                    </q-chip>
                    <q-chip
                      v-for="role in user.roles"
                      :key="role.id"
                      color="primary"
                      text-color="white"
                      size="sm"
                      class="q-ml-sm"
                    >
                      {{ role.display_name }}
                    </q-chip>
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Personal Information -->
        <div class="col-12 col-md-6">
          <q-card class="full-height">
            <q-card-section>
              <div class="text-h6 q-mb-md">Personal Information</div>
              <q-list>
                <q-item v-if="user.employee_id">
                  <q-item-section>
                    <q-item-label caption>Employee ID</q-item-label>
                    <q-item-label>{{ user.employee_id }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.first_name">
                  <q-item-section>
                    <q-item-label caption>First Name</q-item-label>
                    <q-item-label>{{ user.first_name }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.last_name">
                  <q-item-section>
                    <q-item-label caption>Last Name</q-item-label>
                    <q-item-label>{{ user.last_name }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.preferred_name">
                  <q-item-section>
                    <q-item-label caption>Preferred Name</q-item-label>
                    <q-item-label>{{ user.preferred_name }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.pin_code">
                  <q-item-section>
                    <q-item-label caption>PIN Code</q-item-label>
                    <q-item-label>{{ user.pin_code }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.paytype">
                  <q-item-section>
                    <q-item-label caption>Pay Type</q-item-label>
                    <q-item-label>{{ user.paytype }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </div>

        <!-- Contact Information -->
        <div class="col-12 col-md-6">
          <q-card class="full-height">
            <q-card-section>
              <div class="text-h6 q-mb-md">Contact Information</div>
              <q-list>
                <q-item>
                  <q-item-section avatar>
                    <q-icon name="email" color="primary" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Email</q-item-label>
                    <q-item-label>{{ user.email }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.personal_email">
                  <q-item-section avatar>
                    <q-icon name="alternate_email" color="secondary" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Personal Email</q-item-label>
                    <q-item-label>{{ user.personal_email }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.dext_email">
                  <q-item-section avatar>
                    <q-icon name="email" color="grey" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Dext Email</q-item-label>
                    <q-item-label>{{ user.dext_email }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.phone_number">
                  <q-item-section avatar>
                    <q-icon name="phone" color="positive" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Phone Number</q-item-label>
                    <q-item-label>{{ formatPhoneNumber(user.phone_number) }}</q-item-label>
                  </q-item-section>
                </q-item>

                <q-item v-if="user.slack_id">
                  <q-item-section avatar>
                    <q-icon name="chat" color="purple" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Slack ID</q-item-label>
                    <q-item-label>{{ user.slack_id }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </div>

        <!-- Work Information -->
        <div class="col-12 col-md-6">
          <q-card class="full-height">
            <q-card-section>
              <div class="text-h6 q-mb-md">Work Information</div>
              <q-list>
                <q-item>
                  <q-item-section avatar>
                    <q-icon name="store" color="primary" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Home Shop</q-item-label>
                    <q-item-label>
                      {{ user.home_location?.name || user.home_shop || '—' }}
                      <span v-if="user.home_location?.city || user.home_location?.state" class="text-grey text-caption">
                        ({{ [user.home_location?.city, user.home_location?.state].filter(Boolean).join(', ') }})
                      </span>
                    </q-item-label>
                  </q-item-section>
                </q-item>

                <q-item>
                  <q-item-section avatar>
                    <q-icon name="badge" color="secondary" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Roles</q-item-label>
                    <q-item-label>
                      <span v-if="user.roles && user.roles.length > 0">
                        {{ user.roles.map(r => r.display_name).join(', ') }}
                      </span>
                      <span v-else class="text-grey">No roles assigned</span>
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </div>

        <!-- Address Information -->
        <div class="col-12 col-md-6">
          <q-card class="full-height">
            <q-card-section>
              <div class="text-h6 q-mb-md">Address</div>
              <q-list>
                <q-item v-if="hasAddress">
                  <q-item-section avatar>
                    <q-icon name="location_on" color="red" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label caption>Address</q-item-label>
                    <q-item-label>
                      <div v-if="user.address_line_1">{{ user.address_line_1 }}</div>
                      <div v-if="user.address_line_2">{{ user.address_line_2 }}</div>
                      <div v-if="user.city || user.state || user.zip">
                        {{ [user.city, user.state].filter(Boolean).join(', ') }}
                        <span v-if="user.zip"> {{ user.zip }}</span>
                      </div>
                      <div v-if="user.address && !user.address_line_1">{{ user.address }}</div>
                    </q-item-label>
                  </q-item-section>
                </q-item>
                <q-item v-else>
                  <q-item-section>
                    <q-item-label class="text-grey">No address on file</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </div>

        <!-- System Information -->
        <div class="col-12">
          <q-card>
            <q-card-section>
              <div class="text-h6 q-mb-md">System Information</div>
              <div class="row q-col-gutter-md">
                <div class="col-12 col-sm-6 col-md-3">
                  <div class="text-caption text-grey">User ID</div>
                  <div>{{ user.id }}</div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <div class="text-caption text-grey">Status</div>
                  <div>
                    <q-chip
                      :color="user.active ? 'positive' : 'grey'"
                      text-color="white"
                      size="sm"
                    >
                      {{ user.active ? 'Active' : 'Inactive' }}
                    </q-chip>
                  </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <div class="text-caption text-grey">Created</div>
                  <div>{{ formatDate(user.created_at) }}</div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                  <div class="text-caption text-grey">Last Modified</div>
                  <div>{{ formatDate(user.updated_at) }}</div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </template>
    </div>

    <!-- Edit User Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card style="width: 100%; max-width: 600px; max-height: 80vh">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Edit User</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section v-if="editForm" class="scroll">
          <q-form @submit.prevent="saveUser">
            <div class="row q-col-gutter-md">
              <!-- Avatar Section -->
              <div class="col-12">
                <div class="text-subtitle2 q-mb-sm">Profile Picture</div>
                <AvatarSelector
                  :user-id="editForm.id"
                  :current-avatar="editForm.avatar"
                  :first-name="editForm.first_name"
                  :last-name="editForm.last_name"
                  :username="editForm.username"
                  @avatar-updated="handleAvatarUpdated"
                />
                <q-separator class="q-mt-md" />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.username"
                  label="Username"
                  outlined
                  dense
                  :rules="[(val) => (val && val.length > 0) || 'Username is required']"
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.employee_id"
                  label="Employee ID"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-select
                  v-model="editForm.role_ids"
                  label="Roles"
                  outlined
                  dense
                  :options="roles"
                  option-value="id"
                  option-label="display_name"
                  emit-value
                  map-options
                  multiple
                  use-chips
                  hint="Assign one or more roles to this user"
                  popup-content-class="mobile-select-popup"
                  popup-content-style="max-width: 100%"
                >
                  <template v-slot:selected-item="scope">
                    <q-chip
                      removable
                      dense
                      @remove="scope.removeAtIndex(scope.index)"
                      :tabindex="scope.tabindex"
                      color="primary"
                      text-color="white"
                      size="sm"
                    >
                      {{ scope.opt.display_name || scope.opt }}
                    </q-chip>
                  </template>
                </q-select>
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.first_name"
                  label="First Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.last_name"
                  label="Last Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.preferred_name"
                  label="Preferred Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  :model-value="editForm.phone_number"
                  @update:model-value="(val) => editForm.phone_number = formatPhoneNumber(val as string)"
                  label="Phone Number"
                  outlined
                  dense
                  maxlength="14"
                  hint="Format: (xxx)xxx-xxxx"
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.email"
                  label="Email"
                  type="email"
                  outlined
                  dense
                  :rules="[
                    (val) => (val && val.length > 0) || 'Email is required',
                    (val) => /.+@.+\..+/.test(val) || 'Invalid email format',
                  ]"
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.personal_email"
                  label="Personal Email"
                  type="email"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.dext_email"
                  label="Dext Email"
                  type="email"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.pin_code"
                  label="PIN Code"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-select
                  v-model="editForm.home_location_id"
                  label="Home Shop Location"
                  outlined
                  dense
                  :options="shopLocations"
                  option-value="id"
                  :option-label="(loc: ShopLocation) => loc.city && loc.state ? `${loc.name} – ${loc.city}, ${loc.state}` : loc.name"
                  emit-value
                  map-options
                  clearable
                  hint="Select user's primary shop location"
                >
                  <template v-slot:option="scope">
                    <q-item v-bind="scope.itemProps">
                      <q-item-section>
                        <q-item-label>{{ scope.opt.name }}</q-item-label>
                        <q-item-label caption v-if="scope.opt.city || scope.opt.state">
                          {{ [scope.opt.city, scope.opt.state].filter(Boolean).join(', ') }}
                        </q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.slack_id"
                  label="Slack ID"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.paytype"
                  label="Pay Type"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.address_line_1"
                  label="Address"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.address_line_2"
                  label="Address 2"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.city"
                  label="City"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.state"
                  label="State"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.zip"
                  label="Zip"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-toggle
                  v-model="editForm.active"
                  label="Active User"
                  color="positive"
                  :false-value="false"
                  :true-value="true"
                />
                <div class="text-caption text-grey-7">
                  {{ editForm.active ? 'User is currently active' : 'User is currently inactive' }}
                </div>
              </div>
            </div>

            <div class="row q-mt-md q-gutter-sm">
              <q-btn
                label="Cancel"
                flat
                color="grey"
                v-close-popup
              />
              <q-space />
              <q-btn
                type="submit"
                label="Save"
                color="primary"
                :loading="saving"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';
import { formatPhoneNumber } from 'src/composables/usePhoneFormat';
import UserAvatar from 'src/components/UserAvatar.vue';
import AvatarSelector from 'src/components/AvatarSelector.vue';

interface User {
  id: number;
  username: string;
  email: string;
  role?: string;
  role_ids?: number[];
  roles?: Array<{ id: number; name: string; display_name: string }>;
  employee_id?: string;
  first_name?: string;
  last_name?: string;
  preferred_name?: string;
  phone_number?: string;
  pin_code?: string;
  home_shop?: string;
  home_location_id?: number | null;
  home_location?: { id: number; name: string; city?: string; state?: string };
  personal_email?: string;
  slack_id?: string;
  dext_email?: string;
  avatar?: string;
  address?: string;
  address_line_1?: string;
  address_line_2?: string;
  city?: string;
  state?: string;
  zip?: string;
  paytype?: string;
  active: boolean;
  created_at: string;
  updated_at?: string;
}

interface ShopLocation {
  id: number;
  name: string;
  city?: string;
  state?: string;
  location_type: string;
}

interface Role {
  id: number;
  name: string;
  display_name: string;
  description?: string;
}

const route = useRoute();
const router = useRouter();
const $q = useQuasar();

const user = ref<User | null>(null);
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);
const showEditDialog = ref(false);
const editForm = ref<User | null>(null);
const shopLocations = ref<ShopLocation[]>([]);
const roles = ref<Role[]>([]);

const hasAddress = computed(() => {
  if (!user.value) return false;
  return user.value.address_line_1 || user.value.address_line_2 ||
         user.value.city || user.value.state || user.value.zip ||
         user.value.address;
});

async function fetchUser() {
  const userId = route.params.id;
  if (!userId) {
    error.value = 'No user ID provided';
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const response = await api.get(`/users/${userId}`);
    user.value = response.data.user;
  } catch (err) {
    console.error('Error fetching user:', err);
    const errorMessage =
      err instanceof Error && 'response' in err
        ? (err as { response?: { data?: { message?: string } } }).response
            ?.data?.message
        : undefined;
    error.value = errorMessage || 'Failed to load user details';
  } finally {
    loading.value = false;
  }
}

async function loadShopLocations() {
  try {
    const response = await api.get('/locations', {
      params: {
        type: 'fixed_shop',
        active: 'true',
        per_page: 100,
      },
    });
    shopLocations.value = response.data.data || [];
  } catch (err) {
    console.error('Failed to load shop locations', err);
  }
}

async function loadRoles() {
  try {
    const response = await api.get('/roles');
    roles.value = response.data;
  } catch (err) {
    console.error('Failed to load roles', err);
  }
}

function goBack() {
  void router.push('/users');
}

function editUser() {
  if (!user.value) return;
  editForm.value = { ...user.value };
  showEditDialog.value = true;
}

function handleAvatarUpdated(newAvatar: string | null) {
  if (editForm.value) {
    editForm.value.avatar = newAvatar || undefined;
  }
  if (user.value) {
    user.value.avatar = newAvatar || undefined;
  }
}

async function saveUser() {
  if (!editForm.value) return;

  saving.value = true;
  try {
    await api.put(`/users/${editForm.value.id}`, editForm.value);

    $q.notify({
      type: 'positive',
      message: 'User updated successfully',
      position: 'top',
    });
    showEditDialog.value = false;
    await fetchUser();
  } catch (err) {
    console.error('Error updating user:', err);
    $q.notify({
      type: 'negative',
      message: 'Failed to update user',
      position: 'top',
    });
  } finally {
    saving.value = false;
  }
}

function getUserInitials(userObj: User): string {
  const firstName = userObj.first_name || userObj.preferred_name || '';
  const lastName = userObj.last_name || '';

  if (firstName && lastName) {
    return `${firstName[0]}${lastName[0]}`.toUpperCase();
  } else if (firstName) {
    return firstName.charAt(0).toUpperCase();
  } else if (userObj.username) {
    return userObj.username.charAt(0).toUpperCase();
  }

  return '?';
}

function getDisplayName(userObj: User): string {
  if (userObj.preferred_name) {
    return userObj.preferred_name;
  }
  if (userObj.first_name && userObj.last_name) {
    return `${userObj.first_name} ${userObj.last_name}`;
  }
  if (userObj.first_name) {
    return userObj.first_name;
  }
  return userObj.username;
}

function formatDate(dateString: string | undefined): string {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

onMounted(() => {
  void fetchUser();
  void loadShopLocations();
  void loadRoles();
});
</script>

<style scoped>
.full-height {
  height: 100%;
}
</style>

<style>
/* Global styles for dropdown popup width constraint */
.mobile-select-popup {
  min-width: 0 !important;

  .q-item {
    min-height: 36px;
    padding: 4px 12px;
  }

  .q-item__label {
    white-space: normal;
    word-break: break-word;
  }
}
</style>
