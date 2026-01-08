<template>
  <q-page padding>
    <div class="q-mb-md row items-center justify-between">
      <div>
        <div class="text-h5">Runs Dashboard</div>
        <div class="text-caption text-grey-6">{{ formatDate(selectedDate) }}</div>
      </div>
      <div class="row q-gutter-sm items-center">
        <q-btn
          color="primary"
          icon="add"
          label="On-Demand Run"
          @click="openOnDemandDialog"
        />
        <q-input
          v-model="selectedDate"
          type="date"
          filled
          dense
          style="width: 180px"
        />
      </div>
    </div>

    <div v-if="loading" class="text-center q-py-xl">
      <q-spinner color="primary" size="50px" />
      <div class="q-mt-md text-grey-6">Loading runs...</div>
    </div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-6 col-md-3">
          <q-card class="bg-orange-1">
            <q-card-section class="text-center">
              <div class="text-h4 text-orange">{{ pendingRuns.length }}</div>
              <div class="text-caption text-grey-7">Pending</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-6 col-md-3">
          <q-card class="bg-blue-1">
            <q-card-section class="text-center">
              <div class="text-h4 text-blue">{{ inProgressRuns.length }}</div>
              <div class="text-caption text-grey-7">In Progress</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-6 col-md-3">
          <q-card class="bg-green-1">
            <q-card-section class="text-center">
              <div class="text-h4 text-green">{{ completedRuns.length }}</div>
              <div class="text-caption text-grey-7">Completed</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-6 col-md-3">
          <q-card class="bg-grey-2">
            <q-card-section class="text-center">
              <div class="text-h4 text-grey-7">{{ runs.length }}</div>
              <div class="text-caption text-grey-7">Total</div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Runs Table -->
      <q-card>
        <q-card-section class="q-pa-none">
          <q-table
            :rows="runs"
            :columns="columns"
            row-key="id"
            flat
            :loading="loading"
            :pagination="{ rowsPerPage: 20 }"
            :no-data-label="'No runs scheduled for this date'"
            class="clickable-rows"
            @row-click="onRowClick"
          >
            <!-- Route Name -->
            <template #body-cell-route="props">
              <q-td :props="props">
                <div class="text-weight-medium">{{ props.row.display_name || props.row.route?.name || 'Unknown Route' }}</div>
                <div class="text-caption text-grey-6">{{ props.row.route?.code }}</div>
              </q-td>
            </template>

            <!-- Scheduled Time -->
            <template #body-cell-scheduled_time="props">
              <q-td :props="props">
                {{ formatTime(props.row.scheduled_time) }}
              </q-td>
            </template>

            <!-- Assigned Runner -->
            <template #body-cell-runner="props">
              <q-td :props="props">
                <template v-if="props.row.assigned_runner">
                  <div class="row items-center no-wrap">
                    <q-avatar size="28px" color="primary" text-color="white" class="q-mr-sm">
                      {{ getInitials(props.row.assigned_runner.name) }}
                    </q-avatar>
                    <span>{{ props.row.assigned_runner.name }}</span>
                  </div>
                </template>
                <span v-else class="text-grey-5 text-italic">Unassigned</span>
              </q-td>
            </template>

            <!-- Status -->
            <template #body-cell-status="props">
              <q-td :props="props">
                <q-badge
                  :color="getStatusColor(props.row.status)"
                  :label="formatStatus(props.row.status)"
                />
              </q-td>
            </template>

            <!-- Started -->
            <template #body-cell-started="props">
              <q-td :props="props">
                <template v-if="props.row.actual_start_at">
                  <q-icon name="check_circle" color="green" size="xs" class="q-mr-xs" />
                  {{ formatTime(props.row.actual_start_at) }}
                </template>
                <span v-else class="text-grey-5">-</span>
              </q-td>
            </template>

            <!-- Last Stop -->
            <template #body-cell-last_stop="props">
              <q-td :props="props">
                <template v-if="getLastStop(props.row)">
                  <div>{{ getLastStop(props.row)?.name }}</div>
                  <div class="text-caption text-grey-6">
                    {{ getLastStopTime(props.row) }}
                  </div>
                </template>
                <span v-else-if="props.row.status === 'pending'" class="text-grey-5">Not started</span>
                <span v-else class="text-grey-5">-</span>
              </q-td>
            </template>

            <!-- Progress (pickup/delivered counts) -->
            <template #body-cell-progress="props">
              <q-td :props="props">
                <div v-if="getRequestCount(props.row) > 0" class="row items-center no-wrap" style="min-width: 160px">
                  <div class="col">
                    <div class="row items-center q-gutter-xs q-mb-xs">
                      <span class="text-caption text-grey-7">
                        Picked: {{ getPickedUpCount(props.row) }}/{{ getRequestCount(props.row) }}
                      </span>
                      <span class="text-caption text-grey-5">|</span>
                      <span class="text-caption text-grey-7">
                        Delivered: {{ getDeliveredCount(props.row) }}/{{ getRequestCount(props.row) }}
                      </span>
                    </div>
                    <q-linear-progress
                      :value="getPickedUpCount(props.row) / Math.max(getRequestCount(props.row), 1)"
                      :color="props.row.status === 'completed' ? 'green' : 'blue'"
                      rounded
                      size="6px"
                    />
                  </div>
                  <q-icon
                    v-if="props.row.status === 'completed'"
                    name="check_circle"
                    color="green"
                    size="sm"
                    class="q-ml-sm"
                  />
                </div>
                <span v-else class="text-grey-5">No items</span>
              </q-td>
            </template>

            <!-- Actions -->
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn
                  v-if="props.row.status === 'pending' || props.row.status === 'in_progress'"
                  flat
                  round
                  dense
                  icon="person_add"
                  color="secondary"
                  @click.stop="openAssignRunnerDialog(props.row)"
                >
                  <q-tooltip>{{ props.row.assigned_runner ? 'Change Runner' : 'Assign Runner' }}</q-tooltip>
                </q-btn>
                <q-btn
                  v-if="props.row.status === 'pending' && canMergeRun(props.row)"
                  flat
                  round
                  dense
                  icon="merge"
                  color="orange"
                  @click.stop="openMergeDialog(props.row)"
                >
                  <q-tooltip>Merge with another run</q-tooltip>
                </q-btn>
              </q-td>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </template>

    <!-- Run Detail Dialog -->
    <q-dialog v-model="showRunDetail" full-width full-height>
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div>
            <div class="text-h6">{{ selectedRun?.display_name || selectedRun?.route?.name }}</div>
            <div class="text-caption text-grey-6">
              {{ formatDate(selectedRun?.scheduled_date || '') }} at {{ formatTime(selectedRun?.scheduled_time || '') }}
            </div>
          </div>
          <q-space />
          <q-badge
            v-if="selectedRun"
            :color="getStatusColor(selectedRun.status)"
            :label="formatStatus(selectedRun.status)"
            class="q-mr-md"
          />
          <!-- Action buttons for pending/in_progress runs -->
          <q-btn
            v-if="selectedRun?.status === 'pending' || selectedRun?.status === 'in_progress'"
            flat
            round
            dense
            icon="person_add"
            color="secondary"
            class="q-mr-xs"
            @click="openAssignRunnerDialogFromDetail"
          >
            <q-tooltip>{{ selectedRun?.assigned_runner ? 'Change Runner' : 'Assign Runner' }}</q-tooltip>
          </q-btn>
          <q-btn
            v-if="selectedRun?.status === 'pending' && selectedRun && canMergeRun(selectedRun)"
            flat
            round
            dense
            icon="merge"
            color="orange"
            class="q-mr-xs"
            @click="openMergeDialogFromDetail"
          >
            <q-tooltip>Merge with another run</q-tooltip>
          </q-btn>
          <q-btn flat round dense icon="close" @click="showRunDetail = false" />
        </q-card-section>

        <q-separator class="q-mt-md" />

        <q-card-section class="scroll" style="max-height: calc(100vh - 150px)">
          <div v-if="selectedRun" class="q-gutter-md">
            <!-- Run Info -->
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-card flat bordered>
                  <q-card-section>
                    <div class="text-subtitle2 text-grey-7 q-mb-sm">Runner</div>
                    <div v-if="selectedRun.assigned_runner" class="row items-center">
                      <q-avatar size="36px" color="primary" text-color="white" class="q-mr-sm">
                        {{ getInitials(selectedRun.assigned_runner.name) }}
                      </q-avatar>
                      <div>
                        <div class="text-weight-medium">{{ selectedRun.assigned_runner.name }}</div>
                        <div class="text-caption text-grey-6">{{ selectedRun.assigned_runner.email }}</div>
                      </div>
                    </div>
                    <div v-else class="text-grey-5 text-italic">No runner assigned</div>
                  </q-card-section>
                </q-card>
              </div>
              <div class="col-12 col-md-6">
                <q-card flat bordered>
                  <q-card-section>
                    <div class="text-subtitle2 text-grey-7 q-mb-sm">Timing</div>
                    <div class="row q-col-gutter-sm">
                      <div class="col-6">
                        <div class="text-caption text-grey-6">Scheduled</div>
                        <div>{{ formatTime(selectedRun.scheduled_time) }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-caption text-grey-6">Started</div>
                        <div>{{ selectedRun.actual_start_at ? formatTime(selectedRun.actual_start_at) : '-' }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-caption text-grey-6">Completed</div>
                        <div>{{ selectedRun.actual_end_at ? formatTime(selectedRun.actual_end_at) : '-' }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-caption text-grey-6">Current Stop</div>
                        <div>{{ selectedRun.current_stop?.location?.name || '-' }}</div>
                      </div>
                    </div>
                  </q-card-section>
                </q-card>
              </div>
            </div>

            <!-- Stops Timeline -->
            <q-card flat bordered>
              <q-card-section>
                <div class="text-subtitle2 text-grey-7 q-mb-md">Stops Progress</div>
                <div v-if="selectedRun.stop_actuals?.length" class="q-gutter-sm">
                  <div
                    v-for="actual in selectedRun.stop_actuals"
                    :key="actual.id"
                    class="row items-center q-pa-sm rounded-borders"
                    :class="getStopClass(actual)"
                  >
                    <q-icon
                      :name="getStopIcon(actual)"
                      :color="getStopIconColor(actual)"
                      size="sm"
                      class="q-mr-sm"
                    />
                    <div class="col">
                      <div class="text-weight-medium">{{ actual.route_stop?.location?.name || 'Unknown' }}</div>
                      <div class="text-caption text-grey-6">
                        <span v-if="actual.arrived_at">Arrived: {{ formatTime(actual.arrived_at) }}</span>
                        <span v-if="actual.departed_at"> | Departed: {{ formatTime(actual.departed_at) }}</span>
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="text-caption">
                        {{ actual.tasks_completed }}/{{ actual.tasks_total }} tasks
                      </div>
                      <q-linear-progress
                        :value="actual.tasks_total > 0 ? actual.tasks_completed / actual.tasks_total : 0"
                        :color="actual.tasks_completed === actual.tasks_total ? 'green' : 'orange'"
                        style="width: 80px"
                        rounded
                      />
                    </div>
                  </div>
                </div>
                <div v-else class="text-grey-5 text-center q-py-md">
                  Run has not started yet
                </div>
              </q-card-section>
            </q-card>

            <!-- All Requests on this Run -->
            <q-card flat bordered>
              <q-card-section>
                <div class="row items-center q-mb-md">
                  <div class="text-subtitle2 text-grey-7">
                    All Requests ({{ selectedRun.requests?.length || 0 }})
                  </div>
                  <q-space />
                  <div class="text-caption">
                    <span class="text-blue">Picked: {{ getPickedUpCount(selectedRun) }}</span>
                    <span class="text-grey-5 q-mx-xs">|</span>
                    <span class="text-green">Delivered: {{ getDeliveredCount(selectedRun) }}</span>
                  </div>
                </div>

                <q-table
                  v-if="selectedRun.requests?.length"
                  flat
                  dense
                  :rows="selectedRun.requests"
                  :columns="requestColumns"
                  row-key="id"
                  :pagination="{ rowsPerPage: 10 }"
                  :row-class="getRequestRowClass"
                  class="run-requests-table"
                >
                  <!-- Reference Number -->
                  <template #body-cell-reference_number="props">
                    <q-td :props="props">
                      <div class="text-weight-medium text-primary">
                        {{ props.row.reference_number }}
                      </div>
                    </q-td>
                  </template>

                  <!-- Type -->
                  <template #body-cell-type="props">
                    <q-td :props="props">
                      <q-badge :color="getRequestTypeColor(props.row.request_type?.name)">
                        {{ formatRequestType(props.row.request_type?.name) }}
                      </q-badge>
                    </q-td>
                  </template>

                  <!-- From / To -->
                  <template #body-cell-from_to="props">
                    <q-td :props="props">
                      <div class="row items-center no-wrap">
                        <span>{{ getRequestOrigin(props.row) }}</span>
                        <q-icon name="arrow_forward" size="xs" class="q-mx-xs text-grey-5" />
                        <span>{{ getRequestDestination(props.row) }}</span>
                      </div>
                    </q-td>
                  </template>

                  <!-- Details -->
                  <template #body-cell-details="props">
                    <q-td :props="props">
                      <div class="ellipsis" style="max-width: 200px">
                        {{ props.row.details || '-' }}
                      </div>
                    </q-td>
                  </template>

                  <!-- Urgency -->
                  <template #body-cell-urgency="props">
                    <q-td :props="props">
                      <q-badge :color="getUrgencyColor(props.row.urgency?.name)">
                        {{ formatUrgency(props.row.urgency?.name) }}
                      </q-badge>
                    </q-td>
                  </template>

                  <!-- Status -->
                  <template #body-cell-status="props">
                    <q-td :props="props">
                      <q-badge :color="getRequestStatusColor(props.row.status?.name)">
                        {{ formatRequestStatus(props.row.status?.name) }}
                      </q-badge>
                    </q-td>
                  </template>
                </q-table>

                <div v-else class="text-grey-5 text-center q-py-md">
                  No requests assigned to this run
                </div>
              </q-card-section>
            </q-card>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Close" @click="showRunDetail = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- On-Demand Run Dialog -->
    <q-dialog v-model="showOnDemandDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Create On-Demand Run</div>
          <div class="text-caption text-grey-6">Create a run without a fixed schedule</div>
        </q-card-section>

        <q-separator />

        <q-card-section class="q-gutter-md">
          <q-select
            v-model="onDemandForm.route_id"
            :options="routeOptions"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            label="Route"
            filled
            :rules="[val => !!val || 'Route is required']"
          />

          <q-input
            v-model="onDemandForm.date"
            type="date"
            label="Date"
            filled
            :rules="[val => !!val || 'Date is required']"
          />

          <q-input
            v-model="onDemandForm.time"
            type="time"
            label="Time"
            filled
            :rules="[val => !!val || 'Time is required']"
          />

          <q-select
            v-model="onDemandForm.assigned_runner_user_id"
            :options="runnerOptions"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            label="Assign Runner (Optional)"
            filled
            clearable
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="showOnDemandDialog = false" />
          <q-btn
            flat
            label="Create"
            color="primary"
            @click="createOnDemandRun"
            :loading="creatingRun"
            :disable="!onDemandForm.route_id || !onDemandForm.date || !onDemandForm.time"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Merge Runs Dialog -->
    <q-dialog v-model="showMergeDialog">
      <q-card style="min-width: 450px">
        <q-card-section>
          <div class="text-h6">Merge Runs</div>
          <div class="text-caption text-grey-6">
            Merge "{{ mergeSourceRun?.display_name || mergeSourceRun?.route?.name }}" into another run
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section class="q-gutter-md">
          <div class="text-subtitle2">Select Target Run</div>
          <div class="text-caption text-grey-6 q-mb-sm">
            The source run will be canceled and all its requests will be moved to the target run.
          </div>

          <q-select
            v-model="mergeForm.target_run_id"
            :options="availableMergeTargets"
            option-value="id"
            option-label="label"
            emit-value
            map-options
            label="Target Run"
            filled
            :rules="[val => !!val || 'Target run is required']"
          />

          <div v-if="showRunnerChoice" class="q-mt-md">
            <div class="text-subtitle2 q-mb-sm">Select Runner</div>
            <div class="text-caption text-grey-6 q-mb-sm">
              The runs have different runners assigned. Choose which one to keep.
            </div>
            <q-option-group
              v-model="mergeForm.keep_runner"
              :options="runnerChoiceOptions"
              color="primary"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="showMergeDialog = false" />
          <q-btn
            flat
            label="Merge"
            color="orange"
            @click="mergeRuns"
            :loading="mergingRuns"
            :disable="!mergeForm.target_run_id"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Assign Runner Dialog -->
    <q-dialog v-model="showAssignRunnerDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">
            {{ assignRunnerRun?.assigned_runner ? 'Change Runner' : 'Assign Runner' }}
          </div>
          <div class="text-caption text-grey-6">
            {{ assignRunnerRun?.display_name || assignRunnerRun?.route?.name }} @ {{ formatTime(assignRunnerRun?.scheduled_time || '') }}
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section>
          <q-select
            v-model="assignRunnerForm.runner_id"
            :options="runnerOptions"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            label="Runner"
            filled
            clearable
            :hint="assignRunnerRun?.assigned_runner ? `Currently: ${assignRunnerRun.assigned_runner.name}` : 'No runner assigned'"
          >
            <template #option="{ itemProps, opt }">
              <q-item v-bind="itemProps">
                <q-item-section avatar>
                  <q-avatar size="32px" color="primary" text-color="white">
                    {{ getInitials(opt.name) }}
                  </q-avatar>
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ opt.name }}</q-item-label>
                </q-item-section>
              </q-item>
            </template>
          </q-select>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn
            v-if="assignRunnerRun?.assigned_runner && assignRunnerRun?.status === 'pending'"
            flat
            label="Unassign"
            color="negative"
            @click="unassignRunner"
            :loading="assigningRunner"
          >
            <q-tooltip>Remove runner from this run</q-tooltip>
          </q-btn>
          <q-space />
          <q-btn flat label="Cancel" @click="showAssignRunnerDialog = false" />
          <q-btn
            flat
            label="Save"
            color="primary"
            @click="saveRunnerAssignment"
            :loading="assigningRunner"
            :disable="!assignRunnerForm.runner_id"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, reactive } from 'vue'
