<template>
  <q-page padding>
    <div class="q-pb-md">
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h5">Vendors</div>
        <div class="row q-gutter-sm">
          <q-btn
            flat
            @click="openCreateDialog"
          >
            <q-icon name="add" color="primary" size="sm" class="q-mr-xs" />
            <span class="text-primary">Add Vendor</span>
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

      <!-- Filters -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section class="q-pa-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <q-input
                v-model="filters.search"
                dense
                outlined
                placeholder="Search by name, phone, or email..."
                @update:model-value="debouncedFetch"
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
                <template v-slot:append>
                  <q-icon
                    v-if="filters.search"
                    name="close"
                    class="cursor-pointer"
                    @click="filters.search = ''; fetchVendors()"
                  />
                </template>
              </q-input>
            </div>

            <div class="col-12 col-sm-3">
              <q-select
                v-model="filters.status"
                dense
                outlined
                placeholder="Status"
                :options="statusOptions"
                emit-value
                map-options
                clearable
                @update:model-value="fetchVendors"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Vendors Table -->
      <q-card flat bordered>
        <q-table
          flat
          :rows="vendors"
          :columns="columns"
          row-key="id"
          :loading="loading"
          :pagination="pagination"
          @request="onTableRequest"
        >
          <template v-slot:body-cell-name="props">
            <q-td :props="props">
              <div
                class="text-weight-medium text-primary cursor-pointer"
                @click="openViewDialog(props.row)"
              >
                {{ props.row.name }}
              </div>
              <div v-if="props.row.legal_name && props.row.legal_name !== props.row.name" class="text-caption text-grey-7">
                {{ props.row.legal_name }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-contact="props">
            <q-td :props="props">
              <div v-if="props.row.phone" class="text-caption">
                <q-icon name="phone" size="xs" class="q-mr-xs" />
                {{ props.row.phone }}
              </div>
              <div v-if="props.row.email" class="text-caption">
                <q-icon name="email" size="xs" class="q-mr-xs" />
                {{ props.row.email }}
              </div>
              <span v-if="!props.row.phone && !props.row.email" class="text-grey-6">-</span>
            </q-td>
          </template>

          <template v-slot:body-cell-addresses="props">
            <q-td :props="props">
              <div v-if="props.row.addresses && props.row.addresses.length > 0">
                <div class="text-caption">
                  {{ props.row.addresses.length }} address{{ props.row.addresses.length !== 1 ? 'es' : '' }}
                </div>
                <div v-if="getPrimaryAddress(props.row)" class="text-caption text-grey-7">
                  {{ getPrimaryAddress(props.row)?.one_line_address }}
                </div>
              </div>
              <span v-else class="text-grey-6">No addresses</span>
            </q-td>
          </template>

          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="props.row.status === 'active' ? 'positive' : 'grey'"
                text-color="white"
              >
                {{ props.row.status === 'active' ? 'Active' : 'Inactive' }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn flat dense round icon="more_vert">
                <q-menu>
                  <q-list style="min-width: 150px">
                    <q-item clickable v-close-popup @click="openViewDialog(props.row)">
                      <q-item-section avatar>
                        <q-icon name="visibility" />
                      </q-item-section>
                      <q-item-section>View Details</q-item-section>
                    </q-item>

                    <q-item clickable v-close-popup @click="openEditDialog(props.row)">
                      <q-item-section avatar>
                        <q-icon name="edit" />
                      </q-item-section>
                      <q-item-section>Edit</q-item-section>
                    </q-item>

                    <q-item clickable v-close-popup @click="openAddAddressDialog(props.row)">
                      <q-item-section avatar>
                        <q-icon name="add_location" />
                      </q-item-section>
                      <q-item-section>Add Address</q-item-section>
                    </q-item>

                    <q-separator />

                    <q-item
                      clickable
                      v-close-popup
                      @click="toggleVendorStatus(props.row)"
                    >
                      <q-item-section avatar>
                        <q-icon :name="props.row.status === 'active' ? 'block' : 'check_circle'" />
                      </q-item-section>
                      <q-item-section>
                        {{ props.row.status === 'active' ? 'Deactivate' : 'Activate' }}
                      </q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </q-td>
          </template>
        </q-table>
      </q-card>
    </div>

    <!-- Create/Edit Vendor Dialog -->
    <MobileFormDialog
      v-model="showCreateDialog"
      :title="editingVendor ? 'Edit Vendor' : 'Add Vendor'"
      :submit-label="editingVendor ? 'Save Changes' : 'Create Vendor'"
      :loading="isSubmitting"
      @submit="submitVendorForm"
    >
      <MobileFormField
        name="name"
        v-model="vendorForm.name"
        label="Vendor Name"
        type="text"
        :error="getError('name')"
        @update:model-value="updateField('name', $event); checkForDuplicates(); checkForAcronym()"
        @blur="touchField('name'); checkForAcronym()"
        required
        icon="business"
      />

      <!-- Acronym Detection -->
      <div v-if="showAcronymPrompt && !editingVendor" class="col-12" style="grid-column: 1 / -1;">
        <q-banner class="bg-blue-1 q-mb-md" rounded>
          <template v-slot:avatar>
            <q-icon name="info" color="primary" />
          </template>
          <div class="text-body2">
            This looks like an acronym. Save as <strong>{{ acronymSuggestion }}</strong> (all caps)?
          </div>
          <template v-slot:action>
            <q-btn
              flat
              dense
              label="Use ALL CAPS"
              color="primary"
              @click="acceptAcronymSuggestion"
            />
            <q-btn
              flat
              dense
              label="Keep as typed"
              color="grey"
              @click="rejectAcronymSuggestion"
            />
          </template>
        </q-banner>
      </div>

      <!-- Duplicate Warning -->
      <div v-if="duplicateCandidates.length > 0" class="col-12" style="grid-column: 1 / -1;">
        <q-banner class="bg-warning text-dark q-mb-md">
          <template v-slot:avatar>
            <q-icon name="warning" />
          </template>
          Similar vendors found. Did you mean one of these?
        </q-banner>
        <q-list bordered separator class="q-mb-md">
          <q-item
            v-for="candidate in duplicateCandidates"
            :key="candidate.id"
            clickable
            @click="selectExistingVendor(candidate)"
          >
            <q-item-section>
              <q-item-label>{{ candidate.name }}</q-item-label>
              <q-item-label caption v-if="candidate.phone">
                {{ candidate.phone }}
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-badge color="grey">
                {{ Math.round(candidate.similarity * 100) }}% match
              </q-badge>
            </q-item-section>
          </q-item>
        </q-list>
      </div>

      <MobileFormField
        name="legal_name"
        v-model="vendorForm.legal_name"
        label="Legal Name (if different)"
        type="text"
        :error="getError('legal_name')"
        @update:model-value="updateField('legal_name', $event)"
        @blur="touchField('legal_name')"
        icon="gavel"
        hint="Official business name for invoices"
      />

      <MobileFormField
        name="phone"
        v-model="vendorForm.phone"
        label="Phone"
        type="tel"
        :error="getError('phone')"
        @update:model-value="updateField('phone', $event)"
        @blur="touchField('phone')"
        icon="phone"
      />

      <MobileFormField
        name="email"
        v-model="vendorForm.email"
        label="Email"
        type="email"
        :error="getError('email')"
        @update:model-value="updateField('email', $event)"
        @blur="touchField('email')"
        icon="email"
      />

      <MobileFormField
        name="notes"
        v-model="vendorForm.notes"
        label="Notes"
        type="textarea"
        :rows="3"
        :error="getError('notes')"
        @update:model-value="updateField('notes', $event)"
        @blur="touchField('notes')"
        icon="notes"
        hint="Internal notes about this vendor"
      />
    </MobileFormDialog>

    <!-- View Vendor Dialog -->
    <q-dialog v-model="showViewDialog" maximized>
      <q-card v-if="viewingVendor">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ viewingVendor.name }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <!-- Vendor Info -->
          <div class="q-gutter-sm q-mb-md">
            <div v-if="viewingVendor.legal_name && viewingVendor.legal_name !== viewingVendor.name">
              <strong>Legal Name:</strong> {{ viewingVendor.legal_name }}
            </div>
            <div v-if="viewingVendor.phone">
              <strong>Phone:</strong>
              <a :href="`tel:${viewingVendor.phone}`" class="text-primary">{{ viewingVendor.phone }}</a>
            </div>
            <div v-if="viewingVendor.email">
              <strong>Email:</strong>
              <a :href="`mailto:${viewingVendor.email}`" class="text-primary">{{ viewingVendor.email }}</a>
            </div>
            <div v-if="viewingVendor.notes">
              <strong>Notes:</strong> {{ viewingVendor.notes }}
            </div>
            <div>
              <strong>Status:</strong>
              <q-chip
                dense
                size="sm"
                :color="viewingVendor.status === 'active' ? 'positive' : 'grey'"
                text-color="white"
              >
                {{ viewingVendor.status === 'active' ? 'Active' : 'Inactive' }}
              </q-chip>
            </div>
          </div>

          <q-separator class="q-my-md" />

          <!-- Addresses Section -->
          <div class="row items-center justify-between q-mb-sm">
            <div class="text-subtitle1 text-weight-medium">Addresses</div>
            <q-btn
              flat
              dense
              color="primary"
              icon="add"
              label="Add Address"
              @click="openAddAddressDialog(viewingVendor)"
            />
          </div>

          <div v-if="viewingVendor.addresses && viewingVendor.addresses.length > 0">
            <q-card
              v-for="address in viewingVendor.addresses"
              :key="address.id"
              flat
              bordered
              class="q-mb-sm"
            >
              <q-card-section class="q-py-sm">
                <div class="row items-start justify-between">
                  <div class="col">
                    <div class="row items-center q-gutter-xs q-mb-xs">
                      <span class="text-weight-medium">{{ address.label || 'Address' }}</span>
                      <q-badge v-if="address.pivot?.is_primary" color="primary">Primary</q-badge>
                      <q-badge v-if="address.pivot?.address_type" color="grey" outline>
                        {{ address.pivot.address_type }}
                      </q-badge>
                    </div>
                    <div class="text-body2">{{ address.one_line_address }}</div>
                    <div v-if="address.phone" class="text-caption text-grey-7">
                      <q-icon name="phone" size="xs" /> {{ address.phone }}
                    </div>
                    <div v-if="address.instructions" class="text-caption text-grey-7 q-mt-xs">
                      <q-icon name="info" size="xs" /> {{ address.instructions }}
                    </div>
                  </div>
                  <div class="col-auto">
                    <q-btn flat dense round icon="more_vert" size="sm">
                      <q-menu>
                        <q-list style="min-width: 120px">
                          <q-item
                            v-if="!address.pivot?.is_primary"
                            clickable
                            v-close-popup
                            @click="setAddressPrimary(viewingVendor, address)"
                          >
                            <q-item-section avatar>
                              <q-icon name="star" />
                            </q-item-section>
                            <q-item-section>Set Primary</q-item-section>
                          </q-item>
                          <q-item
                            clickable
                            v-close-popup
                            @click="removeAddress(viewingVendor, address)"
                            class="text-negative"
                          >
                            <q-item-section avatar>
                              <q-icon name="delete" />
                            </q-item-section>
                            <q-item-section>Remove</q-item-section>
                          </q-item>
                        </q-list>
                      </q-menu>
                    </q-btn>
                  </div>
                </div>
              </q-card-section>
            </q-card>
          </div>
          <div v-else class="text-grey-6 text-center q-py-md">
            No addresses added yet
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Edit" color="primary" @click="openEditDialog(viewingVendor)" />
          <q-btn flat label="Close" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add Address Dialog -->
    <q-dialog v-model="showAddressDialog" persistent>
      <q-card style="min-width: 350px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">Add Address</div>
          <div v-if="addressForVendor" class="text-caption text-grey-7">
            {{ addressForVendor.name }}
          </div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-form @submit.prevent="submitAddress" class="q-gutter-sm">
            <q-input
              v-model="addressForm.label"
              label="Label (e.g., Main Counter, Warehouse)"
              filled
            />
            <q-input
              v-model="addressForm.line1"
              label="Street Address *"
              filled
              :rules="[(v: string) => !!v || 'Street address is required']"
            />
            <q-input
              v-model="addressForm.line2"
              label="Suite, Unit, etc."
              filled
            />
            <div class="row q-gutter-sm">
              <q-input
                v-model="addressForm.city"
                label="City *"
                filled
                class="col"
                :rules="[(v: string) => !!v || 'City is required']"
              />
              <q-select
                v-model="addressForm.state"
                label="State *"
                filled
                :options="stateOptions"
                emit-value
                map-options
                class="col-4"
                :rules="[(v: string) => !!v || 'State is required']"
              />
            </div>
            <div class="row q-gutter-sm">
              <q-input
                v-model="addressForm.postal_code"
                label="ZIP Code *"
                filled
                class="col"
                mask="#####-####"
                unmasked-value
                :rules="[(v: string) => !!v || 'ZIP is required']"
              />
              <q-input
                v-model="addressForm.phone"
                label="Phone"
                filled
                type="tel"
                mask="(###) ###-####"
                class="col"
              />
            </div>
            <q-input
              v-model="addressForm.instructions"
              label="Pickup Instructions"
              filled
              type="textarea"
              rows="2"
              hint="Gate codes, receiving hours, etc."
            />
            <q-checkbox
              v-model="addressForm.is_primary"
              label="Set as primary address"
            />
          </q-form>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="showAddressDialog = false" />
          <q-btn
            flat
            label="Add Address"
            color="primary"
            @click="submitAddress"
            :loading="savingAddress"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive, watch } from 'vue';
