<template>
  <q-page class="runner-run">
    <!-- Header -->
    <q-header class="bg-primary">
      <q-toolbar>
        <q-btn flat icon="arrow_back" @click="goBack" />
        <q-toolbar-title>
          {{ runsStore.currentRun?.display_name || 'Loading...' }}
        </q-toolbar-title>
        <q-btn
          v-if="canComplete"
          flat
          icon="check"
          @click="completeRunDialog = true"
        />
      </q-toolbar>

      <!-- Stop Tabs -->
      <q-tabs
        v-if="runsStore.currentRunStops.length > 0"
        v-model="selectedStopId"
        class="bg-primary-dark text-white"
        dense
        inline-label
        outside-arrows
        mobile-arrows
      >
        <q-tab
          name="all"
          label="All"
          :class="{ 'tab-with-badge': totalOpenItems > 0 }"
        />
        <q-tab
          v-for="stop in runsStore.currentRunStops"
          :key="stop.id"
          :name="stop.id"
          :class="{
            'tab-with-badge': stop.open_items > 0,
            'tab-current': isCurrentStop(stop.id),
          }"
        >
          <div class="flex items-center q-gutter-x-xs">
            <span>{{ stop.location_name }}</span>
            <q-badge
              v-if="stop.open_items > 0"
              color="orange"
              :label="stop.open_items"
              class="q-ml-xs"
            />
            <q-icon
              v-if="isCurrentStop(stop.id)"
              name="my_location"
              size="xs"
              class="q-ml-xs"
            />
          </div>
        </q-tab>
      </q-tabs>
    </q-header>

    <!-- Main Content -->
    <div class="q-pa-md">
      <!-- Loading State -->
      <div v-if="runsStore.loading && !runsStore.currentRun" class="text-center q-py-xl">
        <q-spinner-dots size="40px" color="primary" />
      </div>

      <template v-else-if="runsStore.currentRun">
        <!-- Run Status Banner -->
        <q-banner
          v-if="runsStore.currentRun.status === 'pending'"
          class="bg-blue-1 q-mb-md"
          rounded
        >
          <template v-slot:avatar>
            <q-icon name="info" color="primary" />
          </template>
          Run not started yet
          <template v-slot:action>
            <q-btn
              flat
              color="primary"
              label="Start Run"
              @click="startRun"
              :loading="startingRun"
            />
          </template>
        </q-banner>

        <!-- GPS Status -->
        <q-banner
          v-if="locationStore.error"
          class="bg-orange-1 q-mb-md"
          rounded
        >
          <template v-slot:avatar>
            <q-icon name="location_off" color="orange" />
          </template>
          {{ locationStore.error }}
        </q-banner>

        <!-- Current Location Info -->
        <div
          v-if="locationStore.nearestStop && runsStore.currentRun.status === 'in_progress'"
          class="q-mb-md"
        >
          <q-chip
            :color="locationStore.isInsideStop ? 'positive' : 'grey-6'"
            text-color="white"
            icon="place"
          >
            <span v-if="locationStore.isInsideStop">
              At {{ locationStore.currentStopName }}
            </span>
            <span v-else>
              {{ locationStore.distanceToNearestStop }}m from
              {{ locationStore.nearestStop.location_name }}
            </span>
          </q-chip>
        </div>

        <!-- Items List -->
        <div v-if="itemsStore.items.length > 0">
          <q-list bordered separator class="rounded-borders">
            <q-item
              v-for="item in sortedItems"
              :key="item.id"
              :class="{ 'bg-grey-2': item.is_completed }"
              clickable
              @click="selectItem(item)"
            >
              <q-item-section avatar>
                <q-icon
                  :name="getItemIcon(item)"
                  :color="getItemColor(item)"
                />
              </q-item-section>

              <q-item-section>
                <q-item-label :class="{ 'text-grey-6': item.is_completed }">
                  {{ item.reference_number }}
                </q-item-label>
                <q-item-label caption>
                  <span v-if="item.action_at_stop === 'pickup'">
                    Pickup from {{ item.origin.location_name }}
                  </span>
                  <span v-else-if="item.action_at_stop === 'dropoff'">
                    Deliver to {{ item.destination.location_name }}
                  </span>
                  <span v-else>
                    {{ item.origin.location_name }} to
                    {{ item.destination.location_name }}
                  </span>
                </q-item-label>
              </q-item-section>

              <q-item-section side top>
                <q-badge
                  :color="item.status.color || 'grey'"
                  :label="item.status.display_name"
                />
                <div class="q-mt-xs flex q-gutter-xs">
                  <q-icon
                    v-if="item.has_pickup_photo"
                    name="photo_camera"
                    size="xs"
                    color="positive"
                  />
                  <q-icon
                    v-if="item.urgency"
                    name="priority_high"
                    size="xs"
                    :color="item.urgency.color || 'orange'"
                  />
                </div>
              </q-item-section>

              <q-item-section side>
                <q-icon name="chevron_right" color="grey-6" />
              </q-item-section>
            </q-item>
          </q-list>
        </div>

        <!-- No Items Message -->
        <div
          v-else-if="!itemsStore.loading"
          class="text-center q-py-xl"
        >
          <q-icon name="check_circle" size="64px" color="positive" />
          <div class="text-h6 text-grey-7 q-mt-md">All done!</div>
          <div class="text-caption text-grey-6">
            No items at this stop
          </div>
        </div>
      </template>
    </div>

    <!-- Item Detail Dialog -->
    <RunnerItemDialog
      v-model="showItemDialog"
      :item="selectedItem"
      @status-updated="onItemUpdated"
      @photo-uploaded="onPhotoUploaded"
    />

    <!-- Exit Warning Dialog -->
    <ExitWarningDialog
      v-model="showExitWarning"
      :open-items="exitWarningItems"
      :stop-name="exitStopName"
      @go-back="handleGoBack"
      @mark-exceptions="handleMarkExceptions"
      @confirm-leave="handleConfirmLeave"
    />

    <!-- Complete Run Dialog -->
    <q-dialog v-model="completeRunDialog">
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">Complete Run?</div>
        </q-card-section>

        <q-card-section>
          <div v-if="totalOpenItems > 0" class="text-warning">
            Warning: {{ totalOpenItems }} item(s) are still open.
          </div>
          <div v-else class="text-positive">
            All items completed!
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            label="Complete"
            @click="completeRun"
            :loading="completingRun"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useRunnerRunsStore } from 'stores/runnerRuns';
