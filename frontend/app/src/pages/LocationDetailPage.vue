<template>
  <q-page padding>
    <div v-if="loading && !location" class="flex flex-center" style="min-height: 400px">
      <q-spinner size="50px" color="primary" />
    </div>

    <div v-else-if="location">
      <!-- Header -->
      <div class="row items-center justify-between q-mb-md">
        <div>
          <q-btn flat dense icon="arrow_back" @click="$router.back()" class="q-mr-sm" />
          <span class="text-h5">{{ location.name }}</span>
          <q-chip
            v-if="location.code"
            dense
            class="q-ml-sm"
            color="grey-4"
            text-color="grey-8"
          >
            {{ location.code }}
          </q-chip>
        </div>
        <div class="row q-gutter-sm">
          <q-btn
            v-if="can('service_locations.update_details')"
            flat
            icon="edit"
            label="Edit"
            color="primary"
            @click="openEditDialog"
          />
          <q-btn
            v-if="can('service_locations.delete')"
            flat
            icon="delete"
            label="Delete"
            color="negative"
            @click="confirmDelete"
          />
        </div>
      </div>

      <div class="row q-col-gutter-md">
        <!-- Left Column -->
        <div class="col-12 col-md-8">
          <!-- Basic Information -->
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Basic Information</div>

              <div class="row q-col-gutter-md">
                <div class="col-12 col-sm-6">
                  <div class="text-caption text-grey-7">Type</div>
                  <q-chip
                    :color="getTypeColor(location.location_type)"
                    text-color="white"
                    dense
                  >
                    {{ getTypeLabel(location.location_type) }}
                  </q-chip>
                </div>

                <div v-if="location.status" class="col-12 col-sm-6">
                  <div class="text-caption text-grey-7">Status</div>
                  <q-chip
                    :color="getStatusColor(location.status)"
                    text-color="white"
                    dense
                  >
                    {{ getStatusLabel(location.status) }}
                  </q-chip>
                </div>

                <div class="col-12 col-sm-6">
                  <div class="text-caption text-grey-7">Active</div>
                  <div class="text-body1">
                    <q-toggle
                      :model-value="location.is_active"
                      :disable="!can('service_locations.update_details')"
                      @update:model-value="toggleActive"
                    />
                    {{ location.is_active ? 'Active' : 'Inactive' }}
                  </div>
                </div>

                <div v-if="location.location_type.includes('mobile')" class="col-12 col-sm-6">
                  <div class="text-caption text-grey-7">Dispatchable</div>
                  <div class="text-body1">{{ location.is_dispatchable ? 'Yes' : 'No' }}</div>
                </div>

                <div v-if="location.home_base" class="col-12">
                  <div class="text-caption text-grey-7">Home Base</div>
                  <div class="text-body1">
                    <router-link
                      :to="`/locations/${location.home_base.id}`"
                      class="text-primary"
                      style="text-decoration: none"
                    >
                      {{ location.home_base.name }}
                    </router-link>
                  </div>
                </div>

                <div v-if="location.assigned_user" class="col-12">
                  <div class="text-caption text-grey-7">Assigned User</div>
                  <div class="text-body1">
                    <q-icon name="person" size="sm" class="q-mr-xs" />
                    {{ location.assigned_user.first_name }} {{ location.assigned_user.last_name }}
                  </div>
                </div>

                <div v-if="location.notes" class="col-12">
                  <div class="text-caption text-grey-7">Notes</div>
                  <div class="text-body1">{{ location.notes }}</div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Address (for fixed locations) -->
          <q-card
            v-if="!location.location_type.includes('mobile')"
            flat
            bordered
            class="q-mb-md"
          >
            <q-card-section>
              <div class="text-h6 q-mb-md">Address</div>

              <div v-if="hasAddress">
                <div v-if="location.address_line1" class="text-body1">
                  {{ location.address_line1 }}
                </div>
                <div v-if="location.address_line2" class="text-body1">
                  {{ location.address_line2 }}
                </div>
                <div class="text-body1">
                  {{ [location.city, location.state, location.postal_code].filter(Boolean).join(', ') }}
                </div>
                <div v-if="location.country" class="text-body1">
                  {{ location.country }}
                </div>
              </div>
              <div v-else class="text-grey-6">No address information</div>
            </q-card-section>
          </q-card>

          <!-- Contact Information -->
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="row items-center justify-between q-mb-md">
                <div class="text-h6">Contact Information</div>
                <q-btn
                  v-if="can('service_locations.update_contacts')"
                  flat
                  dense
                  icon="add"
                  label="Add Contact"
                  color="primary"
                  size="sm"
                >
                  <q-menu>
                    <q-list>
                      <q-item clickable v-close-popup @click="openAddPhoneDialog">
                        <q-item-section avatar>
                          <q-icon name="phone" />
                        </q-item-section>
                        <q-item-section>Add Phone</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup @click="openAddEmailDialog">
                        <q-item-section avatar>
                          <q-icon name="email" />
                        </q-item-section>
                        <q-item-section>Add Email</q-item-section>
                      </q-item>
                    </q-list>
                  </q-menu>
                </q-btn>
              </div>

              <!-- Phone Numbers -->
              <div v-if="location.phones && location.phones.length > 0" class="q-mb-md">
                <div class="text-subtitle2 q-mb-sm">Phone Numbers</div>
                <q-list bordered separator>
                  <q-item v-for="phone in location.phones" :key="phone.id">
                    <q-item-section>
                      <q-item-label>
                        <q-icon name="phone" size="xs" class="q-mr-xs" />
                        {{ phone.phone_number }}
                        <span v-if="phone.extension"> ext. {{ phone.extension }}</span>
                        <q-chip
                          v-if="phone.is_primary"
                          dense
                          size="sm"
                          color="primary"
                          text-color="white"
                          class="q-ml-sm"
                        >
                          Primary
                        </q-chip>
                      </q-item-label>
                      <q-item-label caption>{{ phone.label }}</q-item-label>
                    </q-item-section>
                    <q-item-section side v-if="can('service_locations.update_contacts')">
                      <div>
                        <q-btn
                          flat
                          dense
                          round
                          icon="edit"
                          size="sm"
                          @click="openEditPhoneDialog(phone)"
                        />
                        <q-btn
                          flat
                          dense
                          round
                          icon="delete"
                          size="sm"
                          color="negative"
                          @click="confirmDeletePhone(phone)"
                        />
                      </div>
                    </q-item-section>
                  </q-item>
                </q-list>
              </div>

              <!-- Emails -->
              <div v-if="location.emails && location.emails.length > 0">
                <div class="text-subtitle2 q-mb-sm">Email Addresses</div>
                <q-list bordered separator>
                  <q-item v-for="email in location.emails" :key="email.id">
                    <q-item-section>
                      <q-item-label>
                        <q-icon name="email" size="xs" class="q-mr-xs" />
                        {{ email.email }}
                        <q-chip
                          v-if="email.is_primary"
                          dense
                          size="sm"
                          color="primary"
                          text-color="white"
                          class="q-ml-sm"
                        >
                          Primary
                        </q-chip>
                      </q-item-label>
                      <q-item-label caption>{{ email.label }}</q-item-label>
                    </q-item-section>
                    <q-item-section side v-if="can('service_locations.update_contacts')">
                      <div>
                        <q-btn
                          flat
                          dense
                          round
                          icon="edit"
                          size="sm"
                          @click="openEditEmailDialog(email)"
                        />
                        <q-btn
                          flat
                          dense
                          round
                          icon="delete"
                          size="sm"
                          color="negative"
                          @click="confirmDeleteEmail(email)"
                        />
                      </div>
                    </q-item-section>
                  </q-item>
                </q-list>
              </div>

              <div
                v-if="(!location.phones || location.phones.length === 0) && (!location.emails || location.emails.length === 0)"
                class="text-grey-6"
              >
                No contact information
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Right Column -->
        <div class="col-12 col-md-4">
          <!-- Metadata Card -->
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Metadata</div>

              <div class="q-mb-sm">
                <div class="text-caption text-grey-7">Created</div>
                <div class="text-body2">{{ formatDateTime(location.created_at) }}</div>
              </div>

              <div class="q-mb-sm">
                <div class="text-caption text-grey-7">Last Updated</div>
                <div class="text-body2">{{ formatDateTime(location.updated_at) }}</div>
              </div>

              <div v-if="location.last_known_at">
                <div class="text-caption text-grey-7">Last Known Position</div>
                <div class="text-body2">{{ formatDateTime(location.last_known_at) }}</div>
                <div v-if="location.last_known_lat && location.last_known_lng" class="text-caption">
                  {{ location.last_known_lat.toFixed(6) }}, {{ location.last_known_lng.toFixed(6) }}
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Quick Actions -->
          <q-card
            v-if="location.location_type.includes('mobile') && can('service_locations.assign_user')"
            flat
            bordered
          >
            <q-card-section>
              <div class="text-h6 q-mb-md">Quick Actions</div>

              <q-btn
                flat
                color="primary"
                icon="person_add"
                label="Assign User"
                class="full-width"
                @click="openAssignUserDialog"
              />
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Edit Location Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card style="width: 100%; max-width: 800px; max-height: 80vh">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Edit Location</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-separator />

        <q-card-section style="max-height: 70vh" class="scroll">
          <q-form @submit="saveLocation" class="q-gutter-md">
            <div class="row q-col-gutter-md">
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
            />

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
            />

            <!-- Fixed Location Fields -->
            <div v-if="locationForm.location_type && !locationForm.location_type.includes('mobile')">
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
            <div v-if="locationForm.location_type && locationForm.location_type.includes('mobile')">
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
              />

              <q-toggle
                v-model="locationForm.is_dispatchable"
                label="Dispatchable"
              />
            </div>

            <q-input
              v-model="locationForm.notes"
              label="Notes"
              outlined
              type="textarea"
              rows="3"
            />

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

    <!-- Add/Edit Phone Dialog -->
    <q-dialog v-model="showPhoneDialog" persistent>
      <q-card style="width: 100%; max-width: 500px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ editingPhone ? 'Edit' : 'Add' }} Phone Number</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-form @submit="savePhone" class="q-gutter-md">
            <q-input
              v-model="phoneForm.label"
              label="Label *"
              outlined
              :rules="[val => !!val || 'Label is required']"
              hint="e.g., Main, Office, Mobile"
            />

            <q-input
              v-model="phoneForm.phone_number"
              label="Phone Number *"
              outlined
              :rules="[val => !!val || 'Phone number is required']"
            />

            <q-input
              v-model="phoneForm.extension"
              label="Extension"
              outlined
            />

            <q-toggle
              v-model="phoneForm.is_primary"
              label="Primary Phone"
            />

            <q-toggle
              v-model="phoneForm.is_public"
              label="Public"
              hint="Visible to customers"
            />
          </q-form>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Save"
            color="primary"
            @click="savePhone"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add/Edit Email Dialog -->
    <q-dialog v-model="showEmailDialog" persistent>
      <q-card style="width: 100%; max-width: 500px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ editingEmail ? 'Edit' : 'Add' }} Email Address</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveEmail" class="q-gutter-md">
            <q-input
              v-model="emailForm.label"
              label="Label *"
              outlined
              :rules="[val => !!val || 'Label is required']"
              hint="e.g., General, Support, Sales"
            />

            <q-input
              v-model="emailForm.email"
              label="Email Address *"
              type="email"
              outlined
              :rules="[
                val => !!val || 'Email is required',
                val => /.+@.+\..+/.test(val) || 'Invalid email format'
              ]"
            />

            <q-toggle
              v-model="emailForm.is_primary"
              label="Primary Email"
            />

            <q-toggle
              v-model="emailForm.is_public"
              label="Public"
              hint="Visible to customers"
            />
          </q-form>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Save"
            color="primary"
            @click="saveEmail"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Assign User Dialog -->
    <q-dialog v-model="showAssignDialog" persistent>
      <q-card style="width: 100%; max-width: 500px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Assign User</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-select
            v-model="selectedUserId"
            label="Select User"
            outlined
            :options="availableUsers"
            option-value="id"
            option-label="full_name"
            emit-value
            map-options
            clearable
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Assign"
            color="primary"
            @click="assignUser"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useLocationsStore, type ServiceLocation, type ServiceLocationPhone, type ServiceLocationEmail } from 'src/stores/locations';
