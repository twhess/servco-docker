<template>
  <q-page padding>
    <div class="row q-col-gutter-md">
      <div class="col-12">
        <div class="text-h4 q-mb-md">Users</div>
      </div>

      <div class="col-12">
        <q-card>
          <q-card-section>
            <div class="row items-center q-mb-md">
              <div class="text-h6">User Management</div>
              <q-space />
              <q-input
                outlined
                dense
                v-model="searchQuery"
                placeholder="Search users..."
                class="q-mr-sm"
                style="width: 100%; max-width: 250px"
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
              </q-input>
              <q-btn
                flat
                @click="openAddUserDialog"
                class="q-mr-sm"
              >
                <q-icon name="add" color="primary" size="sm" class="q-mr-xs" />
                <span class="text-primary">Add User</span>
              </q-btn>
              <q-btn
                flat
                @click="toggleShowInactive"
              >
                <q-icon
                  :name="showInactive ? 'visibility' : 'visibility_off'"
                  :color="showInactive ? 'secondary' : 'grey'"
                  size="sm"
                  class="q-mr-xs"
                />
                <span :class="showInactive ? 'text-secondary' : 'text-grey'">
                  {{ showInactive ? 'Hide Inactive' : 'Show Inactive' }}
                </span>
              </q-btn>
            </div>

            <q-table
              :rows="filteredUsers"
              :columns="columns"
              row-key="id"
              :loading="loading"
              :pagination="pagination"
              @update:pagination="pagination = $event"
              flat
              bordered
              class="users-table"
              @row-click="onRowClick"
            >
              <!-- Employee ID Column -->
              <template v-slot:body-cell-employee_id="props">
                <q-td :props="props">
                  <span class="text-body2">{{ props.row.employee_id || '—' }}</span>
                </q-td>
              </template>

              <!-- Username with Avatar Column -->
              <template v-slot:body-cell-username="props">
                <q-td :props="props">
                  <div class="row items-center no-wrap">
                    <UserAvatar
                      :avatar="props.row.avatar"
                      :first-name="props.row.first_name"
                      :last-name="props.row.last_name"
                      :preferred-name="props.row.preferred_name"
                      :username="props.row.username"
                      size="32px"
                      class="q-mr-sm"
                    />
                    <span class="text-weight-medium">{{ props.row.username }}</span>
                  </div>
                </q-td>
              </template>

              <!-- Preferred/First Name Column -->
              <template v-slot:body-cell-display_name="props">
                <q-td :props="props">
                  <span class="text-body2">{{ props.row.preferred_name || props.row.first_name || '—' }}</span>
                </q-td>
              </template>

              <!-- Last Name Column -->
              <template v-slot:body-cell-last_name="props">
                <q-td :props="props">
                  <span class="text-body2">{{ props.row.last_name || '—' }}</span>
                </q-td>
              </template>

              <!-- Home Shop Column -->
              <template v-slot:body-cell-home_shop="props">
                <q-td :props="props">
                  <span class="text-body2">{{ getHomeShopName(props.row) }}</span>
                </q-td>
              </template>

              <!-- Email Column -->
              <template v-slot:body-cell-email="props">
                <q-td :props="props">
                  <span class="text-body2">{{ props.row.email || '—' }}</span>
                </q-td>
              </template>

              <!-- Phone Column -->
              <template v-slot:body-cell-phone_number="props">
                <q-td :props="props">
                  <span class="text-body2">{{ formatPhoneNumber(props.row.phone_number) || '—' }}</span>
                </q-td>
              </template>

              <!-- Last Modified Column -->
              <template v-slot:body-cell-updated_at="props">
                <q-td :props="props">
                  <span class="text-body2">{{ formatDate(props.row.updated_at) }}</span>
                </q-td>
              </template>

              <!-- Active/Deactive Toggle Column -->
              <template v-slot:body-cell-active="props">
                <q-td :props="props" @click.stop>
                  <div class="row items-center no-wrap">
                    <q-toggle
                      :model-value="props.row.active"
                      :disable="!canToggleStatus(props.row) || togglingUserId === props.row.id"
                      :loading="togglingUserId === props.row.id"
                      color="positive"
                      @update:model-value="(val) => handleToggleActive(props.row, val)"
                    />
                    <span
                      class="text-caption q-ml-xs"
                      :class="props.row.active ? 'text-positive' : 'text-grey'"
                    >
                      {{ props.row.active ? 'Active' : 'Deactive' }}
                    </span>
                    <q-tooltip v-if="!canToggleStatus(props.row)">
                      {{ getToggleTooltip(props.row) }}
                    </q-tooltip>
                  </div>
                </q-td>
              </template>
            </q-table>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Edit User Dialog -->
    <q-dialog v-model="showEditDialog">
      <q-card style="width: 100%; max-width: 600px; max-height: 80vh">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Edit User</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section v-if="editForm" class="scroll">
          <q-form @submit.prevent="saveUser">
            <div class="row q-col-gutter-md">
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
                  @update:model-value="(val) => onEditPhoneInput(val as string)"
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

    <!-- Add User Dialog -->
    <q-dialog v-model="showAddDialog" persistent>
      <q-card style="width: 100%; max-width: 600px; max-height: 80vh">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Add New User</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section class="scroll">
          <q-form @submit.prevent="addUser">
            <div class="row q-col-gutter-md">
              <div class="col-12">
                <q-input
                  v-model="addForm.username"
                  label="Username"
                  outlined
                  dense
                  :rules="[(val) => (val && val.length > 0) || 'Username is required']"
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.password"
                  label="Password"
                  type="password"
                  outlined
                  dense
                  :rules="[(val) => (val && val.length >= 8) || 'Password must be at least 8 characters']"
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.password_confirmation"
                  label="Confirm Password"
                  type="password"
                  outlined
                  dense
                  :rules="[(val) => val === addForm.password || 'Passwords do not match']"
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.employee_id"
                  label="Employee ID"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-select
                  v-model="addForm.role_ids"
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
                  v-model="addForm.first_name"
                  label="First Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.last_name"
                  label="Last Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.preferred_name"
                  label="Preferred Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  :model-value="addForm.phone_number"
                  @update:model-value="(val) => onAddPhoneInput(val as string)"
                  label="Phone Number"
                  outlined
                  dense
                  maxlength="14"
                  hint="Format: (xxx)xxx-xxxx"
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.email"
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
                  v-model="addForm.personal_email"
                  label="Personal Email"
                  type="email"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.dext_email"
                  label="Dext Email"
                  type="email"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.pin_code"
                  label="PIN Code"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-select
                  v-model="addForm.home_location_id"
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
                  v-model="addForm.slack_id"
                  label="Slack ID"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.paytype"
                  label="Pay Type"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.address_line_1"
                  label="Address"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.address_line_2"
                  label="Address 2"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.city"
                  label="City"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.state"
                  label="State"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="addForm.zip"
                  label="Zip"
                  outlined
                  dense
                />
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
                label="Add User"
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
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';
import { formatPhoneNumber } from 'src/composables/usePhoneFormat';
import { useAuthStore } from 'stores/auth';
import UserAvatar from 'src/components/UserAvatar.vue';

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