import { useRunnerItemsStore } from 'stores/runnerItems';
import { useRunnerLocationStore } from 'stores/runnerLocation';
import { useQuasar } from 'quasar';
import RunnerItemDialog from 'components/runner/RunnerItemDialog.vue';
import ExitWarningDialog from 'components/runner/ExitWarningDialog.vue';

const route = useRoute();
const router = useRouter();
const runsStore = useRunnerRunsStore();
const itemsStore = useRunnerItemsStore();
const locationStore = useRunnerLocationStore();
const $q = useQuasar();

const runId = computed(() => Number(route.params.runId));
const selectedStopId = ref<number | 'all'>('all');
const selectedItem = ref<typeof itemsStore.items[0] | null>(null);
const showItemDialog = ref(false);
const showExitWarning = ref(false);

interface OpenItem {
  id: number;
  reference_number: string;
  status: { name: string; display_name: string; color: string };
  is_completed: boolean;
  action_at_stop: 'pickup' | 'dropoff';
}
const exitWarningItems = ref<OpenItem[]>([]);
const exitStopName = ref('');
const previousStopId = ref<number | null>(null);

const startingRun = ref(false);
const completingRun = ref(false);
const completeRunDialog = ref(false);

const totalOpenItems = computed(() => itemsStore.openItemCount);

const canComplete = computed(
  () =>
    runsStore.currentRun?.status === 'in_progress'
);

const sortedItems = computed(() => {
  const items = [...itemsStore.items];
  // Show open items first, then completed
  return items.sort((a, b) => {
    if (a.is_completed === b.is_completed) return 0;
    return a.is_completed ? 1 : -1;
  });
});

const isCurrentStop = (stopId: number) => {
  return locationStore.currentStopId === stopId;
};

const getItemIcon = (item: typeof itemsStore.items[0]) => {
  if (item.is_completed) return 'check_circle';
  if (item.action_at_stop === 'pickup') return 'download';
  if (item.action_at_stop === 'dropoff') return 'upload';
  return 'local_shipping';
};

const getItemColor = (item: typeof itemsStore.items[0]) => {
  if (item.is_completed) return 'positive';
  if (item.status.name === 'exception') return 'negative';
  return item.status.color || 'primary';
};

