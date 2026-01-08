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

      <template v-slot:selected-item>
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

        <!-- Acronym Detection -->
        <q-card-section v-if="showAcronymPrompt" class="q-pt-none">
          <q-banner class="bg-blue-1" rounded>
            <template v-slot:avatar>
              <q-icon name="info" color="primary" />
            </template>
            <div class="text-body2">
              This looks like an acronym. Save as <strong>{{ acronymSuggestion }}</strong> (all caps)?
            </div>
            <template v-slot:action>
              <q-btn
                flat
                dense
                label="Use ALL CAPS"
                color="primary"
                @click="acceptAcronymSuggestion"
              />
              <q-btn
                flat
                dense
                label="Keep as typed"
                color="grey"
                @click="rejectAcronymSuggestion"
              />
            </template>
          </q-banner>
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
              @update:model-value="checkForAcronym"
              @blur="checkForAcronym"
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
import type { Vendor, VendorDuplicateCandidate } from 'src/types/vendors';
import { detectAcronym } from 'src/composables/useAcronymDetector';

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
const selectRef = ref<InstanceType<typeof import('quasar').QSelect> | null>(null);

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

// Acronym detection state
const showAcronymPrompt = ref(false);
const acronymSuggestion = ref('');
const isAcronymConfirmed = ref<boolean | null>(null);

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
  _abort: () => void
) => {
  if (val.length < 1) {
    update(() => {
      selectOptions.value = [];
    });
    return;
  }

  const results = await vendorsStore.searchVendors(val);

  update(() => {
    selectOptions.value = results.map(v => {
      const option: SelectOption = {
        id: v.id,
        name: v.name,
      };
      if (v.phone !== undefined) option.phone = v.phone;
      const addr = v.addresses?.[0]?.one_line_address;
      if (addr) option.primaryAddress = addr;
      return option;
    });
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
  // Reset acronym state
  showAcronymPrompt.value = false;
  acronymSuggestion.value = '';
  isAcronymConfirmed.value = null;
  showCreateDialog.value = true;

  // Close the select dropdown
  selectRef.value?.hidePopup();

  // Check for acronym on the initial value
  if (inputValue.value) {
    checkForAcronym();
  }
};

const closeCreateDialog = () => {
  showCreateDialog.value = false;
  duplicateCandidates.value = [];
  newVendor.value = { name: '', phone: '', email: '' };
  // Reset acronym state
  showAcronymPrompt.value = false;
  acronymSuggestion.value = '';
  isAcronymConfirmed.value = null;
};

// Acronym detection functions
function checkForAcronym() {
  // Don't show prompt if user already made a decision
  if (isAcronymConfirmed.value !== null) {
    return;
  }

  const name = newVendor.value.name.trim();
  if (name.length < 2) {
    showAcronymPrompt.value = false;
    acronymSuggestion.value = '';
    return;
  }

  const result = detectAcronym(name);
  if (result.isLikely) {
    acronymSuggestion.value = result.suggestedName;
    // Only show prompt if the suggestion is different from what they typed
    showAcronymPrompt.value = name !== result.suggestedName;
  } else {
    showAcronymPrompt.value = false;
    acronymSuggestion.value = '';
  }
}

function acceptAcronymSuggestion() {
  newVendor.value.name = acronymSuggestion.value;
  isAcronymConfirmed.value = true;
  showAcronymPrompt.value = false;
}

function rejectAcronymSuggestion() {
  isAcronymConfirmed.value = false;
  showAcronymPrompt.value = false;
}

const submitCreateVendor = async () => {
  if (!newVendor.value.name) return;

  creating.value = true;
  try {
    const result = await vendorsStore.createVendor({
      name: newVendor.value.name,
      phone: newVendor.value.phone || null,
      email: newVendor.value.email || null,
      // Pass is_acronym if user explicitly confirmed (true) or rejected (false)
      ...(isAcronymConfirmed.value !== null ? { is_acronym: isAcronymConfirmed.value } : {}),
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
      phone: newVendor.value.phone || null,
      email: newVendor.value.email || null,
      // Pass is_acronym if user explicitly confirmed (true) or rejected (false)
      ...(isAcronymConfirmed.value !== null ? { is_acronym: isAcronymConfirmed.value } : {}),
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