import { useRunsStore } from 'src/stores/runs'
import { useRoutesStore } from 'src/stores/routes'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import type { RunInstance, RunStopActual } from 'src/types/runs'

interface SimpleUser {
  id: number
  name: string
  active?: boolean
}

const runsStore = useRunsStore()
const routesStore = useRoutesStore()
const $q = useQuasar()
const users = ref<SimpleUser[]>([])

const loading = ref(false)
const selectedDate = ref(new Date().toISOString().split('T')[0] ?? '')
const showRunDetail = ref(false)
const selectedRun = ref<RunInstance | null>(null)

// On-demand run form
const showOnDemandDialog = ref(false)
const creatingRun = ref(false)
const onDemandForm = reactive({
  route_id: null as number | null,
  date: new Date().toISOString().split('T')[0] ?? '',
  time: '',
  assigned_runner_user_id: null as number | null,
})

// Merge runs form
const showMergeDialog = ref(false)
const mergingRuns = ref(false)
const mergeSourceRun = ref<RunInstance | null>(null)
const mergeForm = reactive({
  target_run_id: null as number | null,
  keep_runner: 'target' as 'target' | 'source',
})

// Assign runner form
const showAssignRunnerDialog = ref(false)
const assigningRunner = ref(false)
const assignRunnerRun = ref<RunInstance | null>(null)
const assignRunnerForm = reactive({
  runner_id: null as number | null,
})

