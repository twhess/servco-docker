<template>
  <q-page padding>
    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="40px" />
    </div>

    <div v-else-if="!route" class="text-center text-grey-6 q-py-md">
      Route not found
    </div>

    <div v-else>
      <div class="q-mb-md">
        <q-btn flat icon="arrow_back" label="Back to Routes" @click="$router.push('/routes')" />
      </div>

      <q-card class="q-mb-md">
        <q-card-section>
          <div class="row items-center justify-between">
            <div>
              <div class="text-h5">{{ route.name }}</div>
              <div class="text-caption text-grey-6">{{ route.code }}</div>
            </div>
            <q-badge
              :color="route.is_active ? 'positive' : 'grey'"
              :label="route.is_active ? 'Active' : 'Inactive'"
            />
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section>
          <div class="text-body2">
            <div v-if="route.description" class="q-mb-sm">{{ route.description }}</div>
            <div><strong>Start Location:</strong> {{ route.start_location?.name }}</div>
            <div><strong>Created:</strong> {{ formatDate(route.created_at) }}</div>
          </div>
        </q-card-section>

        <q-card-actions>
          <q-btn
            flat
            color="primary"
            icon="edit"
            label="Edit Details"
            @click="$router.push(`/routes`)"
          />
          <q-btn
            flat
            color="primary"
            icon="cached"
            label="Rebuild Cache"
            @click="rebuildCache"
          />
        </q-card-actions>
      </q-card>

      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-7">
          <RouteStopManager
            :route-id="route.id"
            :initial-stops="route.stops || []"
            @stops-updated="refreshRoute"
          />
        </div>

        <div class="col-12 col-md-5">
          <RouteScheduleManager
            :route-id="route.id"
            :initial-schedules="route.schedules || []"
          />
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useRoutesStore } from 'src/stores/routes'
import { useQuasar } from 'quasar'
import RouteStopManager from 'src/components/RouteStopManager.vue'
import RouteScheduleManager from 'src/components/RouteScheduleManager.vue'
import type { Route } from 'src/types/routes'

const route = useRoute()
const routesStore = useRoutesStore()
const $q = useQuasar()

const loading = ref(false)
const routeData = ref<Route | null>(null)

const routeId = computed(() => parseInt(route.params.id as string))

onMounted(async () => {
  await loadRoute()
})

async function loadRoute() {
  loading.value = true
  try {
    routeData.value = await routesStore.fetchRoute(routeId.value)
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.message || 'Failed to load route',
    })
  } finally {
    loading.value = false
  }
}

async function refreshRoute() {
  await loadRoute()
}

async function rebuildCache() {
  $q.dialog({
    title: 'Rebuild Route Cache',
    message: 'This will recalculate all routing paths. This may take a few moments. Continue?',
    cancel: true,
  }).onOk(async () => {
    try {
      await routesStore.rebuildCache()
      $q.notify({
        type: 'positive',
        message: 'Route cache rebuilt successfully',
      })
    } catch (error: any) {
      $q.notify({
        type: 'negative',
        message: error.message || 'Failed to rebuild cache',
      })
    }
  })
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString()
}
</script>
