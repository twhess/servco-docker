<template>
  <q-page padding>
    <div class="q-mb-md row items-center justify-between">
      <div>
        <div class="text-h5">Routes</div>
        <div class="text-caption text-grey-6">Manage delivery routes and schedules</div>
      </div>
      <q-btn
        color="primary"
        icon="add"
        label="Create Route"
        @click="showCreateDialog = true"
      />
    </div>

    <div class="q-mb-md">
      <q-toggle
        v-model="showInactive"
        label="Show Inactive Routes"
        color="primary"
      />
    </div>

    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="40px" />
    </div>

    <div v-else-if="filteredRoutes.length === 0" class="text-center text-grey-6 q-py-md">
      No routes found. Create your first route to get started.
    </div>

    <div v-else class="row q-col-gutter-md">
      <div
        v-for="route in filteredRoutes"
        :key="route.id"
        class="col-12 col-sm-6 col-md-4"
      >
        <q-card @click="$router.push(`/routes/${route.id}`)">
          <q-card-section>
            <div class="row items-center justify-between">
              <div class="text-h6">{{ route.name }}</div>
              <q-badge
                :color="route.is_active ? 'positive' : 'grey'"
                :label="route.is_active ? 'Active' : 'Inactive'"
              />
            </div>
            <div class="text-caption text-grey-6">{{ route.code }}</div>
          </q-card-section>

          <q-separator />

          <q-card-section>
            <div v-if="route.description" class="text-body2 q-mb-sm">
              {{ route.description }}
            </div>
            <div class="text-caption">
              <div><strong>Start:</strong> {{ route.start_location?.name }}</div>
              <div><strong>Stops:</strong> {{ route.stops?.length || 0 }}</div>
              <div><strong>Schedules:</strong> {{ route.schedules?.length || 0 }} times/day</div>
            </div>
          </q-card-section>

          <q-card-actions>
            <q-btn
              flat
              color="primary"
              icon="visibility"
              label="View"
              @click.stop="$router.push(`/routes/${route.id}`)"
            />
            <q-btn
              flat
              color="primary"
              icon="edit"
              label="Edit"
              @click.stop="editRoute(route)"
            />
            <q-space />
            <q-btn
              v-if="route.is_active"
              flat
              color="negative"
              icon="visibility_off"
              @click.stop="deactivateRoute(route)"
            />
            <q-btn
              v-else
              flat
              color="positive"
              icon="visibility"
              @click.stop="activateRoute(route)"
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Create/Edit Route Dialog -->
    <q-dialog v-model="showCreateDialog" persistent>
      <q-card style="min-width: 500px">
        <q-card-section>
          <div class="text-h6">{{ editingRoute ? 'Edit Route' : 'Create Route' }}</div>
        </q-card-section>

        <q-separator />

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="routeForm.name"
            label="Route Name"
            filled
            :rules="[val => !!val || 'Name is required']"
          />

          <q-input
            v-model="routeForm.code"
            label="Route Code"
            filled
            :rules="[val => !!val || 'Code is required']"
          />

          <q-input
            v-model="routeForm.description"
            label="Description"
            type="textarea"
            filled
            rows="2"
          />

          <q-select
            v-model="routeForm.start_location_id"
            label="Start Location"
            :options="locationOptions"
            option-value="value"
            option-label="label"
            emit-value
            map-options
            filled
            :rules="[val => !!val || 'Start location is required']"
          />

          <q-toggle
            v-model="routeForm.is_active"
            label="Active"
            color="primary"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeDialog" />
          <q-btn
            flat
            label="Save"
            color="primary"
            @click="saveRoute"
            :loading="saving"
            :disable="!isFormValid"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoutesStore } from 'src/stores/routes'
import { useLocationsStore } from 'src/stores/locations'
import { useQuasar } from 'quasar'
import type { Route } from 'src/types/routes'

const routesStore = useRoutesStore()
const locationsStore = useLocationsStore()
const $q = useQuasar()

const loading = ref(false)
const saving = ref(false)
const showInactive = ref(false)
const showCreateDialog = ref(false)
const editingRoute = ref<Route | null>(null)

const routeForm = ref({
  name: '',
  code: '',
  description: '',
  start_location_id: null as number | null,
  is_active: true,
})

const filteredRoutes = computed(() => {
  if (showInactive.value) {
    return routesStore.routes
  }
  return routesStore.routes.filter(r => r.is_active)
})

const locationOptions = computed(() => {
  return locationsStore.locations
    .filter(loc => loc.location_type === 'fixed_shop')
    .map(loc => ({
      value: loc.id,
      label: loc.name,
    }))
})

const isFormValid = computed(() => {
  return !!(routeForm.value.name && routeForm.value.code && routeForm.value.start_location_id)
})

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([
      routesStore.fetchRoutes(),
      locationsStore.fetchLocations(),
    ])
  } finally {
    loading.value = false
  }
})

function editRoute(route: Route) {
  editingRoute.value = route
  routeForm.value = {
    name: route.name,
    code: route.code,
    description: route.description || '',
    start_location_id: route.start_location_id,
    is_active: route.is_active,
  }
  showCreateDialog.value = true
}

async function saveRoute() {
  if (!routeForm.value.start_location_id) return

  saving.value = true
  try {
    if (editingRoute.value) {
      await routesStore.updateRoute(editingRoute.value.id, {
        ...routeForm.value,
        start_location_id: routeForm.value.start_location_id
      })
      $q.notify({
        type: 'positive',
        message: 'Route updated successfully',
      })
    } else {
      await routesStore.createRoute({
        ...routeForm.value,
        start_location_id: routeForm.value.start_location_id
      })
      $q.notify({
        type: 'positive',
        message: 'Route created successfully',
      })
    }
    closeDialog()
  } catch (error: unknown) {
    const err = error as { message?: string }
    $q.notify({
      type: 'negative',
      message: err.message || 'Failed to save route',
    })
  } finally {
    saving.value = false
  }
}

function closeDialog() {
  showCreateDialog.value = false
  editingRoute.value = null
  routeForm.value = {
    name: '',
    code: '',
    description: '',
    start_location_id: null,
    is_active: true,
  }
}

async function activateRoute(route: Route) {
  try {
    await routesStore.activateRoute(route.id)
    $q.notify({
      type: 'positive',
      message: 'Route activated',
    })
  } catch (error: unknown) {
    const err = error as { message?: string }
    $q.notify({
      type: 'negative',
      message: err.message || 'Failed to activate route',
    })
  }
}

function deactivateRoute(route: Route) {
  $q.dialog({
    title: 'Deactivate Route',
    message: `Are you sure you want to deactivate "${route.name}"? This will prevent new runs from being created.`,
    cancel: true,
  }).onOk(() => {
    void (async () => {
      try {
        await routesStore.deleteRoute(route.id)
        $q.notify({
          type: 'positive',
          message: 'Route deactivated',
        })
      } catch (error: unknown) {
        const err = error as { message?: string }
        $q.notify({
          type: 'negative',
          message: err.message || 'Failed to deactivate route',
        })
      }
    })()
  })
}
</script>
