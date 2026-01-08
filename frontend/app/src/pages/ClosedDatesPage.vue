<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h5">Closed Dates</div>
      <q-btn color="primary" icon="add" label="Add Closed Date" @click="openAddDialog" />
    </div>

    <q-card>
      <q-card-section>
        <div class="row q-gutter-md q-mb-md">
          <q-select
            v-model="selectedYear"
            :options="yearOptions"
            label="Year"
            dense
            outlined
            style="min-width: 120px"
            @update:model-value="loadClosedDates"
          />
        </div>
      </q-card-section>

      <q-table
        :rows="closedDatesStore.closedDates"
        :columns="columns"
        row-key="id"
        :loading="closedDatesStore.loading"
        flat
        bordered
      >
        <template #body-cell-date="props">
          <q-td :props="props">
            <div class="text-weight-medium">
              {{ formatDate(props.row.date) }}
            </div>
            <div class="text-caption text-grey-6">
              {{ getDayOfWeek(props.row.date) }}
            </div>
          </q-td>
        </template>

        <template #body-cell-name="props">
          <q-td :props="props">
            {{ props.row.name }}
          </q-td>
        </template>

        <template #body-cell-notes="props">
          <q-td :props="props">
            <span v-if="props.row.notes" class="text-grey-7">{{ props.row.notes }}</span>
            <span v-else class="text-grey-5">-</span>
          </q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props">
            <q-btn flat dense round icon="edit" color="primary" @click="openEditDialog(props.row)">
              <q-tooltip>Edit</q-tooltip>
            </q-btn>
            <q-btn
              flat
              dense
              round
              icon="delete"
              color="negative"
              @click="confirmDelete(props.row)"
            >
              <q-tooltip>Delete</q-tooltip>
            </q-btn>
          </q-td>
        </template>

        <template #no-data>
          <div class="text-center text-grey-6 q-py-lg">
            No closed dates for {{ selectedYear }}
          </div>
        </template>
      </q-table>
    </q-card>

    <!-- Add/Edit Dialog -->
    <q-dialog v-model="dialogOpen" persistent>
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">{{ editingDate ? 'Edit Closed Date' : 'Add Closed Date' }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-input
            v-model="form.date"
            label="Date"
            type="date"
            outlined
            dense
            class="q-mb-md"
            :rules="[(v) => !!v || 'Date is required']"
          />

          <q-input
            v-model="form.name"
            label="Name"
            outlined
            dense
            class="q-mb-md"
            placeholder="e.g., Christmas Day, Thanksgiving"
            :rules="[(v) => !!v || 'Name is required']"
          />

          <q-input
            v-model="form.notes"
            label="Notes (optional)"
            type="textarea"
            outlined
            dense
            rows="2"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="closeDialog" />
          <q-btn
            color="primary"
            :label="editingDate ? 'Save' : 'Add'"
            :loading="closedDatesStore.loading"
            @click="saveClosedDate"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { useClosedDatesStore } from 'src/stores/closedDates'
import type { ClosedDate } from 'src/stores/closedDates'

const $q = useQuasar()
const closedDatesStore = useClosedDatesStore()

const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)
const dialogOpen = ref(false)
const editingDate = ref<ClosedDate | null>(null)

const form = ref({
  date: '',
  name: '',
  notes: '',
})

const yearOptions = computed(() => {
  const years: number[] = []
  for (let y = currentYear - 1; y <= currentYear + 2; y++) {
    years.push(y)
  }
  return years
})

const columns = [
  { name: 'date', label: 'Date', field: 'date', align: 'left' as const, sortable: true },
  { name: 'name', label: 'Name', field: 'name', align: 'left' as const },
  { name: 'notes', label: 'Notes', field: 'notes', align: 'left' as const },
  { name: 'actions', label: '', field: 'actions', align: 'right' as const },
]

onMounted(() => {
  void loadClosedDates()
})

async function loadClosedDates() {
  try {
    await closedDatesStore.fetchClosedDates({ year: selectedYear.value })
  } catch {
    $q.notify({
      type: 'negative',
      message: 'Failed to load closed dates',
    })
  }
}

function formatDate(dateString: string): string {
  const date = new Date(dateString + 'T00:00:00')
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function getDayOfWeek(dateString: string): string {
  const date = new Date(dateString + 'T00:00:00')
  return date.toLocaleDateString('en-US', { weekday: 'long' })
}

function openAddDialog() {
  editingDate.value = null
  form.value = {
    date: '',
    name: '',
    notes: '',
  }
  dialogOpen.value = true
}

function openEditDialog(closedDate: ClosedDate) {
  editingDate.value = closedDate
  form.value = {
    date: closedDate.date,
    name: closedDate.name,
    notes: closedDate.notes || '',
  }
  dialogOpen.value = true
}

function closeDialog() {
  dialogOpen.value = false
  editingDate.value = null
}

async function saveClosedDate() {
  if (!form.value.date || !form.value.name) {
    $q.notify({
      type: 'warning',
      message: 'Please fill in required fields',
    })
    return
  }

  try {
    if (editingDate.value) {
      await closedDatesStore.updateClosedDate(editingDate.value.id, {
        date: form.value.date,
        name: form.value.name,
        notes: form.value.notes || null,
      })
      $q.notify({
        type: 'positive',
        message: 'Closed date updated',
      })
    } else {
      const createData: { date: string; name: string; notes?: string } = {
        date: form.value.date,
        name: form.value.name,
      }
      if (form.value.notes) {
        createData.notes = form.value.notes
      }
      await closedDatesStore.createClosedDate(createData)
      $q.notify({
        type: 'positive',
        message: 'Closed date added',
      })
    }
    closeDialog()
  } catch (error: unknown) {
    const err = error as { response?: { data?: { message?: string } } }
    $q.notify({
      type: 'negative',
      message: err.response?.data?.message || 'Failed to save closed date',
    })
  }
}

function confirmDelete(closedDate: ClosedDate) {
  $q.dialog({
    title: 'Delete Closed Date',
    message: `Are you sure you want to delete "${closedDate.name}" (${formatDate(closedDate.date)})?`,
    cancel: true,
    persistent: true,
  }).onOk(() => {
    void (async () => {
      try {
        await closedDatesStore.deleteClosedDate(closedDate.id)
        $q.notify({
          type: 'positive',
          message: 'Closed date deleted',
        })
      } catch {
        $q.notify({
          type: 'negative',
          message: 'Failed to delete closed date',
        })
      }
    })()
  })
}
</script>
