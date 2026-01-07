<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    persistent
  >
    <q-card style="min-width: 320px">
      <q-card-section class="bg-orange-1">
        <div class="row items-center">
          <q-icon name="warning" color="orange" size="md" class="q-mr-sm" />
          <div class="text-h6 text-orange-10">Open Items</div>
        </div>
      </q-card-section>

      <q-card-section>
        <p>
          You left <strong>{{ stopName }}</strong> with
          <strong>{{ openItems.length }}</strong> open item(s):
        </p>

        <q-list dense bordered separator class="q-mb-md">
          <q-item v-for="item in displayItems" :key="item.id">
            <q-item-section avatar>
              <q-icon
                :name="item.action_at_stop === 'pickup' ? 'download' : 'upload'"
                :color="item.action_at_stop === 'pickup' ? 'positive' : 'primary'"
              />
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ item.reference_number }}</q-item-label>
              <q-item-label caption>
                {{ item.action_at_stop === 'pickup' ? 'Pickup' : 'Delivery' }}
              </q-item-label>
            </q-item-section>
          </q-item>
        </q-list>

        <div v-if="openItems.length > 3" class="text-caption text-grey-7 q-mb-md">
          ...and {{ openItems.length - 3 }} more
        </div>

        <p class="text-grey-8">What would you like to do?</p>
      </q-card-section>

      <q-card-actions vertical>
        <q-btn
          color="primary"
          icon="arrow_back"
          label="Go Back to Stop"
          class="full-width q-mb-sm"
          @click="$emit('go-back')"
        />
        <q-btn
          color="orange"
          icon="report_problem"
          label="Mark All as Exception"
          class="full-width q-mb-sm"
          outline
          @click="$emit('mark-exceptions')"
        />
        <q-btn
          flat
          label="Continue Anyway"
          class="full-width"
          @click="$emit('confirm-leave')"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface OpenItem {
  id: number;
  reference_number: string;
  status: { name: string; display_name: string; color: string };
  is_completed: boolean;
  action_at_stop: 'pickup' | 'dropoff';
}

const props = defineProps<{
  modelValue: boolean;
  openItems: OpenItem[];
  stopName: string;
}>();

defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'go-back'): void;
  (e: 'mark-exceptions'): void;
  (e: 'confirm-leave'): void;
}>();

const displayItems = computed(() => props.openItems.slice(0, 3));
</script>
