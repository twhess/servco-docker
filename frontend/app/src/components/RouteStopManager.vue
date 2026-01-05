<template>
  <div class="route-stop-manager">
    <q-card>
      <q-card-section>
        <div class="row items-center justify-between">
          <div class="text-h6">Route Stops</div>
          <q-btn
            color="primary"
            icon="add"
            label="Add Stop"
            @click="showAddStopDialog = true"
          />
        </div>
      </q-card-section>

      <q-separator />

      <q-card-section v-if="stops.length === 0">
        <div class="text-center text-grey-6">
          No stops configured. Add your first stop to get started.
        </div>
      </q-card-section>

      <q-list v-else separator>
        <draggable
          v-model="stops"
          item-key="id"
          handle=".drag-handle"
          @end="onReorder"
        >
          <template #item="{ element: stop, index }">
            <q-item>
              <q-item-section side>
                <q-icon name="drag_indicator" class="drag-handle cursor-pointer" />
              </q-item-section>

              <q-item-section>
                <q-item-label>
                  <q-badge :color="getStopTypeColor(stop.stop_type)" class="q-mr-sm">
                    {{ stop.stop_order }}
                  </q-badge>
                  <span class="text-weight-medium">
                    {{ getStopDisplayName(stop) }}
                  </span>
                </q-item-label>
                <q-item-label caption>
                  {{ stop.stop_type }} • {{ stop.estimated_duration_minutes }} min
                  <span v-if="stop.notes"> • {{ stop.notes }}</span>
                </q-item-label>

                <!-- Vendor cluster locations -->
                <div v-if="stop.stop_type === 'VENDOR_CLUSTER' && stop.vendor_cluster_locations" class="q-mt-xs">
                  <q-chip
                    v-for="vcl in stop.vendor_cluster_locations"
                    :key="vcl.id"
                    size="sm"
                    color="blue-grey-2"
                    text-color="blue-grey-8"
                  >
                    {{ vcl.vendor?.name }}
                    <q-badge v-if="vcl.location_order > 0" color="primary" floating>
                      {{ vcl.location_order }}
                    </q-badge>
                    <q-badge v-if="vcl.is_optional" color="orange" floating>
                      Optional
                    </q-badge>
                  </q-chip>
                </div>
              </q-item-section>

              <q-item-section side>
                <div class="row q-gutter-xs">
                  <q-btn
                    flat
                    round
                    dense
                    icon="edit"
                    color="primary"
                    @click="editStop(stop)"
                  />
                  <q-btn
                    flat
                    round
                    dense
                    icon="delete"
                    color="negative"
                    @click="confirmDeleteStop(stop)"
                  />
                </div>
              </q-item-section>
            </q-item>
          </template>
        </draggable>
      </q-list>
    </q-card>

    <!-- Add/Edit Stop Dialog -->
    <q-dialog v-model="showAddStopDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section>
          <div class="text-h6">{{ editingStop ? 'Edit Stop' : 'Add Stop' }}</div>
        </q-card-section>

        <q-separator />

        <q-card-section class="q-gutter-md">
          <q-select
            v-model="stopForm.stop_type"
            :options="stopTypes"
            label="Stop Type"
            filled
            emit-value
            map-options
          />

          <q-select
            v-if="stopForm.stop_type !== 'VENDOR_CLUSTER'"
            v-model="stopForm.location_id"
            :options="filteredLocations"
            option-value="id"
            option-label="name"
            label="Location"
            filled
            emit-value
            map-options
            use-input
            @filter="filterLocations"
          />

          <!-- Vendor Cluster Configuration -->
          <div v-if="stopForm.stop_type === 'VENDOR_CLUSTER'">
            <div class="row items-center justify-between q-mb-sm">
              <div class="text-subtitle2">Vendors in Cluster</div>
              <q-btn
                flat
                dense
                icon="add_business"
                label="New Vendor"
                color="secondary"
                size="sm"
                @click="openCreateVendorDialog"
              />
            </div>
            <div v-for="(vendorEntry, idx) in stopForm.vendor_locations" :key="idx" class="row q-gutter-sm q-mb-sm items-center">
              <q-select
                v-model="vendorEntry.vendor_id"
                :options="vendorSearchOptions"
                option-value="id"
                option-label="name"
                label="Search vendor..."
                filled
                emit-value
                map-options
                use-input
                hide-selected
                fill-input
                input-debounce="300"
                class="col"
                :loading="vendorsStore.searching"
                @filter="searchVendors"
                @update:model-value="(val) => onVendorSelected(val, idx)"
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
                <template v-slot:option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section>
                      <q-item-label>{{ scope.opt.name }}</q-item-label>
                      <q-item-label caption v-if="scope.opt.phone">{{ scope.opt.phone }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
                <template v-slot:no-option>
                  <q-item>
                    <q-item-section class="text-grey">
                      Type to search vendors...
                    </q-item-section>
                  </q-item>
                  <q-item clickable @click="openCreateVendorDialog">
                    <q-item-section avatar>
                      <q-icon name="add_circle" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-primary">Create new vendor</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
                <template v-slot:selected-item="scope">
                  <span>{{ getVendorName(vendorEntry.vendor_id) }}</span>
                </template>
              </q-select>
              <q-input
                v-model.number="vendorEntry.location_order"
                type="number"
                label="Order (0=any)"
                filled
                style="max-width: 120px"
              />
              <q-checkbox
                v-model="vendorEntry.is_optional"
                label="Optional"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                @click="stopForm.vendor_locations.splice(idx, 1)"
              />
            </div>
            <q-btn
              flat
              icon="add"
              label="Add Vendor"
              color="primary"
              @click="addVendorToCluster"
            />
          </div>

          <q-input
            v-model.number="stopForm.estimated_duration_minutes"
            type="number"
            label="Estimated Duration (minutes)"
            filled
          />

          <q-input
            v-model="stopForm.notes"
            type="textarea"
            label="Notes"
            filled
            rows="2"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeStopDialog" />
          <q-btn
            flat
            label="Save"
            color="primary"
            @click="saveStop"
            :loading="loading"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Create Vendor Dialog -->
    <q-dialog v-model="showCreateVendorDialog" persistent>
      <q-card style="min-width: 350px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">Add New Vendor</div>
        </q-card-section>

        <q-card-section class="q-pt-none q-gutter-sm">
          <q-input
            v-model="newVendorForm.name"
            label="Vendor Name *"
            filled
            autofocus
            :rules="[(v: string) => !!v || 'Name is required']"
          />
          <q-input
            v-model="newVendorForm.phone"
            label="Phone"
            filled
            type="tel"
            mask="(###) ###-####"
          />
          <q-input
            v-model="newVendorForm.email"
            label="Email"
            filled
            type="email"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeCreateVendorDialog" />
          <q-btn
            flat
            label="Create & Add"
            color="primary"
            @click="createVendorAndAdd"
            :loading="creatingVendor"
            :disable="!newVendorForm.name"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoutesStore } from 'src/stores/routes'
