<template>
  <q-page padding>
    <div class="q-pb-md">
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h5">Parts Requests</div>
        <q-btn
          flat
          @click="openCreateDialog"
        >
          <q-icon name="add" color="primary" size="sm" class="q-mr-xs" />
          <span class="text-primary">Create Request</span>
        </q-btn>
      </div>

      <!-- Filters -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section class="q-pa-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-4">
              <q-input
                v-model="filters.search"
                dense
                outlined
                placeholder="Search by reference #, vendor, customer..."
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
                    @click="filters.search = ''; fetchRequests()"
                  />
                </template>
              </q-input>
            </div>

            <div class="col-12 col-sm-2">
              <q-select
                v-model="filters.status"
                dense
                outlined
                placeholder="Status"
                :options="lookups.statuses"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
                @update:model-value="fetchRequests"
              />
            </div>

            <div class="col-12 col-sm-2">
              <q-select
                v-model="filters.urgency"
                dense
                outlined
                placeholder="Urgency"
                :options="lookups.urgency_levels"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
                @update:model-value="fetchRequests"
              />
            </div>

            <div class="col-12 col-sm-2">
              <q-select
                v-model="filters.assigned_runner"
                dense
                outlined
                placeholder="Runner"
                :options="runners"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
                @update:model-value="fetchRequests"
              />
            </div>

            <div class="col-12 col-sm-2">
              <q-toggle
                v-model="filters.unassigned"
                label="Unassigned Only"
                @update:model-value="fetchRequests"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Requests Table -->
      <q-card flat bordered>
        <q-table
          flat
          :rows="requests"
          :columns="columns"
          row-key="id"
          :loading="loading"
          :pagination="pagination"
          @request="onTableRequest"
        >
          <template v-slot:body-cell-reference_number="props">
            <q-td :props="props">
              <div class="text-weight-medium text-primary cursor-pointer" @click="viewRequest(props.row)">
                {{ props.row.reference_number }}
              </div>
              <div class="text-caption text-grey-7">
                {{ formatDateTime(props.row.requested_at) }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-type="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="getTypeColor(props.row.request_type.name)"
                text-color="white"
              >
                {{ getTypeLabel(props.row.request_type.name) }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-from_to="props">
            <q-td :props="props" style="max-width: 200px">
              <div class="text-weight-medium">
                <q-icon name="place" size="xs" class="q-mr-xs" />
                {{ getOriginText(props.row) }}
              </div>
              <div class="text-caption">
                <q-icon name="flag" size="xs" class="q-mr-xs" />
                {{ getDestinationText(props.row) }}
              </div>
              <div v-if="props.row.vendor_name" class="text-caption text-grey-7">
                Vendor: {{ props.row.vendor_name }}
              </div>
              <div v-if="props.row.customer_name" class="text-caption text-grey-7">
                Customer: {{ props.row.customer_name }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-details="props">
            <q-td :props="props" style="max-width: 300px">
              <div class="text-body2">{{ props.row.details }}</div>
              <div v-if="props.row.special_instructions" class="text-caption text-orange-8 q-mt-xs">
                <q-icon name="warning" size="xs" />
                {{ props.row.special_instructions }}
              </div>
              <div v-if="props.row.pickup_run" class="q-mt-xs">
                <q-chip dense size="sm" color="blue-grey" text-color="white">
                  Pickup Run
                </q-chip>
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-urgency="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="getUrgencyColor(props.row.urgency.name)"
                text-color="white"
              >
                {{ props.row.urgency.name.toUpperCase() }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="getStatusColor(props.row.status.name)"
                text-color="white"
              >
                {{ getStatusLabel(props.row.status.name) }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-runner="props">
            <q-td :props="props">
              <div v-if="props.row.assigned_runner" class="text-weight-medium">
                <q-icon name="person" size="xs" class="q-mr-xs" />
                {{ props.row.assigned_runner.name }}
              </div>
              <div v-else-if="can('parts_requests.assign')">
                <q-btn
                  flat
                  dense
                  size="sm"
                  color="primary"
                  label="Assign"
                  @click="openAssignDialog(props.row)"
                />
              </div>
              <span v-else class="text-grey-6">Unassigned</span>
            </q-td>
          </template>

          <template v-slot:body-cell-requested_by="props">
            <q-td :props="props">
              <div class="text-weight-medium">
                {{ props.row.requested_by?.name || 'Unknown' }}
              </div>
              <div class="text-caption text-grey-7">
                {{ formatDateTime(props.row.requested_at) }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn flat dense round icon="more_vert">
                <q-menu>
                  <q-list style="min-width: 150px">
                    <q-item clickable v-close-popup @click="viewRequest(props.row)">
                      <q-item-section avatar>
                        <q-icon name="visibility" />
                      </q-item-section>
                      <q-item-section>View Details</q-item-section>
                    </q-item>

                    <q-item
                      v-if="can('parts_requests.assign') && !props.row.assigned_runner"
                      clickable
                      v-close-popup
                      @click="openAssignDialog(props.row)"
                    >
                      <q-item-section avatar>
                        <q-icon name="person_add" />
                      </q-item-section>
                      <q-item-section>Assign Runner</q-item-section>
                    </q-item>

                    <q-item
                      v-if="can('parts_requests.assign') && props.row.assigned_runner"
                      clickable
                      v-close-popup
                      @click="unassignRunner(props.row)"
                    >
                      <q-item-section avatar>
                        <q-icon name="person_remove" />
                      </q-item-section>
                      <q-item-section>Unassign Runner</q-item-section>
                    </q-item>

                    <q-item clickable v-close-popup @click="viewTimeline(props.row)">
                      <q-item-section avatar>
                        <q-icon name="timeline" />
                      </q-item-section>
                      <q-item-section>View Timeline</q-item-section>
                    </q-item>

                    <q-item clickable v-close-popup @click="viewPhotos(props.row)">
                      <q-item-section avatar>
                        <q-icon name="photo_library" />
                      </q-item-section>
                      <q-item-section>View Photos</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </q-td>
          </template>
        </q-table>
      </q-card>
    </div>

    <!-- Create Request Dialog -->
    <MobileFormDialog
      v-model="showCreateDialog"
      title="Create Parts Request"
      submit-label="Create Request"
      :loading="isSubmitting"
      :has-draft="hasDraft"
      :draft-age="formatDraftAge()"
      @submit="submitCreateForm"
      @load-draft="loadDraft"
      @discard-draft="clearDraft"
    >
      <MobileSelect
        name="request_type_id"
        v-model="requestForm.request_type_id"
        label="Request Type"
        :options="lookups.request_types"
        option-value="id"
        option-label="name"
        :error="getError('request_type_id')"
        @update:model-value="updateField('request_type_id', $event)"
        @blur="touchField('request_type_id')"
        required
        icon="category"
      />

      <MobileSelect
        name="urgency_id"
        v-model="requestForm.urgency_id"
        label="Urgency Level"
        :options="lookups.urgency_levels"
        option-value="id"
        option-label="name"
        :error="getError('urgency_id')"
        @update:model-value="updateField('urgency_id', $event)"
        @blur="touchField('urgency_id')"
        required
        icon="priority_high"
      />

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <div class="text-subtitle1 text-weight-medium q-my-sm">Supplying Location</div>
      </div>

      <MobileSelect
        name="origin_location_id"
        v-model="requestForm.origin_location_id"
        label="Shop Location (Pickup)"
        :options="locations"
        option-value="id"
        option-label="name"
        :error="getError('origin_location_id')"
        @update:model-value="updateField('origin_location_id', $event)"
        @blur="touchField('origin_location_id')"
        clearable
        icon="store"
        hint="Select if picking up from one of our shops"
      />

      <MobileFormField
        name="vendor_name"
        v-model="requestForm.vendor_name"
        label="Vendor Name"
        type="text"
        :error="getError('vendor_name')"
        @update:model-value="updateField('vendor_name', $event)"
        @blur="touchField('vendor_name')"
        icon="business"
        hint="Enter vendor name if picking up from external vendor"
      />

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <div class="text-subtitle1 text-weight-medium q-my-sm">Receiving Location</div>
      </div>

      <MobileSelect
        name="receiving_location_id"
        v-model="requestForm.receiving_location_id"
        label="Shop Location (Delivery)"
        :options="locations"
        option-value="id"
        option-label="name"
        :error="getError('receiving_location_id')"
        @update:model-value="updateField('receiving_location_id', $event)"
        @blur="touchField('receiving_location_id')"
        clearable
        icon="location_on"
        hint="Select if delivering to one of our shops"
      />

      <MobileFormField
        name="customer_name"
        v-model="requestForm.customer_name"
        label="Customer Name"
        type="text"
        :error="getError('customer_name')"
        @update:model-value="updateField('customer_name', $event)"
        @blur="touchField('customer_name')"
        icon="person"
        hint="Enter customer name if delivering to customer"
      />

      <MobileFormField
        name="customer_address"
        v-model="requestForm.customer_address"
        label="Customer Address"
        type="textarea"
        :rows="2"
        :error="getError('customer_address')"
        @update:model-value="updateField('customer_address', $event)"
        @blur="touchField('customer_address')"
        icon="home"
        hint="Full address for customer delivery"
      />

      <MobileFormField
        name="customer_phone"
        v-model="requestForm.customer_phone"
        label="Customer Phone"
        type="tel"
        :error="getError('customer_phone')"
        @update:model-value="updateField('customer_phone', $event)"
        @blur="touchField('customer_phone')"
        icon="phone"
        hint="Contact number for delivery"
      />

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
      </div>

      <MobileFormField
        name="details"
        v-model="requestForm.details"
        label="Details"
        type="textarea"
        :rows="3"
        :error="getError('details')"
        @update:model-value="updateField('details', $event)"
        @blur="touchField('details')"
        required
        icon="description"
      />

      <MobileFormField
        name="special_instructions"
        v-model="requestForm.special_instructions"
        label="Special Instructions"
        type="textarea"
        :rows="2"
        :error="getError('special_instructions')"
        @update:model-value="updateField('special_instructions', $event)"
        @blur="touchField('special_instructions')"
        icon="info"
      />

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <div class="text-subtitle2 q-my-sm">Options</div>
      </div>

      <div class="col-12" style="grid-column: 1 / -1;">
        <q-toggle
          v-model="requestForm.pickup_run"
          label="This is a pickup run"
        />
      </div>

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <div class="text-subtitle2 q-my-sm">Slack Notifications</div>
      </div>

      <div class="col-12" style="grid-column: 1 / -1;">
        <q-toggle
          v-model="requestForm.slack_notify_pickup"
          label="Notify on pickup"
        />
        <q-toggle
          v-model="requestForm.slack_notify_delivery"
          label="Notify on delivery"
          class="q-ml-md"
        />
      </div>

      <MobileFormField
        v-if="requestForm.slack_notify_pickup || requestForm.slack_notify_delivery"
        name="slack_channel"
        v-model="requestForm.slack_channel"
        label="Slack Channel (optional)"
        type="text"
        :error="getError('slack_channel')"
        @update:model-value="updateField('slack_channel', $event)"
        @blur="touchField('slack_channel')"
        icon="tag"
        hint="e.g., #parts-alerts"
      />
    </MobileFormDialog>

    <!-- Assign Runner Dialog -->
    <q-dialog v-model="showAssignDialog">
      <q-card style="width: 100%; max-width: 500px">
        <q-card-section>
          <div class="text-h6">Assign Runner</div>
          <div class="text-caption">{{ selectedRequest?.reference_number }}</div>
        </q-card-section>

        <q-card-section>
          <q-select
            v-model="selectedRunnerId"
            label="Select Runner"
            outlined
            :options="runners"
            option-value="id"
            option-label="name"
            emit-value
            map-options
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Assign"
            color="primary"
            @click="assignRunner"
            :disable="!selectedRunnerId"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- View Request Dialog -->
    <q-dialog v-model="showViewDialog">
      <q-card style="width: 100%; max-width: 800px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ viewingRequest?.reference_number }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section v-if="viewingRequest">
          <div class="q-gutter-sm">
            <div><strong>Type:</strong> {{ getTypeLabel(viewingRequest.request_type.name) }}</div>
            <div><strong>Status:</strong> {{ getStatusLabel(viewingRequest.status.name) }}</div>
            <div><strong>Urgency:</strong> {{ viewingRequest.urgency.name.toUpperCase() }}</div>
            <div><strong>From:</strong> {{ getOriginText(viewingRequest) }}</div>
            <div><strong>To:</strong> {{ getDestinationText(viewingRequest) }}</div>
            <div><strong>Requested By:</strong> {{ viewingRequest.requested_by.name }}</div>
            <div v-if="viewingRequest.assigned_runner">
              <strong>Assigned To:</strong> {{ viewingRequest.assigned_runner.name }}
            </div>
            <div><strong>Details:</strong> {{ viewingRequest.details }}</div>
            <div v-if="viewingRequest.special_instructions">
              <strong>Special Instructions:</strong> {{ viewingRequest.special_instructions }}
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Close" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Timeline Dialog -->
    <q-dialog v-model="showTimelineDialog">
      <q-card style="width: 100%; max-width: 600px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Timeline</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-timeline color="primary">
            <q-timeline-entry
              v-for="event in timeline"
              :key="event.id"
              :title="getEventDisplayName(event.event_type)"
              :subtitle="formatDateTime(event.event_at)"
            >
              <div v-if="event.user">By: {{ event.user.name }}</div>
              <div v-if="event.notes" class="text-caption">{{ event.notes }}</div>
            </q-timeline-entry>
          </q-timeline>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Photos Dialog -->
    <q-dialog v-model="showPhotosDialog">
      <q-card style="width: 100%; max-width: 700px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Photos</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <div v-if="photos.length === 0" class="text-center text-grey-7">
            No photos uploaded yet
          </div>
          <div v-else class="row q-col-gutter-md">
            <div v-for="photo in photos" :key="photo.id" class="col-12 col-sm-6">
              <q-card>
                <q-img :src="photo.url" ratio="4/3" />
                <q-card-section>
                  <div class="text-weight-medium">{{ photo.stage.toUpperCase() }}</div>
                  <div class="text-caption">{{ formatDateTime(photo.taken_at) }}</div>
                  <div class="text-caption">By: {{ photo.taken_by }}</div>
                  <div v-if="photo.notes" class="text-caption">{{ photo.notes }}</div>
                </q-card-section>
              </q-card>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue';
import { usePartsRequestsStore, type PartsRequest, type PartsRequestEvent, type PartsRequestPhoto } from 'src/stores/partsRequests';
import { useAuthStore } from 'src/stores/auth';
import { api } from 'boot/axios';
import { debounce } from 'quasar';
import { useFormValidation, validationRules } from 'src/composables/useFormValidation';
import { useDraftState } from 'src/composables/useDraftState';
import MobileFormDialog from 'src/components/MobileFormDialog.vue';
import MobileFormField from 'src/components/MobileFormField.vue';
import MobileSelect from 'src/components/MobileSelect.vue';

const partsRequestsStore = usePartsRequestsStore();
const authStore = useAuthStore();

const requests = computed(() => partsRequestsStore.requests);
const loading = computed(() => partsRequestsStore.loading);
const lookups = computed(() => partsRequestsStore.lookups);

const showCreateDialog = ref(false);
const showAssignDialog = ref(false);
const showViewDialog = ref(false);
const showTimelineDialog = ref(false);
const showPhotosDialog = ref(false);

const selectedRequest = ref<PartsRequest | null>(null);
const selectedRunnerId = ref<number | null>(null);
const viewingRequest = ref<PartsRequest | null>(null);
const timeline = ref<PartsRequestEvent[]>([]);
const photos = ref<PartsRequestPhoto[]>([]);

const runners = ref<any[]>([]);
const locations = ref<any[]>([]);

const filters = ref({
  search: '',
  status: null as number | null,
  urgency: null as number | null,
  assigned_runner: null as number | null,
  unassigned: false,
});

const pagination = ref({
  sortBy: 'requested_at',
  descending: true,
  page: 1,
  rowsPerPage: 20,
  rowsNumber: 0,
});

const requestForm = reactive({
  request_type_id: null as number | null,
  urgency_id: null as number | null,
  origin_location_id: null as number | null,
  vendor_name: '',
  receiving_location_id: null as number | null,
  customer_name: '',
  customer_address: '',
  customer_phone: '',
  details: '',
  special_instructions: '',
  pickup_run: false,
  slack_notify_pickup: false,
  slack_notify_delivery: false,
  slack_channel: '',
});

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
registerField('request_type_id', [
  validationRules.required('Please select a request type'),
]);

registerField('urgency_id', [
  validationRules.required('Please select an urgency level'),
]);

registerField('details', [
  validationRules.required('Please provide details about this request'),
  validationRules.minLength(10, 'Details must be at least 10 characters'),
]);

registerField('customer_phone', [
  validationRules.phone(),
]);

// Draft state
const {
  hasDraft,
  loadDraft,
  clearDraft,
  formatDraftAge,
} = useDraftState(requestForm, {
  key: 'parts-request-create',
  excludeFields: [], // No sensitive fields in this form
});

const columns = [
  { name: 'reference_number', label: 'Reference #', field: 'reference_number', align: 'left' as const, sortable: true },
  { name: 'type', label: 'Type', field: 'request_type', align: 'left' as const },
  { name: 'from_to', label: 'From / To', field: 'from_to', align: 'left' as const },
  { name: 'details', label: 'Details', field: 'details', align: 'left' as const },
  { name: 'urgency', label: 'Urgency', field: 'urgency', align: 'center' as const },
  { name: 'status', label: 'Status', field: 'status', align: 'center' as const },
  { name: 'runner', label: 'Runner', field: 'assigned_runner', align: 'left' as const },
  { name: 'requested_by', label: 'Requested By', field: 'requested_by', align: 'left' as const },
  { name: 'actions', label: '', field: 'actions', align: 'right' as const },
];

function can(ability: string): boolean {
  return authStore.can(ability);
}

function getTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    pickup: 'Pickup',
    delivery: 'Delivery',
    transfer: 'Transfer',
  };
  return labels[type] || type;
}

function getTypeColor(type: string): string {
  const colors: Record<string, string> = {
    pickup: 'blue',
    delivery: 'green',
    transfer: 'orange',
  };
  return colors[type] || 'grey';
}

function getStatusLabel(status: string): string {
  return status.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    new: 'blue',
    assigned: 'cyan',
    en_route_pickup: 'amber',
    picked_up: 'orange',
    en_route_dropoff: 'purple',
    delivered: 'positive',
    canceled: 'grey',
    problem: 'negative',
  };
  return colors[status] || 'grey';
}

function getUrgencyColor(urgency: string): string {
  const colors: Record<string, string> = {
    normal: 'blue',
    today: 'orange',
    asap: 'deep-orange',
    emergency: 'negative',
  };
  return colors[urgency] || 'grey';
}

function getOriginText(request: PartsRequest): string {
  return request.vendor_name || request.origin_location?.name || request.origin_address || 'Unknown';
}

function getDestinationText(request: PartsRequest): string {
  return request.customer_name || request.receiving_location?.name || request.customer_address || 'Unknown';
}

function formatDateTime(dateString: string): string {
  return new Date(dateString).toLocaleString();
}

function getEventDisplayName(eventType: string): string {
  const names: Record<string, string> = {
    created: 'Created',
    assigned: 'Assigned',
    unassigned: 'Unassigned',
    started: 'Started',
    arrived_pickup: 'Arrived at Pickup',
    picked_up: 'Picked Up',
    departed_pickup: 'Departed Pickup',
    arrived_dropoff: 'Arrived at Dropoff',
    delivered: 'Delivered',
    canceled: 'Canceled',
    problem_reported: 'Problem Reported',
    note_added: 'Note Added',
  };
  return names[eventType] || eventType;
}

async function fetchRequests() {
  const params: any = {
    page: pagination.value.page,
    per_page: pagination.value.rowsPerPage,
  };

  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.status) params.status = filters.value.status;
  if (filters.value.urgency) params.urgency = filters.value.urgency;
  if (filters.value.assigned_runner) params.assigned_runner = filters.value.assigned_runner;
  if (filters.value.unassigned) params.unassigned = 'true';

  const result = await partsRequestsStore.fetchRequests(params);
  if (result) {
    pagination.value.rowsNumber = result.total;
  }
}

const debouncedFetch = debounce(() => {
  fetchRequests();
}, 500);

function onTableRequest(props: any) {
  pagination.value.page = props.pagination.page;
  pagination.value.rowsPerPage = props.pagination.rowsPerPage;
  fetchRequests();
}

function openCreateDialog() {
  // Reset form
  Object.assign(requestForm, {
    request_type_id: null,
    urgency_id: null,
    origin_location_id: null,
    vendor_name: '',
    receiving_location_id: null,
    customer_name: '',
    customer_address: '',
    customer_phone: '',
    details: '',
    special_instructions: '',
    pickup_run: false,
    slack_notify_pickup: false,
    slack_notify_delivery: false,
    slack_channel: '',
  });
  resetValidation();
  showCreateDialog.value = true;
}

async function submitCreateForm() {
  await handleSubmit(async () => {
    await partsRequestsStore.createRequest(requestForm);
    showCreateDialog.value = false;
    clearDraft();
    await fetchRequests();
  });
}

function openAssignDialog(request: PartsRequest) {
  selectedRequest.value = request;
  selectedRunnerId.value = null;
  showAssignDialog.value = true;
}

async function assignRunner() {
  if (!selectedRequest.value || !selectedRunnerId.value) return;

  try {
    await partsRequestsStore.assignRunner(selectedRequest.value.id, selectedRunnerId.value);
    showAssignDialog.value = false;
    fetchRequests();
  } catch (error) {
    // Error handled by store
  }
}

async function unassignRunner(request: PartsRequest) {
  try {
    await partsRequestsStore.unassignRunner(request.id);
    fetchRequests();
  } catch (error) {
    // Error handled by store
  }
}

async function viewRequest(request: PartsRequest) {
  viewingRequest.value = await partsRequestsStore.fetchRequest(request.id);
  showViewDialog.value = true;
}

async function viewTimeline(request: PartsRequest) {
  timeline.value = await partsRequestsStore.fetchTimeline(request.id);
  showTimelineDialog.value = true;
}

async function viewPhotos(request: PartsRequest) {
  photos.value = await partsRequestsStore.fetchPhotos(request.id);
  showPhotosDialog.value = true;
}

async function loadRunners() {
  try {
    const response = await api.get('/users', { params: { active: true } });
    runners.value = response.data.filter((u: any) => u.role === 'runner_driver');
  } catch (error) {
    console.error('Failed to load runners', error);
  }
}

async function loadLocations() {
  try {
    const response = await api.get('/locations', { params: { per_page: 100 } });
    locations.value = response.data.data;
  } catch (error) {
    console.error('Failed to load locations', error);
  }
}

onMounted(async () => {
  await partsRequestsStore.fetchLookups();
  await fetchRequests();
  await loadRunners();
  await loadLocations();
});
</script>
