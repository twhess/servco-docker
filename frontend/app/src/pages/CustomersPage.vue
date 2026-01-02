<template>
  <q-page padding>
    <div class="q-pb-md">
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h5">Customers</div>
        <div class="row q-gutter-sm">
          <q-btn flat @click="openImportDialog">
            <q-icon name="upload_file" color="secondary" size="sm" class="q-mr-xs" />
            <span class="text-secondary">Import CSV</span>
            <q-badge v-if="pendingMergeCount > 0" color="warning" floating>
              {{ pendingMergeCount }}
            </q-badge>
          </q-btn>
          <q-btn flat @click="openCreateDialog">
            <q-icon name="add" color="primary" size="sm" class="q-mr-xs" />
            <span class="text-primary">Add Customer</span>
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
                placeholder="Search by name, DOT, phone..."
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
                    @click="filters.search = ''; fetchCustomers()"
                  />
                </template>
              </q-input>
            </div>

            <div class="col-6 col-sm-3">
              <q-select
                v-model="filters.source"
                dense
                outlined
                placeholder="Source"
                :options="sourceOptions"
                emit-value
                map-options
                clearable
                @update:model-value="fetchCustomers"
              />
            </div>

            <div class="col-6 col-sm-3">
              <q-select
                v-model="filters.status"
                dense
                outlined
                placeholder="Status"
                :options="statusOptions"
                emit-value
                map-options
                clearable
                @update:model-value="fetchCustomers"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Pending Merges Banner -->
      <q-banner v-if="pendingMergeCount > 0" class="bg-warning text-dark q-mb-md">
        <template v-slot:avatar>
          <q-icon name="merge_type" />
        </template>
        <strong>{{ pendingMergeCount }}</strong> imported customer(s) need review for potential merge with existing records.
        <template v-slot:action>
          <q-btn flat dense label="Review Now" @click="openMergeQueue" />
        </template>
      </q-banner>

      <!-- Customers Table -->
      <q-card flat bordered>
        <q-table
          flat
          :rows="customers"
          :columns="columns"
          row-key="id"
          :loading="loading"
          v-model:pagination="pagination"
          :rows-per-page-options="[15, 25, 50, 100]"
          @request="onTableRequest"
        >
          <template v-slot:body-cell-name="props">
            <q-td :props="props">
              <div
                class="text-weight-medium text-primary cursor-pointer"
                @click="openViewDialog(props.row)"
              >
                {{ props.row.formatted_name }}
              </div>
              <div v-if="props.row.detail" class="text-caption text-grey-7">
                {{ props.row.detail }}
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
              <div v-if="props.row.dot_number" class="text-caption">
                <q-icon name="badge" size="xs" class="q-mr-xs" />
                DOT: {{ props.row.dot_number }}
              </div>
              <span v-if="!props.row.phone && !props.row.email && !props.row.dot_number" class="text-grey-6">-</span>
            </q-td>
          </template>

          <template v-slot:body-cell-source="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="props.row.source === 'import' ? 'info' : 'grey'"
                text-color="white"
              >
                {{ props.row.source === 'import' ? 'Import' : 'Manual' }}
              </q-chip>
              <div v-if="props.row.fb_id" class="text-caption text-grey-6">
                FB: {{ props.row.fb_id }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="props.row.is_active ? 'positive' : 'grey'"
                text-color="white"
              >
                {{ props.row.is_active ? 'Active' : 'Inactive' }}
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
                      @click="toggleCustomerStatus(props.row)"
                    >
                      <q-item-section avatar>
                        <q-icon :name="props.row.is_active ? 'block' : 'check_circle'" />
                      </q-item-section>
                      <q-item-section>
                        {{ props.row.is_active ? 'Deactivate' : 'Activate' }}
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

    <!-- Create/Edit Customer Dialog -->
    <MobileFormDialog
      v-model="showCreateDialog"
      :title="editingCustomer ? 'Edit Customer' : 'Add Customer'"
      :submit-label="editingCustomer ? 'Save Changes' : 'Create Customer'"
      :loading="isSubmitting"
      @submit="submitCustomerForm"
    >
      <MobileFormField
        name="company_name"
        v-model="customerForm.company_name"
        label="Company Name"
        type="text"
        :error="getError('company_name')"
        @update:model-value="updateField('company_name', $event); checkForDuplicates()"
        @blur="touchField('company_name')"
        required
        icon="business"
        hint="Use format: Company Name (Location) for detail"
      />

      <!-- Duplicate Warning -->
      <div v-if="duplicateCandidates.length > 0" class="col-12" style="grid-column: 1 / -1;">
        <q-banner class="bg-warning text-dark q-mb-md">
          <template v-slot:avatar>
            <q-icon name="warning" />
          </template>
          Similar customers found. Did you mean one of these?
        </q-banner>
        <q-list bordered separator class="q-mb-md">
          <q-item
            v-for="candidate in duplicateCandidates"
            :key="candidate.id"
            clickable
            @click="selectExistingCustomer(candidate)"
          >
            <q-item-section>
              <q-item-label>{{ candidate.formatted_name }}</q-item-label>
              <q-item-label caption>
                <span v-if="candidate.dot_number">DOT: {{ candidate.dot_number }}</span>
                <span v-if="candidate.phone"> | {{ candidate.phone }}</span>
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-badge color="grey">
                {{ candidate.score }}% match
              </q-badge>
            </q-item-section>
          </q-item>
        </q-list>
      </div>

      <MobileFormField
        name="phone"
        v-model="customerForm.phone"
        label="Phone"
        type="tel"
        :error="getError('phone')"
        @update:model-value="updateField('phone', $event)"
        @blur="touchField('phone')"
        icon="phone"
      />

      <MobileFormField
        name="email"
        v-model="customerForm.email"
        label="Email"
        type="email"
        :error="getError('email')"
        @update:model-value="updateField('email', $event)"
        @blur="touchField('email')"
        icon="email"
      />

      <MobileFormField
        name="dot_number"
        v-model="customerForm.dot_number"
        label="DOT Number"
        type="text"
        :error="getError('dot_number')"
        @update:model-value="updateField('dot_number', $event)"
        @blur="touchField('dot_number')"
        icon="badge"
      />

      <MobileFormField
        name="customer_group"
        v-model="customerForm.customer_group"
        label="Customer Group"
        type="text"
        :error="getError('customer_group')"
        @update:model-value="updateField('customer_group', $event)"
        @blur="touchField('customer_group')"
        icon="group"
      />

      <MobileFormField
        name="assigned_shop"
        v-model="customerForm.assigned_shop"
        label="Assigned Shop"
        type="text"
        :error="getError('assigned_shop')"
        @update:model-value="updateField('assigned_shop', $event)"
        @blur="touchField('assigned_shop')"
        icon="store"
      />

      <MobileFormField
        name="notes"
        v-model="customerForm.notes"
        label="Notes"
        type="textarea"
        :rows="3"
        :error="getError('notes')"
        @update:model-value="updateField('notes', $event)"
        @blur="touchField('notes')"
        icon="notes"
      />
    </MobileFormDialog>

    <!-- View Customer Dialog -->
    <q-dialog v-model="showViewDialog" maximized>
      <q-card v-if="viewingCustomer">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ viewingCustomer.formatted_name }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <div class="q-gutter-sm q-mb-md">
            <div v-if="viewingCustomer.detail">
              <strong>Detail:</strong> {{ viewingCustomer.detail }}
            </div>
            <div v-if="viewingCustomer.phone">
              <strong>Phone:</strong>
              <a :href="`tel:${viewingCustomer.phone}`" class="text-primary">{{ viewingCustomer.phone }}</a>
            </div>
            <div v-if="viewingCustomer.phone_secondary">
              <strong>Secondary Phone:</strong> {{ viewingCustomer.phone_secondary }}
            </div>
            <div v-if="viewingCustomer.email">
              <strong>Email:</strong>
              <a :href="`mailto:${viewingCustomer.email}`" class="text-primary">{{ viewingCustomer.email }}</a>
            </div>
            <div v-if="viewingCustomer.dot_number">
              <strong>DOT Number:</strong> {{ viewingCustomer.dot_number }}
            </div>
            <div v-if="viewingCustomer.customer_group">
              <strong>Customer Group:</strong> {{ viewingCustomer.customer_group }}
            </div>
            <div v-if="viewingCustomer.assigned_shop">
              <strong>Assigned Shop:</strong> {{ viewingCustomer.assigned_shop }}
            </div>
            <div v-if="viewingCustomer.sales_rep">
              <strong>Sales Rep:</strong> {{ viewingCustomer.sales_rep }}
            </div>
            <div v-if="viewingCustomer.credit_terms">
              <strong>Credit Terms:</strong> {{ viewingCustomer.credit_terms }}
            </div>
            <div v-if="viewingCustomer.credit_limit">
              <strong>Credit Limit:</strong> ${{ viewingCustomer.credit_limit?.toLocaleString() }}
            </div>
            <div v-if="viewingCustomer.notes">
              <strong>Notes:</strong> {{ viewingCustomer.notes }}
            </div>
            <div class="row q-gutter-sm">
              <q-chip
                dense
                size="sm"
                :color="viewingCustomer.is_active ? 'positive' : 'grey'"
                text-color="white"
              >
                {{ viewingCustomer.is_active ? 'Active' : 'Inactive' }}
              </q-chip>
              <q-chip
                dense
                size="sm"
                :color="viewingCustomer.source === 'import' ? 'info' : 'grey-6'"
                text-color="white"
              >
                {{ viewingCustomer.source === 'import' ? 'Imported' : 'Manual' }}
              </q-chip>
              <q-chip v-if="viewingCustomer.fb_id" dense size="sm" outline>
                FB: {{ viewingCustomer.fb_id }}
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
              @click="openAddAddressDialog(viewingCustomer)"
            />
          </div>

          <div v-if="viewingCustomer.addresses && viewingCustomer.addresses.length > 0">
            <q-card
              v-for="address in viewingCustomer.addresses"
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
                  </div>
                  <div class="col-auto">
                    <q-btn flat dense round icon="more_vert" size="sm">
                      <q-menu>
                        <q-list style="min-width: 120px">
                          <q-item
                            v-if="!address.pivot?.is_primary"
                            clickable
                            v-close-popup
                            @click="setAddressPrimary(viewingCustomer, address)"
                          >
                            <q-item-section avatar>
                              <q-icon name="star" />
                            </q-item-section>
                            <q-item-section>Set Primary</q-item-section>
                          </q-item>
                          <q-item
                            clickable
                            v-close-popup
                            @click="removeAddress(viewingCustomer, address)"
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
          <q-btn flat label="Edit" color="primary" @click="openEditDialog(viewingCustomer)" />
          <q-btn flat label="Close" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add Address Dialog -->
    <q-dialog v-model="showAddressDialog" persistent>
      <q-card style="min-width: 350px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">Add Address</div>
          <div v-if="addressForCustomer" class="text-caption text-grey-7">
            {{ addressForCustomer.formatted_name }}
          </div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-form @submit.prevent="submitAddress" class="q-gutter-sm">
            <q-input
              v-model="addressForm.label"
              label="Label (e.g., Main Office, Warehouse)"
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
              <q-select
                v-model="addressForm.address_type"
                label="Type"
                filled
                :options="addressTypeOptions"
                emit-value
                map-options
                class="col"
              />
            </div>
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

    <!-- Import Dialog -->
    <CustomerImportDialog
      v-model="showImportDialog"
      @imported="onImportComplete"
    />

    <!-- Merge Queue Dialog -->
    <CustomerMergeDialog
      v-model="showMergeDialog"
      @resolved="onMergeResolved"
    />
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useCustomersStore } from 'src/stores/customers'
import type { Customer, CustomerDuplicateCandidate, CustomerCreateRequest } from 'src/types/customers'
import type { Address } from 'src/types/vendors'
import { debounce } from 'quasar'
import { useFormValidation, validationRules } from 'src/composables/useFormValidation'
import MobileFormDialog from 'src/components/MobileFormDialog.vue'
import MobileFormField from 'src/components/MobileFormField.vue'
import CustomerImportDialog from 'src/components/CustomerImportDialog.vue'
import CustomerMergeDialog from 'src/components/CustomerMergeDialog.vue'