import { useVendorsStore } from 'src/stores/vendors';
import type { Vendor, Address, VendorDuplicateCandidate } from 'src/types/vendors';
import { debounce } from 'quasar';
import { useFormValidation, validationRules } from 'src/composables/useFormValidation';
import { detectAcronym } from 'src/composables/useAcronymDetector';
import MobileFormDialog from 'src/components/MobileFormDialog.vue';
import MobileFormField from 'src/components/MobileFormField.vue';

const vendorsStore = useVendorsStore();

const vendors = computed(() => vendorsStore.vendors);
const loading = computed(() => vendorsStore.loading);

const showCreateDialog = ref(false);
const showViewDialog = ref(false);
const showAddressDialog = ref(false);
const showInactive = ref(false);

const editingVendor = ref<Vendor | null>(null);
const viewingVendor = ref<Vendor | null>(null);
const addressForVendor = ref<Vendor | null>(null);
const duplicateCandidates = ref<VendorDuplicateCandidate[]>([]);
const savingAddress = ref(false);

// Acronym detection state
const showAcronymPrompt = ref(false);
const acronymSuggestion = ref('');
const isAcronymConfirmed = ref<boolean | null>(null); // null = not decided, true = use caps, false = keep as typed

const filters = ref({
  search: '',
  status: 'active' as string | null,
});

