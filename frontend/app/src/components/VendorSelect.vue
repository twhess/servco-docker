<template>
  <div :data-field-name="name" class="mobile-form-field">
    <q-select
      ref="selectRef"
      :model-value="modelValue"
      @update:model-value="handleVendorSelect"
      :label="label"
      :options="selectOptions"
      option-value="id"
      option-label="name"
      emit-value
      map-options
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable"
      :required="required"
      use-input
      input-debounce="300"
      @filter="filterVendors"
      @input-value="onInputChange"
      filled
      stack-label
      :dense="false"
      options-dense
      class="mobile-select"
      popup-content-class="mobile-select-popup"
      popup-content-style="max-width: 100%"
      behavior="menu"
      :loading="vendorsStore.searching"
    >
      <template v-slot:prepend>
        <q-icon :name="icon || 'store'" />
      </template>

      <template v-slot:selected-item="scope">
        <span v-if="currentDisplayName">{{ currentDisplayName }}</span>
      </template>

      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>{{ scope.opt.name }}</q-item-label>
            <q-item-label caption v-if="scope.opt.primaryAddress">
              {{ scope.opt.primaryAddress }}
            </q-item-label>
          </q-item-section>
          <q-item-section side v-if="scope.opt.phone">
            <q-item-label caption>{{ scope.opt.phone }}</q-item-label>
          </q-item-section>
        </q-item>
      </template>

      <template v-slot:after-options v-if="showAddNewOption && inputValue">
        <q-item clickable @click="openCreateDialog">
          <q-item-section avatar>
            <q-icon name="add_circle" color="primary" />
          </q-item-section>
          <q-item-section>
            <q-item-label class="text-primary">
              Add "{{ inputValue }}" as new vendor
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>

      <template v-slot:no-option v-if="inputValue">
        <q-item>
          <q-item-section>
            <q-item-label>No vendors found</q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable @click="openCreateDialog" v-if="allowCreate">
          <q-item-section avatar>
            <q-icon name="add_circle" color="primary" />
          </q-item-section>
          <q-item-section>
            <q-item-label class="text-primary">
              Add "{{ inputValue }}" as new vendor
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>

      <template v-if="clearable && modelValue" v-slot:append>
        <q-icon
          name="cancel"
          @click.stop="handleClear"
          class="cursor-pointer"
        />
      </template>
    </q-select>

    <!-- Create Vendor Dialog -->
    <q-dialog v-model="showCreateDialog" persistent>
      <q-card style="min-width: 350px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">Add New Vendor</div>
        </q-card-section>

        <!-- Duplicate Warning -->
        <q-card-section v-if="duplicateCandidates.length > 0" class="q-pt-none">
          <q-banner class="bg-warning text-dark">
            <template v-slot:avatar>
              <q-icon name="warning" />
            </template>
            Similar vendors found. Did you mean one of these?
          </q-banner>
          <q-list bordered separator class="q-mt-sm">
            <q-item
              v-for="candidate in duplicateCandidates"
              :key="candidate.id"
              clickable
              @click="selectExistingVendor(candidate)"
            >
              <q-item-section>
                <q-item-label>{{ candidate.name }}</q-item-label>
                <q-item-label caption v-if="candidate.phone">
                  {{ candidate.phone }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge color="grey">
                  {{ Math.round(candidate.similarity * 100) }}% match
                </q-badge>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-form @submit.prevent="submitCreateVendor" class="q-gutter-sm">
            <q-input
              v-model="newVendor.name"
              label="Vendor Name *"
              filled
              :rules="[(v: string) => !!v || 'Name is required']"
            />
            <q-input
              v-model="newVendor.phone"
              label="Phone"
              filled
              type="tel"
              mask="(###) ###-####"
            />
            <q-input
              v-model="newVendor.email"
              label="Email"
              filled
              type="email"
            />
          </q-form>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeCreateDialog" />
          <q-btn
            v-if="duplicateCandidates.length > 0"
            flat
            label="Create Anyway"
            color="warning"
            @click="forceCreateVendor"
            :loading="creating"
          />
          <q-btn
            v-else
            flat
            label="Create"
            color="primary"
            @click="submitCreateVendor"
            :loading="creating"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useVendorsStore } from 'src/stores/vendors';
import type { Vendor, VendorDuplicateCandidate, VendorSearchResult } from 'src/types/vendors';

interface SelectOption {
  id: number;
  name: string;
  phone?: string | null;
  primaryAddress?: string;
}

interface Props {
  name: string;
  modelValue: number | null;
  initialVendorName?: string;  // Pass in vendor name from parent to avoid refetch
  label?: string;
  hint?: string;
  error?: string | null;
  icon?: string;
  readonly?: boolean;
  disable?: boolean;
  required?: boolean;
  clearable?: boolean;
  allowCreate?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Vendor',
  readonly: false,
  disable: false,
  required: false,
  clearable: true,
  allowCreate: true,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void;
  (e: 'vendor-selected', vendor: Vendor | null): void;
  (e: 'vendor-created', vendor: Vendor): void;
}>();

const vendorsStore = useVendorsStore();
const selectRef = ref<any>(null);

const inputValue = ref('');
// Initialize with prop value if provided
const selectedVendorName = ref(props.initialVendorName || '');
const selectOptions = ref<SelectOption[]>([]);
const showCreateDialog = ref(false);
const duplicateCandidates = ref<VendorDuplicateCandidate[]>([]);
const creating = ref(false);