const router = useRouter();
const $q = useQuasar();
const authStore = useAuthStore();

const users = ref<User[]>([]);
const loading = ref(false);
const saving = ref(false);
const searchQuery = ref('');
const showInactive = ref(false);
const showEditDialog = ref(false);
const showAddDialog = ref(false);
const editForm = ref<User | null>(null);
const togglingUserId = ref<number | null>(null);

const addForm = ref({
  username: '',
  password: '',
  password_confirmation: '',
  employee_id: '',
  role: 'read_only',
  role_ids: [] as number[],
  first_name: '',
  last_name: '',
  preferred_name: '',
  phone_number: '',
  email: '',
  personal_email: '',
  dext_email: '',
  pin_code: '',
  home_shop: '',
  home_location_id: null as number | null,
  slack_id: '',
  paytype: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  state: '',
  zip: '',
});

const shopLocations = ref<ShopLocation[]>([]);
const roles = ref<Role[]>([]);

const pagination = ref({
  sortBy: 'updated_at',
  descending: true,
  page: 1,
  rowsPerPage: 15,
  rowsNumber: 0,
});

// Table columns in exact order specified
const columns = [
  {
    name: 'employee_id',
    label: 'Employee ID',
    align: 'left' as const,
    field: 'employee_id',
    sortable: true,
  },
  {
    name: 'username',
    required: true,
    label: 'Username',
    align: 'left' as const,
    field: 'username',
    sortable: true,
  },
  {
    name: 'display_name',
    label: 'Name',
    align: 'left' as const,
    field: (row: User) => row.preferred_name || row.first_name || '',
    sortable: true,
  },
  {
    name: 'last_name',
    label: 'Last Name',
    align: 'left' as const,
    field: 'last_name',
    sortable: true,
  },
  {
    name: 'home_shop',
    label: 'Home Shop',
    align: 'left' as const,
    field: (row: User) => row.home_location?.name || row.home_shop || '',
    sortable: true,
  },
  {
    name: 'email',
    required: true,
    label: 'Email',
    align: 'left' as const,
    field: 'email',
    sortable: true,
  },
  {
    name: 'phone_number',
    label: 'Phone',
    align: 'left' as const,
    field: 'phone_number',
    sortable: true,
  },
  {
    name: 'updated_at',
    label: 'Last Modified',
    align: 'left' as const,
    field: 'updated_at',
    sortable: true,
  },
  {
    name: 'active',
    label: 'Status',
    align: 'center' as const,
    field: 'active',
    sortable: true,
  },
];

