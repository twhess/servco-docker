<template>
  <q-page padding class="runner-dashboard">
    <div class="text-h5 q-mb-md">My Jobs</div>

    <!-- Auto-tracking toggle -->
    <q-card flat bordered class="q-mb-md" v-if="currentJob">
      <q-card-section class="row items-center">
        <q-toggle
          v-model="autoTrackingEnabled"
          label="Auto GPS Tracking"
          color="primary"
        />
        <q-space />
        <div v-if="autoTrackingEnabled" class="text-caption text-grey-7">
          <q-icon name="gps_fixed" color="positive" />
          Location updating every 30s
        </div>
      </q-card-section>
    </q-card>

    <!-- Active Job Card -->
    <q-card v-if="currentJob" flat bordered class="q-mb-md bg-blue-1">
      <q-card-section>
        <div class="row items-center q-mb-sm">
          <div class="text-h6">{{ currentJob.reference_number }}</div>
          <q-space />
          <q-chip
            :color="getUrgencyColor(currentJob.urgency.name)"
            text-color="white"
            size="md"
          >
            {{ currentJob.urgency.name.toUpperCase() }}
          </q-chip>
        </div>

        <div class="q-gutter-sm">
          <div><strong>Type:</strong> {{ getTypeLabel(currentJob.request_type.name) }}</div>
          <div><strong>From:</strong> {{ getOriginText(currentJob) }}</div>
          <div><strong>To:</strong> {{ getDestinationText(currentJob) }}</div>
          <div><strong>Details:</strong> {{ currentJob.details }}</div>
          <div v-if="currentJob.special_instructions" class="text-orange-8">
            <strong>⚠️ Instructions:</strong> {{ currentJob.special_instructions }}
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="q-mt-md q-gutter-sm">
          <q-btn
            v-if="canShowAction('started')"
            push
            color="primary"
            label="Start Job"
            icon="play_arrow"
            class="full-width"
            size="lg"
            @click="handleEvent('started')"
          />

          <q-btn
            v-if="canShowAction('picked_up')"
            push
            color="orange"
            label="Mark Picked Up"
            icon="shopping_bag"
            class="full-width"
            size="lg"
            @click="handlePickup"
          />

          <q-btn
            v-if="canShowAction('delivered')"
            push
            color="positive"
            label="Mark Delivered"
            icon="check_circle"
            class="full-width"
            size="lg"
            @click="handleDelivery"
          />

          <q-btn
            push
            color="deep-orange"
            label="Report Problem"
            icon="warning"
            class="full-width"
            @click="openProblemDialog"
          />

          <div class="row q-gutter-sm">
            <q-btn
              outline
              color="primary"
              label="View Timeline"
              icon="timeline"
              class="col"
              @click="viewTimeline(currentJob)"
            />
            <q-btn
              outline
              color="primary"
              label="Navigate"
              icon="navigation"
              class="col"
              @click="openNavigation(currentJob)"
            />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Job List -->
    <div v-if="!loading && jobs.length === 0" class="text-center q-py-xl">
      <q-icon name="inbox" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No active jobs</div>
      <div class="text-caption text-grey-6">You have no assigned jobs at this time</div>
    </div>

    <q-list bordered separator v-else>
      <q-item
        v-for="job in jobs"
        :key="job.id"
        clickable
        @click="selectJob(job)"
        :class="{ 'bg-blue-1': currentJob?.id === job.id }"
      >
        <q-item-section>
          <q-item-label class="text-weight-medium">
            {{ job.reference_number }}
            <q-chip
              dense
              size="sm"
              :color="getUrgencyColor(job.urgency.name)"
              text-color="white"
              class="q-ml-sm"
            >
              {{ job.urgency.name.toUpperCase() }}
            </q-chip>
          </q-item-label>
          <q-item-label caption>
            <div>{{ getTypeLabel(job.request_type.name) }}</div>
            <div>{{ getOriginText(job) }} → {{ getDestinationText(job) }}</div>
          </q-item-label>
        </q-item-section>

        <q-item-section side>
          <q-chip
            dense
            size="sm"
            :color="getStatusColor(job.status.name)"
            text-color="white"
          >
            {{ getStatusLabel(job.status.name) }}
          </q-chip>
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Photo Capture Dialog -->
    <q-dialog v-model="showPhotoDialog" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">{{ photoStage === 'pickup' ? 'Pickup Photo' : 'Delivery Photo' }}</div>
          <div class="text-caption">Photo is required to continue</div>
        </q-card-section>

        <q-card-section>
          <q-file
            v-model="photoFile"
            label="Take or select photo"
            outlined
            accept="image/*"
            capture="environment"
            @update:model-value="handlePhotoSelected"
          >
            <template v-slot:prepend>
              <q-icon name="photo_camera" />
            </template>
          </q-file>

          <div v-if="photoPreview" class="q-mt-md">
            <q-img :src="photoPreview" style="max-height: 200px" />
          </div>

          <q-input
            v-model="photoNotes"
            label="Notes (optional)"
            outlined
            type="textarea"
            rows="2"
            class="q-mt-md"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup @click="cancelPhoto" />
          <q-btn
            flat
            label="Upload"
            color="primary"
            @click="uploadPhoto"
            :disable="!photoFile"
            :loading="uploading"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Problem Dialog -->
    <q-dialog v-model="showProblemDialog">
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Report Problem</div>
        </q-card-section>

        <q-card-section>
          <q-input
            v-model="problemNotes"
            label="Describe the problem"
            outlined
            type="textarea"
            rows="4"
            autofocus
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Submit"
            color="negative"
            @click="reportProblem"
            :disable="!problemNotes"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Timeline Dialog -->
    <q-dialog v-model="showTimelineDialog">
      <q-card style="min-width: 400px; max-width: 500px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Timeline</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-timeline color="primary">
            <q-timeline-entry
              v-for="event in timeline"
              :key="event.id"
              :title="getEventDisplayName(event.event_type)"
              :subtitle="formatDateTime(event.event_at)"
            >
              <div v-if="event.user">By: {{ event.user.name }}</div>
              <div v-if="event.notes" class="text-caption">{{ event.notes }}</div>
            </q-timeline-entry>
          </q-timeline>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePartsRequestsStore, type PartsRequest, type PartsRequestEvent } from 'src/stores/partsRequests';