const customersStore = useCustomersStore()

const customers = computed(() => customersStore.customers)
const loading = computed(() => customersStore.loading)
const pendingMergeCount = computed(() => customersStore.pendingMergeCount)

const showCreateDialog = ref(false)
const showViewDialog = ref(false)
const showAddressDialog = ref(false)
const showImportDialog = ref(false)
const showMergeDialog = ref(false)
const showInactive = ref(false)

const editingCustomer = ref<Customer | null>(null)
const viewingCustomer = ref<Customer | null>(null)
const addressForCustomer = ref<Customer | null>(null)
const duplicateCandidates = ref<CustomerDuplicateCandidate[]>([])
const savingAddress = ref(false)

const filters = ref({
  search: '',
  status: 'active' as string | null,
  source: null as string | null,
})

const pagination = ref({
  sortBy: 'formatted_name',
  descending: false,
  page: 1,
  rowsPerPage: 25,
  rowsNumber: 0,
})

const customerForm = reactive({
  company_name: '',
  phone: '',
  email: '',
  dot_number: '',
  customer_group: '',
  assigned_shop: '',
  notes: '',
})

const addressForm = reactive({
  label: '',
  line1: '',
  line2: '',
  city: '',
  state: 'OH',
  postal_code: '',
  address_type: 'physical' as 'physical' | 'billing',
  is_primary: false,
})

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
]