import { useVendorsStore } from 'src/stores/vendors'
import draggable from 'vuedraggable'
import { useQuasar } from 'quasar'

interface Props {
  routeId: number
  initialStops: any[]
  locations: any[]
}

const props = defineProps<Props>()
const routesStore = useRoutesStore()
const vendorsStore = useVendorsStore()
const $q = useQuasar()

const stops = ref([...props.initialStops])
const showAddStopDialog = ref(false)
const editingStop = ref<any>(null)
const loading = ref(false)

// Vendor creation state
const showCreateVendorDialog = ref(false)
const creatingVendor = ref(false)
const newVendorForm = ref({
  name: '',
  phone: '',
  email: '',
})

// Vendor search state
const vendorSearchOptions = ref<Array<{ id: number; name: string; phone?: string | null }>>([])
const selectedVendorNames = ref<Map<number, string>>(new Map())

const stopTypes = [
  { label: 'Shop', value: 'SHOP' },
  { label: 'Vendor Cluster', value: 'VENDOR_CLUSTER' },
  { label: 'Customer', value: 'CUSTOMER' },
  { label: 'Ad Hoc', value: 'AD_HOC' },
]

const stopForm = ref<{
  stop_type: 'SHOP' | 'VENDOR_CLUSTER' | 'CUSTOMER' | 'AD_HOC'
  location_id: number | null
  stop_order: number
  estimated_duration_minutes: number
  notes: string
  vendor_locations: Array<{
    vendor_id: number
    location_order: number
    is_optional: boolean
  }>
}>({
  stop_type: 'SHOP',
  location_id: null,
  stop_order: 1,
  estimated_duration_minutes: 15,
  notes: '',
  vendor_locations: [],
})

