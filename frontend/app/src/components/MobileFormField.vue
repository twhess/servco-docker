<template>
  <div :data-field-name="name" class="mobile-form-field">
    <!-- Text Input -->
    <q-input
      v-if="type === 'text' || type === 'email' || type === 'tel' || type === 'number' || type === 'password'"
      :model-value="modelValue"
      @update:model-value="handleInput"
      @blur="handleBlur"
      :label="label"
      :type="type"
      :inputmode="getInputMode()"
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable"
      :required="required"
      :autofocus="autofocus"
      filled
      stack-label
      :dense="false"
      class="mobile-input"
    >
      <template v-if="icon" v-slot:prepend>
        <q-icon :name="icon" />
      </template>
      <template v-if="$slots.append" v-slot:append>
        <slot name="append" />
      </template>
    </q-input>

    <!-- Textarea -->
    <q-input
      v-else-if="type === 'textarea'"
      :model-value="modelValue"
      @update:model-value="handleInput"
      @blur="handleBlur"
      :label="label"
      type="textarea"
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable"
      :required="required"
      :autofocus="autofocus"
      :rows="rows || 4"
      filled
      stack-label
      class="mobile-input"
    >
      <template v-if="icon" v-slot:prepend>
        <q-icon :name="icon" />
      </template>
    </q-input>

    <!-- Date Input -->
    <q-input
      v-else-if="type === 'date'"
      :model-value="modelValue"
      @update:model-value="handleInput"
      @blur="handleBlur"
      :label="label"
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable"
      :required="required"
      filled
      stack-label
      class="mobile-input"
    >
      <template v-slot:prepend>
        <q-icon :name="icon || 'event'" class="cursor-pointer">
          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
            <q-date
              :model-value="modelValue"
              @update:model-value="handleInput"
              mask="YYYY-MM-DD"
            >
              <div class="row items-center justify-end">
                <q-btn v-close-popup label="Close" color="primary" flat />
              </div>
            </q-date>
          </q-popup-proxy>
        </q-icon>
      </template>
    </q-input>

    <!-- Time Input -->
    <q-input
      v-else-if="type === 'time'"
      :model-value="modelValue"
      @update:model-value="handleInput"
      @blur="handleBlur"
      :label="label"
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable"
      :required="required"
      filled
      stack-label
      class="mobile-input"
    >
      <template v-slot:prepend>
        <q-icon :name="icon || 'access_time'" class="cursor-pointer">
          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
            <q-time
              :model-value="modelValue"
              @update:model-value="handleInput"
              mask="HH:mm"
            >
              <div class="row items-center justify-end">
                <q-btn v-close-popup label="Close" color="primary" flat />
              </div>
            </q-time>
          </q-popup-proxy>
        </q-icon>
      </template>
    </q-input>

    <!-- DateTime Input -->
    <q-input
      v-else-if="type === 'datetime-local'"
      :model-value="modelValue"
      @update:model-value="handleInput"
      @blur="handleBlur"
      :label="label"
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable"
      :required="required"
      filled
      stack-label
      class="mobile-input"
    >
      <template v-slot:prepend>
        <q-icon :name="icon || 'event'" class="cursor-pointer">
          <q-popup-proxy ref="datePopupRef" cover transition-show="scale" transition-hide="scale">
            <q-date
              :model-value="getDatePart(modelValue)"
              @update:model-value="handleDateChange"
              mask="YYYY-MM-DD"
            />
          </q-popup-proxy>
        </q-icon>
      </template>
      <template v-slot:append>
        <q-icon name="access_time" class="cursor-pointer">
          <q-popup-proxy ref="timePopupRef" cover transition-show="scale" transition-hide="scale">
            <q-time
              :model-value="getTimePart(modelValue)"
              @update:model-value="handleTimeChange"
              mask="HH:mm"
            />
          </q-popup-proxy>
        </q-icon>
        <q-icon
          v-if="clearable && modelValue"
          name="cancel"
          class="cursor-pointer q-ml-sm"
          @click.stop="handleClear"
        />
      </template>
    </q-input>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { QPopupProxy } from 'quasar';

// Refs for popup proxies to auto-close after selection
const datePopupRef = ref<QPopupProxy | null>(null);
const timePopupRef = ref<QPopupProxy | null>(null);

interface Props {
  name: string;
  modelValue: any;
  label: string;
  type?: 'text' | 'email' | 'tel' | 'number' | 'password' | 'textarea' | 'date' | 'time' | 'datetime-local';
  hint?: string;
  error?: string | null;
  icon?: string;
  readonly?: boolean;
  disable?: boolean;
  required?: boolean;
  autofocus?: boolean;
  rows?: number;
  clearable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  readonly: false,
  disable: false,
  required: false,
  autofocus: false,
  clearable: false,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void;
  (e: 'blur'): void;
}>();

const handleInput = (value: any) => {
  emit('update:modelValue', value);
};

const handleBlur = () => {
  emit('blur');
};

const handleClear = () => {
  emit('update:modelValue', null);
};

/**
 * Get appropriate inputmode for mobile keyboards
 */
const getInputMode = (): string => {
  switch (props.type) {
    case 'email':
      return 'email';
    case 'tel':
      return 'tel';
    case 'number':
      return 'decimal';
    default:
      return 'text';
  }
};

/**
 * Helper functions for datetime-local input
 */
const getDatePart = (value: string | null): string => {
  if (!value) return '';
  // Handle both ISO format (2025-01-15T10:30:00) and datetime-local format (2025-01-15T10:30)
  return value.split('T')[0] || '';
};

const getTimePart = (value: string | null): string => {
  if (!value) return '';
  const timePart = value.split('T')[1];
  if (!timePart) return '';
  // Return HH:mm format
  return timePart.substring(0, 5);
};

const handleDateChange = (date: string | null) => {
  if (!date) return;
  const currentTime = getTimePart(props.modelValue) || '00:00';
  emit('update:modelValue', `${date}T${currentTime}`);
  // Auto-close the date popup after selection
  datePopupRef.value?.hide();
};

const handleTimeChange = (time: string | null) => {
  if (!time) return;
  const currentDate = getDatePart(props.modelValue) || new Date().toISOString().split('T')[0];
  emit('update:modelValue', `${currentDate}T${time}`);
  // Auto-close the time popup after selection
  timePopupRef.value?.hide();
};
</script>

<style scoped lang="scss">
.mobile-form-field {
  width: 100%;
  margin-bottom: 8px;
}

.mobile-input {
  // Minimum touch target height of 44px
  :deep(.q-field__control) {
    min-height: 44px;
  }

  // Ensure label is always visible (stack-label handles this)
  :deep(.q-field__label) {
    color: rgba(0, 0, 0, 0.6);
  }

  // Make error messages more visible
  :deep(.q-field__messages) {
    font-size: 12px;
    padding-top: 4px;
  }
}
</style>