import { Notify, Loading } from 'quasar';

const partsRequestsStore = usePartsRequestsStore();

const jobs = computed(() => partsRequestsStore.myJobs);
const loading = computed(() => partsRequestsStore.loading);

const currentJob = ref<PartsRequest | null>(null);
const autoTrackingEnabled = ref(false);
const trackingInterval = ref<any>(null);

const showPhotoDialog = ref(false);
const showProblemDialog = ref(false);
const showTimelineDialog = ref(false);

const photoFile = ref<File | null>(null);
const photoPreview = ref<string | null>(null);
const photoStage = ref<'pickup' | 'delivery'>('pickup');
const photoNotes = ref('');
const uploading = ref(false);

const problemNotes = ref('');
const timeline = ref<PartsRequestEvent[]>([]);

function getTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    pickup: 'Pickup',
    delivery: 'Delivery',
    transfer: 'Transfer',
  };
  return labels[type] || type;
}

function getStatusLabel(status: string): string {
  return status.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    new: 'blue',
    assigned: 'cyan',
    en_route_pickup: 'amber',
    picked_up: 'orange',
    en_route_dropoff: 'purple',
    delivered: 'positive',
    canceled: 'grey',
    problem: 'negative',
  };
  return colors[status] || 'grey';
}

function getUrgencyColor(urgency: string): string {
  const colors: Record<string, string> = {
    normal: 'blue',
    today: 'orange',
    asap: 'deep-orange',
    emergency: 'negative',
  };
  return colors[urgency] || 'grey';
}

function getOriginText(request: PartsRequest): string {
  return request.vendor_name || request.origin_location?.name || request.origin_address || 'Unknown';
}

function getDestinationText(request: PartsRequest): string {
  return request.customer_name || request.receiving_location?.name || request.customer_address || 'Unknown';
}

function formatDateTime(dateString: string): string {
  return new Date(dateString).toLocaleString();
}

function getEventDisplayName(eventType: string): string {
  const names: Record<string, string> = {
    created: 'Created',
    assigned: 'Assigned',
    started: 'Started',
    arrived_pickup: 'Arrived at Pickup',
    picked_up: 'Picked Up',
    departed_pickup: 'Departed Pickup',
    arrived_dropoff: 'Arrived at Dropoff',
    delivered: 'Delivered',
    problem_reported: 'Problem Reported',
  };
  return names[eventType] || eventType;
}

function canShowAction(action: string): boolean {
  if (!currentJob.value) return false;

  const status = currentJob.value.status.name;

  if (action === 'started') {
    return status === 'assigned';
  }

  if (action === 'picked_up') {
    return status === 'assigned' || status === 'en_route_pickup';
  }

  if (action === 'delivered') {
    return status === 'picked_up' || status === 'en_route_dropoff';
  }

  return false;
}