// Load vendors on mount for the store's getVendorById getter to work
onMounted(async () => {
  await vendorsStore.fetchVendors({ status: 'active', per_page: 100 })
})

const filteredLocations = ref([...props.locations])

function filterLocations(val: string, update: (fn: () => void) => void) {
  update(() => {
    if (!val) {
      filteredLocations.value = [...props.locations]
    } else {
      const needle = val.toLowerCase()
      filteredLocations.value = props.locations.filter(
        l => l.name.toLowerCase().includes(needle)
      )
    }
  })
}

// Search vendors via API for autocomplete
async function searchVendors(val: string, update: (fn: () => void) => void) {
  if (!val || val.length < 1) {
    update(() => {
      vendorSearchOptions.value = []
    })
    return
  }

  try {
    const results = await vendorsStore.searchVendors(val, 20)
    update(() => {
      vendorSearchOptions.value = results.map(v => ({
        id: v.id,
        name: v.name,
        phone: v.phone ?? null,
      }))
    })
  } catch (error) {
    update(() => {
      vendorSearchOptions.value = []
    })
  }
}

// Handle vendor selection - store vendor name for display
function onVendorSelected(vendorId: number, idx: number) {
  const vendor = vendorSearchOptions.value.find(v => v.id === vendorId)
  if (vendor) {
    selectedVendorNames.value.set(vendorId, vendor.name)
  }
}

// Get vendor name from cache or existing data
function getVendorName(vendorId: number): string {
  if (!vendorId) return ''

  // Check our local cache first
  if (selectedVendorNames.value.has(vendorId)) {
    return selectedVendorNames.value.get(vendorId) || ''
  }

  // Check if it's in the search options
  const fromOptions = vendorSearchOptions.value.find(v => v.id === vendorId)
  if (fromOptions) {
    return fromOptions.name
  }

  // Check the vendors store
  const fromStore = vendorsStore.getVendorById(vendorId)
  if (fromStore) {
    return fromStore.name
  }

  return `Vendor #${vendorId}`
}

// Vendor creation functions
function openCreateVendorDialog() {
  newVendorForm.value = { name: '', phone: '', email: '' }
  showCreateVendorDialog.value = true
}

function closeCreateVendorDialog() {
  showCreateVendorDialog.value = false
  newVendorForm.value = { name: '', phone: '', email: '' }
}

async function createVendorAndAdd() {
  if (!newVendorForm.value.name.trim()) {
    $q.notify({
      type: 'warning',
      message: 'Vendor name is required',
    })
    return
  }

  creatingVendor.value = true
  try {
    const result = await vendorsStore.createVendor({
      name: newVendorForm.value.name.trim(),
      phone: newVendorForm.value.phone || null,
      email: newVendorForm.value.email || null,
      force_create: true, // Skip duplicate check for quick creation
    })

    if (result.status === 'created' && result.data) {
      // Refresh vendors list to include the new vendor
      await vendorsStore.fetchVendors({ status: 'active', per_page: 100 })

      // Add to vendor name cache
      selectedVendorNames.value.set(result.data.id, result.data.name)

      // Add the new vendor to the cluster
      stopForm.value.vendor_locations.push({
        vendor_id: result.data.id,
        location_order: 0,
        is_optional: false,
      })

      $q.notify({
        type: 'positive',
        message: `Vendor "${result.data.name}" created and added to cluster`,
      })

      closeCreateVendorDialog()
    } else {
      $q.notify({
        type: 'negative',
        message: result.message || 'Failed to create vendor',
      })
    }
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Failed to create vendor',
    })
  } finally {
    creatingVendor.value = false
  }
}

