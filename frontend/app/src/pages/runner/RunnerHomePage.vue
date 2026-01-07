<template>
  <q-page class="runner-home">
    <q-header class="bg-primary">
      <q-toolbar>
        <q-toolbar-title>Parts Runner</q-toolbar-title>
        <q-btn flat icon="logout" @click="handleLogout" />
      </q-toolbar>
    </q-header>

    <q-pull-to-refresh @refresh="refreshAll">
      <div class="q-pa-md">
        <!-- Welcome Section -->
        <div class="q-mb-md">
          <div class="text-h6">Hello, {{ authStore.userName }}</div>
          <div class="text-caption text-grey-7">
            {{ formattedDate }}
          </div>
        </div>

        <!-- Current Vehicle Card -->
        <q-card
          class="q-mb-md"
          :class="currentVehicle ? 'bg-positive-1' : 'bg-orange-1'"
          flat
          bordered
        >
          <q-card-section class="row items-center q-py-sm">
            <q-icon
              :name="currentVehicle ? 'local_shipping' : 'warning'"
              :color="currentVehicle ? 'positive' : 'orange'"
              size="md"
              class="q-mr-sm"
            />
            <div class="col">
              <div v-if="currentVehicle" class="text-weight-medium">
                {{ currentVehicle.vehicle_name }}
              </div>
              <div v-else class="text-weight-medium text-orange-9">
                No Vehicle Selected
              </div>
              <div class="text-caption text-grey-7">
                {{ currentVehicle ? 'Current vehicle' : 'Select a vehicle to start' }}
              </div>
            </div>
            <q-btn
              :color="currentVehicle ? 'primary' : 'orange'"
              :label="currentVehicle ? 'Change' : 'Select'"
              dense
              outline
              @click="showVehicleDialog = true"
            />
          </q-card-section>
        </q-card>

        <!-- Loading State -->
        <div v-if="runsStore.loading" class="text-center q-py-xl">
          <q-spinner-dots size="40px" color="primary" />
          <div class="q-mt-md text-grey-7">Loading runs...</div>
        </div>

        <template v-else>
          <!-- My Assigned Runs -->
          <div v-if="runsStore.hasAssignedRuns" class="q-mb-lg">
            <div class="text-subtitle1 text-weight-medium q-mb-sm">
              My Runs
            </div>

            <q-list bordered separator class="rounded-borders">
              <q-item
                v-for="run in runsStore.assignedRuns"
                :key="run.id"
                clickable
                @click="selectRun(run.id)"
              >
                <q-item-section avatar>
                  <q-icon
                    :name="getRunIcon(run.status)"
                    :color="getRunColor(run.status)"
                  />
                </q-item-section>

                <q-item-section>
                  <q-item-label>{{ run.display_name }}</q-item-label>
                  <q-item-label caption>
                    {{ run.stop_count }} stops
                    <span v-if="run.scheduled_time">
                      at {{ run.scheduled_time }}
                    </span>
                  </q-item-label>
                </q-item-section>

                <q-item-section side>
                  <div class="text-right">
                    <q-badge
                      :color="run.open_items > 0 ? 'orange' : 'green'"
                      :label="`${run.open_items} open`"
                    />
                    <div class="text-caption q-mt-xs">
                      {{ run.total_items }} total
                    </div>
                  </div>
                </q-item-section>

                <q-item-section side>
                  <q-icon name="chevron_right" color="grey-6" />
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- Available Runs to Claim (only shown if no assigned runs) -->
          <div v-if="!runsStore.hasAssignedRuns && runsStore.hasAvailableRuns" class="q-mb-lg">
            <div class="text-subtitle1 text-weight-medium q-mb-sm">
              Available Runs
            </div>

            <q-list bordered separator class="rounded-borders">
              <q-item
                v-for="run in runsStore.availableRuns"
                :key="run.id"
              >
                <q-item-section avatar>
                  <q-icon name="directions_car" color="grey-6" />
                </q-item-section>

                <q-item-section>
                  <q-item-label>{{ run.display_name }}</q-item-label>
                  <q-item-label caption>
                    {{ run.stop_count }} stops
                    <span v-if="run.scheduled_time">
                      at {{ run.scheduled_time }}
                    </span>
                  </q-item-label>
                </q-item-section>

                <q-item-section side>
                  <q-btn
                    color="primary"
                    label="Claim"
                    dense
                    @click.stop="claimRun(run.id)"
                    :loading="claimingRunId === run.id"
                  />
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- No Runs Message -->
          <div
            v-if="!runsStore.hasAssignedRuns && !runsStore.hasAvailableRuns"
            class="text-center q-py-xl"
          >
            <q-icon name="event_available" size="64px" color="grey-5" />
            <div class="text-h6 text-grey-7 q-mt-md">No runs today</div>
            <div class="text-caption text-grey-6">
              Check back later or contact dispatch
            </div>
          </div>
        </template>

        <!-- Error Message -->
        <q-banner
          v-if="runsStore.error"
          class="bg-negative text-white q-mt-md"
          rounded
        >
          {{ runsStore.error }}
        </q-banner>
      </div>
    </q-pull-to-refresh>

    <!-- Vehicle Selection Dialog -->
    <VehicleSelectDialog
      v-model="showVehicleDialog"
      @selected="onVehicleSelected"
    />
  </q-page>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useRunnerAuthStore } from 'stores/runnerAuth';