// Computed options
const routeOptions = computed(() => routesStore.routes.filter((r) => r.is_active))
const runnerOptions = computed(() => {
  return users.value.filter((u) => u.active !== false)
})

const availableMergeTargets = computed(() => {
  if (!mergeSourceRun.value) return []
  // Can merge into pending or in_progress runs on the same route
  return runs.value
    .filter(
      (r) =>
        r.id !== mergeSourceRun.value?.id &&
        r.route_id === mergeSourceRun.value?.route_id &&
        (r.status === 'pending' || r.status === 'in_progress')
    )
    .map((r) => ({
      id: r.id,
      label: `${r.display_name || r.route?.name} @ ${formatTime(r.scheduled_time)}${r.status === 'in_progress' ? ' (In Progress)' : ''}`,
      run: r,
    }))
})

// Check if a run can be merged (has other pending or in_progress runs on the same route)
function canMergeRun(run: RunInstance): boolean {
  return runs.value.some(
    (r) =>
      r.id !== run.id &&
      r.route_id === run.route_id &&
      (r.status === 'pending' || r.status === 'in_progress')
  )
}

const showRunnerChoice = computed(() => {
  if (!mergeSourceRun.value || !mergeForm.target_run_id) return false
  const targetRun = runs.value.find((r) => r.id === mergeForm.target_run_id)
  if (!targetRun) return false
  return (
    mergeSourceRun.value.assigned_runner_user_id !== targetRun.assigned_runner_user_id &&
    mergeSourceRun.value.assigned_runner_user_id &&
    targetRun.assigned_runner_user_id
  )
})

