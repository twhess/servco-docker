<template>
  <div :data-field-name="name" class="mobile-form-field">
    <q-select
      ref="selectRef"
      :model-value="modelValue"
      @update:model-value="handleCustomerSelect"
      :label="label"
      :options="selectOptions"
      option-value="id"
      option-label="formatted_name"
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
      @filter="filterCustomers"
      @input-value="onInputChange"
      filled
      stack-label
      :dense="false"
      options-dense
      class="mobile-select"
      popup-content-class="mobile-select-popup"
      popup-content-style="max-width: 100%"
      behavior="menu"
      :loading="customersStore.searching"
    >
      <template v-slot:prepend>
        <q-icon :name="icon || 'business'" />
      </template>

      <template v-slot:selected-item="scope">
        <span v-if="currentDisplayName">{{ currentDisplayName }}</span>
      </template>

      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>
              {{ scope.opt.formatted_name }}
              <span v-if="scope.opt.detail" class="text-grey-6">({{ scope.opt.detail }})</span>
            </q-item-label>
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
              Add "{{ inputValue }}" as new customer
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>

      <template v-slot:no-option v-if="inputValue">
        <q-item>
          <q-item-section>
            <q-item-label>No customers found</q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable @click="openCreateDialog" v-if="allowCreate">
          <q-item-section avatar>
            <q-icon name="add_circle" color="primary" />
          </q-item-section>
          <q-item-section>
            <q-item-label class="text-primary">
              Add "{{ inputValue }}" as new customer
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

    <!-- Create Customer Dialog -->
    <q-dialog v-model="showCreateDialog" persistent>
      <q-card style="min-width: 350px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">Add New Customer</div>
        </q-card-section>

        <!-- Duplicate Warning -->
        <q-card-section v-if="duplicateCandidates.length > 0" class="q-pt-none">
          <q-banner class="bg-warning text-dark">
            <template v-slot:avatar>
              <q-icon name="warning" />
            </template>
            Similar customers found. Did you mean one of these?
          </q-banner>
          <q-list bordered separator class="q-mt-sm">
            <q-item
              v-for="candidate in duplicateCandidates"
              :key="candidate.id"
              clickable
              @click="selectExistingCustomer(candidate)"
            >
              <q-item-section>
                <q-item-label>{{ candidate.formatted_name }}</q-item-label>
                <q-item-label caption v-if="candidate.phone">
                  {{ candidate.phone }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge color="grey">
                  {{ Math.round(candidate.score) }}% match
                </q-badge>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-form @submit.prevent="submitCreateCustomer" class="q-gutter-sm">
            <q-input
              v-model="newCustomer.company_name"
              label="Company Name *"
              filled
              :rules="[(v: string) => !!v || 'Company name is required']"
            />
            <q-input
              v-model="newCustomer.phone"
              label="Phone"
              filled
              type="tel"
              mask="(###) ###-####"
            />
            <q-input
              v-model="newCustomer.email"
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
            @click="forceCreateCustomer"
            :loading="creating"
          />
          <q-btn
            v-else
            flat
            label="Create"
            color="primary"
            @click="submitCreateCustomer"
            :loading="creating"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useCustomersStore } from 'src/stores/customers';
import type { Customer, CustomerSearchResult, CustomerDuplicateCandidate } from 'src/types/customers';

interface SelectOption {
  id: number;
  formatted_name: string;
  detail?: string | null;
  phone?: string | null;
  primaryAddress?: string;
}

interface Props {
  name: string;
  modelValue: number | null;
  initialCustomerName?: string;
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
  label: 'Customer',
  readonly: false,
  disable: false,
  required: false,
  clearable: true,
  allowCreate: true,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void;
  (e: 'customer-selected', customer: Customer | null): void;
  (e: 'customer-created', customer: Customer): void;
}>();

const customersStore = useCustomersStore();
const selectRef = ref<any>(null);

const inputValue = ref('');
const selectedCustomerName = ref(props.initialCustomerName || '');
const selectOptions = ref<SelectOption[]>([]);
const showCreateDialog = ref(false);
const duplicateCandidates = ref<CustomerDuplicateCandidate[]>([]);
const creating = ref(false);

