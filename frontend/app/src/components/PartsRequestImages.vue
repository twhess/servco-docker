<template>
  <div class="parts-request-images">
    <!-- Header -->
    <div class="row items-center no-wrap q-mb-xs">
      <div class="text-caption text-weight-medium text-grey-8">
        {{ headerText }}
      </div>
      <q-space />
      <q-btn
        v-if="canAdd"
        flat
        dense
        size="xs"
        color="primary"
        icon="add_a_photo"
        label="Add"
        @click="triggerImageInput"
      />
      <input
        ref="imageInputRef"
        type="file"
        class="hidden"
        accept="image/*"
        :capture="useCamera ? 'environment' : undefined"
        multiple
        @change="handleImageSelect"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="24px" />
    </div>

    <!-- Empty State -->
    <div v-else-if="images.length === 0" class="text-grey-6 text-center q-py-md">
      {{ emptyText }}
    </div>

    <!-- Image Thumbnails Grid -->
    <div v-else class="image-grid">
      <div
        v-for="(img, index) in images"
        :key="img.id"
        class="image-thumb"
        @click="openCarousel(index)"
      >
        <q-img
          :src="img.thumbnail_url || img.url"
          :alt="img.caption || img.original_filename"
          ratio="1"
          class="rounded-borders"
        >
          <template #loading>
            <q-spinner color="white" />
          </template>
        </q-img>
        <q-badge
          v-if="img.source !== 'requester'"
          :color="img.source === 'pickup' ? 'blue' : 'green'"
          floating
          class="source-badge"
        >
          {{ img.source === 'pickup' ? 'P' : 'D' }}
        </q-badge>
      </div>
    </div>

    <!-- Summary -->
    <div v-if="images.length > 0" class="q-mt-sm text-caption text-grey-7">
      {{ images.length }} image{{ images.length !== 1 ? 's' : '' }}
    </div>

    <!-- Carousel Dialog -->
    <q-dialog v-model="showCarousel" maximized>
      <q-card class="bg-black column full-height">
        <!-- Header -->
        <q-card-section class="row items-center q-py-sm bg-grey-10">
          <div class="text-white">
            {{ currentIndex + 1 }} / {{ images.length }}
          </div>
          <q-space />
          <q-btn
            v-if="canDelete && currentImage"
            flat
            dense
            round
            icon="delete"
            color="negative"
            @click="confirmDelete"
          />
          <q-btn
            flat
            dense
            round
            icon="close"
            color="white"
            v-close-popup
          />
        </q-card-section>

        <!-- Carousel -->
        <q-card-section class="col q-pa-none carousel-container">
          <q-carousel
            v-model="currentSlide"
            swipeable
            animated
            navigation
            control-color="white"
            class="full-height bg-black"
            @update:model-value="onSlideChange"
          >
            <q-carousel-slide
              v-for="(img, index) in images"
              :key="img.id"
              :name="index"
              class="column no-wrap flex-center"
            >
              <q-img
                :src="img.url"
                :alt="img.caption || img.original_filename"
                fit="contain"
                class="carousel-image"
              >
                <template #loading>
                  <div class="flex flex-center full-height">
                    <q-spinner color="white" size="50px" />
                  </div>
                </template>
              </q-img>
            </q-carousel-slide>
          </q-carousel>
        </q-card-section>

        <!-- Caption / Info -->
        <q-card-section v-if="currentImage" class="bg-grey-10 q-py-sm">
          <div v-if="currentImage.caption" class="text-white text-body2 q-mb-xs">
            {{ currentImage.caption }}
          </div>
          <div class="row text-grey-5 text-caption">
            <div v-if="currentImage.uploaded_by" class="q-mr-md">
              By {{ currentImage.uploaded_by.name }}
            </div>
            <div v-if="currentImage.uploaded_at">
              {{ formatDateTime(currentImage.uploaded_at) }}
            </div>
            <q-space />
            <div v-if="currentImage.source !== 'requester'">
              <q-badge :color="currentImage.source === 'pickup' ? 'blue' : 'green'">
                {{ currentImage.source === 'pickup' ? 'Pickup' : 'Delivery' }}
              </q-badge>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Upload Progress Dialog -->
    <q-dialog v-model="showUploadDialog" persistent>
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">Uploading Images</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <div class="text-body2 q-mb-sm">
            {{ uploadProgress.current }} of {{ uploadProgress.total }} images
          </div>
          <q-linear-progress
            :value="uploadProgress.current / uploadProgress.total"
            color="primary"
            class="q-mb-sm"
          />
          <div class="text-caption text-grey-7">
            {{ uploadProgress.currentFile }}
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useQuasar, date } from 'quasar';
import { usePartsRequestsStore, type PartsRequestImage, type ImageSource } from 'src/stores/partsRequests';

const props = defineProps<{
  requestId: number | null;
  source?: ImageSource; // Filter by source if specified
  readonly?: boolean;
  showRunnerImages?: boolean; // Whether to show pickup/delivery images
  allowCamera?: boolean; // Allow camera capture on mobile
}>();

const emit = defineEmits<{
  (e: 'images-changed', images: PartsRequestImage[]): void;
  (e: 'count-changed', count: number): void;
}>();

const $q = useQuasar();
const store = usePartsRequestsStore();

const images = ref<PartsRequestImage[]>([]);
const loading = ref(false);
const imageInputRef = ref<HTMLInputElement | null>(null);