const runnerChoiceOptions = computed(() => {
  if (!mergeSourceRun.value || !mergeForm.target_run_id) return []
  const targetRun = runs.value.find((r) => r.id === mergeForm.target_run_id)
  return [
    {
      value: 'target',
      label: `Keep ${targetRun?.assigned_runner?.name || 'Unknown'} (Target)`,
    },
    {
      value: 'source',
      label: `Keep ${mergeSourceRun.value?.assigned_runner?.name || 'Unknown'} (Source)`,
    },
  ]
})

const runs = computed(() => runsStore.runs)
const pendingRuns = computed(() => runs.value.filter(r => r.status === 'pending'))
const inProgressRuns = computed(() => runs.value.filter(r => r.status === 'in_progress'))
const completedRuns = computed(() => runs.value.filter(r => r.status === 'completed'))

const columns = [
  { name: 'route', label: 'Route', field: 'route', align: 'left' as const, sortable: true },
  { name: 'scheduled_time', label: 'Scheduled', field: 'scheduled_time', align: 'left' as const, sortable: true },
  { name: 'runner', label: 'Assigned Runner', field: 'assigned_runner', align: 'left' as const },
  { name: 'status', label: 'Status', field: 'status', align: 'center' as const, sortable: true },
  { name: 'started', label: 'Started', field: 'actual_start_at', align: 'left' as const, sortable: true },
  { name: 'last_stop', label: 'Last Stop', field: 'current_stop', align: 'left' as const },
  { name: 'progress', label: 'Progress', field: 'progress', align: 'left' as const },
  { name: 'actions', label: '', field: 'actions', align: 'center' as const },
]

