<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h5">Roles & Permissions</div>
      <div class="row q-gutter-sm">
        <q-btn
          v-if="can('roles.create')"
          flat
          @click="openCreateDialog"
        >
          <q-icon name="add" color="primary" size="sm" class="q-mr-xs" />
          <span class="text-primary">Create Role</span>
        </q-btn>
        <q-btn flat @click="showInactive = !showInactive">
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
    </div>

    <q-card flat bordered>
      <q-table
        flat
        :rows="filteredRoles"
        :columns="columns"
        row-key="id"
        :loading="loading"
        :pagination="pagination"
      >
        <template v-slot:body-cell-name="props">
          <q-td :props="props">
            <div class="text-weight-medium">{{ props.row.display_name }}</div>
            <div class="text-caption text-grey-7">{{ props.row.name }}</div>
          </q-td>
        </template>

        <template v-slot:body-cell-description="props">
          <q-td :props="props">
            <div class="text-body2">{{ props.row.description || '-' }}</div>
          </q-td>
        </template>

        <template v-slot:body-cell-permissions="props">
          <q-td :props="props">
            <q-chip
              dense
              color="primary"
              text-color="white"
              size="sm"
            >
              {{ props.row.permissions?.length || 0 }} permissions
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-type="props">
          <q-td :props="props">
            <q-chip
              dense
              :color="props.row.is_system ? 'grey' : 'green'"
              text-color="white"
              size="sm"
            >
              {{ props.row.is_system ? 'System' : 'Custom' }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-active="props">
          <q-td :props="props">
            <q-toggle
              :model-value="props.row.is_active"
              :disable="!can('roles.update')"
              @update:model-value="toggleActive(props.row)"
            />
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props">
            <q-btn flat dense round icon="more_vert">
              <q-menu>
                <q-list style="min-width: 150px">
                  <q-item clickable v-close-popup @click="viewRole(props.row)">
                    <q-item-section avatar>
                      <q-icon name="visibility" />
                    </q-item-section>
                    <q-item-section>View Details</q-item-section>
                  </q-item>

                  <q-item
                    v-if="can('roles.update')"
                    clickable
                    v-close-popup
                    @click="openEditDialog(props.row)"
                  >
                    <q-item-section avatar>
                      <q-icon name="edit" />
                    </q-item-section>
                    <q-item-section>Edit</q-item-section>
                  </q-item>

                  <q-separator v-if="can('roles.delete') && !props.row.is_system" />

                  <q-item
                    v-if="can('roles.delete') && !props.row.is_system"
                    clickable
                    v-close-popup
                    @click="confirmDelete(props.row)"
                  >
                    <q-item-section avatar>
                      <q-icon name="delete" color="negative" />
                    </q-item-section>
                    <q-item-section class="text-negative">Delete</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <!-- View Role Dialog -->
    <q-dialog v-model="showViewDialog">
      <q-card style="width: 100%; max-width: 600px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ viewingRole?.display_name }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section v-if="viewingRole">
          <div class="q-mb-md">
            <div class="text-caption text-grey-7">Role Name</div>
            <div class="text-body1">{{ viewingRole.name }}</div>
          </div>

          <div class="q-mb-md">
            <div class="text-caption text-grey-7">Description</div>
            <div class="text-body1">{{ viewingRole.description || '-' }}</div>
          </div>

          <div class="q-mb-md">
            <div class="text-caption text-grey-7">Type</div>
            <q-chip
              dense
              :color="viewingRole.is_system ? 'grey' : 'green'"
              text-color="white"
              size="sm"
            >
              {{ viewingRole.is_system ? 'System Role' : 'Custom Role' }}
            </q-chip>
          </div>

          <div class="q-mb-md">
            <div class="text-caption text-grey-7">Status</div>
            <q-chip
              dense
              :color="viewingRole.is_active ? 'positive' : 'grey'"
              text-color="white"
              size="sm"
            >
              {{ viewingRole.is_active ? 'Active' : 'Inactive' }}
            </q-chip>
          </div>

          <div>
            <div class="text-caption text-grey-7 q-mb-sm">Permissions ({{ viewingRole.permissions?.length || 0 }})</div>
            <div v-if="viewingRole.permissions && viewingRole.permissions.length > 0">
              <div v-for="module in groupedViewPermissions" :key="module.name" class="q-mb-md">
                <div class="text-weight-medium q-mb-xs">{{ formatModuleName(module.name) }}</div>
                <q-chip
                  v-for="permission in module.permissions"
                  :key="permission.id"
                  dense
                  color="blue-2"
                  text-color="blue-9"
                  size="sm"
                  class="q-mr-xs q-mb-xs"
                >
                  {{ permission.display_name }}
                </q-chip>
              </div>
            </div>
            <div v-else class="text-grey-6">No permissions assigned</div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Create/Edit Role Dialog -->
    <q-dialog v-model="showRoleDialog" persistent>
      <q-card style="width: 100%; max-width: 900px; max-height: 80vh">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ editingRole ? 'Edit Role' : 'Create Role' }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-separator />

        <q-card-section style="max-height: 70vh" class="scroll">
          <q-form @submit="saveRole" class="q-gutter-md">
            <div class="row q-col-gutter-md">
              <div class="col-12 col-sm-6">
                <q-input
                  v-model="roleForm.name"
                  label="Role Name (slug) *"
                  outlined
                  dense
                  :rules="[val => !!val || 'Name is required']"
                  hint="lowercase_with_underscores"
                  :disable="editingRole?.is_system"
                />
              </div>
              <div class="col-12 col-sm-6">
                <q-input
                  v-model="roleForm.display_name"
                  label="Display Name *"
                  outlined
                  dense
                  :rules="[val => !!val || 'Display name is required']"
                />
              </div>
            </div>

            <q-input
              v-model="roleForm.description"
              label="Description"
              outlined
              dense
              type="textarea"
              rows="2"
            />

            <q-toggle
              v-model="roleForm.is_active"
              label="Active"
            />

            <q-separator />

            <div class="text-subtitle1 text-weight-medium q-mb-sm">Permissions</div>

            <div v-if="permissionsGrouped">
              <div v-for="(modulePerms, moduleName) in permissionsGrouped" :key="moduleName" class="q-mb-md">
                <div class="row items-center q-mb-sm">
                  <div class="text-weight-medium">{{ formatModuleName(moduleName) }}</div>
                  <q-space />
                  <q-btn
                    flat
                    dense
                    size="sm"
                    color="primary"
                    label="Select All"
                    @click="selectAllModule(moduleName)"
                  />
                  <q-btn
                    flat
                    dense
                    size="sm"
                    color="grey"
                    label="Deselect All"
                    @click="deselectAllModule(moduleName)"
                  />
                </div>
                <div class="row q-col-gutter-sm">
                  <div
                    v-for="permission in modulePerms"
                    :key="permission.id"
                    class="col-12 col-sm-6 col-md-4"
                  >
                    <q-checkbox
                      v-model="selectedPermissions"
                      :val="permission.id"
                      :label="permission.display_name"
                      dense
                    >
                      <q-tooltip>{{ permission.description }}</q-tooltip>
                    </q-checkbox>
                  </div>
                </div>
              </div>
            </div>
          </q-form>
        </q-card-section>

        <q-separator />

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Save"
            color="primary"
            @click="saveRole"
            :loading="saving"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';