import { useAuthStore } from 'src/stores/auth';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';

const route = useRoute();
const router = useRouter();
const locationsStore = useLocationsStore();
const authStore = useAuthStore();
const $q = useQuasar();

const location = computed(() => locationsStore.currentLocation);
const loading = computed(() => locationsStore.loading);

const showEditDialog = ref(false);
const showPhoneDialog = ref(false);
const showEmailDialog = ref(false);
const showAssignDialog = ref(false);

const editingPhone = ref<ServiceLocationPhone | null>(null);
const editingEmail = ref<ServiceLocationEmail | null>(null);
const fixedShops = ref<ServiceLocation[]>([]);
const availableUsers = ref<any[]>([]);
const selectedUserId = ref<number | null>(null);

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
});

const phoneForm = ref({
  label: '',
  phone_number: '',
  extension: '',
  is_primary: false,
  is_public: true,
});

const emailForm = ref({
  label: '',
  email: '',
  is_primary: false,
  is_public: true,
});

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

const hasAddress = computed(() => {
  if (!location.value) return false;
  return !!(
    location.value.address_line1 ||
    location.value.address_line2 ||
    location.value.city ||
    location.value.state ||
    location.value.postal_code
  );
});

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

function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleString();
}

async function fetchLocation() {
  const id = parseInt(route.params.id as string);
  await locationsStore.fetchLocation(id);
}

