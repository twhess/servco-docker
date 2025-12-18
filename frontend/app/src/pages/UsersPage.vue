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
                style="min-width: 250px"
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
              </q-input>
            </div>

            <q-table
              :rows="filteredUsers"
              :columns="columns"
              row-key="id"
              :loading="loading"
              :pagination="pagination"
              @request="onRequest"
              flat
              bordered
            >
              <template v-slot:body-cell-username="props">
                <q-td :props="props">
                  <div class="row items-center">
                    <q-avatar size="32px" class="q-mr-sm">
                      <img :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(props.row.username)}&background=random`">
                    </q-avatar>
                    <div>
                      <div class="text-weight-medium">{{ props.row.username }}</div>
                    </div>
                  </div>
                </q-td>
              </template>

              <template v-slot:body-cell-email="props">
                <q-td :props="props">
                  <div class="text-body2">{{ props.row.email }}</div>
                </q-td>
              </template>

              <template v-slot:body-cell-created_at="props">
                <q-td :props="props">
                  <div class="text-body2">{{ formatDate(props.row.created_at) }}</div>
                </q-td>
              </template>

              <template v-slot:body-cell-actions="props">
                <q-td :props="props">
                  <q-btn
                    flat
                    dense
                    round
                    icon="more_vert"
                    size="sm"
                  >
                    <q-menu>
                      <q-list style="min-width: 150px">
                        <q-item clickable v-close-popup @click="viewUser(props.row)">
                          <q-item-section avatar>
                            <q-icon name="visibility" />
                          </q-item-section>
                          <q-item-section>View Details</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup @click="editUser(props.row)">
                          <q-item-section avatar>
                            <q-icon name="edit" />
                          </q-item-section>
                          <q-item-section>Edit</q-item-section>
                        </q-item>
                        <q-separator />
                        <q-item
                          clickable
                          v-close-popup
                          @click="deleteUser(props.row)"
                          class="text-negative"
                        >
                          <q-item-section avatar>
                            <q-icon name="delete" color="negative" />
                          </q-item-section>
                          <q-item-section>Delete</q-item-section>
                        </q-item>
                      </q-list>
                    </q-menu>
                  </q-btn>
                </q-td>
              </template>
            </q-table>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Edit User Dialog -->
    <q-dialog v-model="showEditDialog">
      <q-card style="min-width: 600px; max-width: 90vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Edit User</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section v-if="editForm">
          <q-form @submit.prevent="saveUser">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.username"
                  label="Username"
                  outlined
                  dense
                  :rules="[(val) => (val && val.length > 0) || 'Username is required']"
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.employee_id"
                  label="Employee ID"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.first_name"
                  label="First Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.last_name"
                  label="Last Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.preferred_name"
                  label="Preferred Name"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.phone_number"
                  label="Phone Number"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
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

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.personal_email"
                  label="Personal Email"
                  type="email"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.dext_email"
                  label="Dext Email"
                  type="email"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.pin_code"
                  label="PIN Code"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.home_shop"
                  label="Home Shop"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.slack_id"
                  label="Slack ID"
                  outlined
                  dense
                />
              </div>

              <div class="col-12 col-sm-6">
                <q-input
                  v-model="editForm.paytype"
                  label="Pay Type"
                  outlined
                  dense
                />
              </div>

              <div class="col-12">
                <q-input
                  v-model="editForm.address"
                  label="Address"
                  type="textarea"
                  outlined
                  dense
                  rows="2"
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
                label="Save"
                color="primary"
                :loading="loading"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- User Details Dialog -->
    <q-dialog v-model="showUserDialog">
      <q-card style="min-width: 400px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">User Details</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section v-if="selectedUser">
          <div class="q-mb-md text-center">
            <q-avatar size="80px">
              <img :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(selectedUser.username)}&background=random&size=200`">
            </q-avatar>
          </div>

          <q-list>
            <q-item>
              <q-item-section>
                <q-item-label caption>Username</q-item-label>
                <q-item-label>{{ selectedUser.username }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.employee_id">
              <q-item-section>
                <q-item-label caption>Employee ID</q-item-label>
                <q-item-label>{{ selectedUser.employee_id }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.first_name">
              <q-item-section>
                <q-item-label caption>First Name</q-item-label>
                <q-item-label>{{ selectedUser.first_name }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.last_name">
              <q-item-section>
                <q-item-label caption>Last Name</q-item-label>
                <q-item-label>{{ selectedUser.last_name }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.preferred_name">
              <q-item-section>
                <q-item-label caption>Preferred Name</q-item-label>
                <q-item-label>{{ selectedUser.preferred_name }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section>
                <q-item-label caption>Email</q-item-label>
                <q-item-label>{{ selectedUser.email }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.personal_email">
              <q-item-section>
                <q-item-label caption>Personal Email</q-item-label>
                <q-item-label>{{ selectedUser.personal_email }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.dext_email">
              <q-item-section>
                <q-item-label caption>Dext Email</q-item-label>
                <q-item-label>{{ selectedUser.dext_email }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.phone_number">
              <q-item-section>
                <q-item-label caption>Phone Number</q-item-label>
                <q-item-label>{{ selectedUser.phone_number }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.pin_code">
              <q-item-section>
                <q-item-label caption>PIN Code</q-item-label>
                <q-item-label>{{ selectedUser.pin_code }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.home_shop">
              <q-item-section>
                <q-item-label caption>Home Shop</q-item-label>
                <q-item-label>{{ selectedUser.home_shop }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.slack_id">
              <q-item-section>
                <q-item-label caption>Slack ID</q-item-label>
                <q-item-label>{{ selectedUser.slack_id }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.paytype">
              <q-item-section>
                <q-item-label caption>Pay Type</q-item-label>
                <q-item-label>{{ selectedUser.paytype }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.address">
              <q-item-section>
                <q-item-label caption>Address</q-item-label>
                <q-item-label>{{ selectedUser.address }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section>
                <q-item-label caption>User ID</q-item-label>
                <q-item-label>{{ selectedUser.id }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section>
                <q-item-label caption>Created At</q-item-label>
                <q-item-label>{{ formatDate(selectedUser.created_at) }}</q-item-label>
              </q-item-section>
            </q-item>

            <q-item v-if="selectedUser.updated_at">
              <q-item-section>
                <q-item-label caption>Last Updated</q-item-label>
                <q-item-label>{{ formatDate(selectedUser.updated_at) }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';

interface User {
  id: number;
  username: string;
  email: string;
  employee_id?: string;
  first_name?: string;
  last_name?: string;
  preferred_name?: string;
  phone_number?: string;
  pin_code?: string;
  home_shop?: string;
  personal_email?: string;
  slack_id?: string;
  dext_email?: string;
  address?: string;
  paytype?: string;
  created_at: string;
  updated_at?: string;
}

const $q = useQuasar();

const users = ref<User[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const showUserDialog = ref(false);
const showEditDialog = ref(false);
const selectedUser = ref<User | null>(null);
const editForm = ref<User | null>(null);

const pagination = ref({
  sortBy: 'id',
  descending: false,
  page: 1,
  rowsPerPage: 10,
  rowsNumber: 0,
});

const columns = [
  {
    name: 'id',
    required: true,
    label: 'ID',
    align: 'left' as const,
    field: 'id',
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
    name: 'employee_id',
    label: 'Employee ID',
    align: 'left' as const,
    field: 'employee_id',
    sortable: true,
  },
  {
    name: 'first_name',
    label: 'First Name',
    align: 'left' as const,
    field: 'first_name',
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
    name: 'preferred_name',
    label: 'Preferred Name',
    align: 'left' as const,
    field: 'preferred_name',
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
    name: 'personal_email',
    label: 'Personal Email',
    align: 'left' as const,
    field: 'personal_email',
    sortable: true,
  },
  {
    name: 'dext_email',
    label: 'Dext Email',
    align: 'left' as const,
    field: 'dext_email',
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
    name: 'pin_code',
    label: 'PIN',
    align: 'left' as const,
    field: 'pin_code',
    sortable: true,
  },
  {
    name: 'home_shop',
    label: 'Home Shop',
    align: 'left' as const,
    field: 'home_shop',
    sortable: true,
  },
  {
    name: 'slack_id',
    label: 'Slack ID',
    align: 'left' as const,
    field: 'slack_id',
    sortable: true,
  },
  {
    name: 'address',
    label: 'Address',
    align: 'left' as const,
    field: 'address',
    sortable: true,
  },
  {
    name: 'paytype',
    label: 'Pay Type',
    align: 'left' as const,
    field: 'paytype',
    sortable: true,
  },
  {
    name: 'created_at',
    label: 'Created',
    align: 'left' as const,
    field: 'created_at',
    sortable: true,
  },
  {
    name: 'actions',
    label: 'Actions',
    align: 'center' as const,
    field: 'actions',
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
      (user.phone_number && user.phone_number.toLowerCase().includes(query))
  );
});

async function fetchUsers() {
  loading.value = true;
  try {
    const response = await api.get('/users');
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

function onRequest() {
  void fetchUsers();
}

function viewUser(user: User) {
  selectedUser.value = user;
  showUserDialog.value = true;
}

function editUser(user: User) {
  editForm.value = { ...user };
  showEditDialog.value = true;
}

async function saveUser() {
  if (!editForm.value) return;

  loading.value = true;
  try {
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
    loading.value = false;
  }
}

function deleteUser(user: User) {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete ${user.username}?`,
    cancel: true,
    persistent: true,
  }).onOk(() => {
    void (async () => {
      try {
        await api.delete(`/users/${user.id}`);
        $q.notify({
          type: 'positive',
          message: 'User deleted successfully',
          position: 'top',
        });
        await fetchUsers();
      } catch (error) {
        console.error('Error deleting user:', error);
        $q.notify({
          type: 'negative',
          message: 'Failed to delete user',
          position: 'top',
        });
      }
    })();
  });
}

function formatDate(dateString: string) {
  if (!dateString) return '';
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
  void fetchUsers();
});
</script>

<style scoped>
.q-table {
  box-shadow: none;
}
</style>