const pagination = ref({
  sortBy: 'name',
  descending: false,
  page: 1,
  rowsPerPage: 20,
  rowsNumber: 0,
});

const vendorForm = reactive({
  name: '',
  legal_name: '',
  phone: '',
  email: '',
  notes: '',
});

const addressForm = reactive({
  label: '',
  line1: '',
  line2: '',
  city: '',
  state: 'OH',
  postal_code: '',
  phone: '',
  instructions: '',
  is_primary: false,
});

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
];

// US State options
const stateOptions = [
  { label: 'Ohio', value: 'OH' },
  { label: 'Alabama', value: 'AL' },
  { label: 'Alaska', value: 'AK' },
  { label: 'Arizona', value: 'AZ' },
  { label: 'Arkansas', value: 'AR' },
  { label: 'California', value: 'CA' },
  { label: 'Colorado', value: 'CO' },
  { label: 'Connecticut', value: 'CT' },
  { label: 'Delaware', value: 'DE' },
  { label: 'Florida', value: 'FL' },
  { label: 'Georgia', value: 'GA' },
  { label: 'Hawaii', value: 'HI' },
  { label: 'Idaho', value: 'ID' },
  { label: 'Illinois', value: 'IL' },
  { label: 'Indiana', value: 'IN' },
  { label: 'Iowa', value: 'IA' },
  { label: 'Kansas', value: 'KS' },
  { label: 'Kentucky', value: 'KY' },
  { label: 'Louisiana', value: 'LA' },
  { label: 'Maine', value: 'ME' },
  { label: 'Maryland', value: 'MD' },
  { label: 'Massachusetts', value: 'MA' },
  { label: 'Michigan', value: 'MI' },
  { label: 'Minnesota', value: 'MN' },
  { label: 'Mississippi', value: 'MS' },
  { label: 'Missouri', value: 'MO' },
  { label: 'Montana', value: 'MT' },
  { label: 'Nebraska', value: 'NE' },
  { label: 'Nevada', value: 'NV' },
  { label: 'New Hampshire', value: 'NH' },
  { label: 'New Jersey', value: 'NJ' },
  { label: 'New Mexico', value: 'NM' },
  { label: 'New York', value: 'NY' },
  { label: 'North Carolina', value: 'NC' },
  { label: 'North Dakota', value: 'ND' },
  { label: 'Oklahoma', value: 'OK' },
  { label: 'Oregon', value: 'OR' },
  { label: 'Pennsylvania', value: 'PA' },
  { label: 'Rhode Island', value: 'RI' },
  { label: 'South Carolina', value: 'SC' },
  { label: 'South Dakota', value: 'SD' },
  { label: 'Tennessee', value: 'TN' },
  { label: 'Texas', value: 'TX' },
  { label: 'Utah', value: 'UT' },
  { label: 'Vermont', value: 'VT' },
  { label: 'Virginia', value: 'VA' },
  { label: 'Washington', value: 'WA' },
  { label: 'West Virginia', value: 'WV' },
  { label: 'Wisconsin', value: 'WI' },
  { label: 'Wyoming', value: 'WY' },
];