const newCustomer = ref({
  company_name: '',
  phone: '',
  email: '',
});

const showAddNewOption = computed(() => {
  return props.allowCreate && inputValue.value.length >= 2;
});

const currentDisplayName = computed(() => {
  return selectedCustomerName.value || props.initialCustomerName || '';
});

watch(() => props.initialCustomerName, (newName) => {
  if (newName) {
    selectedCustomerName.value = newName;
  }
}, { immediate: true });

watch(() => props.modelValue, async (newValue) => {
  if (!newValue) {
    selectedCustomerName.value = '';
  } else if (!selectedCustomerName.value && !props.initialCustomerName) {
    try {
      const customer = await customersStore.fetchCustomer(newValue);
      selectedCustomerName.value = customer.formatted_name;
    } catch {
      selectedCustomerName.value = '';
    }
  }
}, { immediate: true });

const filterCustomers = async (
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

  const results = await customersStore.searchCustomers(val);

  update(() => {
    selectOptions.value = results.map(c => ({
      id: c.id,
      formatted_name: c.formatted_name,
      detail: c.detail,
      phone: c.phone,
      primaryAddress: c.addresses?.[0]?.one_line_address,
    }));
  });
};

const onInputChange = (val: string) => {
  inputValue.value = val;
};

const handleCustomerSelect = async (customerId: number | null) => {
  emit('update:modelValue', customerId);

  if (customerId) {
    try {
      const customer = await customersStore.fetchCustomer(customerId);
      selectedCustomerName.value = customer.formatted_name;
      emit('customer-selected', customer);
    } catch {
      emit('customer-selected', null);
    }
  } else {
    selectedCustomerName.value = '';
    emit('customer-selected', null);
  }
};

const handleClear = () => {
  emit('update:modelValue', null);
  emit('customer-selected', null);
  inputValue.value = '';
  selectedCustomerName.value = '';
};

const openCreateDialog = () => {
  newCustomer.value = {
    company_name: inputValue.value,
    phone: '',
    email: '',
  };
  duplicateCandidates.value = [];
  showCreateDialog.value = true;
  selectRef.value?.hidePopup();
};

const closeCreateDialog = () => {
  showCreateDialog.value = false;
  duplicateCandidates.value = [];
  newCustomer.value = { company_name: '', phone: '', email: '' };
};

const submitCreateCustomer = async () => {
  if (!newCustomer.value.company_name) return;

  creating.value = true;
  try {
    const result = await customersStore.createCustomer({
      company_name: newCustomer.value.company_name,
      phone: newCustomer.value.phone || undefined,
      email: newCustomer.value.email || undefined,
      force_create: false,
    });

    if (result.status === 'duplicates_found' && result.candidates) {
      duplicateCandidates.value = result.candidates;
      return;
    }

    if (result.status === 'created' && result.data) {
      closeCreateDialog();
      selectedCustomerName.value = result.data.formatted_name;
      emit('update:modelValue', result.data.id);
      emit('customer-created', result.data);
      emit('customer-selected', result.data);
    }
  } finally {
    creating.value = false;
  }
};

const forceCreateCustomer = async () => {
  if (!newCustomer.value.company_name) return;

  creating.value = true;
  try {
    const result = await customersStore.createCustomer({
      company_name: newCustomer.value.company_name,
      phone: newCustomer.value.phone || undefined,
      email: newCustomer.value.email || undefined,
      force_create: true,
    });

    if (result.status === 'created' && result.data) {
      closeCreateDialog();
      selectedCustomerName.value = result.data.formatted_name;
      emit('update:modelValue', result.data.id);
      emit('customer-created', result.data);
      emit('customer-selected', result.data);
    }
  } finally {
    creating.value = false;
  }
};

const selectExistingCustomer = async (candidate: CustomerDuplicateCandidate) => {
  closeCreateDialog();
  selectedCustomerName.value = candidate.formatted_name;
  emit('update:modelValue', candidate.id);

  try {
    const customer = await customersStore.fetchCustomer(candidate.id);
    selectedCustomerName.value = customer.formatted_name;
    emit('customer-selected', customer);
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