function getStopTypeColor(type: string) {
  const colors: Record<string, string> = {
    SHOP: 'primary',
    VENDOR_CLUSTER: 'secondary',
    CUSTOMER: 'accent',
    AD_HOC: 'grey',
  }
  return colors[type] || 'grey'
}

function getStopDisplayName(stop: any) {
  if (stop.stop_type === 'VENDOR_CLUSTER') {
    const count = stop.vendor_cluster_locations?.length || 0
    return `Vendor Cluster (${count} vendors)`
  }
  return stop.location?.name || 'Unknown Location'
}

function editStop(stop: any) {
  editingStop.value = stop

  // Pre-populate vendor name cache from existing stop data
  if (stop.vendor_cluster_locations) {
    stop.vendor_cluster_locations.forEach((vcl: any) => {
      if (vcl.vendor_id && vcl.vendor?.name) {
        selectedVendorNames.value.set(vcl.vendor_id, vcl.vendor.name)
      }
    })
  }

  stopForm.value = {
    stop_type: stop.stop_type,
    location_id: stop.location_id,
    stop_order: stop.stop_order,
    estimated_duration_minutes: stop.estimated_duration_minutes,
    notes: stop.notes || '',
    vendor_locations: stop.vendor_cluster_locations?.map((v: any) => ({
      vendor_id: v.vendor_id,
      location_order: v.location_order,
      is_optional: v.is_optional,
    })) || [],
  }
  showAddStopDialog.value = true
}

function addVendorToCluster() {
  stopForm.value.vendor_locations.push({
    vendor_id: 0,
    location_order: 0,
    is_optional: false,
  })
}

async function saveStop() {
  // Build the payload - only include vendor_locations for VENDOR_CLUSTER type
  const payload: {
    stop_type: typeof stopForm.value.stop_type
    location_id: number | null
    stop_order: number
    estimated_duration_minutes: number
    notes: string
    vendor_locations?: Array<{ vendor_id: number; location_order: number; is_optional: boolean }>
  } = {
    stop_type: stopForm.value.stop_type,
    location_id: stopForm.value.location_id,
    stop_order: stopForm.value.stop_order,
    estimated_duration_minutes: stopForm.value.estimated_duration_minutes,
    notes: stopForm.value.notes,
  }

  // Only include vendor_locations for VENDOR_CLUSTER type
  if (stopForm.value.stop_type === 'VENDOR_CLUSTER') {
    const validVendors = stopForm.value.vendor_locations.filter(v => v.vendor_id > 0)
    if (validVendors.length === 0) {
      $q.notify({
        type: 'warning',
        message: 'Please select at least one vendor for the cluster',
      })
      return
    }
    payload.vendor_locations = validVendors
  }

  loading.value = true
  try {
    if (editingStop.value) {
      await routesStore.updateStop(props.routeId, editingStop.value.id, payload)
    } else {
      payload.stop_order = stops.value.length + 1
      await routesStore.addStop(props.routeId, payload)
    }
    await refreshStops()
    closeStopDialog()
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Failed to save stop',
    })
  } finally {
    loading.value = false
  }
}

function confirmDeleteStop(stop: any) {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Are you sure you want to remove ${getStopDisplayName(stop)}?`,
    cancel: true,
  }).onOk(async () => {
    await routesStore.removeStop(props.routeId, stop.id)
    await refreshStops()
  })
}

async function onReorder() {
  const stopsOrder = stops.value.map((stop, index) => ({
    id: stop.id,
    order: index + 1,
  }))
  await routesStore.reorderStops(props.routeId, stopsOrder)
}

async function refreshStops() {
  const route = await routesStore.fetchRoute(props.routeId)
  if (route) {
    stops.value = route.stops || []
  }
}

function closeStopDialog() {
  showAddStopDialog.value = false
  editingStop.value = null
  stopForm.value = {
    stop_type: 'SHOP',
    location_id: null,
    stop_order: 1,
    estimated_duration_minutes: 15,
    notes: '',
    vendor_locations: [] as Array<{ vendor_id: number; location_order: number; is_optional: boolean }>,
  }
}

watch(() => props.initialStops, (newStops) => {
  stops.value = [...newStops]
}, { deep: true })
</script>

<style scoped>
.drag-handle {
  cursor: move;
}
</style>
