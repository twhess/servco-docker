<template>
  <q-page padding>
    <div class="q-pb-md">
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h5">Locations</div>
        <div class="row q-gutter-sm">
          <q-btn
            v-if="can('service_locations.create')"
            flat
            @click="openCreateDialog"
          >
            <q-icon name="add" color="primary" size="sm" class="q-mr-xs" />
            <span class="text-primary">Add Location</span>
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

      <q-tabs
        v-model="selectedTab"
        dense
        class="text-grey"
        active-color="primary"
        indicator-color="primary"
        align="left"
        @update:model-value="handleTabChange"
      >
        <q-tab name="all" label="All" />
        <q-tab name="fixed_shop" label="Shops" />
        <q-tab name="mobile_service_truck" label="Mobile Trucks" />
        <q-tab name="parts_runner_vehicle" label="Runner Vehicles" />
        <q-tab name="vendor" label="Vendors" />
        <q-tab name="customer_site" label="Customer Sites" />
      </q-tabs>
    </div>

    <q-card flat bordered>
      <q-card-section class="q-pa-sm">
        <div class="row q-gutter-sm">
          <q-input
            v-model="search"
            dense
            outlined
            placeholder="Search by name, code, or city"
            class="col-grow"
            @update:model-value="debouncedSearch"
          >
            <template v-slot:prepend>
              <q-icon name="search" />
            </template>
            <template v-slot:append>
              <q-icon
                v-if="search"
                name="close"
                class="cursor-pointer"
                @click="search = ''; fetchLocations()"
              />
            </template>
          </q-input>

          <q-select
            v-model="selectedStatus"
            dense
            outlined
            placeholder="Status"
            :options="statusOptions"
            option-value="value"
            option-label="label"
            emit-value
            map-options
            clearable
            class="col-auto"
            style="min-width: 150px"
            @update:model-value="fetchLocations"
          />

          <q-toggle
            v-if="!can('service_locations.view_all')"
            v-model="myLocationsOnly"
            label="My Locations Only"
            @update:model-value="fetchLocations"
          />
        </div>
      </q-card-section>

      <q-separator />

      <q-table
        flat
        :rows="locations"
        :columns="columns"
        row-key="id"
        :loading="loading"
        :pagination="pagination"
        @request="onTableRequest"
      >
        <template v-slot:body-cell-name="props">
          <q-td :props="props">
            <router-link
              :to="`/locations/${props.row.id}`"
              class="text-primary text-weight-medium"
              style="text-decoration: none"
            >
              {{ props.row.name }}
            </router-link>
            <div v-if="props.row.code" class="text-caption text-grey-7">
              {{ props.row.code }}
            </div>
          </q-td>
        </template>

        <template v-slot:body-cell-type="props">
          <q-td :props="props">
            <q-chip
              :color="getTypeColor(props.row.location_type)"
              text-color="white"
              dense
              size="sm"
            >
              {{ getTypeLabel(props.row.location_type) }}
            </q-chip>
          </q-td>
        </template>

        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <q-chip
              v-if="props.row.status"
              :color="getStatusColor(props.row.status)"
              text-color="white"
              dense
              size="sm"
            >
              {{ getStatusLabel(props.row.status) }}
            </q-chip>
            <span v-else class="text-grey-6">-</span>
          </q-td>
        </template>

        <template v-slot:body-cell-location="props">
          <q-td :props="props">
            <div v-if="props.row.location_type.includes('mobile')">
              <div v-if="props.row.home_base">
                {{ props.row.home_base.name }}
              </div>
              <div v-else class="text-grey-6">No home base</div>
            </div>
            <div v-else>
              {{ [props.row.city, props.row.state].filter(Boolean).join(', ') || '-' }}
            </div>
          </q-td>
        </template>

        <template v-slot:body-cell-assigned="props">
          <q-td :props="props">
            <div v-if="props.row.assigned_user">
              {{ props.row.assigned_user.first_name }} {{ props.row.assigned_user.last_name }}
            </div>
            <span v-else class="text-grey-6">-</span>
          </q-td>
        </template>

        <template v-slot:body-cell-last_seen="props">
          <q-td :props="props">
            <div v-if="props.row.last_known_at">
              {{ formatDateTime(props.row.last_known_at) }}
            </div>
            <span v-else class="text-grey-6">-</span>
          </q-td>
        </template>

        <template v-slot:body-cell-active="props">
          <q-td :props="props">
            <q-toggle
              :model-value="props.row.is_active"
              :disable="!can('service_locations.update_details')"
              @update:model-value="toggleActive(props.row)"
            />
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props">
            <q-btn flat dense round icon="more_vert">
              <q-menu>
                <q-list style="min-width: 150px">
                  <q-item clickable v-close-popup :to="`/locations/${props.row.id}`">
                    <q-item-section avatar>
                      <q-icon name="visibility" />
                    </q-item-section>
                    <q-item-section>View</q-item-section>
                  </q-item>

                  <q-item
                    v-if="can('service_locations.update_details')"
                    clickable
                    v-close-popup
                    @click="openEditDialog(props.row)"
                  >
                    <q-item-section avatar>
                      <q-icon name="edit" />
                    </q-item-section>
                    <q-item-section>Edit</q-item-section>
                  </q-item>

                  <q-item
                    v-if="can('service_locations.assign_user') && props.row.location_type.includes('mobile')"
                    clickable
                    v-close-popup
                    @click="openAssignDialog(props.row)"
                  >
                    <q-item-section avatar>
                      <q-icon name="person_add" />
                    </q-item-section>
                    <q-item-section>Assign User</q-item-section>
                  </q-item>

                  <q-separator v-if="can('service_locations.delete')" />

                  <q-item
                    v-if="can('service_locations.delete')"
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

    <!-- Create/Edit Location Dialog -->
    <q-dialog v-model="showLocationDialog" persistent>
      <q-card style="width: 100%; max-width: 800px; max-height: 80vh">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ editingLocation ? 'Edit Location' : 'Create Location' }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-separator />

        <q-card-section style="max-height: 70vh" class="scroll">
          <q-form @submit="saveLocation">
            <!-- Name and Code Row -->
            <div class="row q-col-gutter-md q-mb-md">
              <div class="col-12 col-sm-8">
                <q-input
                  v-model="locationForm.name"
                  label="Name *"
                  outlined
                  :rules="[val => !!val || 'Name is required']"
                />
              </div>
              <div class="col-12 col-sm-4">
                <q-input
                  v-model="locationForm.code"
                  label="Code"
                  outlined
                />
              </div>
            </div>

            <!-- Type Select -->
            <q-select
              v-model="locationForm.location_type"
              label="Type *"
              outlined
              :options="typeOptions"
              option-value="value"
              option-label="label"
              emit-value
              map-options
              :rules="[val => !!val || 'Type is required']"
              class="q-mb-md"
            />

            <!-- Status Select -->
            <q-select
              v-if="locationForm.location_type && !['vendor', 'customer_site'].includes(locationForm.location_type)"
              v-model="locationForm.status"
              label="Status"
              outlined
              :options="statusOptions"
              option-value="value"
              option-label="label"
              emit-value
              map-options
              clearable
              class="q-mb-md"
            />

            <!-- Fixed Location Fields -->
            <div v-if="locationForm.location_type && !locationForm.location_type.includes('mobile')" class="q-mb-md">
              <div class="text-subtitle2 q-mb-sm">Address</div>
              <div class="row q-col-gutter-md">
                <div class="col-12">
                  <q-input v-model="locationForm.address_line1" label="Address Line 1" outlined />
                </div>
                <div class="col-12">
                  <q-input v-model="locationForm.address_line2" label="Address Line 2" outlined />
                </div>
                <div class="col-12 col-sm-5">
                  <q-input v-model="locationForm.city" label="City" outlined />
                </div>
                <div class="col-12 col-sm-3">
                  <q-input v-model="locationForm.state" label="State" outlined />
                </div>
                <div class="col-12 col-sm-4">
                  <q-input v-model="locationForm.postal_code" label="Postal Code" outlined />
                </div>
              </div>
            </div>

            <!-- Mobile Location Fields -->
            <template v-if="locationForm.location_type && locationForm.location_type.includes('mobile')">
              <q-select
                v-model="locationForm.home_base_location_id"
                label="Home Base Shop"
                outlined
                :options="fixedShops"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
                class="q-mb-md"
              />

              <q-toggle
                v-model="locationForm.is_dispatchable"
                label="Dispatchable"
                class="q-mb-md"
              />
            </template>

            <!-- Notes -->
            <q-input
              v-model="locationForm.notes"
              label="Notes"
              outlined
              type="textarea"
              rows="3"
              class="q-mb-md"
            />

            <!-- Color Settings -->
            <div v-if="['fixed_shop', 'mobile_service_truck', 'parts_runner_vehicle'].includes(locationForm.location_type || '')" class="q-mb-md">
              <div class="text-subtitle2 q-mb-sm">Display Colors</div>
              <div class="row q-col-gutter-md">
                <div class="col-12 col-sm-6">
                  <div class="row items-center no-wrap">
                    <q-input
                      v-model="locationForm.text_color"
                      label="Text Color"
                      outlined
                      class="col"
                      placeholder="#FFFFFF"
                    >
                      <template v-slot:append>
                        <q-icon name="colorize" class="cursor-pointer">
                          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                            <q-color
                              v-model="locationForm.text_color"
                              format-model="hex"
                            />
                          </q-popup-proxy>
                        </q-icon>
                      </template>
                    </q-input>
                    <div
                      v-if="locationForm.text_color"
                      class="color-preview q-ml-sm"
                      :style="{ backgroundColor: locationForm.text_color }"
                    />
                  </div>
                </div>
                <div class="col-12 col-sm-6">
                  <div class="row items-center no-wrap">
                    <q-input
                      v-model="locationForm.background_color"
                      label="Background Color"
                      outlined
                      class="col"
                      placeholder="#1976D2"
                    >
                      <template v-slot:append>
                        <q-icon name="colorize" class="cursor-pointer">
                          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                            <q-color
                              v-model="locationForm.background_color"
                              format-model="hex"
                            />
                          </q-popup-proxy>
                        </q-icon>
                      </template>
                    </q-input>
                    <div
                      v-if="locationForm.background_color"
                      class="color-preview q-ml-sm"
                      :style="{ backgroundColor: locationForm.background_color }"
                    />
                  </div>
                </div>
              </div>
              <div class="q-mt-md">
                <div class="text-caption text-grey-7 q-mb-xs">Preview:</div>
                <q-btn
                  :label="locationForm.name || 'Location Name'"
                  :style="getLocationButtonStyle(locationForm)"
                  no-caps
                  unelevated
                />
              </div>
            </div>

            <!-- Active Toggle -->
            <q-toggle
              v-model="locationForm.is_active"
              label="Active"
            />
          </q-form>
        </q-card-section>

        <q-separator />

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Save"
            color="primary"
            @click="saveLocation"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useLocationsStore, type ServiceLocation } from 'src/stores/locations';