const sourceOptions = [
  { label: 'Manual', value: 'manual' },
  { label: 'Imported', value: 'import' },
]

const addressTypeOptions = [
  { label: 'Physical', value: 'physical' },
  { label: 'Billing', value: 'billing' },
]

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
]

const columns = [
  { name: 'name', label: 'Name', field: 'formatted_name', align: 'left' as const, sortable: true },
  { name: 'contact', label: 'Contact', field: 'contact', align: 'left' as const },
  { name: 'source', label: 'Source', field: 'source', align: 'center' as const },
  { name: 'status', label: 'Status', field: 'is_active', align: 'center' as const },
  { name: 'actions', label: '', field: 'actions', align: 'right' as const },
]

const {
  registerField,
  updateField,
  touchField,
  getError,
  handleSubmit,
  reset: resetValidation,
  isSubmitting,
} = useFormValidation()

registerField('company_name', [
  validationRules.required('Company name is required'),
  validationRules.minLength(2, 'Name must be at least 2 characters'),
])

registerField('email', [
  validationRules.email(),
])

registerField('phone', [
  validationRules.phone(),
])

watch(showInactive, (val) => {
  filters.value.status = val ? null : 'active'
  fetchCustomers()
})

async function fetchCustomers() {
  const params: Record<string, any> = {
    page: pagination.value.page,
    per_page: pagination.value.rowsPerPage,
  }

  if (filters.value.search) params.search = filters.value.search
  if (filters.value.status) params.status = filters.value.status
  if (filters.value.source) params.source = filters.value.source

  const result = await customersStore.fetchCustomers(params)
  if (result) {
    pagination.value.rowsNumber = result.total
  }
}

