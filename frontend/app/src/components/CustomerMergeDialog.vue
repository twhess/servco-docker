<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    maximized
  >
    <q-card>
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">
          Review Merge Candidates
          <q-badge v-if="summary" color="warning" class="q-ml-sm">
            {{ summary.pending }} pending
          </q-badge>
        </div>
        <q-space />
        <q-btn icon="close" flat round dense @click="closeDialog" />
      </q-card-section>

      <!-- Summary Stats -->
      <q-card-section v-if="summary" class="q-pt-sm q-pb-none">
        <div class="row q-col-gutter-sm">
          <div class="col-auto">
            <q-chip dense size="sm" color="warning" text-color="dark">
              {{ summary.pending }} Pending
            </q-chip>
          </div>
          <div class="col-auto">
            <q-chip dense size="sm" color="positive">
              {{ summary.merged }} Merged
            </q-chip>
          </div>
          <div class="col-auto">
            <q-chip dense size="sm" color="info">
              {{ summary.created_new }} Created New
            </q-chip>
          </div>
          <div class="col-auto">
            <q-chip dense size="sm" color="grey">
              {{ summary.skipped }} Skipped
            </q-chip>
          </div>
        </div>
      </q-card-section>

      <!-- Main Content -->
      <q-card-section class="q-pt-md" style="height: calc(100vh - 180px); overflow-y: auto;">
        <!-- List View -->
        <div v-if="!selectedCandidate">
          <div v-if="candidates.length === 0 && !loading" class="text-center q-py-xl">
            <q-icon name="check_circle" color="positive" size="64px" />
            <div class="text-h6 q-mt-md">All Clear!</div>
            <div class="text-body2 text-grey-7">No pending merge candidates to review.</div>
          </div>

          <q-list v-else bordered separator class="rounded-borders">
            <q-item
              v-for="candidate in candidates"
              :key="candidate.id"
              clickable
              @click="selectCandidate(candidate)"
            >
              <q-item-section>
                <q-item-label class="text-weight-medium">
                  {{ candidate.incoming_data.formatted_name || candidate.incoming_data.company_name }}
                </q-item-label>
                <q-item-label caption>
                  Matches: {{ candidate.matched_customer?.formatted_name }}
                </q-item-label>
                <q-item-label caption>
                  <span v-if="candidate.incoming_data.dot_number">
                    DOT: {{ candidate.incoming_data.dot_number }}
                  </span>
                  <span v-if="candidate.incoming_data.phone">
                    | {{ candidate.incoming_data.phone }}
                  </span>
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge :color="getScoreColor(candidate.match_score)">
                  {{ candidate.match_score }}% match
                </q-badge>
              </q-item-section>
              <q-item-section side>
                <div class="row q-gutter-xs">
                  <q-icon
                    v-if="candidate.match_reasons.name"
                    name="badge"
                    color="positive"
                    size="xs"
                  >
                    <q-tooltip>Name match</q-tooltip>
                  </q-icon>
                  <q-icon
                    v-if="candidate.match_reasons.dot"
                    name="local_shipping"
                    color="positive"
                    size="xs"
                  >
                    <q-tooltip>DOT match</q-tooltip>
                  </q-icon>
                  <q-icon
                    v-if="candidate.match_reasons.phone"
                    name="phone"
                    color="positive"
                    size="xs"
                  >
                    <q-tooltip>Phone match</q-tooltip>
                  </q-icon>
                  <q-icon
                    v-if="candidate.match_reasons.address"
                    name="location_on"
                    color="positive"
                    size="xs"
                  >
                    <q-tooltip>Address match</q-tooltip>
                  </q-icon>
                </div>
              </q-item-section>
              <q-item-section side>
                <q-icon name="chevron_right" />
              </q-item-section>
            </q-item>
          </q-list>

          <!-- Batch Actions -->
          <div v-if="candidates.length > 0" class="q-mt-md row q-gutter-sm justify-end">
            <q-btn
              flat
              color="grey"
              label="Skip All Pending"
              icon="skip_next"
              @click="batchSkip"
              :loading="batchLoading"
            />
            <q-btn
              flat
              color="info"
              label="Create All as New"
              icon="add"
              @click="batchCreateNew"
              :loading="batchLoading"
            />
          </div>
        </div>

        <!-- Comparison View -->
        <div v-else>
          <q-btn
            flat
            icon="arrow_back"
            label="Back to List"
            @click="selectedCandidate = null"
            class="q-mb-md"
          />

          <div class="text-subtitle1 text-weight-medium q-mb-md">
            Compare and Choose Fields
            <q-badge :color="getScoreColor(comparison?.match_score || 0)" class="q-ml-sm">
              {{ comparison?.match_score }}% match
            </q-badge>
          </div>

          <!-- Comparison Table -->
          <q-card flat bordered class="q-mb-md">
            <q-table
              flat
              :rows="comparisonRows"
              :columns="comparisonColumns"
              row-key="field"
              hide-pagination
              :pagination="{ rowsPerPage: 0 }"
            >
              <template v-slot:body-cell-existing="props">
                <q-td :props="props" :class="{ 'bg-blue-1': props.row.isDifferent }">
                  <q-radio
                    v-if="props.row.isDifferent && props.row.field !== 'id'"
                    v-model="fieldSelections[props.row.field]"
                    val="existing"
                    dense
                    class="q-mr-sm"
                  />
                  <span :class="{ 'text-weight-medium': fieldSelections[props.row.field] === 'existing' }">
                    {{ formatValue(props.row.existing) }}
                  </span>
                </q-td>
              </template>

              <template v-slot:body-cell-incoming="props">
                <q-td :props="props" :class="{ 'bg-green-1': props.row.isDifferent }">
                  <q-radio
                    v-if="props.row.isDifferent && props.row.field !== 'id'"
                    v-model="fieldSelections[props.row.field]"
                    val="incoming"
                    dense
                    class="q-mr-sm"
                  />
                  <span :class="{ 'text-weight-medium': fieldSelections[props.row.field] === 'incoming' }">
                    {{ formatValue(props.row.incoming) }}
                  </span>
                </q-td>
              </template>
            </q-table>
          </q-card>

          <!-- Actions -->
          <div class="row q-gutter-md justify-center q-mt-lg">
            <q-btn
              color="primary"
              label="Merge with Existing"
              icon="merge_type"
              @click="resolveMerge"
              :loading="resolving"
            />
            <q-btn
              color="info"
              label="Create as New Customer"
              icon="add"
              @click="resolveCreateNew"
              :loading="resolving"
            />
            <q-btn
              flat
              color="grey"
              label="Skip"
              icon="skip_next"
              @click="resolveSkip"
              :loading="resolving"
            />
          </div>
        </div>
      </q-card-section>

      <q-inner-loading :showing="loading" />
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue'
import { useCustomersStore } from 'src/stores/customers'
import type { CustomerMergeCandidate, MergeComparisonData, MergeSummary } from 'src/types/customers'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'resolved': []
}>()