import { useAuthStore } from 'src/stores/auth';
import { useQuasar } from 'quasar';
import { debounce } from 'quasar';

const locationsStore = useLocationsStore();
const authStore = useAuthStore();
const $q = useQuasar();

const locations = computed(() => locationsStore.locations);
const loading = computed(() => locationsStore.loading);

const selectedTab = ref('all');
const search = ref('');
const selectedStatus = ref<string | null>(null);
const showInactive = ref(false);
const myLocationsOnly = ref(!authStore.can('service_locations.view_all'));
const showLocationDialog = ref(false);
const editingLocation = ref<ServiceLocation | null>(null);
const fixedShops = ref<ServiceLocation[]>([]);

const pagination = ref({
  sortBy: 'name',
  descending: false,
  page: 1,
  rowsPerPage: 15,
  rowsNumber: 0,
});

const locationForm = ref<Partial<ServiceLocation>>({
  name: '',
  code: '',
  location_type: 'fixed_shop' as any,
  status: 'available' as any,
  is_active: true,
  is_dispatchable: false,
  address_line1: '',
  address_line2: '',
  city: '',
  state: '',
  postal_code: '',
  notes: '',
  home_base_location_id: null,
  text_color: null,
  background_color: null,
});

const columns = [
  { name: 'name', label: 'Name', field: 'name', align: 'left' as const, sortable: true },
  { name: 'type', label: 'Type', field: 'location_type', align: 'left' as const, sortable: true },
  { name: 'status', label: 'Status', field: 'status', align: 'left' as const, sortable: true },
  { name: 'location', label: 'Location/Home Base', field: 'city', align: 'left' as const },
  { name: 'assigned', label: 'Assigned To', field: 'assigned_user_id', align: 'left' as const },
  { name: 'last_seen', label: 'Last Seen', field: 'last_known_at', align: 'left' as const },
  { name: 'active', label: 'Active', field: 'is_active', align: 'center' as const },
  { name: 'actions', label: '', field: 'actions', align: 'right' as const },
];