function selectJob(job: PartsRequest) {
  currentJob.value = job;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function handleEvent(eventType: string) {
  if (!currentJob.value) return;

  try {
    Loading.show({ message: 'Updating status...' });
    await partsRequestsStore.addEvent(currentJob.value.id, eventType);
    await refreshJobs();
    Loading.hide();
  } catch (error) {
    Loading.hide();
  }
}

function handlePickup() {
  photoStage.value = 'pickup';
  showPhotoDialog.value = true;
}

function handleDelivery() {
  photoStage.value = 'delivery';
  showPhotoDialog.value = true;
}

function handlePhotoSelected(file: File | null) {
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      photoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
  } else {
    photoPreview.value = null;
  }
}

async function uploadPhoto() {
  if (!currentJob.value || !photoFile.value) return;

  uploading.value = true;

  try {
    // Get current position
    const position = await getCurrentPosition();

    const formData = new FormData();
    formData.append('file', photoFile.value);
    formData.append('stage', photoStage.value);
    formData.append('lat', position.coords.latitude.toString());
    formData.append('lng', position.coords.longitude.toString());
    if (photoNotes.value) {
      formData.append('notes', photoNotes.value);
    }

    await partsRequestsStore.uploadPhoto(currentJob.value.id, formData);

    Notify.create({
      type: 'positive',
      message: `${photoStage.value === 'pickup' ? 'Pickup' : 'Delivery'} photo uploaded!`,
    });

    showPhotoDialog.value = false;
    cancelPhoto();
    await refreshJobs();

  } catch (error: any) {
    Notify.create({
      type: 'negative',
      message: error.message || 'Failed to upload photo',
    });
  } finally {
    uploading.value = false;
  }
}

function cancelPhoto() {
  photoFile.value = null;
  photoPreview.value = null;
  photoNotes.value = '';
}

function openProblemDialog() {
  problemNotes.value = '';
  showProblemDialog.value = true;
}

async function reportProblem() {
  if (!currentJob.value || !problemNotes.value) return;

  try {
    await partsRequestsStore.addEvent(currentJob.value.id, 'problem_reported', problemNotes.value);
    showProblemDialog.value = false;
    await refreshJobs();
  } catch (error) {
    // Error handled by store
  }
}

async function viewTimeline(job: PartsRequest) {
  timeline.value = await partsRequestsStore.fetchTimeline(job.id);
  showTimelineDialog.value = true;
}

function openNavigation(job: PartsRequest) {
  const dest = job.customer_address || job.receiving_location?.name;
  if (dest) {
    const encoded = encodeURIComponent(dest);
    window.open(`https://www.google.com/maps/dir/?api=1&destination=${encoded}`, '_blank');
  } else {
    Notify.create({
      type: 'warning',
      message: 'No destination address available',
    });
  }
}

async function getCurrentPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocation not supported'));
      return;
    }

    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    });
  });
}

async function postLocation() {
  if (!currentJob.value || !autoTrackingEnabled.value) return;

  try {
    const position = await getCurrentPosition();

    await partsRequestsStore.postLocation(currentJob.value.id, {
      lat: position.coords.latitude,
      lng: position.coords.longitude,
      accuracy_m: position.coords.accuracy,
      speed_mps: position.coords.speed || 0,
      source: 'gps',
    });

  } catch (error) {
    console.error('Failed to post location', error);
  }
}

function startTracking() {
  if (trackingInterval.value) {
    clearInterval(trackingInterval.value);
  }

  // Post immediately
  postLocation();

  // Then every 30 seconds
  trackingInterval.value = setInterval(() => {
    postLocation();
  }, 30000);
}

function stopTracking() {
  if (trackingInterval.value) {
    clearInterval(trackingInterval.value);
    trackingInterval.value = null;
  }
}

watch(autoTrackingEnabled, (enabled) => {
  if (enabled && currentJob.value) {
    startTracking();
  } else {
    stopTracking();
  }
});

async function refreshJobs() {
  await partsRequestsStore.fetchMyJobs();

  // Update current job if it still exists
  if (currentJob.value) {
    const updated = jobs.value.find(j => j.id === currentJob.value?.id);
    currentJob.value = updated || jobs.value[0] || null;
  } else if (jobs.value.length > 0) {
    currentJob.value = jobs.value[0] || null;
  }
}

onMounted(async () => {
  await refreshJobs();

  // Auto-select first job
  if (jobs.value.length > 0) {
    currentJob.value = jobs.value[0] || null;
  }

  // Refresh jobs every 60 seconds
  const refreshInterval = setInterval(() => {
    refreshJobs();
  }, 60000);

  onUnmounted(() => {
    clearInterval(refreshInterval);
    stopTracking();
  });
});
</script>

<style scoped>
.runner-dashboard .q-btn {
  font-weight: 500;
}
</style>
