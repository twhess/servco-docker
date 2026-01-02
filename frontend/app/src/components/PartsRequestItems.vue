<template>
  <div class="parts-request-items">
    <!-- Header -->
    <div class="row items-center no-wrap q-mb-xs">
      <div class="text-caption text-weight-medium text-grey-8">Line Items</div>
      <q-space />
      <q-btn
        v-if="!readonly"
        flat
        dense
        size="xs"
        color="primary"
        icon="add"
        label="Add"
        @click="showAddDialog = true"
      />
    </div>

    <!-- Items List -->
    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="24px" />
    </div>

    <div v-else-if="localItems.length === 0" class="text-grey-6 text-center q-py-md">
      No items added yet
    </div>

    <q-list v-else separator class="rounded-borders bg-grey-1">
      <q-item
        v-for="(item, index) in localItems"
        :key="item.id || `new-${index}`"
        class="q-py-sm"
      >
        <!-- Verification checkbox for runners -->
        <q-item-section v-if="showVerification" side>
          <q-checkbox
            :model-value="item.is_verified"
            color="positive"
            @update:model-value="toggleVerification(item)"
          />
        </q-item-section>

        <q-item-section>
          <q-item-label class="text-weight-medium">
            <span class="text-primary">{{ item.quantity }}x</span>
            {{ item.description }}
          </q-item-label>
          <q-item-label v-if="item.part_number" caption>
            Part #: {{ item.part_number }}
          </q-item-label>
          <q-item-label v-if="item.notes" caption class="text-grey-7">
            {{ item.notes }}
          </q-item-label>
          <q-item-label v-if="item.is_verified && item.verified_by" caption class="text-positive">
            Verified by {{ item.verified_by.name }}
          </q-item-label>
        </q-item-section>

        <q-item-section v-if="!readonly" side>
          <div class="row no-wrap q-gutter-xs">
            <q-btn
              flat
              dense
              round
              size="sm"
              icon="edit"
              color="grey-7"
              @click="editItem(item, index)"
            />
            <q-btn
              flat
              dense
              round
              size="sm"
              icon="delete"
              color="negative"
              @click="removeItem(index)"
            />
          </div>
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Summary -->
    <div v-if="localItems.length > 0" class="q-mt-sm text-caption text-grey-7">
      {{ localItems.length }} item{{ localItems.length !== 1 ? 's' : '' }}
      <template v-if="showVerification">
        ({{ verifiedCount }} verified)
      </template>
    </div>

    <!-- Add/Edit Item Dialog -->
    <q-dialog v-model="showAddDialog" persistent>
      <q-card style="min-width: 320px">
        <q-card-section>
          <div class="text-h6">{{ editingIndex !== null ? 'Edit Item' : 'Add Item' }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-input
            v-model="itemForm.description"
            label="Description *"
            outlined
            dense
            class="q-mb-sm"
            :rules="[val => !!val || 'Description is required']"
          />

          <q-input
            v-model.number="itemForm.quantity"
            label="Quantity *"
            outlined
            dense
            type="number"
            min="1"
            class="q-mb-sm"
            :rules="[val => val >= 1 || 'Quantity must be at least 1']"
          />

          <q-input
            v-model="itemForm.part_number"
            label="Part Number"
            outlined
            dense
            class="q-mb-sm"
          />

          <q-input
            v-model="itemForm.notes"
            label="Notes"
            outlined
            dense
            type="textarea"
            rows="2"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" color="grey-7" @click="cancelDialog" :disable="saving" />
          <q-btn
            flat
            :label="editingIndex !== null ? 'Update' : 'Add'"
            color="primary"
            :disable="!itemForm.description || itemForm.quantity < 1"
            :loading="saving"
            @click="saveItem"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePartsRequestsStore } from 'src/stores/partsRequests';

interface LocalItem {
  id?: number | undefined;
  description: string;
  quantity: number;
  part_number: string | null;
  notes: string | null;
  is_verified: boolean;
  verified_by?: { id: number; name: string } | null | undefined;
}

const props = defineProps<{
  modelValue?: LocalItem[];
  requestId?: number | null;
  readonly?: boolean;
  showVerification?: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: LocalItem[]): void;
  (e: 'verify', item: LocalItem): void;
  (e: 'unverify', item: LocalItem): void;
  (e: 'count-changed', count: number): void;
}>();

const store = usePartsRequestsStore();
const localItems = ref<LocalItem[]>(props.modelValue ? [...props.modelValue] : []);
const loading = ref(false);
const saving = ref(false);

// Determine if we're in API mode (requestId provided) or local mode (modelValue)
const isApiMode = computed(() => !!props.requestId);

// Watch modelValue changes in local mode
watch(
  () => props.modelValue,
  (newValue) => {
    if (!isApiMode.value && newValue) {
      localItems.value = [...newValue];
    }
  },
  { deep: true }
);

// Watch requestId changes in API mode
watch(
  () => props.requestId,
  () => {
    if (isApiMode.value) {
      loadItems();
    }
  },
  { immediate: true }
);