const debouncedFetch = debounce(() => {
  fetchCustomers()
}, 500)

const debouncedDuplicateCheck = debounce(async () => {
  if (customerForm.company_name.length >= 2 && !editingCustomer.value) {
    const checkData: { formatted_name: string; dot_number?: string; phone?: string } = {
      formatted_name: customerForm.company_name,
    }
    if (customerForm.dot_number) checkData.dot_number = customerForm.dot_number
    if (customerForm.phone) checkData.phone = customerForm.phone
    const candidates = await customersStore.checkDuplicate(checkData)
    duplicateCandidates.value = candidates
  } else {
    duplicateCandidates.value = []
  }
}, 300)

function checkForDuplicates() {
  debouncedDuplicateCheck()
}

function onTableRequest(props: any) {
  pagination.value.page = props.pagination.page
  pagination.value.rowsPerPage = props.pagination.rowsPerPage
  fetchCustomers()
}

function openCreateDialog() {
  resetValidation()
  editingCustomer.value = null
  duplicateCandidates.value = []
  Object.assign(customerForm, {
    company_name: '',
    phone: '',
    email: '',
    dot_number: '',
    customer_group: '',
    assigned_shop: '',
    notes: '',
  })
  showCreateDialog.value = true
}

function openEditDialog(customer: Customer) {
  resetValidation()
  editingCustomer.value = customer
  duplicateCandidates.value = []
  Object.assign(customerForm, {
    company_name: customer.company_name,
    phone: customer.phone || '',
    email: customer.email || '',
    dot_number: customer.dot_number || '',
    customer_group: customer.customer_group || '',
    assigned_shop: customer.assigned_shop || '',
    notes: customer.notes || '',
  })
  showViewDialog.value = false
  showCreateDialog.value = true
}