const typeOptions = [
  { value: 'fixed_shop', label: 'Fixed Shop' },
  { value: 'mobile_service_truck', label: 'Mobile Service Truck' },
  { value: 'parts_runner_vehicle', label: 'Parts Runner Vehicle' },
  { value: 'vendor', label: 'Vendor' },
  { value: 'customer_site', label: 'Customer Site' },
];

const statusOptions = [
  { value: 'available', label: 'Available' },
  { value: 'on_job', label: 'On Job' },
  { value: 'on_run', label: 'On Run' },
  { value: 'offline', label: 'Offline' },
  { value: 'maintenance', label: 'Maintenance' },
];

function can(ability: string): boolean {
  return authStore.can(ability);
}

function getTypeLabel(type: string): string {
  const option = typeOptions.find(o => o.value === type);
  return option?.label || type;
}

function getTypeColor(type: string): string {
  const colors: Record<string, string> = {
    fixed_shop: 'blue',
    mobile_service_truck: 'green',
    parts_runner_vehicle: 'orange',
    vendor: 'purple',
    customer_site: 'teal',
  };
  return colors[type] || 'grey';
}

function getStatusLabel(status: string): string {
  const option = statusOptions.find(o => o.value === status);
  return option?.label || status;
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    available: 'positive',
    on_job: 'info',
    on_run: 'warning',
    offline: 'grey',
    maintenance: 'negative',
  };
  return colors[status] || 'grey';
}

