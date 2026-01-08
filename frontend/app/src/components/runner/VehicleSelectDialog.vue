<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    persistent
  >
    <q-card style="min-width: 340px; max-width: 400px">
      <q-card-section class="bg-primary text-white">
        <div class="text-h6">Select Vehicle</div>
        <div class="text-caption">Choose the vehicle you're using today</div>
      </q-card-section>

      <q-card-section>
        <!-- Loading State -->
        <div v-if="loading" class="text-center q-py-md">
          <q-spinner-dots size="40px" color="primary" />
        </div>

        <template v-else>
          <!-- Known Vehicles -->
          <div v-if="vehicles.length > 0" class="q-mb-md">
            <div class="text-subtitle2 q-mb-sm">Company Vehicles</div>
            <q-list bordered separator class="rounded-borders">
              <q-item
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                clickable
                @click="selectKnownVehicle(vehicle)"
                :active="selectedVehicleId === vehicle.id && !isGeneric"
                active-class="bg-primary-1"
              >
                <q-item-section avatar>
                  <q-icon name="local_shipping" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ vehicle.name }}</q-item-label>
                  <q-item-label v-if="vehicle.description" caption>
                    {{ vehicle.description }}
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon
                    v-if="selectedVehicleId === vehicle.id && !isGeneric"
                    name="check_circle"
                    color="primary"
                  />
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- Generic Vehicle Option -->
          <div class="q-mb-md">
            <q-separator v-if="vehicles.length > 0" class="q-my-md" />
            <div class="text-subtitle2 q-mb-sm">Or Use Another Vehicle</div>

            <q-expansion-item
              v-model="showGenericForm"
              icon="directions_car"
              label="Personal / Other Vehicle"
              caption="Enter vehicle details manually"
              class="rounded-borders overflow-hidden"
              style="border: 1px solid #e0e0e0"
            >
              <q-card>
                <q-card-section>
                  <!-- Vehicle Type -->
                  <q-select
                    v-model="genericType"
                    :options="genericTypes"
                    label="Vehicle Type"
                    outlined
                    dense
                    emit-value
                    map-options
                    class="q-mb-md"
                    :rules="[v => !!v || 'Select vehicle type']"
                    behavior="menu"
                    popup-content-class="vehicle-type-dropdown"
                  />

                  <!-- Description -->
                  <q-input
                    v-model="genericDescription"
                    label="Vehicle Description"
                    placeholder="e.g., White Ford F-150"
                    outlined
                    dense
                    class="q-mb-md"
                  />

                  <!-- License Plate -->
                  <q-input
                    v-model="genericLicensePlate"
                    label="License Plate (optional)"
                    outlined
                    dense
                    class="q-mb-sm"
                  />
                </q-card-section>
              </q-card>
            </q-expansion-item>
          </div>
        </template>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn
          flat
          label="Cancel"
          color="grey"
          @click="$emit('update:modelValue', false)"
        />
        <q-btn
          color="primary"
          label="Confirm"
          :loading="submitting"
          :disable="!canSubmit"
          @click="handleSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { api } from 'boot/axios';
import { useQuasar } from 'quasar';

interface Vehicle {
  id: number;
  name: string;
  description: string | null;
}

interface GenericType {
  value: string;
  label: string;
}

const props = defineProps<{
  modelValue: boolean;
  runId?: number | null;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'selected', vehicle: {
    sessionId: number;
    isGeneric: boolean;
    vehicleName: string;
  }): void;
}>();

const $q = useQuasar();

const loading = ref(false);
const submitting = ref(false);
const vehicles = ref<Vehicle[]>([]);
const genericTypes = ref<GenericType[]>([]);

const selectedVehicleId = ref<number | null>(null);
const isGeneric = ref(false);
const showGenericForm = ref(false);
const genericType = ref<string | null>(null);
const genericDescription = ref('');
const genericLicensePlate = ref('');

const canSubmit = computed(() => {
  if (showGenericForm.value || isGeneric.value) {
    return !!genericType.value;
  }
  return selectedVehicleId.value !== null;
});

const selectKnownVehicle = (vehicle: Vehicle) => {
  selectedVehicleId.value = vehicle.id;
  isGeneric.value = false;
  showGenericForm.value = false;
};

const fetchVehicles = async () => {
  loading.value = true;
  try {
    const response = await api.get('/runner/vehicles');
    vehicles.value = response.data.vehicles || [];
    genericTypes.value = response.data.generic_types || [];
  } catch (error) {
    console.error('Failed to fetch vehicles:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to load vehicles',
      position: 'top',
    });
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  submitting.value = true;

  try {
    const payload: Record<string, unknown> = {
      is_generic: showGenericForm.value,
      run_id: props.runId || null,
    };

    if (showGenericForm.value) {
      payload.generic_vehicle_type = genericType.value;
      payload.generic_vehicle_description = genericDescription.value || null;
      payload.generic_license_plate = genericLicensePlate.value || null;
    } else {
      payload.vehicle_location_id = selectedVehicleId.value;
    }

    const response = await api.post('/runner/vehicle/select', payload);

    $q.notify({
      type: 'positive',
      message: 'Vehicle selected',
      position: 'top',
    });

    emit('selected', {
      sessionId: response.data.vehicle.session_id,
      isGeneric: response.data.vehicle.is_generic,
      vehicleName: response.data.vehicle.vehicle_name,
    });

    emit('update:modelValue', false);
  } catch (error) {
    console.error('Failed to select vehicle:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to select vehicle',
      position: 'top',
    });
  } finally {
    submitting.value = false;
  }
};

// Reset form when dialog opens
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    selectedVehicleId.value = null;
    isGeneric.value = false;
    showGenericForm.value = false;
    genericType.value = null;
    genericDescription.value = '';
    genericLicensePlate.value = '';
    void fetchVehicles();
  }
});

// When showing generic form, clear selected vehicle
watch(showGenericForm, (show) => {
  if (show) {
    selectedVehicleId.value = null;
    isGeneric.value = true;
  }
});

onMounted(() => {
  if (props.modelValue) {
    void fetchVehicles();
  }
});
</script>

<style scoped>
.bg-primary-1 {
  background-color: rgba(25, 118, 210, 0.1);
}
</style>

<style>
/* Constrain vehicle type dropdown width - needs to be unscoped since menu renders outside component */
.vehicle-type-dropdown {
  min-width: 200px !important;
  max-width: 280px !important;
}
</style>