const columns = [
  { name: 'name', label: 'Name', field: 'name', align: 'left' as const, sortable: true },
  { name: 'contact', label: 'Contact', field: 'contact', align: 'left' as const },
  { name: 'addresses', label: 'Addresses', field: 'addresses', align: 'left' as const },
  { name: 'status', label: 'Status', field: 'status', align: 'center' as const },
  { name: 'actions', label: '', field: 'actions', align: 'right' as const },
];

// Form validation
const {
  registerField,
  updateField,
  touchField,
  getError,
  handleSubmit,
  reset: resetValidation,
  isSubmitting,
} = useFormValidation();

// Register validation rules
registerField('name', [
  validationRules.required('Vendor name is required'),
  validationRules.minLength(2, 'Name must be at least 2 characters'),
]);

registerField('email', [
  validationRules.email(),
]);

registerField('phone', [
  validationRules.phone(),
]);

// Watch showInactive to update filters
watch(showInactive, (val) => {
  filters.value.status = val ? null : 'active';
  void fetchVendors();
});

function getPrimaryAddress(vendor: Vendor): Address | undefined {
  return vendor.addresses?.find(a => a.pivot?.is_primary) || vendor.addresses?.[0];
}

async function fetchVendors() {
  const params: Record<string, string | number | null> = {
    page: pagination.value.page,
    per_page: pagination.value.rowsPerPage,
  };

  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.status) params.status = filters.value.status;

  await vendorsStore.fetchVendors(params);
  pagination.value.rowsNumber = vendorsStore.pagination?.total || 0;
}

