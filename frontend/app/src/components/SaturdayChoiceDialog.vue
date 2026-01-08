<template>
  <q-dialog
    v-model="dialogVisible"
    persistent
  >
    <q-card style="min-width: 320px; max-width: 400px">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Schedule for Saturday?</div>
        <q-space />
        <q-btn
          icon="close"
          flat
          round
          dense
          v-close-popup
          @click="cancel"
        />
      </q-card-section>

      <q-card-section>
        <div class="text-body1 q-mb-md">
          The next available run is scheduled for:
        </div>
        <div class="text-subtitle1 text-weight-bold text-primary q-mb-md">
          Saturday, {{ formattedDate }} at {{ formattedTime }}
        </div>
        <div class="text-body2 text-grey-7">
          Would you like to schedule for Saturday, or wait until the next business day?
        </div>
      </q-card-section>

      <q-card-actions align="right" class="q-pa-md q-pt-none">
        <q-btn
          flat
          label="Next Business Day"
          color="grey-7"
          :loading="loading"
          @click="selectNextBusinessDay"
        />
        <q-btn
          unelevated
          label="Use Saturday"
          color="primary"
          :loading="loading"
          @click="selectSaturday"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { date } from 'quasar'

interface Props {
  modelValue: boolean
  saturdayDate: string
  saturdayTime: string
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'select', useSaturday: boolean): void
  (e: 'cancel'): void
}>()

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const formattedDate = computed(() => {
  if (!props.saturdayDate) return ''
  try {
    return date.formatDate(props.saturdayDate, 'MMMM D, YYYY')
  } catch {
    return props.saturdayDate
  }
})

const formattedTime = computed(() => {
  if (!props.saturdayTime) return ''
  try {
    // Convert H:i to 12-hour format
    const [hours, minutes] = props.saturdayTime.split(':')
    const hour = parseInt(hours || '0')
    const ampm = hour >= 12 ? 'PM' : 'AM'
    const displayHour = hour % 12 || 12
    return `${displayHour}:${minutes} ${ampm}`
  } catch {
    return props.saturdayTime
  }
})

function selectSaturday() {
  emit('select', true)
}

function selectNextBusinessDay() {
  emit('select', false)
}

function cancel() {
  emit('cancel')
}
</script>
