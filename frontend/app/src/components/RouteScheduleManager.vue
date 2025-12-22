<template>
  <div class="route-schedule-manager">
    <q-card>
      <q-card-section>
        <div class="row items-center justify-between">
          <div class="text-h6">Scheduled Times</div>
          <q-btn
            color="primary"
            icon="add"
            label="Add Time"
            @click="showAddDialog = true"
          />
        </div>
      </q-card-section>

      <q-separator />

      <q-card-section v-if="schedules.length === 0">
        <div class="text-center text-grey-6">
          No scheduled times. Add times for when this route runs.
        </div>
      </q-card-section>

      <q-list v-else separator>
        <q-item v-for="schedule in sortedSchedules" :key="schedule.id">
          <q-item-section avatar>
            <q-icon name="schedule" color="primary" />
          </q-item-section>

          <q-item-section>
            <q-item-label class="text-h6">
              {{ formatTime(schedule.scheduled_time) }}
            </q-item-label>
            <q-item-label caption>
              {{ schedule.is_active ? 'Active' : 'Inactive' }}
            </q-item-label>
          </q-item-section>

          <q-item-section side>
            <q-btn
              flat
              round
              dense
              icon="delete"
              color="negative"
              @click="confirmDelete(schedule)"
            />
          </q-item-section>
        </q-item>
      </q-list>

      <q-card-section v-if="schedules.length > 0">
        <div class="text-caption text-grey-6 text-center">
          {{ schedules.length }} run{{ schedules.length === 1 ? '' : 's' }} per day
        </div>
      </q-card-section>
    </q-card>

    <!-- Add Time Dialog -->
    <q-dialog v-model="showAddDialog">
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Add Scheduled Time</div>
        </q-card-section>

        <q-separator />

        <q-card-section>
          <q-input
            v-model="newTime"
            type="time"
            label="Time"
            filled
            :rules="[val => !!val || 'Time is required']"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeDialog" />
          <q-btn
            flat
            label="Add"
            color="primary"
            @click="addSchedule"
            :loading="loading"
            :disable="!newTime"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoutesStore } from 'src/stores/routes'
import { useQuasar } from 'quasar'

interface Props {
  routeId: number
  initialSchedules: any[]
}

const props = defineProps<Props>()
const routesStore = useRoutesStore()
const $q = useQuasar()

const schedules = ref([...props.initialSchedules])
const showAddDialog = ref(false)
const newTime = ref('')
const loading = ref(false)

const sortedSchedules = computed(() => {
  return [...schedules.value].sort((a, b) => {
    return a.scheduled_time.localeCompare(b.scheduled_time)
  })
})

function formatTime(time: string) {
  // Convert 24h time to 12h format
  const [hours, minutes] = time.split(':')
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minutes} ${ampm}`
}

async function addSchedule() {
  loading.value = true
  try {
    await routesStore.addSchedule(props.routeId, newTime.value)
    await refreshSchedules()
    closeDialog()
  } finally {
    loading.value = false
  }
}

function confirmDelete(schedule: any) {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Remove ${formatTime(schedule.scheduled_time)} from schedule?`,
    cancel: true,
  }).onOk(async () => {
    await routesStore.removeSchedule(props.routeId, schedule.id)
    await refreshSchedules()
  })
}

async function refreshSchedules() {
  const route = await routesStore.fetchRoute(props.routeId)
  schedules.value = route.schedules || []
}

function closeDialog() {
  showAddDialog.value = false
  newTime.value = ''
}
</script>
