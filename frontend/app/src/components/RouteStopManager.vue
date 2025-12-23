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
                    v-for="vendor in stop.vendor_cluster_locations"
                    :key="vendor.id"
                    size="sm"
                    color="blue-grey-2"
                    text-color="blue-grey-8"
                  >
                    {{ vendor.vendor_location?.name }}
                    <q-badge v-if="vendor.location_order > 0" color="primary" floating>
                      {{ vendor.location_order }}
                    </q-badge>
                    <q-badge v-if="vendor.is_optional" color="orange" floating>
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
            :options="locations"
            option-value="id"
            option-label="name"
            label="Location"
            filled
            use-input
            @filter="filterLocations"
          />

          <!-- Vendor Cluster Configuration -->
          <div v-if="stopForm.stop_type === 'VENDOR_CLUSTER'">
            <div class="text-subtitle2 q-mb-sm">Vendor Locations</div>
            <div v-for="(vendor, idx) in stopForm.vendor_locations" :key="idx" class="row q-gutter-sm q-mb-sm">
              <q-select
                v-model="vendor.vendor_location_id"
                :options="vendorLocations"
                option-value="id"
                option-label="name"
                label="Vendor"
                filled
                class="col"
              />
              <q-input
                v-model.number="vendor.location_order"
                type="number"
                label="Order (0=any)"
                filled
                style="max-width: 120px"
              />
              <q-checkbox
                v-model="vendor.is_optional"
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
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoutesStore } from 'src/stores/routes'
import draggable from 'vuedraggable'
import { useQuasar } from 'quasar'

interface Props {
  routeId: number
  initialStops: any[]
  locations: any[]
}

const props = defineProps<Props>()
const routesStore = useRoutesStore()
const $q = useQuasar()

const stops = ref([...props.initialStops])
const showAddStopDialog = ref(false)
const editingStop = ref<any>(null)
const loading = ref(false)

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
    vendor_location_id: number
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

const vendorLocations = computed(() => {
  return props.locations.filter(l => l.location_type === 'vendor')
})

const filteredLocations = ref([...props.locations])

function filterLocations(val: string, update: Function) {
  update(() => {
    const needle = val.toLowerCase()
    filteredLocations.value = props.locations.filter(
      l => l.name.toLowerCase().includes(needle)
    )
  })
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
  stopForm.value = {
    stop_type: stop.stop_type,
    location_id: stop.location_id,
    stop_order: stop.stop_order,
    estimated_duration_minutes: stop.estimated_duration_minutes,
    notes: stop.notes || '',
    vendor_locations: stop.vendor_cluster_locations?.map((v: any) => ({
      vendor_location_id: v.vendor_location_id,
      location_order: v.location_order,
      is_optional: v.is_optional,
    })) || [],
  }
  showAddStopDialog.value = true
}

function addVendorToCluster() {
  stopForm.value.vendor_locations.push({
    vendor_location_id: 0,
    location_order: 0,
    is_optional: false,
  })
}

async function saveStop() {
  loading.value = true
  try {
    if (editingStop.value) {
      await routesStore.updateStop(props.routeId, editingStop.value.id, stopForm.value)
    } else {
      stopForm.value.stop_order = stops.value.length + 1
      await routesStore.addStop(props.routeId, stopForm.value)
    }
    await refreshStops()
    closeStopDialog()
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
    vendor_locations: [],
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