// Carousel state
const showCarousel = ref(false);
const currentSlide = ref(0);
const currentIndex = ref(0);

// Upload state
const showUploadDialog = ref(false);
const uploadProgress = ref({
  current: 0,
  total: 0,
  currentFile: '',
});

const headerText = computed(() => {
  if (props.source === 'pickup') return 'Pickup Photos';
  if (props.source === 'delivery') return 'Delivery Photos';
  if (props.showRunnerImages) return 'All Photos';
  return 'Photos';
});

const emptyText = computed(() => {
  if (props.source === 'pickup') return 'No pickup photos yet';
  if (props.source === 'delivery') return 'No delivery photos yet';
  return 'No photos added yet';
});

const canAdd = computed(() => !props.readonly && props.requestId);
const canDelete = computed(() => !props.readonly);
const useCamera = computed(() => props.allowCamera && $q.platform.is.mobile);

const currentImage = computed(() => {
  return images.value[currentIndex.value] || null;
});

async function loadImages() {
  if (!props.requestId) {
    images.value = [];
    return;
  }

  loading.value = true;
  try {
    const fetchedImages = await store.fetchImages(props.requestId, props.source);
    // If showRunnerImages is false and no source filter, only show requester images
    if (!props.showRunnerImages && !props.source) {
      images.value = fetchedImages.filter(img => img.source === 'requester');
    } else {
      images.value = fetchedImages;
    }
    emit('images-changed', images.value);
    emit('count-changed', images.value.length);
  } catch {
    // Error handled in store
  } finally {
    loading.value = false;
  }
}

function triggerImageInput() {
  imageInputRef.value?.click();
}

async function handleImageSelect(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = input.files;
  if (!files || files.length === 0 || !props.requestId) return;

  const fileArray = Array.from(files);
  uploadProgress.value = {
    current: 0,
    total: fileArray.length,
    currentFile: '',
  };
  showUploadDialog.value = true;

  try {
    for (let i = 0; i < fileArray.length; i++) {
      const file = fileArray[i];
      if (!file) continue;

      uploadProgress.value.current = i + 1;
      uploadProgress.value.currentFile = file.name;

      // Get current location if available
      let latitude: number | undefined;
      let longitude: number | undefined;

      if (navigator.geolocation && props.allowCamera) {
        try {
          const position = await new Promise<GeolocationPosition>((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
              enableHighAccuracy: true,
              timeout: 5000,
              maximumAge: 60000,
            });
          });
          latitude = position.coords.latitude;
          longitude = position.coords.longitude;
        } catch {
          // Location not available, continue without it
        }
      }

      const uploadOptions: { source: ImageSource; caption?: string; latitude?: number; longitude?: number } = {
        source: props.source || 'requester',
      };
      if (latitude !== undefined) uploadOptions.latitude = latitude;
      if (longitude !== undefined) uploadOptions.longitude = longitude;

      const newImage = await store.uploadImage(props.requestId, file, uploadOptions);
      images.value.unshift(newImage);
    }

    emit('images-changed', images.value);
  } catch {
    // Error handled in store
  } finally {
    showUploadDialog.value = false;
    input.value = '';
  }
}

function openCarousel(index: number) {
  currentIndex.value = index;
  currentSlide.value = index;
  showCarousel.value = true;
}

function onSlideChange(newSlide: string | number) {
  currentIndex.value = typeof newSlide === 'number' ? newSlide : parseInt(newSlide, 10);
}

function confirmDelete() {
  if (!currentImage.value) return;

  $q.dialog({
    title: 'Delete Image',
    message: 'Are you sure you want to delete this image?',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    if (!props.requestId || !currentImage.value) return;

    try {
      await store.deleteImage(props.requestId, currentImage.value.id);
      const deletedIndex = currentIndex.value;
      images.value = images.value.filter(img => img.id !== currentImage.value!.id);

      if (images.value.length === 0) {
        showCarousel.value = false;
      } else {
        // Move to next or previous image
        currentIndex.value = Math.min(deletedIndex, images.value.length - 1);
        currentSlide.value = currentIndex.value;
      }

      emit('images-changed', images.value);
    } catch {
      // Error handled in store
    }
  });
}

function formatDateTime(dateString: string): string {
  return date.formatDate(dateString, 'MMM D, YYYY h:mm A');
}

watch(() => props.requestId, () => {
  loadImages();
}, { immediate: true });

watch(() => props.source, () => {
  loadImages();
});

onMounted(() => {
  if (props.requestId) {
    loadImages();
  }
});
</script>

<style scoped>
.parts-request-images {
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

.hidden {
  display: none;
}

.image-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

@media (max-width: 400px) {
  .image-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.image-thumb {
  position: relative;
  cursor: pointer;
  border-radius: 4px;
  overflow: hidden;
}

.image-thumb:hover {
  opacity: 0.9;
}

.source-badge {
  font-size: 10px;
  padding: 2px 4px;
  top: 4px;
  right: 4px;
}

.carousel-container {
  position: relative;
}

.carousel-image {
  max-width: 100%;
  max-height: 100%;
}

:deep(.q-carousel) {
  height: 100% !important;
}

:deep(.q-carousel__slide) {
  padding: 0;
}

:deep(.q-carousel__navigation) {
  bottom: 16px;
}

:deep(.q-carousel__navigation-icon) {
  font-size: 10px;
}
</style>
