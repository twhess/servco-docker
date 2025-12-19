<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :persistent="persistent"
    :maximized="maximized"
  >
    <q-card class="mobile-form-dialog">
      <!-- Header -->
      <q-card-section class="dialog-header">
        <div class="text-h6">{{ title }}</div>
        <q-btn
          icon="close"
          flat
          round
          dense
          v-close-popup
          class="close-btn"
        />
      </q-card-section>

      <!-- Draft Banner -->
      <q-banner
        v-if="showDraftBanner && hasDraft"
        class="bg-amber-2 text-dark draft-banner"
      >
        <template v-slot:avatar>
          <q-icon name="drafts" color="amber-8" />
        </template>
        <div class="row items-center">
          <div class="col">
            Draft saved {{ draftAge }}
          </div>
          <div class="col-auto">
            <q-btn
              label="Load"
              color="amber-8"
              flat
              dense
              @click="$emit('load-draft')"
              class="q-mr-sm"
            />
            <q-btn
              label="Discard"
              color="amber-8"
              flat
              dense
              @click="$emit('discard-draft')"
            />
          </div>
        </div>
      </q-banner>

      <!-- Form Content -->
      <q-card-section class="dialog-content">
        <q-form @submit.prevent="handleSubmit" class="mobile-form">
          <!-- Single column on mobile, 2 columns on tablet+ -->
          <div :class="singleColumn ? 'form-single-column' : 'form-grid'">
            <slot />
          </div>
        </q-form>
      </q-card-section>

      <!-- Actions -->
      <q-card-actions align="right" class="dialog-actions">
        <q-btn
          flat
          label="Cancel"
          v-close-popup
          :disable="loading"
          class="action-btn"
        />
        <q-btn
          unelevated
          :label="submitLabel"
          color="primary"
          @click="handleSubmit"
          :loading="loading"
          :disable="loading"
          class="action-btn"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useQuasar } from 'quasar';

interface Props {
  modelValue: boolean;
  title: string;
  submitLabel?: string;
  loading?: boolean;
  persistent?: boolean;
  maximized?: boolean;
  singleColumn?: boolean;
  hasDraft?: boolean;
  draftAge?: string;
  showDraftBanner?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  submitLabel: 'Submit',
  loading: false,
  persistent: false,
  maximized: false,
  singleColumn: false,
  hasDraft: false,
  draftAge: '',
  showDraftBanner: true,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'submit'): void;
  (e: 'load-draft'): void;
  (e: 'discard-draft'): void;
}>();

const $q = useQuasar();

const handleSubmit = () => {
  emit('submit');
};
</script>

<style scoped lang="scss">
.mobile-form-dialog {
  width: 100%;
  max-width: 800px;

  // On mobile, use full screen
  @media (max-width: 599px) {
    max-width: 100%;
    height: 100%;
  }
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);

  .close-btn {
    margin-left: auto;
  }
}

.draft-banner {
  padding: 12px 24px;
}

.dialog-content {
  padding: 24px;
  max-height: calc(100vh - 200px);
  overflow-y: auto;

  // Better scrolling on mobile
  -webkit-overflow-scrolling: touch;
}

.mobile-form {
  width: 100%;
}

// Single column layout
.form-single-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

// Grid layout: 1 column on mobile, 2 columns on tablet+
.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;

  @media (min-width: 600px) {
    grid-template-columns: 1fr 1fr;
  }
}

.dialog-actions {
  padding: 16px 24px;
  border-top: 1px solid rgba(0, 0, 0, 0.12);

  .action-btn {
    min-width: 80px;
    min-height: 44px; // Touch target
  }

  // Full width buttons on mobile for easier tapping
  @media (max-width: 599px) {
    display: flex;
    flex-direction: column-reverse;
    gap: 8px;

    .action-btn {
      width: 100%;
    }
  }
}
</style>
