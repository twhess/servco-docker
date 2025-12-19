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
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  name: string;
  modelValue: any;
  label: string;
  type?: 'text' | 'email' | 'tel' | 'number' | 'password' | 'textarea' | 'date' | 'time';
  hint?: string;
  error?: string | null;
  icon?: string;
  readonly?: boolean;
  disable?: boolean;
  required?: boolean;
  autofocus?: boolean;
  rows?: number;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  readonly: false,
  disable: false,
  required: false,
  autofocus: false,
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