import { useRunnerRunsStore } from 'stores/runnerRuns';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';
import VehicleSelectDialog from 'components/runner/VehicleSelectDialog.vue';

interface VehicleSession {
  session_id: number;
  is_generic: boolean;
  vehicle_location_id: number | null;
  vehicle_name: string;
  generic_vehicle_type: string | null;
  generic_vehicle_description: string | null;
  generic_license_plate: string | null;
  started_at: string;
}

const router = useRouter();
const authStore = useRunnerAuthStore();
const runsStore = useRunnerRunsStore();
const $q = useQuasar();

const claimingRunId = ref<number | null>(null);
const showVehicleDialog = ref(false);
const currentVehicle = ref<VehicleSession | null>(null);
const loadingVehicle = ref(false);

const formattedDate = computed(() => {
  const date = new Date(runsStore.selectedDate);
  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  });
});

const getRunIcon = (status: string) => {
  switch (status) {
    case 'in_progress':
      return 'play_circle';
    case 'completed':
      return 'check_circle';
    case 'pending':
    default:
      return 'schedule';
  }
};

const getRunColor = (status: string) => {
  switch (status) {
    case 'in_progress':
      return 'primary';
    case 'completed':
      return 'positive';
    case 'pending':
    default:
      return 'grey-6';
  }
};

const fetchCurrentVehicle = async () => {
  loadingVehicle.value = true;
  try {
    const response = await api.get('/runner/vehicle/current');
    if (response.data.has_vehicle) {
      currentVehicle.value = response.data.vehicle;
    } else {
      currentVehicle.value = null;
    }
  } catch (error) {
    console.error('Failed to fetch current vehicle:', error);
  } finally {
    loadingVehicle.value = false;
  }
};

const onVehicleSelected = (vehicle: { sessionId: number; isGeneric: boolean; vehicleName: string }) => {
  // Refresh the current vehicle display
  fetchCurrentVehicle();
};

const selectRun = async (runId: number) => {
  // Require vehicle selection before accessing a run
  if (!currentVehicle.value) {
    $q.notify({
      type: 'warning',
      message: 'Please select a vehicle first',
      position: 'top',
    });
    showVehicleDialog.value = true;
    return;
  }
  await router.push(`/runner/run/${runId}`);
};

const claimRun = async (runId: number) => {
  // Require vehicle selection before claiming
  if (!currentVehicle.value) {
    $q.notify({
      type: 'warning',
      message: 'Please select a vehicle first',
      position: 'top',
    });
    showVehicleDialog.value = true;
    return;
  }

  claimingRunId.value = runId;

  const result = await runsStore.claimRun(runId);

  claimingRunId.value = null;

  if (result.success) {
    $q.notify({
      type: 'positive',
      message: 'Run claimed successfully!',
      position: 'top',
    });
  } else {
    $q.notify({
      type: 'negative',
      message: result.error || 'Failed to claim run',
      position: 'top',
    });
  }
};

const refreshAll = async (done: () => void) => {
  await Promise.all([
    runsStore.fetchRuns(),
    fetchCurrentVehicle(),
  ]);
  done();
};

const handleLogout = async () => {
  // End vehicle session on logout
  if (currentVehicle.value) {
    try {
      await api.post('/runner/vehicle/end-session');
    } catch (error) {
      console.error('Failed to end vehicle session:', error);
    }
  }
  await authStore.logout();
  await router.push('/runner/login');
};

onMounted(async () => {
  // Initialize auth to load user data if we have a token
  await authStore.initializeAuth();
  await Promise.all([
    runsStore.fetchRuns(),
    fetchCurrentVehicle(),
  ]);
});
</script>

<style scoped>
.runner-home {
  background-color: #f5f5f5;
  min-height: 100vh;
}

.q-header {
  position: sticky;
  top: 0;
  z-index: 100;
}

.bg-positive-1 {
  background-color: rgba(76, 175, 80, 0.1);
}

.bg-orange-1 {
  background-color: rgba(255, 152, 0, 0.1);
}
</style>