// Request columns for the run detail dialog (similar to PartsRequestsPage but without assigned_run)
const requestColumns = [
  { name: 'reference_number', label: 'Reference #', field: 'reference_number', align: 'left' as const, sortable: true },
  { name: 'type', label: 'Type', field: 'request_type', align: 'left' as const },
  { name: 'from_to', label: 'From / To', field: 'from_to', align: 'left' as const },
  { name: 'details', label: 'Details', field: 'details', align: 'left' as const },
  { name: 'urgency', label: 'Urgency', field: 'urgency', align: 'center' as const },
  { name: 'status', label: 'Status', field: 'status', align: 'center' as const },
]

onMounted(() => {
  void Promise.all([loadRuns(), routesStore.fetchRoutes(), loadUsers()])
})

async function loadUsers() {
  try {
    const response = await api.get('/users', { params: { active: true } })
    users.value = response.data
  } catch (error) {
    console.error('Failed to load users', error)
  }
}

watch(selectedDate, async () => {
  await loadRuns()
})

function onRowClick(_evt: Event, row: RunInstance) {
  void viewRunDetails(row)
}

async function loadRuns() {
  loading.value = true
  try {
    const dateFilter = selectedDate.value || undefined
    await runsStore.fetchRuns(dateFilter ? { date: dateFilter } : {})
  } catch (error: unknown) {
    const err = error as { message?: string }
    $q.notify({
      type: 'negative',
      message: err.message || 'Failed to load runs',
    })
  } finally {
    loading.value = false
  }
}