async function toggleActive() {
  if (!location.value) return;
  await locationsStore.updateLocation(location.value.id, {
    is_active: !location.value.is_active,
  });
  await fetchLocation();
}

function openEditDialog() {
  if (!location.value) return;
  locationForm.value = { ...location.value };
  showEditDialog.value = true;
}

async function saveLocation() {
  if (!location.value) return;
  try {
    await locationsStore.updateLocation(location.value.id, locationForm.value);
    showEditDialog.value = false;
    await fetchLocation();
  } catch (error) {
    // Error handled by store
  }
}

function confirmDelete() {
  if (!location.value) return;
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete "${location.value.name}"?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    if (!location.value) return;
    await locationsStore.deleteLocation(location.value.id);
    router.push('/locations');
  });
}

// Phone Management
function openAddPhoneDialog() {
  editingPhone.value = null;
  phoneForm.value = {
    label: '',
    phone_number: '',
    extension: '',
    is_primary: false,
    is_public: true,
  };
  showPhoneDialog.value = true;
}

function openEditPhoneDialog(phone: ServiceLocationPhone) {
  editingPhone.value = phone;
  phoneForm.value = {
    label: phone.label,
    phone_number: phone.phone_number,
    extension: phone.extension || '',
    is_primary: phone.is_primary,
    is_public: phone.is_public,
  };
  showPhoneDialog.value = true;
}

