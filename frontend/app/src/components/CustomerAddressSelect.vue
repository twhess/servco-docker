<template>
  <div :data-field-name="name" class="mobile-form-field">
    <q-select
      :model-value="modelValue"
      @update:model-value="handleSelect"
      :label="label"
      :options="addressOptions"
      option-value="id"
      option-label="displayLabel"
      emit-value
      map-options
      :hint="hint"
      :error="!!error"
      :error-message="error || undefined"
      :readonly="readonly"
      :disable="disable || !customerId"
      :required="required"
      filled
      stack-label
      :dense="false"
      options-dense
      class="mobile-select"
      popup-content-class="mobile-select-popup"
      popup-content-style="max-width: 100%"
      behavior="menu"
    >
      <template v-slot:prepend>
        <q-icon name="place" />
      </template>

      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>
              {{ scope.opt.label || scope.opt.addressType }}
              <q-badge v-if="scope.opt.isPrimary" color="primary" class="q-ml-xs">Primary</q-badge>
            </q-item-label>
            <q-item-label caption>{{ scope.opt.oneLine }}</q-item-label>
            <q-item-label caption v-if="scope.opt.instructions" class="text-grey-7">
              <q-icon name="info" size="xs" /> {{ scope.opt.instructions }}
            </q-item-label>
          </q-item-section>
          <q-item-section side v-if="scope.opt.phone">
            <q-item-label caption>{{ scope.opt.phone }}</q-item-label>
          </q-item-section>
        </q-item>
      </template>

      <template v-slot:selected-item="scope">
        <div class="row items-center no-wrap">
          <div class="col">
            <div>{{ scope.opt.label || scope.opt.addressType }}</div>
            <div class="text-caption text-grey-7">{{ scope.opt.oneLine }}</div>
          </div>
        </div>
      </template>

      <template v-slot:after-options v-if="allowCreate && customerId">
        <q-item clickable @click="openAddAddressDialog">
          <q-item-section avatar>
            <q-icon name="add_circle" color="primary" />
          </q-item-section>
          <q-item-section>
            <q-item-label class="text-primary">Add new address</q-item-label>
          </q-item-section>
        </q-item>
      </template>

      <template v-slot:no-option>
        <q-item v-if="customerId">
          <q-item-section>
            <q-item-label>No addresses for this customer</q-item-label>
          </q-item-section>
        </q-item>
        <q-item clickable @click="openAddAddressDialog" v-if="allowCreate && customerId">
          <q-item-section avatar>
            <q-icon name="add_circle" color="primary" />
          </q-item-section>
          <q-item-section>
            <q-item-label class="text-primary">Add address</q-item-label>
          </q-item-section>
        </q-item>
        <q-item v-if="!customerId">
          <q-item-section>
            <q-item-label class="text-grey-6">Select a customer first</q-item-label>
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

    <!-- Add Address Dialog -->
    <q-dialog v-model="showAddDialog" persistent>
      <q-card style="min-width: 350px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">Add Customer Address</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-form @submit.prevent="submitAddress" class="q-gutter-sm">
            <q-input
              v-model="newAddress.label"
              label="Label (e.g., Main Office, Yard)"
              filled
            />
            <q-input
              v-model="newAddress.line1"
              label="Street Address *"
              filled
              :rules="[(v: string) => !!v || 'Street address is required']"
            />
            <q-input
              v-model="newAddress.line2"
              label="Suite, Unit, etc."
              filled
            />
            <div class="row q-gutter-sm">
              <q-input
                v-model="newAddress.city"
                label="City *"
                filled
                class="col"
                :rules="[(v: string) => !!v || 'City is required']"
              />
              <q-select
                v-model="newAddress.state"
                label="State *"
                filled
                :options="stateOptions"
                emit-value
                map-options
                class="col-4"
                :rules="[(v: string) => !!v || 'State is required']"
              />
            </div>
            <div class="row q-gutter-sm">
              <q-input
                v-model="newAddress.postal_code"
                label="ZIP Code *"
                filled
                class="col"
                mask="#####-####"
                unmasked-value
                :rules="[(v: string) => !!v || 'ZIP is required']"
              />
              <q-input
                v-model="newAddress.phone"
                label="Phone"
                filled
                type="tel"
                mask="(###) ###-####"
                class="col"
              />
            </div>
            <q-input
              v-model="newAddress.instructions"
              label="Delivery Instructions"
              filled
              type="textarea"
              rows="2"
              hint="Gate codes, delivery hours, etc."
            />
            <q-select
              v-model="newAddress.address_type"
              label="Address Type"
              filled
              :options="addressTypeOptions"
              emit-value
              map-options
            />
            <q-checkbox
              v-model="newAddress.is_primary"
              label="Set as primary address"
            />
          </q-form>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeAddDialog" />
          <q-btn
            flat
            label="Add Address"
            color="primary"
            @click="submitAddress"
            :loading="saving"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useCustomersStore } from 'src/stores/customers';
