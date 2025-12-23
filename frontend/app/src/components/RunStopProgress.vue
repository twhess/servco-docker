<template>
  <div class="run-stop-progress">
    <q-card>
      <q-card-section>
        <div class="text-h6">{{ run.route?.name }}</div>
        <div class="text-caption text-grey-6">
          {{ formatDate(run.scheduled_date) }} • {{ formatTime(run.scheduled_time) }}
        </div>
      </q-card-section>

      <q-separator />

      <q-list>
        <q-expansion-item
          v-for="(stop, index) in orderedStops"
          :key="stop.id"
          :icon="getStopIcon(stop, index)"
          :label="getStopLabel(stop)"
          :caption="getStopCaption(stop)"
          :header-class="getStopHeaderClass(stop, index)"
          :default-opened="isCurrentStop(stop)"
        >
          <q-card>
            <q-card-section>
              <!-- Stop details -->
              <div class="text-subtitle2 q-mb-sm">
                {{ getStopDisplayName(stop) }}
              </div>

              <div class="row q-gutter-sm q-mb-md">
                <q-chip v-if="getStopActual(stop)?.arrived_at" size="sm" color="green" text-color="white">
                  Arrived: {{ formatDateTime(getStopActual(stop)!.arrived_at!) }}
                </q-chip>
                <q-chip v-if="getStopActual(stop)?.departed_at" size="sm" color="blue" text-color="white">
                  Departed: {{ formatDateTime(getStopActual(stop)!.departed_at!) }}
                </q-chip>
                <q-chip v-else size="sm" color="grey-4" text-color="grey-8">
                  ETA: {{ getEstimatedArrival(stop) }}
                </q-chip>
              </div>

              <!-- Requests at this stop -->
              <div v-if="getRequestsForStop(stop.id).length > 0">
                <div class="text-caption text-weight-medium q-mb-xs">
                  Tasks ({{ getStopActual(stop)?.tasks_completed || 0 }} / {{ getStopActual(stop)?.tasks_total || 0 }})
                </div>

                <q-list bordered separator>
                  <q-item
                    v-for="request in getRequestsForStop(stop.id)"
                    :key="request.id"
                    clickable
                    @click="$emit('request-click', request)"
                  >
                    <q-item-section>
                      <q-item-label>
                        <q-badge :color="getUrgencyColor(request.urgency)">
                          {{ request.reference_number }}
                        </q-badge>
                      </q-item-label>
                      <q-item-label caption>
                        {{ request.request_type.name }} • {{ request.status.name }}
                      </q-item-label>
                      <q-item-label caption>
                        {{ request.details }}
                      </q-item-label>
                    </q-item-section>

                    <q-item-section side>
                      <q-icon name="chevron_right" />
                    </q-item-section>
                  </q-item>
                </q-list>
              </div>

              <div v-else class="text-caption text-grey-6">
                No tasks at this stop
              </div>

              <!-- Actions for current stop -->
              <div v-if="isCurrentStop(stop) && canInteract" class="q-mt-md">
                <div class="row q-gutter-sm">
                  <q-btn
                    v-if="!getStopActual(stop)?.arrived_at"
                    color="primary"
                    label="Arrive"
                    @click="$emit('arrive', stop.id)"
                  />
                  <q-btn
                    v-if="getStopActual(stop)?.arrived_at && !getStopActual(stop)?.departed_at"
                    color="secondary"
                    label="Depart"
                    @click="$emit('depart', stop.id)"
                  />
                </div>
              </div>
            </q-card-section>
          </q-card>
        </q-expansion-item>
      </q-list>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RunInstance } from 'src/types/runs'

interface Props {
  run: RunInstance
  canInteract?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canInteract: false,
})

defineEmits<{
  (e: 'arrive', stopId: number): void
  (e: 'depart', stopId: number): void
  (e: 'request-click', request: any): void
}>()

const orderedStops = computed(() => {
  return props.run.route?.stops || []
})

function getStopIcon(stop: any, index: number) {
  const actual = getStopActual(stop)
  if (actual?.departed_at) return 'check_circle'
  if (actual?.arrived_at) return 'radio_button_checked'
  if (isCurrentStop(stop)) return 'arrow_forward'
  return 'radio_button_unchecked'
}

function getStopLabel(stop: any) {
  return getStopDisplayName(stop)
}

function getStopCaption(stop: any) {
  const actual = getStopActual(stop)
  const requestCount = getRequestsForStop(stop.id).length
  const parts = []

  if (actual?.arrived_at && actual?.departed_at) {
    parts.push('Completed')
  } else if (actual?.arrived_at) {
    parts.push('In Progress')
  } else {
    parts.push(getEstimatedArrival(stop))
  }

  if (requestCount > 0) {
    parts.push(`${requestCount} task${requestCount === 1 ? '' : 's'}`)
  }

  return parts.join(' • ')
}

function getStopHeaderClass(stop: any, index: number) {
  const actual = getStopActual(stop)
  if (actual?.departed_at) return 'bg-green-1'
  if (actual?.arrived_at) return 'bg-blue-1'
  if (isCurrentStop(stop)) return 'bg-orange-1'
  return ''
}

function isCurrentStop(stop: any) {
  return props.run.current_stop_id === stop.id
}

function getStopDisplayName(stop: any) {
  if (stop.stop_type === 'VENDOR_CLUSTER') {
    const count = stop.vendor_cluster_locations?.length || 0
    return `Vendor Cluster (${count} vendors)`
  }
  return stop.location?.name || 'Unknown Location'
}

function getStopActual(stop: any) {
  return props.run.stop_actuals?.find((a: any) => a.route_stop_id === stop.id)
}

function getRequestsForStop(stopId: number) {
  return (props.run.requests || []).filter(
    (r: any) => r.pickup_stop_id === stopId || r.dropoff_stop_id === stopId
  )
}

function getEstimatedArrival(stop: any) {
  // This is a simplified version - in production you'd calculate based on actual times
  const stopOrder = stop.stop_order
  const baseTime = new Date(`${props.run.scheduled_date} ${props.run.scheduled_time}`)

  // Add estimated duration for all previous stops
  const previousStops = orderedStops.value.filter(s => s.stop_order < stopOrder)
  const totalMinutes = previousStops.reduce((sum, s) => sum + s.estimated_duration_minutes, 0)

  const eta = new Date(baseTime.getTime() + totalMinutes * 60000)
  return formatTime(eta.toTimeString().slice(0, 5))
}

function getUrgencyColor(urgency: any) {
  const colors: Record<string, string> = {
    low: 'green',
    normal: 'blue',
    high: 'orange',
    urgent: 'red',
  }
  return colors[urgency?.name?.toLowerCase()] || 'grey'
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString()
}

function formatTime(time: string | undefined) {
  if (!time) return ''
  const [hours, minutes] = time.split(':')
  if (!hours || !minutes) return time
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minutes} ${ampm}`
}

function formatDateTime(datetime: string) {
  const date = new Date(datetime)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
</script>