async function savePhone() {
  if (!location.value) return;
  try {
    if (editingPhone.value) {
      await locationsStore.updatePhone(location.value.id, editingPhone.value.id, phoneForm.value);
    } else {
      await locationsStore.addPhone(location.value.id, phoneForm.value);
    }
    showPhoneDialog.value = false;
    await fetchLocation();
  } catch (error) {
    // Error handled by store
  }
}

function confirmDeletePhone(phone: ServiceLocationPhone) {
  if (!location.value) return;
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete this phone number?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    if (!location.value) return;
    await locationsStore.deletePhone(location.value.id, phone.id);
    await fetchLocation();
  });
}

// Email Management
function openAddEmailDialog() {
  editingEmail.value = null;
  emailForm.value = {
    label: '',
    email: '',
    is_primary: false,
    is_public: true,
  };
  showEmailDialog.value = true;
}

function openEditEmailDialog(email: ServiceLocationEmail) {
  editingEmail.value = email;
  emailForm.value = { ...email };
  showEmailDialog.value = true;
}

async function saveEmail() {
  if (!location.value) return;
  try {
    if (editingEmail.value) {
      await locationsStore.updateEmail(location.value.id, editingEmail.value.id, emailForm.value);
    } else {
      await locationsStore.addEmail(location.value.id, emailForm.value);
    }
    showEmailDialog.value = false;
    await fetchLocation();
  } catch (error) {
    // Error handled by store
  }
}

function confirmDeleteEmail(email: ServiceLocationEmail) {
  if (!location.value) return;
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to delete this email address?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    if (!location.value) return;
    await locationsStore.deleteEmail(location.value.id, email.id);
    await fetchLocation();
  });
}

// User Assignment
function openAssignUserDialog() {
  if (location.value?.assigned_user) {
    selectedUserId.value = location.value.assigned_user.id;
  } else {
    selectedUserId.value = null;
  }
  showAssignDialog.value = true;
}

async function assignUser() {
  if (!location.value) return;
  try {
    await locationsStore.assignUser(location.value.id, selectedUserId.value);
    showAssignDialog.value = false;
    await fetchLocation();
  } catch (error) {
    // Error handled by store
  }
}

async function loadFixedShops() {
  try {
    const response = await api.get('/locations', { params: { type: 'fixed_shop', per_page: 100 } });
    fixedShops.value = response.data.data;
  } catch (error) {
    console.error('Failed to load fixed shops', error);
  }
}

async function loadAvailableUsers() {
  try {
    const response = await api.get('/users', { params: { per_page: 100 } });
    availableUsers.value = response.data.data.map((user: any) => ({
      ...user,
      full_name: `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username,
    }));
  } catch (error) {
    console.error('Failed to load users', error);
  }
}

onMounted(async () => {
  await fetchLocation();
  await loadFixedShops();
  await loadAvailableUsers();
});
</script>