// On-demand run handlers
function openOnDemandDialog() {
  onDemandForm.route_id = null
  onDemandForm.date = selectedDate.value || new Date().toISOString().split('T')[0] || ''
  onDemandForm.time = ''
  onDemandForm.assigned_runner_user_id = null
  showOnDemandDialog.value = true
}

async function createOnDemandRun() {
  if (!onDemandForm.route_id || !onDemandForm.date || !onDemandForm.time) return

  creatingRun.value = true
  try {
    const data: { route_id: number; date: string; time: string; assigned_runner_user_id?: number } = {
      route_id: onDemandForm.route_id,
      date: onDemandForm.date,
      time: onDemandForm.time,
    }
    if (onDemandForm.assigned_runner_user_id) {
      data.assigned_runner_user_id = onDemandForm.assigned_runner_user_id
    }
    await runsStore.createOnDemandRun(data)
    $q.notify({
      type: 'positive',
      message: 'On-demand run created successfully',
    })
    showOnDemandDialog.value = false
    await loadRuns()
  } catch (error: unknown) {
    const err = error as { response?: { data?: { message?: string } } }
    $q.notify({
      type: 'negative',
      message: err.response?.data?.message || 'Failed to create on-demand run',
    })
  } finally {
    creatingRun.value = false
  }
}

// Merge runs handlers
function openMergeDialog(run: RunInstance) {
  mergeSourceRun.value = run
  mergeForm.target_run_id = null
  mergeForm.keep_runner = 'target'
  showMergeDialog.value = true
}

function openMergeDialogFromDetail() {
  if (!selectedRun.value) return
  openMergeDialog(selectedRun.value)
}

async function mergeRuns() {
  if (!mergeSourceRun.value || !mergeForm.target_run_id) return

  mergingRuns.value = true
  try {
    const result = await runsStore.mergeRuns(
      mergeForm.target_run_id,
      mergeSourceRun.value.id,
      mergeForm.keep_runner
    )
    $q.notify({
      type: 'positive',
      message: result.message,
    })
    showMergeDialog.value = false
    await loadRuns()
    // Refresh detail dialog if open (show the target run after merge)
    if (showRunDetail.value && mergeForm.target_run_id) {
      await runsStore.fetchRun(mergeForm.target_run_id)
      selectedRun.value = runsStore.activeRun
    }
  } catch (error: unknown) {
    const err = error as { response?: { data?: { message?: string } } }
    $q.notify({
      type: 'negative',
      message: err.response?.data?.message || 'Failed to merge runs',
    })
  } finally {
    mergingRuns.value = false
  }
}