import { useAuthStore } from 'src/stores/auth';

interface Permission {
  id: number;
  name: string;
  module: string;
  action: string;
  display_name: string;
  description: string;
}

interface Role {
  id: number;
  name: string;
  display_name: string;
  description: string | null;
  is_system: boolean;
  is_active: boolean;
  permissions?: Permission[];
  created_at: string;
  updated_at: string;
}

const $q = useQuasar();
const authStore = useAuthStore();

const roles = ref<Role[]>([]);
const permissions = ref<Record<string, Permission[]>>({});
const loading = ref(false);
const saving = ref(false);
const showInactive = ref(false);
const showViewDialog = ref(false);
const showRoleDialog = ref(false);
const editingRole = ref<Role | null>(null);
const viewingRole = ref<Role | null>(null);

const roleForm = ref({
  name: '',
  display_name: '',
  description: '',
  is_active: true,
});

const selectedPermissions = ref<number[]>([]);

const pagination = ref({
  rowsPerPage: 0, // Show all
});

const columns = [
  { name: 'name', label: 'Role', field: 'display_name', align: 'left' as const, sortable: true },
  { name: 'description', label: 'Description', field: 'description', align: 'left' as const },
  { name: 'permissions', label: 'Permissions', field: 'permissions', align: 'center' as const },
  { name: 'type', label: 'Type', field: 'is_system', align: 'center' as const },
  { name: 'active', label: 'Active', field: 'is_active', align: 'center' as const },
  { name: 'actions', label: '', field: 'actions', align: 'right' as const },
];