const filteredUsers = computed(() => {
  if (!searchQuery.value) {
    return users.value;
  }

  const query = searchQuery.value.toLowerCase();
  return users.value.filter(
    (user) =>
      user.username.toLowerCase().includes(query) ||
      user.email.toLowerCase().includes(query) ||
      user.id.toString().includes(query) ||
      (user.employee_id && user.employee_id.toLowerCase().includes(query)) ||
      (user.first_name && user.first_name.toLowerCase().includes(query)) ||
      (user.last_name && user.last_name.toLowerCase().includes(query)) ||
      (user.preferred_name && user.preferred_name.toLowerCase().includes(query)) ||
      (user.phone_number && user.phone_number.toLowerCase().includes(query))
  );
});

async function fetchUsers() {
  loading.value = true;
  try {
    const params = showInactive.value ? { include_inactive: 'true' } : {};
    const response = await api.get('/users', { params });
    users.value = response.data;
    pagination.value.rowsNumber = response.data.length;
  } catch (error) {
    console.error('Error fetching users:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to fetch users',
      position: 'top',
    });
  } finally {
    loading.value = false;
  }
}

function onRowClick(_evt: Event, row: User) {
  // Navigate to user detail page
  void router.push(`/users/${row.id}`);
}

function getUserInitials(user: User): string {
  const firstName = user.first_name || user.preferred_name || '';
  const lastName = user.last_name || '';

  if (firstName && lastName) {
    return `${firstName[0]}${lastName[0]}`.toUpperCase();
  } else if (firstName) {
    return firstName.charAt(0).toUpperCase();
  } else if (user.username) {
    return user.username.charAt(0).toUpperCase();
  }

  return '?';
}

function getHomeShopName(user: User): string {
  if (user.home_location?.name) {
    return user.home_location.name;
  }
  if (user.home_shop) {
    return user.home_shop;
  }
  return '—';
}

function canToggleStatus(user: User): boolean {
  // Can't toggle own status
  if (authStore.user?.id === user.id) {
    return false;
  }
  // Check for permission - allow if user has any user management permission
  // If no permission system set up, default to allowing
  if (Object.keys(authStore.abilities).length === 0) {
    return true;
  }
  return authStore.can('users.update') || authStore.can('users.manage');
}

function getToggleTooltip(user: User): string {
  if (authStore.user?.id === user.id) {
    return 'Cannot change your own status';
  }
  return 'No permission';
}