const customersStore = useCustomersStore()

const loading = ref(false)
const resolving = ref(false)
const batchLoading = ref(false)
const selectedCandidate = ref<CustomerMergeCandidate | null>(null)
const comparison = ref<MergeComparisonData | null>(null)
const fieldSelections = reactive<Record<string, 'existing' | 'incoming'>>({})

const candidates = computed(() => customersStore.mergeCandidates)
const summary = computed(() => customersStore.mergeSummary)

const comparisonColumns = [
  { name: 'field', label: 'Field', field: 'field', align: 'left' as const },
  { name: 'existing', label: 'Existing Customer', field: 'existing', align: 'left' as const },
  { name: 'incoming', label: 'Incoming (Import)', field: 'incoming', align: 'left' as const },
]

const comparisonRows = computed(() => {
  if (!comparison.value) return []

  const fields = [
    { key: 'id', label: 'ID' },
    { key: 'fb_id', label: 'FullBay ID' },
    { key: 'company_name', label: 'Company Name' },
    { key: 'formatted_name', label: 'Formatted Name' },
    { key: 'detail', label: 'Detail' },
    { key: 'phone', label: 'Phone' },
    { key: 'phone_secondary', label: 'Secondary Phone' },
    { key: 'fax', label: 'Fax' },
    { key: 'email', label: 'Email' },
    { key: 'dot_number', label: 'DOT Number' },
    { key: 'customer_group', label: 'Customer Group' },
    { key: 'assigned_shop', label: 'Assigned Shop' },
    { key: 'sales_rep', label: 'Sales Rep' },
    { key: 'credit_terms', label: 'Credit Terms' },
    { key: 'credit_limit', label: 'Credit Limit' },
    { key: 'tax_location', label: 'Tax Location' },
    { key: 'price_level', label: 'Price Level' },
    { key: 'is_taxable', label: 'Taxable' },
    { key: 'is_active', label: 'Active' },
    { key: 'physical_address', label: 'Physical Address' },
    { key: 'billing_address', label: 'Billing Address' },
  ]

  return fields.map(f => ({
    field: f.label,
    fieldKey: f.key,
    existing: comparison.value?.existing[f.key],
    incoming: comparison.value?.incoming[f.key],
    isDifferent: comparison.value?.differences.includes(f.key),
  }))
})