const debouncedFetch = debounce(() => {
  void fetchVendors();
}, 500);

const debouncedDuplicateCheck = debounce(async () => {
  if (vendorForm.name.length >= 2 && !editingVendor.value) {
    const result = await vendorsStore.checkDuplicate(vendorForm.name);
    duplicateCandidates.value = result?.candidates || [];
  } else {
    duplicateCandidates.value = [];
  }
}, 300);

function checkForDuplicates() {
  debouncedDuplicateCheck();
}

// Acronym detection - runs on input change and blur
const debouncedAcronymCheck = debounce(() => {
  // Don't show prompt if user already made a decision
  if (isAcronymConfirmed.value !== null) {
    return;
  }

  const name = vendorForm.name.trim();
  if (name.length < 2) {
    showAcronymPrompt.value = false;
    acronymSuggestion.value = '';
    return;
  }

  const result = detectAcronym(name);
  if (result.isLikely) {
    acronymSuggestion.value = result.suggestedName;
    // Only show prompt if the suggestion is different from what they typed
    showAcronymPrompt.value = name !== result.suggestedName;
  } else {
    showAcronymPrompt.value = false;
    acronymSuggestion.value = '';
  }
}, 300);

function checkForAcronym() {
  debouncedAcronymCheck();
}

function acceptAcronymSuggestion() {
  vendorForm.name = acronymSuggestion.value;
  isAcronymConfirmed.value = true;
  showAcronymPrompt.value = false;
}

function rejectAcronymSuggestion() {
  isAcronymConfirmed.value = false;
  showAcronymPrompt.value = false;
}

function onTableRequest(props: { pagination: { page: number; rowsPerPage: number } }) {
  pagination.value.page = props.pagination.page;
  pagination.value.rowsPerPage = props.pagination.rowsPerPage;
  void fetchVendors();
}

function openCreateDialog() {
  resetValidation();
  editingVendor.value = null;
  duplicateCandidates.value = [];
  // Reset acronym state
  showAcronymPrompt.value = false;
  acronymSuggestion.value = '';
  isAcronymConfirmed.value = null;
  Object.assign(vendorForm, {
    name: '',
    legal_name: '',
    phone: '',
    email: '',
    notes: '',
  });
  showCreateDialog.value = true;
}