const filteredRoles = computed(() => {
  if (showInactive.value) {
    return roles.value;
  }
  return roles.value.filter(r => r.is_active);
});

const permissionsGrouped = computed(() => {
  return permissions.value;
});

const groupedViewPermissions = computed(() => {
  if (!viewingRole.value?.permissions) return [];

  const grouped = viewingRole.value.permissions.reduce((acc, perm) => {
    if (!acc[perm.module]) {
      acc[perm.module] = [];
    }
    acc[perm.module].push(perm);
    return acc;
  }, {} as Record<string, Permission[]>);

  return Object.entries(grouped).map(([name, perms]) => ({
    name,
    permissions: perms,
  }));
});

function can(ability: string): boolean {
  return authStore.can(ability);
}

function formatModuleName(module: string): string {
  return module
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

async function fetchRoles() {
  loading.value = true;
  try {
    const response = await api.get('/roles', {
      params: { include_inactive: showInactive.value },
    });
    roles.value = response.data;
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Failed to load roles',
    });
  } finally {
    loading.value = false;
  }
}

async function fetchPermissions() {
  try {
    const response = await api.get('/permissions');
    permissions.value = response.data;
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Failed to load permissions',
    });
  }
}

function openCreateDialog() {
  editingRole.value = null;
  roleForm.value = {
    name: '',
    display_name: '',
    description: '',
    is_active: true,
  };
  selectedPermissions.value = [];
  showRoleDialog.value = true;
}

function openEditDialog(role: Role) {
  editingRole.value = role;
  roleForm.value = {
    name: role.name,
    display_name: role.display_name,
    description: role.description || '',
    is_active: role.is_active,
  };
  selectedPermissions.value = role.permissions?.map(p => p.id) || [];
  showRoleDialog.value = true;
}

function viewRole(role: Role) {
  viewingRole.value = role;
  showViewDialog.value = true;
}

async function saveRole() {
  if (!roleForm.value.name || !roleForm.value.display_name) {
    $q.notify({
      type: 'warning',
      message: 'Please fill in all required fields',
    });
    return;
  }

  saving.value = true;
  try {
    const payload = {
      ...roleForm.value,
      permission_ids: selectedPermissions.value,
    };

    if (editingRole.value) {
      await api.put(`/roles/${editingRole.value.id}`, payload);
      $q.notify({
        type: 'positive',
        message: 'Role updated successfully',
      });
    } else {
      await api.post('/roles', payload);
      $q.notify({
        type: 'positive',
        message: 'Role created successfully',
      });
    }

    showRoleDialog.value = false;
    await fetchRoles();
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Failed to save role',
    });
  } finally {
    saving.value = false;
  }
}

async function toggleActive(role: Role) {
  try {
    await api.put(`/roles/${role.id}`, {
      is_active: !role.is_active,
    });
    $q.notify({
      type: 'positive',
      message: 'Role status updated',
    });
    await fetchRoles();
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Failed to update role',
    });
  }
}

function confirmDelete(role: Role) {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete the role "${role.display_name}"? This action cannot be undone.`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/roles/${role.id}`);
      $q.notify({
        type: 'positive',
        message: 'Role deleted successfully',
      });
      await fetchRoles();
    } catch (error: any) {
      $q.notify({
        type: 'negative',
        message: error.response?.data?.message || 'Failed to delete role',
      });
    }
  });
}

function selectAllModule(moduleName: string) {
  const modulePerms = permissions.value[moduleName] || [];
  const moduleIds = modulePerms.map(p => p.id);

  // Add all module permissions that aren't already selected
  moduleIds.forEach(id => {
    if (!selectedPermissions.value.includes(id)) {
      selectedPermissions.value.push(id);
    }
  });
}

function deselectAllModule(moduleName: string) {
  const modulePerms = permissions.value[moduleName] || [];
  const moduleIds = modulePerms.map(p => p.id);

  // Remove all module permissions
  selectedPermissions.value = selectedPermissions.value.filter(
    id => !moduleIds.includes(id)
  );
}

onMounted(() => {
  void fetchRoles();
  void fetchPermissions();
});
</script>
