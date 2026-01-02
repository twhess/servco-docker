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
            @click="openAddDialog"
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
            <q-item-label class="text-weight-medium">
              {{ schedule.name || 'Unnamed' }}
            </q-item-label>
            <q-item-label caption>
              {{ formatTime(schedule.scheduled_time) }} - {{ getDaysDescription(schedule.days_of_week) }}
              <q-badge
                v-if="!schedule.is_active"
                color="grey"
                label="Inactive"
                class="q-ml-sm"
              />
            </q-item-label>
          </q-item-section>

          <q-item-section side>
            <div class="row q-gutter-xs">
              <q-btn
                flat
                round
                dense
                icon="edit"
                color="primary"
                @click="openEditDialog(schedule)"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                @click="confirmDelete(schedule)"
              />
            </div>
          </q-item-section>
        </q-item>
      </q-list>

      <q-card-section v-if="schedules.length > 0">
        <div class="text-caption text-grey-6 text-center">
          {{ schedules.length }} run{{ schedules.length === 1 ? '' : 's' }} per day
        </div>
      </q-card-section>
    </q-card>

    <!-- Add/Edit Time Dialog -->
    <q-dialog v-model="showDialog">
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">{{ isEditing ? 'Edit Scheduled Time' : 'Add Scheduled Time' }}</div>
        </q-card-section>

        <q-separator />

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="formData.name"
            label="Name"
            filled
            hint="e.g., Morning, Afternoon, Express"
            placeholder="Morning"
          />

          <q-input
            v-model="formData.time"
            type="time"
            label="Time"
            filled
            :rules="[val => !!val || 'Time is required']"
          />

          <div>
            <div class="text-subtitle2 q-mb-sm">Days of Week</div>
            <div class="row q-gutter-sm">
              <q-checkbox
                v-for="day in DAY_OPTIONS"
                :key="day.value"
                v-model="formData.daysOfWeek"
                :val="day.value"
                :label="day.label"
                dense
              />
            </div>
            <div class="text-caption text-grey-6 q-mt-xs">
              {{ getDaysDescription(formData.daysOfWeek) }}
            </div>
          </div>

          <q-toggle
            v-if="isEditing"
            v-model="formData.isActive"
            label="Active"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeDialog" />
          <q-btn
            flat
            :label="isEditing ? 'Save' : 'Add'"
            color="primary"
            @click="saveSchedule"
            :loading="loading"
            :disable="!formData.time"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useRoutesStore } from 'src/stores/routes'
import { useQuasar } from 'quasar'

interface Schedule {
  id: number
  scheduled_time: string
  name?: string | null
  is_active: boolean
  days_of_week?: number[]
}

const DAY_OPTIONS = [
  { value: 0, label: 'Sun' },
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
]

const DEFAULT_WEEKDAYS = [1, 2, 3, 4, 5]

interface Props {
  routeId: number
  initialSchedules: Schedule[]
}

const props = defineProps<Props>()
const routesStore = useRoutesStore()
const $q = useQuasar()

const schedules = ref<Schedule[]>([...props.initialSchedules])
const showDialog = ref(false)
const loading = ref(false)
const isEditing = ref(false)
const editingScheduleId = ref<number | null>(null)

const formData = reactive({
  name: '',
  time: '',
  isActive: true,
  daysOfWeek: [...DEFAULT_WEEKDAYS] as number[],
})

const sortedSchedules = computed(() => {
  return [...schedules.value].sort((a, b) => {
    return a.scheduled_time.localeCompare(b.scheduled_time)
  })
})

function formatTime(time: string | undefined) {
  if (!time) return ''
  // Convert 24h time to 12h format
  const [hours, minutes] = time.split(':')
  if (!hours || !minutes) return time
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minutes} ${ampm}`
}

function getDaysDescription(days: number[] | undefined): string {
  const d = days ?? DEFAULT_WEEKDAYS
  const sorted = [...d].sort((a, b) => a - b)

  // Check for common patterns
  if (JSON.stringify(sorted) === JSON.stringify([1, 2, 3, 4, 5])) {
    return 'Weekdays'
  }
  if (JSON.stringify(sorted) === JSON.stringify([0, 6])) {
    return 'Weekends'
  }
  if (JSON.stringify(sorted) === JSON.stringify([0, 1, 2, 3, 4, 5, 6])) {
    return 'Every day'
  }

  // Otherwise list the days
  const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
  return sorted.map((day) => dayLabels[day]).join(', ')
}

function openAddDialog() {
  isEditing.value = false
  editingScheduleId.value = null
  formData.name = ''
  formData.time = ''
  formData.isActive = true
  formData.daysOfWeek = [...DEFAULT_WEEKDAYS]
  showDialog.value = true
}

function openEditDialog(schedule: Schedule) {
  isEditing.value = true
  editingScheduleId.value = schedule.id
  formData.name = schedule.name || ''
  formData.time = schedule.scheduled_time.substring(0, 5) // Get HH:mm
  formData.isActive = schedule.is_active
  formData.daysOfWeek = schedule.days_of_week ? [...schedule.days_of_week] : [...DEFAULT_WEEKDAYS]
  showDialog.value = true
}

async function saveSchedule() {
  loading.value = true
  try {
    if (isEditing.value && editingScheduleId.value) {
      const updateData: { scheduled_time: string; name?: string; is_active: boolean; days_of_week: number[] } = {
        scheduled_time: formData.time,
        is_active: formData.isActive,
        days_of_week: formData.daysOfWeek,
      }
      if (formData.name) {
        updateData.name = formData.name
      }
      await routesStore.updateSchedule(props.routeId, editingScheduleId.value, updateData)
      $q.notify({
        type: 'positive',
        message: 'Schedule updated successfully',
      })
    } else {
      const scheduleName = formData.name || undefined
      await routesStore.addSchedule(props.routeId, formData.time, scheduleName, formData.daysOfWeek)
      $q.notify({
        type: 'positive',
        message: 'Schedule added successfully',
      })
    }
    await refreshSchedules()
    closeDialog()
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : 'Failed to save schedule'
    $q.notify({
      type: 'negative',
      message,
    })
  } finally {
    loading.value = false
  }
}

function confirmDelete(schedule: Schedule) {
  $q.dialog({
    title: 'Confirm Delete',
    message: `Remove "${schedule.name || formatTime(schedule.scheduled_time)}" from schedule?`,
    cancel: true,
  }).onOk(() => {
    void (async () => {
      try {
        await routesStore.removeSchedule(props.routeId, schedule.id)
        await refreshSchedules()
        $q.notify({
          type: 'positive',
          message: 'Schedule removed successfully',
        })
      } catch (error: unknown) {
        const message = error instanceof Error ? error.message : 'Failed to remove schedule'
        $q.notify({
          type: 'negative',
          message,
        })
      }
    })()
  })
}

async function refreshSchedules() {
  const route = await routesStore.fetchRoute(props.routeId)
  if (route) {
    schedules.value = route.schedules || []
  }
}

function closeDialog() {
  showDialog.value = false
  formData.name = ''
  formData.time = ''
  formData.isActive = true
  formData.daysOfWeek = [...DEFAULT_WEEKDAYS]
  isEditing.value = false
  editingScheduleId.value = null
}
</script>