async function handleToggleActive(user: User, newValue: boolean) {
  // Prevent toggling own status
  if (authStore.user?.id === user.id) {
    $q.notify({
      type: 'warning',
      message: 'You cannot change your own active status',
      position: 'top',
    });
    return;
  }

  const previousValue = user.active;
  togglingUserId.value = user.id;

  // Optimistic update
  const userIndex = users.value.findIndex(u => u.id === user.id);
  if (userIndex !== -1) {
    users.value[userIndex].active = newValue;
  }

  try {
    await api.patch(`/users/${user.id}/status`, { active: newValue });

    $q.notify({
      type: 'positive',
      message: newValue ? 'User activated successfully' : 'User deactivated successfully',
      position: 'top',
    });
  } catch (error) {
    // Revert on error
    if (userIndex !== -1) {
      users.value[userIndex].active = previousValue;
    }

    console.error('Error updating user status:', error);
    const errorMessage =
      error instanceof Error && 'response' in error
        ? (error as { response?: { data?: { message?: string } } }).response
            ?.data?.message
        : undefined;
    $q.notify({
      type: 'negative',
      message: errorMessage || 'Failed to update user status',
      position: 'top',
    });
  } finally {
    togglingUserId.value = null;
  }
}

function openAddUserDialog() {
  // Reset the form
  addForm.value = {
    username: '',
    password: '',
    password_confirmation: '',
    employee_id: '',
    role: 'read_only',
    role_ids: [],
    first_name: '',
    last_name: '',
    preferred_name: '',
    phone_number: '',
    email: '',
    personal_email: '',
    dext_email: '',
    pin_code: '',
    home_shop: '',
    home_location_id: null,
    slack_id: '',
    paytype: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    zip: '',
  };
  showAddDialog.value = true;
}

async function loadShopLocations() {
  try {
    // Fetch only fixed_shop type locations for the Home Shop dropdown
    const response = await api.get('/locations', {
      params: {
        type: 'fixed_shop',
        active: 'true',
        per_page: 100,
      },
    });
    shopLocations.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load shop locations', error);
  }
}

async function loadRoles() {
  try {
    const response = await api.get('/roles');
    roles.value = response.data;
  } catch (error) {
    console.error('Failed to load roles', error);
  }
}

async function saveUser() {
  if (!editForm.value) return;

  saving.value = true;
  try {
    // Backend now handles role sync in the same update call
    await api.put(`/users/${editForm.value.id}`, editForm.value);

    $q.notify({
      type: 'positive',
      message: 'User updated successfully',
      position: 'top',
    });
    showEditDialog.value = false;
    await fetchUsers();
  } catch (error) {
    console.error('Error updating user:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to update user',
      position: 'top',
    });
  } finally {
    saving.value = false;
  }
}

async function addUser() {
  saving.value = true;
  try {
    await api.post('/register', addForm.value);
    $q.notify({
      type: 'positive',
      message: 'User added successfully',
      position: 'top',
    });
    showAddDialog.value = false;
    await fetchUsers();
  } catch (error) {
    console.error('Error adding user:', error);
    const errorMessage =
      error instanceof Error && 'response' in error
        ? (error as { response?: { data?: { message?: string } } }).response
            ?.data?.message
        : undefined;
    $q.notify({
      type: 'negative',
      message: errorMessage || 'Failed to add user',
      position: 'top',
    });
  } finally {
    saving.value = false;
  }
}

function toggleShowInactive() {
  showInactive.value = !showInactive.value;
  void fetchUsers();
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

// Phone input handlers using shared utility
function onEditPhoneInput(val: string) {
  if (editForm.value) {
    editForm.value.phone_number = formatPhoneNumber(val);
  }
}

function onAddPhoneInput(val: string) {
  addForm.value.phone_number = formatPhoneNumber(val);
}

onMounted(() => {
  void fetchUsers();
  void loadShopLocations();
  void loadRoles();
});
</script>

<style scoped>
.users-table :deep(tbody tr) {
  cursor: pointer;
}

.users-table :deep(tbody tr:hover) {
  background-color: rgba(0, 0, 0, 0.03);
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