// Assign runner handlers
function openAssignRunnerDialog(run: RunInstance) {
  assignRunnerRun.value = run
  assignRunnerForm.runner_id = run.assigned_runner_user_id
  showAssignRunnerDialog.value = true
}

function openAssignRunnerDialogFromDetail() {
  if (!selectedRun.value) return
  openAssignRunnerDialog(selectedRun.value)
}

async function saveRunnerAssignment() {
  if (!assignRunnerRun.value || !assignRunnerForm.runner_id) return

  assigningRunner.value = true
  try {
    await runsStore.assignRunner(assignRunnerRun.value.id, assignRunnerForm.runner_id)
    $q.notify({
      type: 'positive',
      message: 'Runner assigned successfully',
    })
    showAssignRunnerDialog.value = false
    await loadRuns()
    // Refresh detail dialog if open
    if (showRunDetail.value && selectedRun.value?.id === assignRunnerRun.value.id) {
      await runsStore.fetchRun(assignRunnerRun.value.id)
      selectedRun.value = runsStore.activeRun
    }
  } catch (error: unknown) {
    const err = error as { response?: { data?: { message?: string } } }
    $q.notify({
      type: 'negative',
      message: err.response?.data?.message || 'Failed to assign runner',
    })
  } finally {
    assigningRunner.value = false
  }
}

async function unassignRunner() {
  if (!assignRunnerRun.value) return

  assigningRunner.value = true
  try {
    await runsStore.unassignRunner(assignRunnerRun.value.id)
    $q.notify({
      type: 'positive',
      message: 'Runner unassigned successfully',
    })
    showAssignRunnerDialog.value = false
    // These can fail but unassign already succeeded
    try {
      await loadRuns()
      // Refresh detail dialog if open
      if (showRunDetail.value && selectedRun.value?.id === assignRunnerRun.value.id) {
        await runsStore.fetchRun(assignRunnerRun.value.id)
        selectedRun.value = runsStore.activeRun
      }
    } catch (refreshError) {
      console.warn('Failed to refresh runs after unassign:', refreshError)
    }
  } catch (error: unknown) {
    const err = error as { response?: { data?: { message?: string } } }
    $q.notify({
      type: 'negative',
      message: err.response?.data?.message || 'Failed to unassign runner',
    })
  } finally {
    assigningRunner.value = false
  }
}

async function viewRunDetails(run: RunInstance) {
  try {
    await runsStore.fetchRun(run.id)
    selectedRun.value = runsStore.activeRun
    showRunDetail.value = true
  } catch (error: unknown) {
    const err = error as { message?: string }
    $q.notify({
      type: 'negative',
      message: err.message || 'Failed to load run details',
    })
  }
}

function getLastStop(run: RunInstance): { name: string } | null {
  if (!run.stop_actuals || run.stop_actuals.length === 0) return null

  // Find the last stop where the runner arrived or departed
  const stopsWithActivity = run.stop_actuals
    .filter(s => s.arrived_at || s.departed_at)
    .sort((a, b) => {
      const timeA = a.departed_at || a.arrived_at || ''
      const timeB = b.departed_at || b.arrived_at || ''
      return timeB.localeCompare(timeA)
    })

  const lastStop = stopsWithActivity[0]
  if (!lastStop) return null

  return { name: lastStop.route_stop?.location?.name || 'Unknown' }
}

function getLastStopTime(run: RunInstance): string {
  if (!run.stop_actuals || run.stop_actuals.length === 0) return ''

  const stopsWithActivity = run.stop_actuals
    .filter(s => s.arrived_at || s.departed_at)
    .sort((a, b) => {
      const timeA = a.departed_at || a.arrived_at || ''
      const timeB = b.departed_at || b.arrived_at || ''
      return timeB.localeCompare(timeA)
    })

  const lastStop = stopsWithActivity[0]
  if (!lastStop) return ''

  if (lastStop.departed_at) {
    return `Departed ${formatTime(lastStop.departed_at)}`
  } else if (lastStop.arrived_at) {
    return `Arrived ${formatTime(lastStop.arrived_at)}`
  }
  return ''
}

// Note: getProgressPercent and getProgressLabel were removed as they are unused
// They can be re-added if stop-based progress display is needed

// Request progress tracking functions
function getRequestCount(run: RunInstance): number {
  return run.requests?.length ?? 0
}

function getPickedUpCount(run: RunInstance): number {
  const pickedUpStatuses = ['picked_up', 'en_route_dropoff', 'delivered']
  return (run.requests ?? []).filter(r =>
    pickedUpStatuses.includes(r.status?.name ?? '')
  ).length
}