const selectItem = async (item: typeof itemsStore.items[0]) => {
  // First show dialog with basic info
  selectedItem.value = item;
  showItemDialog.value = true;

  // Then fetch full details (including line items, documents, notes)
  const result = await itemsStore.fetchItem(item.id);
  if (result.success && result.item) {
    selectedItem.value = result.item;
  }
};

const onItemUpdated = () => {
  void itemsStore.fetchItems(runId.value, selectedStopId.value === 'all' ? null : selectedStopId.value);
};

const onPhotoUploaded = () => {
  void itemsStore.fetchItems(runId.value, selectedStopId.value === 'all' ? null : selectedStopId.value);
};

const startRun = async () => {
  startingRun.value = true;
  const result = await runsStore.startRun(runId.value);
  startingRun.value = false;

  if (result.success) {
    $q.notify({
      type: 'positive',
      message: 'Run started!',
      position: 'top',
    });
    // Start location tracking
    locationStore.startWatching(runId.value);
  } else {
    $q.notify({
      type: 'negative',
      message: result.error || 'Failed to start run',
      position: 'top',
    });
  }
};

const completeRun = async () => {
  completingRun.value = true;
  const result = await runsStore.completeRun(
    runId.value,
    totalOpenItems.value > 0
  );
  completingRun.value = false;
  completeRunDialog.value = false;

  if (result.success) {
    locationStore.stopWatching();
    $q.notify({
      type: 'positive',
      message: 'Run completed!',
      position: 'top',
    });
    await router.push('/runner/home');
  } else {
    $q.notify({
      type: 'negative',
      message: result.error || 'Failed to complete run',
      position: 'top',
    });
  }
};

const goBack = () => {
  runsStore.clearCurrentRun();
  locationStore.stopWatching();
  router.push('/runner/home');
};

// Exit warning handlers
const handleGoBack = () => {
  showExitWarning.value = false;
  // Navigate back to the stop they left
  if (previousStopId.value) {
    selectedStopId.value = previousStopId.value;
  }
};

const handleMarkExceptions = async () => {
  // Mark all open items as exceptions
  for (const item of exitWarningItems.value) {
    await itemsStore.markException(item.id, 'Left at stop without completing');
  }
  showExitWarning.value = false;
  void itemsStore.fetchItems(runId.value, selectedStopId.value === 'all' ? null : selectedStopId.value);
};

const handleConfirmLeave = () => {
  showExitWarning.value = false;
};

// Watch for stop changes to check for open items
watch(
  () => locationStore.currentStopId,
  async (newStopId, oldStopId) => {
    if (oldStopId && newStopId !== oldStopId) {
      // User left a stop, check for open items
      const result = await locationStore.checkExit(oldStopId, runId.value);
      if (result && result.exited && result.open_items_count > 0) {
        previousStopId.value = oldStopId;
        exitStopName.value = result.stop_name;
        exitWarningItems.value = result.open_items
          .filter((item): item is typeof item & { action_at_stop: 'pickup' | 'dropoff' } =>
            item.action_at_stop === 'pickup' || item.action_at_stop === 'dropoff'
          )
          .map((item) => ({
            id: item.id,
            reference_number: item.reference_number,
            status: { name: item.status, display_name: item.status, color: 'grey' },
            is_completed: false,
            action_at_stop: item.action_at_stop,
          }));
        showExitWarning.value = true;
      }
    }

    // Auto-select tab when entering a stop
    if (newStopId) {
      selectedStopId.value = newStopId;
    }
  }
);

// Fetch items when stop selection changes
watch(selectedStopId, (stopId) => {
  void itemsStore.fetchItems(runId.value, stopId === 'all' ? null : stopId);
});

onMounted(() => {
  void (async () => {
    await runsStore.fetchRunDetails(runId.value);
    await itemsStore.fetchItems(runId.value);

    // Start location tracking if run is in progress
    if (runsStore.currentRun?.status === 'in_progress') {
      locationStore.startWatching(runId.value);
    }
  })();
});

onUnmounted(() => {
  locationStore.stopWatching();
  runsStore.clearCurrentRun();
  itemsStore.clearItems();
});
</script>

<style scoped>
.runner-run {
  background-color: #f5f5f5;
  min-height: 100vh;
}

.q-header {
  position: sticky;
  top: 0;
  z-index: 100;
}

.bg-primary-dark {
  background-color: #1565c0;
}

.tab-current {
  border-bottom: 3px solid #ffc107;
}
</style>