const newVendor = ref({
  name: '',
  phone: '',
  email: '',
});

const showAddNewOption = computed(() => {
  return props.allowCreate && inputValue.value.length >= 2;
});

// The current display name - uses local state first, then prop fallback
// This is what shows in the selected-item slot
const currentDisplayName = computed(() => {
  return selectedVendorName.value || props.initialVendorName || '';
});

// Watch for initialVendorName prop changes - parent knows the vendor name
// Runs immediately to handle mount with existing value
watch(() => props.initialVendorName, (newName) => {
  if (newName) {
    selectedVendorName.value = newName;
  }
}, { immediate: true });

// Watch for modelValue being cleared or set without a name
watch(() => props.modelValue, async (newValue) => {
  if (!newValue) {
    // Value was cleared
    selectedVendorName.value = '';
  } else if (!selectedVendorName.value && !props.initialVendorName) {
    // We have a value but no name and no initial name from parent - fetch it
    try {
      const vendor = await vendorsStore.fetchVendor(newValue);
      selectedVendorName.value = vendor.name;
    } catch {
      selectedVendorName.value = '';
    }
  }
}, { immediate: true });

/**
 * Filter vendors based on input
 */
const filterVendors = async (
  val: string,
  update: (fn: () => void) => void,
  abort: () => void
) => {
  if (val.length < 1) {
    update(() => {
      selectOptions.value = [];
    });
    return;
  }

  const results = await vendorsStore.searchVendors(val);

  update(() => {
    selectOptions.value = results.map(v => ({
      id: v.id,
      name: v.name,
      phone: v.phone,
      primaryAddress: v.addresses?.[0]?.one_line_address,
    }));
  });
};

const onInputChange = (val: string) => {
  inputValue.value = val;
};

const handleVendorSelect = async (vendorId: number | null) => {
  emit('update:modelValue', vendorId);

  if (vendorId) {
    // Fetch full vendor details
    try {
      const vendor = await vendorsStore.fetchVendor(vendorId);
      selectedVendorName.value = vendor.name;
      emit('vendor-selected', vendor);
    } catch {
      emit('vendor-selected', null);
    }
  } else {
    selectedVendorName.value = '';
    emit('vendor-selected', null);
  }
};

const handleClear = () => {
  emit('update:modelValue', null);
  emit('vendor-selected', null);
  inputValue.value = '';
  selectedVendorName.value = '';
};

const openCreateDialog = () => {
  newVendor.value = {
    name: inputValue.value,
    phone: '',
    email: '',
  };
  duplicateCandidates.value = [];
  showCreateDialog.value = true;

  // Close the select dropdown
  selectRef.value?.hidePopup();
};

const closeCreateDialog = () => {
  showCreateDialog.value = false;
  duplicateCandidates.value = [];
  newVendor.value = { name: '', phone: '', email: '' };
};

const submitCreateVendor = async () => {
  if (!newVendor.value.name) return;

  creating.value = true;
  try {
    const result = await vendorsStore.createVendor({
      name: newVendor.value.name,
      phone: newVendor.value.phone || undefined,
      email: newVendor.value.email || undefined,
      force_create: false,
    });

    if (result.status === 'duplicates_found' && result.candidates) {
      duplicateCandidates.value = result.candidates;
      return;
    }

    if (result.status === 'created' && result.data) {
      closeCreateDialog();
      selectedVendorName.value = result.data.name;
      emit('update:modelValue', result.data.id);
      emit('vendor-created', result.data);
      emit('vendor-selected', result.data);
    }
  } finally {
    creating.value = false;
  }
};

const forceCreateVendor = async () => {
  if (!newVendor.value.name) return;

  creating.value = true;
  try {
    const result = await vendorsStore.createVendor({
      name: newVendor.value.name,
      phone: newVendor.value.phone || undefined,
      email: newVendor.value.email || undefined,
      force_create: true,
    });

    if (result.status === 'created' && result.data) {
      closeCreateDialog();
      selectedVendorName.value = result.data.name;
      emit('update:modelValue', result.data.id);
      emit('vendor-created', result.data);
      emit('vendor-selected', result.data);
    }
  } finally {
    creating.value = false;
  }
};

const selectExistingVendor = async (candidate: VendorDuplicateCandidate) => {
  closeCreateDialog();
  selectedVendorName.value = candidate.name;
  emit('update:modelValue', candidate.id);

  // Fetch full vendor details
  try {
    const vendor = await vendorsStore.fetchVendor(candidate.id);
    selectedVendorName.value = vendor.name;
    emit('vendor-selected', vendor);
  } catch {
    // Still emit the ID even if fetch fails
  }
};
</script>

<style scoped lang="scss">
.mobile-form-field {
  width: 100%;
  margin-bottom: 8px;
}

.mobile-select {
  :deep(.q-field__control) {
    min-height: 44px;
  }

  :deep(.q-field__label) {
    color: rgba(0, 0, 0, 0.6);
  }

  :deep(.q-field__messages) {
    font-size: 12px;
    padding-top: 4px;
  }
}
</style>

<style lang="scss">
.mobile-select-popup {
  min-width: 0 !important;

  .q-item {
    min-height: 36px;
    padding: 4px 12px;
  }

  .q-item__label {
    white-space: normal;
    word-break: break-word;
  }
}
</style>