function getLocationButtonStyle(location: Partial<ServiceLocation>): Record<string, string> {
  const style: Record<string, string> = {};
  if (location.background_color) {
    style.backgroundColor = location.background_color;
  } else {
    style.backgroundColor = '#1976D2'; // Default primary color
  }
  if (location.text_color) {
    style.color = location.text_color;
  } else {
    style.color = '#FFFFFF'; // Default white text
  }
  return style;
}

function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleString();
}

async function fetchLocations() {
  const params: any = {
    page: pagination.value.page,
    per_page: pagination.value.rowsPerPage,
    active: !showInactive.value,
    my_locations_only: myLocationsOnly.value,
  };

  if (selectedTab.value !== 'all') {
    params.type = selectedTab.value;
  }

  if (search.value) {
    params.search = search.value;
  }

  if (selectedStatus.value) {
    params.status = selectedStatus.value;
  }

  await locationsStore.fetchLocations(params);
  pagination.value.rowsNumber = locationsStore.pagination.total;
}

const debouncedSearch = debounce(() => {
  fetchLocations();
}, 500);

function handleTabChange() {
  pagination.value.page = 1;
  fetchLocations();
}

function onTableRequest(props: any) {
  pagination.value.page = props.pagination.page;
  pagination.value.rowsPerPage = props.pagination.rowsPerPage;
  fetchLocations();
}

function openCreateDialog() {
  editingLocation.value = null;
  locationForm.value = {
    name: '',
    code: '',
    location_type: 'fixed_shop' as any,
    status: 'available' as any,
    is_active: true,
    is_dispatchable: false,
    address_line1: '',
    address_line2: '',
    city: '',
    state: '',
    postal_code: '',
    notes: '',
    home_base_location_id: null,
    text_color: null,
    background_color: null,
  };
  showLocationDialog.value = true;
}

function openEditDialog(location: ServiceLocation) {
  editingLocation.value = location;
  locationForm.value = { ...location };
  showLocationDialog.value = true;
}

async function saveLocation() {
  try {
    if (editingLocation.value) {
      await locationsStore.updateLocation(editingLocation.value.id, locationForm.value);
    } else {
      await locationsStore.createLocation(locationForm.value);
    }
    showLocationDialog.value = false;
    fetchLocations();
  } catch (error) {
    // Error handled by store
  }
}

async function toggleActive(location: ServiceLocation) {
  await locationsStore.updateLocation(location.id, {
    is_active: !location.is_active,
  });
  fetchLocations();
}

function openAssignDialog(location: ServiceLocation) {
  // TODO: Implement assign dialog
  console.log('Assign dialog for', location);
}

function confirmDelete(location: ServiceLocation) {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete "${location.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    await locationsStore.deleteLocation(location.id);
    fetchLocations();
  });
}

async function loadFixedShops() {
  const response = await locationsStore.fetchLocations({ type: 'fixed_shop', per_page: 100 });
  fixedShops.value = locationsStore.locations;
}

onMounted(() => {
  fetchLocations();
  loadFixedShops();
});
</script>

<style scoped lang="scss">
.color-preview {
  width: 32px;
  height: 32px;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  flex-shrink: 0;
}
</style>