function openEditDialog(vendor: Vendor) {
  resetValidation();
  editingVendor.value = vendor;
  duplicateCandidates.value = [];
  Object.assign(vendorForm, {
    name: vendor.name,
    legal_name: vendor.legal_name || '',
    phone: vendor.phone || '',
    email: vendor.email || '',
    notes: vendor.notes || '',
  });
  showViewDialog.value = false;
  showCreateDialog.value = true;
}

async function openViewDialog(vendor: Vendor) {
  // Fetch full vendor details with addresses
  viewingVendor.value = await vendorsStore.fetchVendor(vendor.id);
  showViewDialog.value = true;
}

function selectExistingVendor(candidate: VendorDuplicateCandidate) {
  showCreateDialog.value = false;
  void vendorsStore.fetchVendor(candidate.id).then(vendor => {
    viewingVendor.value = vendor;
    showViewDialog.value = true;
  });
}

async function submitVendorForm() {
  await handleSubmit(async () => {
    if (editingVendor.value) {
      // Update existing vendor
      await vendorsStore.updateVendor(editingVendor.value.id, {
        name: vendorForm.name,
        legal_name: vendorForm.legal_name || undefined,
        phone: vendorForm.phone || undefined,
        email: vendorForm.email || undefined,
        notes: vendorForm.notes || undefined,
      });
    } else {
      // Create new vendor - pass is_acronym if user confirmed
      const result = await vendorsStore.createVendor({
        name: vendorForm.name,
        legal_name: vendorForm.legal_name || null,
        phone: vendorForm.phone || null,
        email: vendorForm.email || null,
        notes: vendorForm.notes || null,
        // Pass is_acronym if user explicitly confirmed (true) or rejected (false)
        // If null (not prompted or not decided), let backend auto-detect
        ...(isAcronymConfirmed.value !== null ? { is_acronym: isAcronymConfirmed.value } : {}),
        force_create: duplicateCandidates.value.length > 0,
      });

      if (result.status === 'duplicates_found') {
        duplicateCandidates.value = result.candidates || [];
        return; // Don't close dialog
      }
    }

    showCreateDialog.value = false;
    await fetchVendors();
  });
}

async function toggleVendorStatus(vendor: Vendor) {
  const newStatus = vendor.status === 'active' ? 'inactive' : 'active';
  await vendorsStore.updateVendor(vendor.id, { status: newStatus });
  await fetchVendors();
}

function openAddAddressDialog(vendor: Vendor) {
  addressForVendor.value = vendor;
  Object.assign(addressForm, {
    label: '',
    line1: '',
    line2: '',
    city: '',
    state: 'OH',
    postal_code: '',
    phone: '',
    instructions: '',
    is_primary: !vendor.addresses || vendor.addresses.length === 0,
  });
  showAddressDialog.value = true;
}

async function submitAddress() {
  if (!addressForm.line1 || !addressForm.city || !addressForm.state || !addressForm.postal_code) {
    return;
  }

  if (!addressForVendor.value) return;

  savingAddress.value = true;
  try {
    const updatedVendor = await vendorsStore.attachAddress(addressForVendor.value.id, {
      address: {
        label: addressForm.label || undefined,
        line1: addressForm.line1,
        line2: addressForm.line2 || undefined,
        city: addressForm.city,
        state: addressForm.state,
        postal_code: addressForm.postal_code,
        phone: addressForm.phone || undefined,
        instructions: addressForm.instructions || undefined,
      },
      address_type: 'pickup',
      is_primary: addressForm.is_primary,
    });

    showAddressDialog.value = false;

    // Refresh viewing vendor if open
    if (viewingVendor.value?.id === addressForVendor.value.id) {
      viewingVendor.value = updatedVendor;
    }

    await fetchVendors();
  } finally {
    savingAddress.value = false;
  }
}

async function setAddressPrimary(vendor: Vendor, address: Address) {
  await vendorsStore.updateAddressPivot(vendor.id, address.id, { is_primary: true });
  // Refresh vendor details
  viewingVendor.value = await vendorsStore.fetchVendor(vendor.id);
}

async function removeAddress(vendor: Vendor, address: Address) {
  await vendorsStore.detachAddress(vendor.id, address.id);
  // Refresh vendor details
  viewingVendor.value = await vendorsStore.fetchVendor(vendor.id);
  await fetchVendors();
}

onMounted(() => {
  void fetchVendors();
});
</script>