async function loadItems() {
  if (!props.requestId) {
    localItems.value = [];
    return;
  }

  loading.value = true;
  try {
    const items = await store.fetchItems(props.requestId);
    localItems.value = items.map(item => ({
      id: item.id,
      description: item.description,
      quantity: item.quantity,
      part_number: item.part_number,
      notes: item.notes,
      is_verified: item.is_verified,
      verified_by: item.verified_by ?? null,
    }));
    emit('count-changed', localItems.value.length);
  } catch {
    // Error handled in store
  } finally {
    loading.value = false;
  }
}

const showAddDialog = ref(false);
const editingIndex = ref<number | null>(null);
const editingItem = ref<LocalItem | null>(null);

const itemForm = ref({
  description: '',
  quantity: 1,
  part_number: '',
  notes: '',
});

const verifiedCount = computed(() => {
  return localItems.value.filter((item) => item.is_verified).length;
});

function resetForm() {
  itemForm.value = {
    description: '',
    quantity: 1,
    part_number: '',
    notes: '',
  };
  editingIndex.value = null;
  editingItem.value = null;
}

function editItem(item: LocalItem, index: number) {
  itemForm.value = {
    description: item.description,
    quantity: item.quantity,
    part_number: item.part_number || '',
    notes: item.notes || '',
  };
  editingIndex.value = index;
  editingItem.value = item;
  showAddDialog.value = true;
}

async function saveItem() {
  saving.value = true;

  try {
    if (isApiMode.value && props.requestId) {
      // API mode - save to server
      if (editingItem.value?.id) {
        // Update existing item
        const updated = await store.updateItem(props.requestId, editingItem.value.id, {
          description: itemForm.value.description,
          quantity: itemForm.value.quantity,
          part_number: itemForm.value.part_number || null,
          notes: itemForm.value.notes || null,
        });
        const index = localItems.value.findIndex(i => i.id === editingItem.value!.id);
        const existingAtIndex = localItems.value[index];
        if (index !== -1 && existingAtIndex) {
          localItems.value[index] = {
            ...existingAtIndex,
            description: updated.description,
            quantity: updated.quantity,
            part_number: updated.part_number,
            notes: updated.notes,
          };
        }
      } else {
        // Add new item
        const newItem = await store.addItem(props.requestId, {
          description: itemForm.value.description,
          quantity: itemForm.value.quantity,
          part_number: itemForm.value.part_number || undefined,
          notes: itemForm.value.notes || undefined,
        });
        localItems.value.push({
          id: newItem.id,
          description: newItem.description,
          quantity: newItem.quantity,
          part_number: newItem.part_number,
          notes: newItem.notes,
          is_verified: newItem.is_verified,
          verified_by: newItem.verified_by ?? null,
        });
      }
    } else {
      // Local mode - update local array
      const newItem: LocalItem = {
        description: itemForm.value.description,
        quantity: itemForm.value.quantity,
        part_number: itemForm.value.part_number || null,
        notes: itemForm.value.notes || null,
        is_verified: false,
      };

      if (editingIndex.value !== null) {
        // Update existing item
        const existingItem = localItems.value[editingIndex.value];
        if (existingItem) {
          newItem.id = existingItem.id;
          newItem.is_verified = existingItem.is_verified;
          newItem.verified_by = existingItem.verified_by;
        }
        localItems.value[editingIndex.value] = newItem;
      } else {
        // Add new item
        localItems.value.push(newItem);
      }

      emit('update:modelValue', localItems.value);
    }

    showAddDialog.value = false;
    resetForm();
  } catch {
    // Error handled in store
  } finally {
    saving.value = false;
  }
}

async function removeItem(index: number) {
  const item = localItems.value[index];
  if (!item) return;

  if (isApiMode.value && props.requestId && item.id) {
    // API mode - delete from server
    try {
      await store.removeItem(props.requestId, item.id);
      localItems.value.splice(index, 1);
    } catch {
      // Error handled in store
    }
  } else {
    // Local mode - just remove from array
    localItems.value.splice(index, 1);
    emit('update:modelValue', localItems.value);
  }
}

function cancelDialog() {
  showAddDialog.value = false;
  resetForm();
}

async function toggleVerification(item: LocalItem) {
  if (isApiMode.value && props.requestId && item.id) {
    // API mode - call verify/unverify endpoint
    try {
      if (item.is_verified) {
        await store.unverifyItem(props.requestId, item.id);
      } else {
        await store.verifyItem(props.requestId, item.id);
      }
      // Reload items to get updated verification info
      await loadItems();
    } catch {
      // Error handled in store
    }
  } else {
    // Local mode - emit events
    if (item.is_verified) {
      emit('unverify', item);
    } else {
      emit('verify', item);
    }
  }
}
</script>

<style scoped>
.parts-request-items {
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

.parts-request-items :deep(.q-item) {
  min-height: 40px;
  padding: 4px 8px;
}

.parts-request-items :deep(.q-item__section--main) {
  min-width: 0;
  overflow: hidden;
}

.parts-request-items :deep(.q-item__label) {
  word-break: break-word;
  overflow-wrap: break-word;
}

.parts-request-items :deep(.q-item__section--side) {
  padding-left: 4px;
  flex-shrink: 0;
}
</style>
