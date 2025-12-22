<template>
  <q-page padding>
    <div class="q-mb-md">
      <div class="text-h5">My Runs</div>
      <div class="text-caption text-grey-6">{{ formatDate(selectedDate) }}</div>
    </div>

    <div class="q-mb-md">
      <q-input
        v-model="selectedDate"
        type="date"
        filled
        dense
        label="Select Date"
      />
    </div>

    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="40px" />
    </div>

    <div v-else-if="myRuns.length === 0" class="text-center text-grey-6 q-py-md">
      <q-icon name="event_busy" size="64px" />
      <div class="q-mt-md">No runs assigned for this date</div>
    </div>

    <div v-else class="q-gutter-md">
      <q-card
        v-for="run in myRuns"
        :key="run.id"
        :class="getRunCardClass(run)"
        @click="selectRun(run)"
      >
        <q-card-section>
          <div class="row items-center justify-between">
            <div>
              <div class="text-h6">{{ run.route?.name }}</div>
              <div class="text-caption text-grey-6">
                Scheduled: {{ formatTime(run.scheduled_time) }}
              </div>
            </div>
            <q-badge
              :color="getStatusColor(run.status)"
              :label="formatStatus(run.status)"
            />
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section>
          <div class="text-body2">
            <div v-if="run.actual_start_at">
              <strong>Started:</strong> {{ formatDateTime(run.actual_start_at) }}
            </div>
            <div v-if="run.actual_end_at">
              <strong>Completed:</strong> {{ formatDateTime(run.actual_end_at) }}
            </div>
            <div v-if="run.current_stop">
              <strong>Current Stop:</strong> {{ run.current_stop.location?.name }}
            </div>
          </div>
        </q-card-section>

        <q-card-actions>
          <q-btn
            v-if="run.status === 'pending'"
            flat
            color="positive"
            icon="play_arrow"
            label="Start Run"
            @click.stop="startRun(run)"
          />
          <q-btn
            v-if="run.status === 'in_progress'"
            flat
            color="primary"
            icon="visibility"
            label="View Progress"
            @click.stop="selectRun(run)"
          />
          <q-btn
            v-if="run.status === 'in_progress' && canCompleteRun(run)"
            flat
            color="positive"
            icon="check_circle"
            label="Complete Run"
            @click.stop="completeRun(run)"
          />
        </q-card-actions>
      </q-card>
    </div>

    <!-- Run Detail Dialog -->
    <q-dialog v-model="showRunDetail" full-width full-height>
      <q-card>
        <q-card-section class="row items-center">
          <div class="text-h6">{{ selectedRun?.route?.name }}</div>
          <q-space />
          <q-btn flat round dense icon="close" @click="showRunDetail = false" />
        </q-card-section>

        <q-separator />

        <q-card-section class="scroll" style="max-height: calc(100vh - 150px)">
          <RunStopProgress
            v-if="selectedRun"
            :run="selectedRun"
            @refresh="refreshRuns"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn
            v-if="selectedRun?.status === 'in_progress' && canCompleteRun(selectedRun)"
            color="positive"
            icon="check_circle"
            label="Complete Run"
            @click="completeRun(selectedRun)"
          />
          <q-btn flat label="Close" @click="showRunDetail = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRunsStore } from 'src/stores/runs'
import { useQuasar } from 'quasar'
import RunStopProgress from 'src/components/RunStopProgress.vue'
import type { RunInstance } from 'src/types/runs'

const runsStore = useRunsStore()
const $q = useQuasar()

const loading = ref(false)
const selectedDate = ref(new Date().toISOString().split('T')[0])
const showRunDetail = ref(false)
const selectedRun = ref<RunInstance | null>(null)

const myRuns = computed(() => runsStore.myRuns)

onMounted(async () => {
  await loadRuns()
})

watch(selectedDate, async () => {
  await loadRuns()
})

async function loadRuns() {
  loading.value = true
  try {
    await runsStore.fetchMyRuns(selectedDate.value)
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.message || 'Failed to load runs',
    })
  } finally {
    loading.value = false
  }
}

async function refreshRuns() {
  await loadRuns()
  if (selectedRun.value) {
    // Refresh selected run details
    const updated = myRuns.value.find(r => r.id === selectedRun.value!.id)
    if (updated) {
      selectedRun.value = updated
    }
  }
}

function selectRun(run: RunInstance) {
  selectedRun.value = run
  showRunDetail.value = true
}

async function startRun(run: RunInstance) {
  $q.dialog({
    title: 'Start Run',
    message: `Start "${run.route?.name}" run?`,
    cancel: true,
  }).onOk(async () => {
    try {
      await runsStore.startRun(run.id)
      $q.notify({
        type: 'positive',
        message: 'Run started',
      })
      await refreshRuns()
    } catch (error: any) {
      $q.notify({
        type: 'negative',
        message: error.message || 'Failed to start run',
      })
    }
  })
}

function canCompleteRun(run: RunInstance): boolean {
  // Check if all stops have been completed
  if (!run.stop_actuals || run.stop_actuals.length === 0) return false
  return run.stop_actuals.every(actual => actual.departed_at !== null)
}

async function completeRun(run: RunInstance) {
  // Check if there are incomplete tasks
  const incompleteTasks = run.stop_actuals?.some(
    actual => actual.tasks_completed < actual.tasks_total
  )

  if (incompleteTasks) {
    $q.dialog({
      title: 'Incomplete Tasks',
      message: 'Some stops have incomplete tasks. Are you sure you want to complete this run?',
      cancel: true,
    }).onOk(async () => {
      await performCompleteRun(run)
    })
  } else {
    await performCompleteRun(run)
  }
}

async function performCompleteRun(run: RunInstance) {
  try {
    await runsStore.completeRun(run.id)
    $q.notify({
      type: 'positive',
      message: 'Run completed successfully',
    })
    showRunDetail.value = false
    await refreshRuns()
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.message || 'Failed to complete run',
    })
  }
}

function getRunCardClass(run: RunInstance): string {
  if (run.status === 'in_progress') return 'border-primary'
  if (run.status === 'completed') return 'bg-grey-2'
  return ''
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'pending':
      return 'orange'
    case 'in_progress':
      return 'blue'
    case 'completed':
      return 'positive'
    case 'canceled':
      return 'negative'
    default:
      return 'grey'
  }
}

function formatStatus(status: string): string {
  return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function formatTime(timeString: string): string {
  const [hours, minutes] = timeString.split(':')
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minutes} ${ampm}`
}

function formatDateTime(dateTimeString: string): string {
  return new Date(dateTimeString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}
</script>

<style scoped>
.border-primary {
  border-left: 4px solid var(--q-primary);
}
</style>
