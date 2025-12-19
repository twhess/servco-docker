<template>
  <div :data-field-name="name" class="mobile-form-field">
    <q-select
      :model-value="modelValue"
      @update:model-value="handleInput"
      @blur="handleBlur"
      :label="label"
      :options="filteredOptions"
      :option-value="optionValue"
      :option-label="optionLabel"
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable"
      :required="required"
      :multiple="multiple"
      :use-chips="multiple"
      :use-input="useSearch"
      @filter="filterFn"
      filled
      stack-label
      :dense="false"
      class="mobile-select"
      behavior="dialog"
    >
      <template v-if="icon" v-slot:prepend>
        <q-icon :name="icon" />
      </template>

      <template v-if="multiple" v-slot:selected-item="scope">
        <q-chip
          removable
          dense
          @remove="scope.removeAtIndex(scope.index)"
          :tabindex="scope.tabindex"
          color="primary"
          text-color="white"
          class="mobile-chip"
        >
          {{ getOptionLabel(scope.opt) }}
        </q-chip>
      </template>

      <template v-if="clearable && modelValue" v-slot:append>
        <q-icon
          name="cancel"
          @click.stop="handleClear"
          class="cursor-pointer"
        />
      </template>

      <template v-if="$slots.noOption" v-slot:no-option>
        <slot name="no-option" />
      </template>
    </q-select>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';

interface Props {
  name: string;
  modelValue: any;
  label: string;
  options: any[];
  optionValue?: string;
  optionLabel?: string;
  hint?: string;
  error?: string | null;
  icon?: string;
  readonly?: boolean;
  disable?: boolean;
  required?: boolean;
  multiple?: boolean;
  clearable?: boolean;
  searchThreshold?: number; // Show search if options exceed this number
}

const props = withDefaults(defineProps<Props>(), {
  optionValue: 'id',
  optionLabel: 'name',
  readonly: false,
  disable: false,
  required: false,
  multiple: false,
  clearable: false,
  searchThreshold: 10,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void;
  (e: 'blur'): void;
}>();

const filterText = ref('');
const filteredOptions = ref(props.options);

/**
 * Determine if search should be enabled
 */
const useSearch = computed(() => {
  return props.options.length > props.searchThreshold;
});

/**
 * Get label for an option
 */
const getOptionLabel = (option: any): string => {
  if (!option) return '';
  if (typeof option === 'string') return option;
  return option[props.optionLabel] || '';
};

/**
 * Filter function for search
 */
const filterFn = (val: string, update: (fn: () => void) => void) => {
  update(() => {
    if (val === '') {
      filteredOptions.value = props.options;
    } else {
      const needle = val.toLowerCase();
      filteredOptions.value = props.options.filter((option) => {
        const label = getOptionLabel(option).toLowerCase();
        return label.includes(needle);
      });
    }
  });
};

const handleInput = (value: any) => {
  emit('update:modelValue', value);
};

const handleBlur = () => {
  emit('blur');
};

const handleClear = () => {
  emit('update:modelValue', props.multiple ? [] : null);
};
</script>

<style scoped lang="scss">
.mobile-form-field {
  width: 100%;
  margin-bottom: 8px;
}

.mobile-select {
  // Minimum touch target height of 44px
  :deep(.q-field__control) {
    min-height: 44px;
  }

  // Ensure label is always visible
  :deep(.q-field__label) {
    color: rgba(0, 0, 0, 0.6);
  }

  // Make error messages more visible
  :deep(.q-field__messages) {
    font-size: 12px;
    padding-top: 4px;
  }

  // Optimize chips for mobile
  .mobile-chip {
    min-height: 32px;
    margin: 2px;
  }
}
</style>