import type { Address } from 'src/types/vendors';

interface AddressOption {
  id: number;
  label: string | null | undefined;
  oneLine: string;
  displayLabel: string;
  isPrimary: boolean;
  instructions?: string | null | undefined;
  phone?: string | null | undefined;
  addressType: string;
}

interface Props {
  name: string;
  modelValue: number | null;
  customerId: number | null;
  addresses?: Address[];
  label?: string;
  hint?: string;
  error?: string | null;
  readonly?: boolean;
  disable?: boolean;
  required?: boolean;
  clearable?: boolean;
  allowCreate?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Delivery Address',
  readonly: false,
  disable: false,
  required: false,
  clearable: true,
  allowCreate: true,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void;
  (e: 'address-selected', address: Address | null): void;
  (e: 'address-created', address: Address): void;
}>();

const customersStore = useCustomersStore();

const showAddDialog = ref(false);
const saving = ref(false);

const newAddress = ref({
  label: '',
  line1: '',
  line2: '',
  city: '',
  state: '',
  postal_code: '',
  phone: '',
  instructions: '',
  address_type: 'shipping' as 'pickup' | 'billing' | 'shipping' | 'physical' | 'other',
  is_primary: false,
});

// Address type options
const addressTypeOptions = [
  { label: 'Shipping', value: 'shipping' },
  { label: 'Physical', value: 'physical' },
  { label: 'Billing', value: 'billing' },
  { label: 'Other', value: 'other' },
];

// US State options
const stateOptions = [
  { label: 'Ohio', value: 'OH' },
  { label: 'Alabama', value: 'AL' },
  { label: 'Alaska', value: 'AK' },
  { label: 'Arizona', value: 'AZ' },
  { label: 'Arkansas', value: 'AR' },
  { label: 'California', value: 'CA' },
  { label: 'Colorado', value: 'CO' },
  { label: 'Connecticut', value: 'CT' },
  { label: 'Delaware', value: 'DE' },
  { label: 'Florida', value: 'FL' },
  { label: 'Georgia', value: 'GA' },
  { label: 'Hawaii', value: 'HI' },
  { label: 'Idaho', value: 'ID' },
  { label: 'Illinois', value: 'IL' },
  { label: 'Indiana', value: 'IN' },
  { label: 'Iowa', value: 'IA' },
  { label: 'Kansas', value: 'KS' },
  { label: 'Kentucky', value: 'KY' },
  { label: 'Louisiana', value: 'LA' },
  { label: 'Maine', value: 'ME' },
  { label: 'Maryland', value: 'MD' },
  { label: 'Massachusetts', value: 'MA' },
  { label: 'Michigan', value: 'MI' },
  { label: 'Minnesota', value: 'MN' },
  { label: 'Mississippi', value: 'MS' },
  { label: 'Missouri', value: 'MO' },
  { label: 'Montana', value: 'MT' },
  { label: 'Nebraska', value: 'NE' },
  { label: 'Nevada', value: 'NV' },
  { label: 'New Hampshire', value: 'NH' },
  { label: 'New Jersey', value: 'NJ' },
  { label: 'New Mexico', value: 'NM' },
  { label: 'New York', value: 'NY' },
  { label: 'North Carolina', value: 'NC' },
  { label: 'North Dakota', value: 'ND' },
  { label: 'Oklahoma', value: 'OK' },
  { label: 'Oregon', value: 'OR' },
  { label: 'Pennsylvania', value: 'PA' },
  { label: 'Rhode Island', value: 'RI' },
  { label: 'South Carolina', value: 'SC' },
  { label: 'South Dakota', value: 'SD' },
  { label: 'Tennessee', value: 'TN' },
  { label: 'Texas', value: 'TX' },
  { label: 'Utah', value: 'UT' },
  { label: 'Vermont', value: 'VT' },
  { label: 'Virginia', value: 'VA' },
  { label: 'Washington', value: 'WA' },
  { label: 'West Virginia', value: 'WV' },
  { label: 'Wisconsin', value: 'WI' },
  { label: 'Wyoming', value: 'WY' },
];