function getDeliveredCount(run: RunInstance): number {
  return (run.requests ?? []).filter(r =>
    r.status?.name === 'delivered'
  ).length
}

interface RequestWithStatus {
  status?: { name?: string }
  vendor?: { name?: string }
  origin_location?: { name?: string }
  receiving_location?: { name?: string }
  customer?: { name?: string }
}

function isRequestCompleted(request: RequestWithStatus): boolean {
  return request.status?.name === 'delivered'
}

// Request table helper functions
function getRequestRowClass(row: RequestWithStatus): string {
  return isRequestCompleted(row) ? 'is-completed' : ''
}

function getRequestOrigin(request: RequestWithStatus): string {
  if (request.vendor?.name) {
    return request.vendor.name
  }
  if (request.origin_location?.name) {
    return request.origin_location.name
  }
  return 'Unknown'
}

function getRequestDestination(request: RequestWithStatus): string {
  if (request.receiving_location?.name) {
    return request.receiving_location.name
  }
  if (request.customer?.name) {
    return request.customer.name
  }
  return 'Unknown'
}

function getRequestTypeColor(typeName: string | undefined): string {
  switch (typeName) {
    case 'pickup': return 'blue'
    case 'delivery': return 'green'
    case 'transfer': return 'purple'
    case 'return': return 'orange'
    default: return 'grey'
  }
}

function formatRequestType(typeName: string | undefined): string {
  if (!typeName) return 'Unknown'
  return typeName.charAt(0).toUpperCase() + typeName.slice(1)
}

function getUrgencyColor(urgencyName: string | undefined): string {
  switch (urgencyName) {
    case 'hot': return 'red'
    case 'rush': return 'orange'
    case 'first_available': return 'blue'
    case 'scheduled': return 'grey'
    default: return 'grey'
  }
}

function formatUrgency(urgencyName: string | undefined): string {
  if (!urgencyName) return 'Normal'
  return urgencyName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function getRequestStatusColor(statusName: string | undefined): string {
  switch (statusName) {
    case 'new': return 'grey'
    case 'assigned': return 'blue-grey'
    case 'confirmed': return 'cyan'
    case 'en_route_pickup': return 'blue'
    case 'picked_up': return 'teal'
    case 'en_route_dropoff': return 'indigo'
    case 'delivered': return 'green'
    case 'canceled': return 'red'
    case 'problem': return 'orange'
    default: return 'grey'
  }
}

function formatRequestStatus(statusName: string | undefined): string {
  if (!statusName) return 'Unknown'
  return statusName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function getStopClass(actual: RunStopActual): string {
  if (actual.departed_at) return 'bg-green-1'
  if (actual.arrived_at) return 'bg-blue-1'
  return 'bg-grey-2'
}

function getStopIcon(actual: RunStopActual): string {
  if (actual.departed_at) return 'check_circle'
  if (actual.arrived_at) return 'location_on'
  return 'radio_button_unchecked'
}

function getStopIconColor(actual: RunStopActual): string {
  if (actual.departed_at) return 'green'
  if (actual.arrived_at) return 'blue'
  return 'grey'
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'pending': return 'orange'
    case 'in_progress': return 'blue'
    case 'completed': return 'positive'
    case 'canceled': return 'negative'
    default: return 'grey'
  }
}

function formatStatus(status: string): string {
  return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function formatDate(dateString: string): string {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function formatTime(timeString: string): string {
  if (!timeString) return ''

  // Handle full datetime strings
  if (timeString.includes('T') || timeString.includes(' ')) {
    return new Date(timeString).toLocaleTimeString('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    })
  }

  // Handle time-only strings (HH:MM:SS)
  const parts = timeString.split(':')
  const hours = parts[0] ?? '0'
  const minutes = parts[1] ?? '00'
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minutes} ${ampm}`
}

function getInitials(name: string): string {
  if (!name) return '?'
  const parts = name.split(' ')
  const firstPart = parts[0]
  const lastPart = parts[parts.length - 1]
  if (parts.length >= 2 && firstPart && lastPart) {
    return `${firstPart[0]}${lastPart[0]}`.toUpperCase()
  }
  return name.charAt(0).toUpperCase()
}
</script>

<style scoped>
/* Clickable table rows */
.clickable-rows :deep(tbody tr) {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.clickable-rows :deep(tbody tr:hover) {
  background-color: rgba(0, 0, 0, 0.04);
}

/* Completed request styling (40% opacity) */
.run-requests-table :deep(tr.is-completed) {
  opacity: 0.4;
  filter: grayscale(30%);
}

.run-requests-table :deep(tr.is-completed:hover) {
  opacity: 0.5;
}
</style>