watch(() => props.modelValue, async (val) => {
  if (val) {
    await loadCandidates()
  }
})

async function loadCandidates() {
  loading.value = true
  try {
    await customersStore.fetchMergeCandidates({ status: 'pending', limit: 100 })
    await customersStore.fetchMergeSummary()
  } finally {
    loading.value = false
  }
}

async function selectCandidate(candidate: CustomerMergeCandidate) {
  loading.value = true
  try {
    const result = await customersStore.fetchMergeCandidate(candidate.id)
    selectedCandidate.value = result.candidate
    comparison.value = result.comparison

    // Initialize field selections - default to existing for all different fields
    Object.keys(fieldSelections).forEach(key => delete fieldSelections[key])
    if (result.comparison?.differences) {
      result.comparison.differences.forEach(field => {
        // Default: keep existing values except for fb_id (take incoming if existing is empty)
        if (field === 'fb_id' && !result.comparison.existing.fb_id) {
          fieldSelections[field] = 'incoming'
        } else {
          fieldSelections[field] = 'existing'
        }
      })
    }
  } finally {
    loading.value = false
  }
}

async function resolveMerge() {
  if (!selectedCandidate.value) return

  resolving.value = true
  try {
    const result = await customersStore.resolveMerge(
      selectedCandidate.value.id,
      'merge',
      fieldSelections
    )

    if (result.success) {
      selectedCandidate.value = null
      comparison.value = null
      await loadCandidates()
      emit('resolved')
    }
  } finally {
    resolving.value = false
  }
}

async function resolveCreateNew() {
  if (!selectedCandidate.value) return

  resolving.value = true
  try {
    const result = await customersStore.resolveMerge(
      selectedCandidate.value.id,
      'create_new'
    )

    if (result.success) {
      selectedCandidate.value = null
      comparison.value = null
      await loadCandidates()
      emit('resolved')
    }
  } finally {
    resolving.value = false
  }
}

async function resolveSkip() {
  if (!selectedCandidate.value) return

  resolving.value = true
  try {
    const result = await customersStore.resolveMerge(
      selectedCandidate.value.id,
      'skip'
    )

    if (result.success) {
      selectedCandidate.value = null
      comparison.value = null
      await loadCandidates()
      emit('resolved')
    }
  } finally {
    resolving.value = false
  }
}

async function batchSkip() {
  const ids = candidates.value.map(c => c.id)
  if (ids.length === 0) return

  batchLoading.value = true
  try {
    await customersStore.batchResolveMerge(ids, 'skip')
    await loadCandidates()
    emit('resolved')
  } finally {
    batchLoading.value = false
  }
}

async function batchCreateNew() {
  const ids = candidates.value.map(c => c.id)
  if (ids.length === 0) return

  batchLoading.value = true
  try {
    await customersStore.batchResolveMerge(ids, 'create_new')
    await loadCandidates()
    emit('resolved')
  } finally {
    batchLoading.value = false
  }
}

function closeDialog() {
  emit('update:modelValue', false)
  selectedCandidate.value = null
  comparison.value = null
}

function getScoreColor(score: number): string {
  if (score >= 80) return 'positive'
  if (score >= 60) return 'warning'
  return 'grey'
}

function formatValue(value: any): string {
  if (value === null || value === undefined) return '-'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number') return value.toLocaleString()
  return String(value)
}
</script>