async function openViewDialog(customer: Customer) {
  viewingCustomer.value = await customersStore.fetchCustomer(customer.id)
  showViewDialog.value = true
}

function selectExistingCustomer(candidate: CustomerDuplicateCandidate) {
  showCreateDialog.value = false
  customersStore.fetchCustomer(candidate.id).then(customer => {
    viewingCustomer.value = customer
    showViewDialog.value = true
  })
}

async function submitCustomerForm() {
  await handleSubmit(async () => {
    if (editingCustomer.value) {
      const updateData: Partial<Customer> = {
        company_name: customerForm.company_name,
      }
      if (customerForm.phone) updateData.phone = customerForm.phone
      if (customerForm.email) updateData.email = customerForm.email
      if (customerForm.dot_number) updateData.dot_number = customerForm.dot_number
      if (customerForm.customer_group) updateData.customer_group = customerForm.customer_group
      if (customerForm.assigned_shop) updateData.assigned_shop = customerForm.assigned_shop
      if (customerForm.notes) updateData.notes = customerForm.notes

      await customersStore.updateCustomer(editingCustomer.value.id, updateData)
    } else {
      const createData: CustomerCreateRequest = {
        company_name: customerForm.company_name,
      }
      if (customerForm.phone) createData.phone = customerForm.phone
      if (customerForm.email) createData.email = customerForm.email
      if (customerForm.dot_number) createData.dot_number = customerForm.dot_number
      if (customerForm.customer_group) createData.customer_group = customerForm.customer_group
      if (customerForm.assigned_shop) createData.assigned_shop = customerForm.assigned_shop
      if (customerForm.notes) createData.notes = customerForm.notes
      if (duplicateCandidates.value.length > 0) createData.force_create = true

      const result = await customersStore.createCustomer(createData)

      if (result.status === 'duplicates_found') {
        duplicateCandidates.value = result.candidates || []
        return
      }
    }

    showCreateDialog.value = false
    await fetchCustomers()
  })
}

async function toggleCustomerStatus(customer: Customer) {
  await customersStore.updateCustomer(customer.id, { is_active: !customer.is_active })
  await fetchCustomers()
}

function openAddAddressDialog(customer: Customer) {
  addressForCustomer.value = customer
  Object.assign(addressForm, {
    label: '',
    line1: '',
    line2: '',
    city: '',
    state: 'OH',
    postal_code: '',
    address_type: 'physical',
    is_primary: !customer.addresses || customer.addresses.length === 0,
  })
  showAddressDialog.value = true
}

async function submitAddress() {
  if (!addressForm.line1 || !addressForm.city || !addressForm.state || !addressForm.postal_code) {
    return
  }

  if (!addressForCustomer.value) return

  savingAddress.value = true
  try {
    const updatedCustomer = await customersStore.attachAddress(addressForCustomer.value.id, {
      address: {
        label: addressForm.label || null,
        line1: addressForm.line1,
        line2: addressForm.line2 || null,
        city: addressForm.city,
        state: addressForm.state,
        postal_code: addressForm.postal_code,
      },
      address_type: addressForm.address_type,
      is_primary: addressForm.is_primary,
    })

    showAddressDialog.value = false

    if (viewingCustomer.value?.id === addressForCustomer.value.id) {
      viewingCustomer.value = updatedCustomer
    }

    await fetchCustomers()
  } finally {
    savingAddress.value = false
  }
}

async function setAddressPrimary(customer: Customer, address: Address) {
  await customersStore.updateAddressPivot(customer.id, address.id, { is_primary: true })
  viewingCustomer.value = await customersStore.fetchCustomer(customer.id)
}

async function removeAddress(customer: Customer, address: Address) {
  await customersStore.detachAddress(customer.id, address.id)
  viewingCustomer.value = await customersStore.fetchCustomer(customer.id)
  await fetchCustomers()
}

function openImportDialog() {
  showImportDialog.value = true
}

function openMergeQueue() {
  showMergeDialog.value = true
}

async function onImportComplete() {
  await fetchCustomers()
  await customersStore.fetchMergeSummary()
}

async function onMergeResolved() {
  await fetchCustomers()
  await customersStore.fetchMergeSummary()
}

onMounted(async () => {
  await fetchCustomers()
  await customersStore.fetchMergeSummary()
})
</script>