const formatAddressType = (type: string): string => {
  const types: Record<string, string> = {
    shipping: 'Shipping',
    physical: 'Physical',
    billing: 'Billing',
    pickup: 'Pickup',
    other: 'Other',
  };
  return types[type] || type;
};

const addressOptions = computed((): AddressOption[] => {
  if (!props.addresses) return [];

  return props.addresses.map(addr => ({
    id: addr.id,
    label: addr.label,
    oneLine: addr.one_line_address,
    displayLabel: addr.label
      ? `${addr.label} - ${addr.one_line_address}`
      : addr.one_line_address,
    isPrimary: addr.pivot?.is_primary || false,
    instructions: addr.instructions,
    phone: addr.phone,
    addressType: formatAddressType(addr.pivot?.address_type || 'other'),
  }));
});

// Auto-select primary address when customer changes
watch(() => props.addresses, (newAddresses) => {
  if (newAddresses && newAddresses.length > 0 && !props.modelValue) {
    const primary = newAddresses.find(a => a.pivot?.is_primary);
    if (primary) {
      emit('update:modelValue', primary.id);
      emit('address-selected', primary);
    }
  }
}, { immediate: true });

// Clear selection when customer changes
watch(() => props.customerId, () => {
  emit('update:modelValue', null);
  emit('address-selected', null);
});

const handleSelect = (addressId: number | null) => {
  emit('update:modelValue', addressId);

  if (addressId && props.addresses) {
    const address = props.addresses.find(a => a.id === addressId);
    emit('address-selected', address || null);
  } else {
    emit('address-selected', null);
  }
};

const handleClear = () => {
  emit('update:modelValue', null);
  emit('address-selected', null);
};

const openAddAddressDialog = () => {
  newAddress.value = {
    label: '',
    line1: '',
    line2: '',
    city: '',
    state: 'OH', // Default to Ohio
    postal_code: '',
    phone: '',
    instructions: '',
    address_type: 'shipping',
    is_primary: addressOptions.value.length === 0, // Auto-set primary if first address
  };
  showAddDialog.value = true;
};

const closeAddDialog = () => {
  showAddDialog.value = false;
};

const submitAddress = async () => {
  if (!newAddress.value.line1 || !newAddress.value.city ||
      !newAddress.value.state || !newAddress.value.postal_code) {
    return;
  }

  if (!props.customerId) return;

  saving.value = true;
  try {
    const customer = await customersStore.attachAddress(props.customerId, {
      address: {
        label: newAddress.value.label || null,
        line1: newAddress.value.line1,
        line2: newAddress.value.line2 || null,
        city: newAddress.value.city,
        state: newAddress.value.state,
        postal_code: newAddress.value.postal_code,
        phone: newAddress.value.phone || null,
        instructions: newAddress.value.instructions || null,
      },
      address_type: newAddress.value.address_type,
      is_primary: newAddress.value.is_primary,
    });

    closeAddDialog();

    // Select the newly created address
    if (customer.addresses && customer.addresses.length > 0) {
      const newAddr = customer.addresses[customer.addresses.length - 1];
      if (newAddr) {
        emit('update:modelValue', newAddr.id);
        emit('address-created', newAddr);
        emit('address-selected', newAddr);
      }
    }
  } finally {
    saving.value = false;
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
