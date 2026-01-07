<template>
  <q-page padding>
    <div class="q-pb-md">
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h5">Parts Requests</div>
        <q-btn
          flat
          @click="openCreateDialog"
        >
          <q-icon name="add" color="primary" size="sm" class="q-mr-xs" />
          <span class="text-primary">Create Request</span>
        </q-btn>
      </div>

      <!-- Filters -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section class="q-pa-sm">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-4">
              <q-input
                v-model="filters.search"
                dense
                outlined
                placeholder="Search by reference #, vendor, customer..."
                @update:model-value="debouncedFetch"
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
                <template v-slot:append>
                  <q-icon
                    v-if="filters.search"
                    name="close"
                    class="cursor-pointer"
                    @click="filters.search = ''; fetchRequests()"
                  />
                </template>
              </q-input>
            </div>

            <div class="col-12 col-sm-2">
              <q-select
                v-model="filters.status"
                dense
                outlined
                placeholder="Status"
                :options="lookups.statuses"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
                @update:model-value="fetchRequests"
              />
            </div>

            <div class="col-12 col-sm-2">
              <q-select
                v-model="filters.urgency"
                dense
                outlined
                placeholder="Urgency"
                :options="lookups.urgency_levels"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
                @update:model-value="fetchRequests"
              />
            </div>

            <div class="col-12 col-sm-2">
              <q-select
                v-model="filters.assigned_runner"
                dense
                outlined
                placeholder="Runner"
                :options="runners"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
                @update:model-value="fetchRequests"
              />
            </div>

            <div class="col-12 col-sm-2">
              <q-toggle
                v-model="filters.unassigned"
                label="Unassigned Only"
                @update:model-value="fetchRequests"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Requests Table -->
      <q-card flat bordered>
        <q-table
          flat
          :rows="requests"
          :columns="columns"
          row-key="id"
          :loading="loading"
          :pagination="pagination"
          class="clickable-rows"
          @request="onTableRequest"
          @row-click="onRowClick"
        >
          <template v-slot:body-cell-reference_number="props">
            <q-td :props="props">
              <div class="text-weight-medium text-primary">
                {{ props.row.reference_number }}
              </div>
              <div class="text-caption text-grey-7">
                {{ formatDateTime(props.row.requested_at) }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-type="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="getTypeColor(props.row.request_type.name)"
                text-color="white"
              >
                {{ getTypeLabel(props.row.request_type.name) }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-from_to="props">
            <q-td :props="props" style="max-width: 200px">
              <div class="text-weight-medium">
                <q-icon name="place" size="xs" class="q-mr-xs" />
                {{ getOriginText(props.row) }}
              </div>
              <!-- Show vendor address under origin for non-return requests -->
              <div v-if="props.row.vendor_address && props.row.request_type?.name !== 'return'" class="text-caption text-grey-7">
                {{ props.row.vendor_address.one_line_address }}
              </div>
              <div class="text-caption">
                <q-icon name="flag" size="xs" class="q-mr-xs" />
                {{ getDestinationText(props.row) }}
              </div>
              <!-- Show vendor address under destination for return requests -->
              <div v-if="props.row.vendor_address && props.row.request_type?.name === 'return'" class="text-caption text-grey-7">
                {{ props.row.vendor_address.one_line_address }}
              </div>
              <div v-if="props.row.customer_name && !props.row.vendor && props.row.request_type?.name !== 'return'" class="text-caption text-grey-7">
                Customer: {{ props.row.customer_name }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-details="props">
            <q-td :props="props" style="max-width: 300px">
              <div class="text-body2">{{ props.row.details }}</div>
              <div v-if="props.row.special_instructions" class="text-caption text-orange-8 q-mt-xs">
                <q-icon name="warning" size="xs" />
                {{ props.row.special_instructions }}
              </div>
            </q-td>
          </template>

          <template v-slot:body-cell-urgency="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="getUrgencyColor(props.row.urgency.name)"
                text-color="white"
              >
                {{ getUrgencyLabel(props.row.urgency.name) }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-chip
                dense
                size="sm"
                :color="getStatusColor(props.row.status.name)"
                text-color="white"
              >
                {{ getStatusLabel(props.row.status.name) }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-assigned_run="props">
            <q-td :props="props">
              <div v-if="props.row.run_instance" class="text-weight-medium">
                <q-icon name="directions_car" size="xs" class="q-mr-xs" />
                {{ props.row.run_instance.route?.name || 'Unknown Route' }}
                <div class="text-caption text-grey-7">
                  {{ props.row.run_instance.schedule?.name || formatTime(props.row.run_instance.scheduled_time) }}
                </div>
              </div>
              <span v-else class="text-grey-6">Not Assigned</span>
            </q-td>
          </template>

          <template v-slot:body-cell-requested_by="props">
            <q-td :props="props">
              <div class="text-weight-medium">
                {{ props.row.requested_by?.name || 'Unknown' }}
              </div>
              <div class="text-caption text-grey-7">
                {{ formatDateTime(props.row.requested_at) }}
              </div>
            </q-td>
          </template>

        </q-table>
      </q-card>
    </div>

    <!-- Create Request Dialog -->
    <MobileFormDialog
      v-model="showCreateDialog"
      title="Create Parts Request"
      submit-label="Create Request"
      :loading="isSubmitting"
      :has-draft="hasDraft"
      :draft-age="formatDraftAge()"
      @submit="submitCreateForm"
      @load-draft="loadDraft"
      @discard-draft="clearDraft"
    >
      <!-- Request Type Buttons -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <div class="text-subtitle2 q-mb-sm">Request Type <span class="text-negative">*</span></div>
        <q-btn-toggle
          v-model="requestForm.request_type_id"
          spread
          no-caps
          toggle-color="primary"
          :options="requestTypeButtons"
          class="full-width"
          @update:model-value="updateField('request_type_id', $event)"
        />
        <div v-if="getError('request_type_id')" class="text-negative text-caption q-mt-xs">
          {{ getError('request_type_id') }}
        </div>
      </div>

      <!-- Urgency Level -->
      <MobileSelect
        name="urgency_id"
        v-model="requestForm.urgency_id"
        label="Urgency Level"
        :options="formattedUrgencyLevels"
        option-value="id"
        option-label="label"
        :error="getError('urgency_id')"
        @update:model-value="updateField('urgency_id', $event)"
        @blur="touchField('urgency_id')"
        required
        icon="priority_high"
        hint="Default: First Available Run"
      />

      <!-- Ready When DateTime Picker -->
      <MobileFormField
        name="not_before_datetime"
        v-model="requestForm.not_before_datetime"
        label="Ready When (Optional)"
        type="datetime-local"
        :error="getError('not_before_datetime')"
        @update:model-value="updateField('not_before_datetime', $event)"
        @blur="touchField('not_before_datetime')"
        icon="schedule"
        hint="Leave empty for next available run"
        clearable
      />

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <div class="text-subtitle1 text-weight-medium q-my-sm">Supplying Location</div>
      </div>

      <!-- Origin Shop Location Buttons (hidden for pickup - pickup is always from vendor) -->
      <div v-if="!isPickupType || isReturnType" class="col-12" style="grid-column: 1 / -1;">
        <div class="text-subtitle2 q-mb-sm">
          <q-icon name="store" class="q-mr-xs" />
          {{ isReturnType ? 'Return From Shop' : 'Pickup From Shop' }}
        </div>
        <div class="row q-gutter-sm">
          <q-btn
            v-for="loc in availableOriginLocations"
            :key="loc.id"
            :label="loc.name"
            :style="getLocationButtonStyle(loc, requestForm.origin_location_id === loc.id)"
            @click="toggleOriginLocation(loc.id)"
            no-caps
            unelevated
            size="sm"
          />
          <q-btn
            v-if="requestForm.origin_location_id"
            label="Clear"
            color="grey-3"
            text-color="grey-7"
            @click="requestForm.origin_location_id = null; updateField('origin_location_id', null)"
            no-caps
            flat
            size="sm"
          />
        </div>
        <div class="text-caption text-grey-6 q-mt-xs">Select if picking up from one of our shops</div>
      </div>

      <!-- Vendor Selection for Pickup (supplying location - where we pick up FROM) -->
      <template v-if="showPickupVendorSelection">
        <VendorSelect
          name="vendor_id"
          v-model="requestForm.vendor_id"
          :initial-vendor-name="selectedVendorName"
          label="Vendor"
          :error="getError('vendor_id')"
          icon="business"
          @vendor-selected="handleVendorSelected"
          @vendor-created="handleVendorCreated"
        />

        <VendorAddressSelect
          v-if="requestForm.vendor_id"
          name="vendor_address_id"
          v-model="requestForm.vendor_address_id"
          :vendor-id="requestForm.vendor_id"
          :addresses="selectedVendorAddresses"
          label="Pickup Address"
          :error="getError('vendor_address_id')"
          @address-selected="handleAddressSelected"
          @address-created="handleAddressCreated"
        />
      </template>

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <div class="text-subtitle1 text-weight-medium q-my-sm">Receiving Location</div>
      </div>

      <!-- Receiving Shop Location Buttons (hidden for delivery and return - delivery is to customer, return is to vendor) -->
      <div v-if="!isDeliveryType && !isReturnType" class="col-12" style="grid-column: 1 / -1;">
        <div class="text-subtitle2 q-mb-sm">
          <q-icon name="location_on" class="q-mr-xs" />
          Deliver To Shop
        </div>
        <div class="row q-gutter-sm">
          <q-btn
            v-for="loc in availableReceivingLocations"
            :key="loc.id"
            :label="loc.name"
            :style="getLocationButtonStyle(loc, requestForm.receiving_location_id === loc.id)"
            @click="toggleReceivingLocation(loc.id)"
            no-caps
            unelevated
            size="sm"
          />
          <q-btn
            v-if="requestForm.receiving_location_id"
            label="Clear"
            color="grey-3"
            text-color="grey-7"
            @click="requestForm.receiving_location_id = null; updateField('receiving_location_id', null)"
            no-caps
            flat
            size="sm"
          />
        </div>
        <div class="text-caption text-grey-6 q-mt-xs">Select if delivering to one of our shops</div>
      </div>

      <!-- Vendor Selection for Return (receiving location - where we return TO) -->
      <template v-if="isReturnType">
        <VendorSelect
          name="vendor_id"
          v-model="requestForm.vendor_id"
          :initial-vendor-name="selectedVendorName"
          label="Return To Vendor"
          :error="getError('vendor_id')"
          icon="business"
          required
          @vendor-selected="handleVendorSelected"
          @vendor-created="handleVendorCreated"
        />

        <VendorAddressSelect
          v-if="requestForm.vendor_id"
          name="vendor_address_id"
          v-model="requestForm.vendor_address_id"
          :vendor-id="requestForm.vendor_id"
          :addresses="selectedVendorAddresses"
          label="Return Address"
          :error="getError('vendor_address_id')"
          @address-selected="handleAddressSelected"
          @address-created="handleAddressCreated"
        />
      </template>

      <template v-if="((!requestForm.receiving_location_id && !isTransferType && !isPickupType && !isReturnType) || isDeliveryType)">
        <CustomerSelect
          name="customer_id"
          v-model="requestForm.customer_id"
          :initial-customer-name="selectedCustomerName"
          label="Customer"
          :error="getError('customer_id')"
          icon="business"
          @customer-selected="handleCustomerSelected"
          @customer-created="handleCustomerCreated"
        />

        <CustomerAddressSelect
          v-if="requestForm.customer_id"
          name="customer_address_id"
          v-model="requestForm.customer_address_id"
          :customer-id="requestForm.customer_id"
          :addresses="selectedCustomerAddresses"
          label="Delivery Address"
          :error="getError('customer_address_id')"
          @address-selected="handleCustomerAddressSelected"
          @address-created="handleCustomerAddressCreated"
        />

        <MobileFormField
          name="customer_phone"
          v-model="requestForm.customer_phone"
          label="Customer Phone"
          type="tel"
          :error="getError('customer_phone')"
          @update:model-value="updateField('customer_phone', $event)"
          @blur="touchField('customer_phone')"
          icon="phone"
          hint="Contact number for delivery (auto-filled from address)"
        />
      </template>

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
      </div>

      <MobileFormField
        name="details"
        v-model="requestForm.details"
        label="Details"
        type="textarea"
        :rows="3"
        :error="getError('details')"
        @update:model-value="updateField('details', $event)"
        @blur="touchField('details')"
        required
        icon="description"
      />

      <MobileFormField
        name="special_instructions"
        v-model="requestForm.special_instructions"
        label="Special Instructions"
        type="textarea"
        :rows="2"
        :error="getError('special_instructions')"
        @update:model-value="updateField('special_instructions', $event)"
        @blur="touchField('special_instructions')"
        icon="info"
      />

      <!-- Line Items Section (Collapsible) -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <q-expansion-item
          v-model="createItemsExpanded"
          dense
          header-class="bg-grey-2 text-grey-8 rounded-borders"
          expand-icon-class="text-grey-7"
        >
          <template v-slot:header>
            <q-item-section>
              <q-item-label class="text-caption text-weight-medium">
                Line Items
                <q-badge v-if="requestForm.items.length > 0" color="primary" class="q-ml-sm">
                  {{ requestForm.items.length }}
                </q-badge>
              </q-item-label>
            </q-item-section>
          </template>
          <div class="q-pa-sm">
            <PartsRequestItems
              v-model="requestForm.items"
              :readonly="false"
              :show-verification="false"
            />
          </div>
        </q-expansion-item>
      </div>

      <!-- Documents Section (Collapsible) -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <q-expansion-item
          v-model="createDocumentsExpanded"
          dense
          header-class="bg-grey-2 text-grey-8 rounded-borders"
          expand-icon-class="text-grey-7"
        >
          <template v-slot:header>
            <q-item-section>
              <q-item-label class="text-caption text-weight-medium">
                Documents
                <q-badge v-if="pendingDocuments.length > 0" color="primary" class="q-ml-sm">
                  {{ pendingDocuments.length }}
                </q-badge>
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-btn
                flat
                dense
                size="xs"
                color="primary"
                icon="attach_file"
                label="Add"
                @click.stop="triggerPendingFileInput"
              />
            </q-item-section>
          </template>
          <div class="q-pa-sm">
            <input
              ref="pendingFileInputRef"
              type="file"
              class="hidden"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.txt,.csv"
              multiple
              @change="handlePendingFileSelect"
            />

            <div v-if="pendingDocuments.length === 0" class="text-grey-6 text-center q-py-md">
              No documents added yet. Documents will be uploaded when you create the request.
            </div>

            <q-list v-else separator class="rounded-borders bg-grey-1">
              <q-item
                v-for="(doc, index) in pendingDocuments"
                :key="index"
                class="q-py-sm"
              >
                <q-item-section avatar>
                  <q-icon :name="getPendingDocIcon(doc.file)" color="grey-7" />
                </q-item-section>

                <q-item-section>
                  <q-item-label class="text-weight-medium ellipsis">
                    {{ doc.file.name }}
                  </q-item-label>
                  <q-item-label caption>
                    {{ formatFileSize(doc.file.size) }}
                  </q-item-label>
                </q-item-section>

                <q-item-section side>
                  <q-btn
                    flat
                    dense
                    round
                    size="sm"
                    icon="delete"
                    color="negative"
                    @click="removePendingDocument(index)"
                  />
                </q-item-section>
              </q-item>
            </q-list>

            <div v-if="pendingDocuments.length > 0" class="q-mt-sm text-caption text-grey-7">
              {{ pendingDocuments.length }} file{{ pendingDocuments.length !== 1 ? 's' : '' }} will be uploaded on create
            </div>
          </div>
        </q-expansion-item>
      </div>

      <!-- Photos Section (Collapsible) -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <q-expansion-item
          v-model="createPhotosExpanded"
          dense
          header-class="bg-grey-2 text-grey-8 rounded-borders"
          expand-icon-class="text-grey-7"
        >
          <template v-slot:header>
            <q-item-section>
              <q-item-label class="text-caption text-weight-medium">
                Photos
                <q-badge v-if="pendingImages.length > 0" color="primary" class="q-ml-sm">
                  {{ pendingImages.length }}
                </q-badge>
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-btn
                flat
                dense
                size="xs"
                color="primary"
                icon="add_a_photo"
                label="Add"
                @click.stop="triggerPendingImageInput"
              />
            </q-item-section>
          </template>
          <div class="q-pa-sm">
            <input
              ref="pendingImageInputRef"
              type="file"
              class="hidden"
              accept="image/*"
              :capture="$q.platform.is.mobile ? 'environment' : undefined"
              multiple
              @change="handlePendingImageSelect"
            />

            <div v-if="pendingImages.length === 0" class="text-grey-6 text-center q-py-md">
              No photos added yet. Add photos to help identify parts.
            </div>

            <div v-else class="pending-images-grid">
              <div
                v-for="(img, index) in pendingImages"
                :key="index"
                class="pending-image-thumb"
                @click="openPendingCarousel(index)"
              >
                <q-img
                  :src="img.preview"
                  ratio="1"
                  class="rounded-borders cursor-pointer"
                />
                <q-btn
                  round
                  dense
                  size="xs"
                  icon="close"
                  color="negative"
                  class="remove-image-btn"
                  @click.stop="removePendingImage(index)"
                />
              </div>
            </div>

            <div v-if="pendingImages.length > 0" class="q-mt-sm text-caption text-grey-7">
              {{ pendingImages.length }} photo{{ pendingImages.length !== 1 ? 's' : '' }} will be uploaded on create
            </div>
          </div>
        </q-expansion-item>
      </div>

      <!-- Section Divider -->
      <div class="col-12" style="grid-column: 1 / -1;">
        <q-separator class="q-my-sm" />
        <div class="text-subtitle2 q-my-sm">Slack Notifications</div>
      </div>

      <div class="col-12" style="grid-column: 1 / -1;">
        <q-toggle
          v-model="requestForm.slack_notify_pickup"
          label="Notify on pickup"
        />
        <q-toggle
          v-model="requestForm.slack_notify_delivery"
          label="Notify on delivery"
          class="q-ml-md"
        />
      </div>

      <MobileFormField
        v-if="requestForm.slack_notify_pickup || requestForm.slack_notify_delivery"
        name="slack_channel"
        v-model="requestForm.slack_channel"
        label="Slack Channel (optional)"
        type="text"
        :error="getError('slack_channel')"
        @update:model-value="updateField('slack_channel', $event)"
        @blur="touchField('slack_channel')"
        icon="tag"
        hint="e.g., #parts-alerts"
      />
    </MobileFormDialog>

    <!-- Assign Runner Dialog -->
    <q-dialog v-model="showAssignDialog">
      <q-card style="width: 100%; max-width: 500px">
        <q-card-section>
          <div class="text-h6">Assign Runner</div>
          <div class="text-caption">{{ selectedRequest?.reference_number }}</div>
        </q-card-section>

        <q-card-section>
          <q-select
            v-model="selectedRunnerId"
            label="Select Runner"
            outlined
            :options="runners"
            option-value="id"
            option-label="name"
            emit-value
            map-options
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat
            label="Assign"
            color="primary"
            @click="assignRunner"
            :disable="!selectedRunnerId"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- View Request Dialog -->
    <q-dialog v-model="showViewDialog">
      <q-card style="width: 100%; max-width: 550px; max-height: 90vh;" class="column">
        <!-- Header -->
        <q-card-section class="row items-center q-py-sm bg-grey-2">
          <div class="text-subtitle1 text-weight-medium">{{ viewingRequest?.reference_number }}</div>
          <q-chip
            v-if="viewingRequest"
            dense
            size="sm"
            :color="getStatusColor(viewingRequest.status.name)"
            text-color="white"
            class="q-ml-sm"
          >
            {{ getStatusLabel(viewingRequest.status.name) }}
          </q-chip>
          <q-space />
          <q-btn icon="close" flat round dense size="sm" v-close-popup />
        </q-card-section>

        <!-- Scrollable Content -->
        <q-card-section class="col q-pa-md" style="overflow-y: auto;">
            <div v-if="viewingRequest" class="view-dialog-content">
            <!-- Type & Urgency - inline chips -->
            <div class="row items-center chip-row">
              <q-chip
                dense
                size="sm"
                :color="getTypeColor(viewingRequest.request_type.name)"
                text-color="white"
              >
                {{ getTypeLabel(viewingRequest.request_type.name) }}
              </q-chip>
              <q-chip
                dense
                size="sm"
                :color="getUrgencyColor(viewingRequest.urgency.name)"
                text-color="white"
              >
                {{ getUrgencyLabel(viewingRequest.urgency.name) }}
              </q-chip>
            </div>

            <!-- From → To Side by Side -->
            <div class="row items-start from-to-row bg-grey-1 q-pa-sm rounded-borders">
              <!-- From -->
              <div class="col">
                <div class="text-caption text-weight-bold text-grey-8">FROM</div>
                <div class="text-body2 text-weight-medium text-primary">{{ getOriginText(viewingRequest) }}</div>
                <div v-if="viewingRequest.vendor_address" class="text-caption text-grey-6" style="word-break: break-word;">
                  {{ viewingRequest.vendor_address.one_line_address }}
                </div>
                <div v-if="viewingRequest.vendor_address?.instructions" class="text-caption text-orange-8">
                  <q-icon name="info" size="xs" /> {{ viewingRequest.vendor_address.instructions }}
                </div>
              </div>

              <!-- Arrow -->
              <div class="flex items-center q-px-sm" style="padding-top: 16px;">
                <q-icon name="arrow_forward" size="md" color="grey-6" />
              </div>

              <!-- To -->
              <div class="col">
                <div class="text-caption text-weight-bold text-grey-8">TO</div>
                <div class="text-body2 text-weight-medium text-positive">{{ getDestinationText(viewingRequest) }}</div>
              </div>
            </div>

            <!-- Requested By & Assigned To - inline -->
            <div class="row info-row">
              <div class="col">
                <div class="text-caption text-grey-7">Requested By</div>
                <div class="text-body2">{{ viewingRequest.requested_by.name }}</div>
                <div class="text-caption text-grey-6">{{ formatDateTime(viewingRequest.requested_at) }}</div>
              </div>
              <div v-if="viewingRequest.assigned_runner" class="col">
                <div class="text-caption text-grey-7">Assigned To</div>
                <div class="text-body2">{{ viewingRequest.assigned_runner.name }}</div>
              </div>
            </div>

            <!-- Details -->
            <div>
              <div class="text-caption text-grey-7">Details</div>
              <div class="text-body2" style="word-break: break-word;">{{ viewingRequest.details }}</div>
            </div>

            <!-- Special Instructions -->
            <div v-if="viewingRequest.special_instructions" class="bg-orange-1 q-pa-sm rounded-borders">
              <div class="text-caption text-orange-9">
                <q-icon name="warning" size="xs" class="q-mr-xs" />
                {{ viewingRequest.special_instructions }}
              </div>
            </div>

            <!-- Collapsible Sections - only one open at a time -->
            <q-list>
              <!-- Line Items Section -->
              <q-expansion-item
                group="viewDialogSections"
                dense
                header-class="bg-grey-2 text-grey-8"
                expand-icon-class="text-grey-7"
              >
                <template v-slot:header>
                  <q-item-section>
                    <q-item-label class="text-caption text-weight-medium">
                      Line Items
                      <q-badge v-if="itemsCount > 0" color="primary" class="q-ml-sm">
                        {{ itemsCount }}
                      </q-badge>
                    </q-item-label>
                  </q-item-section>
                </template>
                <div class="q-pa-sm">
                  <PartsRequestItems
                    :request-id="viewingRequest.id"
                    :readonly="false"
                    :show-verification="true"
                    @count-changed="onItemsCountChanged"
                  />
                </div>
              </q-expansion-item>

              <!-- Notes Section -->
              <q-expansion-item
                group="viewDialogSections"
                dense
                header-class="bg-grey-2 text-grey-8"
                expand-icon-class="text-grey-7"
              >
                <template v-slot:header>
                  <q-item-section>
                    <q-item-label class="text-caption text-weight-medium">
                      Notes
                      <q-badge v-if="notesCount > 0" color="orange" class="q-ml-sm">
                        {{ notesCount }}
                      </q-badge>
                    </q-item-label>
                  </q-item-section>
                </template>
                <div class="q-pa-sm">
                  <PartsRequestNotes
                    :parts-request-id="viewingRequest.id"
                    @count-changed="onNotesCountChanged"
                  />
                </div>
              </q-expansion-item>

              <!-- Documents Section -->
              <q-expansion-item
                group="viewDialogSections"
                dense
                header-class="bg-grey-2 text-grey-8"
                expand-icon-class="text-grey-7"
              >
                <template v-slot:header>
                  <q-item-section>
                    <q-item-label class="text-caption text-weight-medium">
                      Documents
                      <q-badge v-if="documentsCount > 0" color="primary" class="q-ml-sm">
                        {{ documentsCount }}
                      </q-badge>
                    </q-item-label>
                  </q-item-section>
                </template>
                <div class="q-pa-sm">
                  <PartsRequestDocuments
                    :request-id="viewingRequest.id"
                    :readonly="false"
                    @count-changed="onDocumentsCountChanged"
                  />
                </div>
              </q-expansion-item>

              <!-- Photos Section -->
              <q-expansion-item
                group="viewDialogSections"
                dense
                header-class="bg-grey-2 text-grey-8"
                expand-icon-class="text-grey-7"
              >
                <template v-slot:header>
                  <q-item-section>
                    <q-item-label class="text-caption text-weight-medium">
                      Photos
                      <q-badge v-if="photosCount > 0" color="primary" class="q-ml-sm">
                        {{ photosCount }}
                      </q-badge>
                    </q-item-label>
                  </q-item-section>
                </template>
                <div class="q-pa-sm">
                  <PartsRequestImages
                    :request-id="viewingRequest.id"
                    source="requester"
                    :readonly="false"
                    @count-changed="onPhotosCountChanged"
                  />
                </div>
              </q-expansion-item>

              <!-- Runner Photos Section -->
              <q-expansion-item
                group="viewDialogSections"
                dense
                header-class="bg-grey-2 text-grey-8"
                expand-icon-class="text-grey-7"
              >
                <template v-slot:header>
                  <q-item-section>
                    <q-item-label class="text-caption text-weight-medium">
                      Runner Photos
                      <q-badge v-if="runnerPhotosCount > 0" color="blue" class="q-ml-sm">
                        {{ runnerPhotosCount }}
                      </q-badge>
                    </q-item-label>
                  </q-item-section>
                </template>
                <div class="q-pa-sm">
                  <PartsRequestImages
                    :request-id="viewingRequest.id"
                    :show-runner-images="true"
                    :readonly="true"
                    @count-changed="onRunnerPhotosCountChanged"
                  />
                </div>
              </q-expansion-item>
            </q-list>
            </div>
        </q-card-section>

        <!-- Footer Actions -->
        <q-card-actions class="bg-grey-1 q-pa-sm justify-between">
          <div>
            <q-btn flat dense size="sm" icon="timeline" @click="viewTimeline(viewingRequest!)" />
          </div>
          <div class="row items-center q-gutter-sm">
            <q-btn
              v-if="viewingRequest && !viewingRequest.run_instance_id"
              flat
              dense
              size="sm"
              label="Add to Next Run"
              icon="add_road"
              color="primary"
              :loading="assigningToNextRun"
              @click="assignToNextRun(viewingRequest!)"
            />
            <q-btn flat dense size="sm" label="Close" color="primary" v-close-popup />
          </div>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Timeline Dialog -->
    <q-dialog v-model="showTimelineDialog">
      <q-card style="width: 100%; max-width: 600px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Timeline</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-timeline color="primary">
            <q-timeline-entry
              v-for="event in timeline"
              :key="event.id"
              :title="getEventDisplayName(event.event_type)"
              :subtitle="formatDateTime(event.event_at)"
            >
              <div v-if="event.user">By: {{ event.user.name }}</div>
              <div v-if="event.notes" class="text-caption">{{ event.notes }}</div>
            </q-timeline-entry>
          </q-timeline>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Photos Dialog -->
    <q-dialog v-model="showPhotosDialog">
      <q-card style="width: 100%; max-width: 700px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Photos</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <div v-if="photos.length === 0" class="text-center text-grey-7">
            No photos uploaded yet
          </div>
          <div v-else class="row q-col-gutter-md">
            <div v-for="photo in photos" :key="photo.id" class="col-12 col-sm-6">
              <q-card>
                <q-img :src="photo.url" ratio="4/3" />
                <q-card-section>
                  <div class="text-weight-medium">{{ photo.stage.toUpperCase() }}</div>
                  <div class="text-caption">{{ formatDateTime(photo.taken_at) }}</div>
                  <div class="text-caption">By: {{ photo.taken_by }}</div>
                  <div v-if="photo.notes" class="text-caption">{{ photo.notes }}</div>
                </q-card-section>
              </q-card>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Pending Images Preview Popup (inline overlay, not full screen) -->
    <q-dialog v-model="showPendingCarousel" position="standard">
      <q-card class="pending-preview-card">
        <!-- Header -->
        <q-card-section class="row items-center q-py-xs q-px-sm bg-grey-2">
          <div class="text-grey-8 text-caption">
            {{ pendingCarouselIndex + 1 }} / {{ pendingImages.length }}
          </div>
          <q-space />
          <q-btn
            flat
            dense
            round
            size="sm"
            icon="delete"
            color="negative"
            @click="removePendingImageFromCarousel"
          >
            <q-tooltip>Remove</q-tooltip>
          </q-btn>
          <q-btn
            flat
            dense
            round
            size="sm"
            icon="close"
            color="grey-7"
            v-close-popup
          />
        </q-card-section>

        <!-- Carousel -->
        <q-card-section class="q-pa-none pending-carousel-container">
          <q-carousel
            v-model="pendingCarouselSlide"
            swipeable
            animated
            navigation
            arrows
            control-color="primary"
            class="bg-grey-3 pending-preview-carousel"
            @update:model-value="onPendingSlideChange"
          >
            <q-carousel-slide
              v-for="(img, index) in pendingImages"
              :key="index"
              :name="index"
              class="column no-wrap flex-center q-pa-sm"
            >
              <q-img
                :src="img.preview"
                :alt="img.file.name"
                fit="contain"
                class="pending-carousel-image"
              >
                <template #loading>
                  <div class="flex flex-center full-height">
                    <q-spinner color="primary" size="40px" />
                  </div>
                </template>
              </q-img>
            </q-carousel-slide>
          </q-carousel>
        </q-card-section>

        <!-- Footer with file name -->
        <q-card-section class="bg-grey-1 q-py-xs q-px-sm">
          <div class="text-body2 ellipsis">
            {{ pendingImages[pendingCarouselIndex]?.file.name || '' }}
          </div>
          <div class="text-grey-7 text-caption">
            Will be uploaded when request is created
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Saturday Prompt Dialog -->
    <q-dialog v-model="showSaturdayPromptDialog" persistent>
      <q-card style="min-width: 300px; max-width: 400px;">
        <q-card-section class="row items-center">
          <q-icon name="event" color="warning" size="md" class="q-mr-sm" />
          <div class="text-h6">Saturday Run</div>
        </q-card-section>

        <q-card-section>
          <p>The next available run is on <strong>Saturday, {{ saturdayPromptData?.saturdayDate }}</strong>.</p>
          <p class="text-grey-7">Would you like to assign to this Saturday run, or wait for the next business day?</p>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn
            flat
            label="Wait for Weekday"
            color="grey"
            @click="handleSaturdayChoice(false)"
          />
          <q-btn
            flat
            label="Use Saturday"
            color="primary"
            @click="handleSaturdayChoice(true)"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive, watch } from 'vue';
import { usePartsRequestsStore, type PartsRequest, type PartsRequestEvent, type PartsRequestPhoto } from 'src/stores/partsRequests';
import { useAuthStore } from 'src/stores/auth';
import { api } from 'boot/axios';
import { debounce, useQuasar } from 'quasar';
import { useFormValidation, validationRules } from 'src/composables/useFormValidation';
import { useDraftState } from 'src/composables/useDraftState';
import MobileFormDialog from 'src/components/MobileFormDialog.vue';
import MobileFormField from 'src/components/MobileFormField.vue';
import MobileSelect from 'src/components/MobileSelect.vue';
import VendorSelect from 'src/components/VendorSelect.vue';
import VendorAddressSelect from 'src/components/VendorAddressSelect.vue';
import CustomerSelect from 'src/components/CustomerSelect.vue';
import CustomerAddressSelect from 'src/components/CustomerAddressSelect.vue';
import PartsRequestItems from 'src/components/PartsRequestItems.vue';
import PartsRequestDocuments from 'src/components/PartsRequestDocuments.vue';
import PartsRequestImages from 'src/components/PartsRequestImages.vue';
import PartsRequestNotes from 'src/components/PartsRequestNotes.vue';
import { useVendorsStore } from 'src/stores/vendors';
import { useCustomersStore } from 'src/stores/customers';
import type { Vendor, Address } from 'src/types/vendors';
import type { Customer } from 'src/types/customers';

const $q = useQuasar();
const partsRequestsStore = usePartsRequestsStore();
const authStore = useAuthStore();
const vendorsStore = useVendorsStore();
const customersStore = useCustomersStore();

const requests = computed(() => partsRequestsStore.requests);
const loading = computed(() => partsRequestsStore.loading);
const lookups = computed(() => partsRequestsStore.lookups);

const showCreateDialog = ref(false);
const showAssignDialog = ref(false);
const showViewDialog = ref(false);
const showTimelineDialog = ref(false);
const showPhotosDialog = ref(false);
const showSaturdayPromptDialog = ref(false);

// "Add to Next Run" state
const assigningToNextRun = ref(false);
const saturdayPromptData = ref<{
  requestId: number;
  saturdayDate: string;
  saturdayTime?: string;
  routeId?: number;
} | null>(null);

const selectedRequest = ref<PartsRequest | null>(null);
const selectedRunnerId = ref<number | null>(null);
const viewingRequest = ref<PartsRequest | null>(null);
const timeline = ref<PartsRequestEvent[]>([]);
const photos = ref<PartsRequestPhoto[]>([]);

const runners = ref<any[]>([]);
const locations = ref<any[]>([]);

// Vendor selection state
const selectedVendor = ref<Vendor | null>(null);
const selectedVendorAddresses = computed(() => selectedVendor.value?.addresses || []);
const selectedVendorName = computed(() => selectedVendor.value?.name || '');

// Pending documents for create form (uploaded after request is created)
interface PendingDocument {
  file: File;
  description: string;
}
const pendingDocuments = ref<PendingDocument[]>([]);
const pendingFileInputRef = ref<HTMLInputElement | null>(null);

// Pending images for create form (uploaded after request is created)
interface PendingImage {
  file: File;
  preview: string; // Data URL for preview
}
const pendingImages = ref<PendingImage[]>([]);
const pendingImageInputRef = ref<HTMLInputElement | null>(null);

// Pending images carousel state
const showPendingCarousel = ref(false);
const pendingCarouselSlide = ref(0);
const pendingCarouselIndex = ref(0);

// View dialog expansion states and counts
const itemsExpanded = ref(false);
const documentsExpanded = ref(false);
const photosExpanded = ref(false);
const runnerPhotosExpanded = ref(false);
const notesExpanded = ref(false);
const itemsCount = ref(0);
const documentsCount = ref(0);
const photosCount = ref(0);
const runnerPhotosCount = ref(0);
const notesCount = ref(0);

// Create form expansion states (auto-expand when data exists)
const createItemsExpanded = ref(false);
const createDocumentsExpanded = ref(false);
const createPhotosExpanded = ref(false);

// Customer selection state
const selectedCustomer = ref<Customer | null>(null);
const selectedCustomerAddresses = computed(() => selectedCustomer.value?.addresses || []);
const selectedCustomerName = computed(() => selectedCustomer.value?.formatted_name || '');

const filters = ref({
  search: '',
  status: null as number | null,
  urgency: null as number | null,
  assigned_runner: null as number | null,
  unassigned: false,
});

const pagination = ref({
  sortBy: 'requested_at',
  descending: true,
  page: 1,
  rowsPerPage: 20,
  rowsNumber: 0,
});

interface LocalItem {
  id?: number;
  description: string;
  quantity: number;
  part_number: string | null;
  notes: string | null;
  is_verified: boolean;
}

const requestForm = reactive({
  request_type_id: null as number | null,
  urgency_id: null as number | null,
  not_before_datetime: null as string | null,
  origin_location_id: null as number | null,
  vendor_name: '',
  vendor_id: null as number | null,
  vendor_address_id: null as number | null,
  receiving_location_id: null as number | null,
  customer_id: null as number | null,
  customer_address_id: null as number | null,
  customer_name: '',
  customer_address: '',
  customer_phone: '',
  details: '',
  special_instructions: '',
  slack_notify_pickup: false,
  slack_notify_delivery: false,
  slack_channel: '',
  items: [] as LocalItem[],
});

// Computed properties for button-based selection
const requestTypeButtons = computed(() =>
  lookups.value.request_types?.map((t: { id: number; name: string }) => ({
    label: t.name.charAt(0).toUpperCase() + t.name.slice(1),
    value: t.id,
    icon: t.name === 'pickup' ? 'upload' : t.name === 'delivery' ? 'download' : 'swap_horiz'
  })) || []
);

// Urgency level labels mapping
const urgencyLabels: Record<string, string> = {
  first_available: 'First Available Run',
  today: 'Today',
  saturday: 'Saturday',
  next_business_day: 'Next Business Day',
};

// Format urgency levels with proper display labels
const formattedUrgencyLevels = computed(() =>
  lookups.value.urgency_levels?.map((u: { id: number; name: string }) => ({
    id: u.id,
    name: u.name,
    label: urgencyLabels[u.name] || u.name.charAt(0).toUpperCase() + u.name.slice(1).replace(/_/g, ' ')
  })) || []
);

// Filter locations to only show fixed shops
const shopLocations = computed(() =>
  locations.value.filter((loc: any) => loc.location_type === 'fixed_shop')
);

// Available origin locations (exclude receiving location)
const availableOriginLocations = computed(() =>
  shopLocations.value.filter((loc: any) => loc.id !== requestForm.receiving_location_id)
);

// Available receiving locations (exclude origin location)
const availableReceivingLocations = computed(() =>
  shopLocations.value.filter((loc: any) => loc.id !== requestForm.origin_location_id)
);

// Check if current request type is "transfer"
const isTransferType = computed(() => getRequestTypeName(requestForm.request_type_id) === 'transfer');

// Check if current request type is "pickup"
const isPickupType = computed(() => getRequestTypeName(requestForm.request_type_id) === 'pickup');

// Check if current request type is "delivery"
const isDeliveryType = computed(() => getRequestTypeName(requestForm.request_type_id) === 'delivery');

// Check if current request type is "return" (returning parts to vendor)
const isReturnType = computed(() => getRequestTypeName(requestForm.request_type_id) === 'return');

// Show vendor selection in Supplying Location section for: pickup, or when no origin shop selected (non-transfer, non-delivery, non-return)
// Return type has its own vendor selection in the Receiving Location section
const showPickupVendorSelection = computed(() => {
  if (isDeliveryType.value) return false;
  if (isReturnType.value) return false;  // Return vendor goes in receiving section
  if (isPickupType.value) return true;
  if (isTransferType.value) return false;
  // For other types, show if no origin location selected
  return !requestForm.origin_location_id;
});

// Get request type name from ID
function getRequestTypeName(typeId: number | null): string | null {
  if (!typeId) return null;
  const type = lookups.value.request_types?.find((t: { id: number; name: string }) => t.id === typeId);
  return type?.name || null;
}

// Toggle functions for shop buttons
function toggleOriginLocation(locId: number) {
  requestForm.origin_location_id = requestForm.origin_location_id === locId ? null : locId;
  updateField('origin_location_id', requestForm.origin_location_id);
  // Clear vendor name when a shop is selected
  if (requestForm.origin_location_id) {
    requestForm.vendor_name = '';
  }
}

function toggleReceivingLocation(locId: number) {
  requestForm.receiving_location_id = requestForm.receiving_location_id === locId ? null : locId;
  updateField('receiving_location_id', requestForm.receiving_location_id);
  // Clear customer details when a shop is selected
  if (requestForm.receiving_location_id) {
    requestForm.customer_name = '';
    requestForm.customer_address = '';
    requestForm.customer_phone = '';
  }
}

// Vendor selection handlers
function handleVendorSelected(vendor: Vendor | null) {
  selectedVendor.value = vendor;
  if (vendor) {
    // Clear the legacy vendor_name field when using structured vendor
    requestForm.vendor_name = '';
  }
  // Clear address selection when vendor changes (VendorAddressSelect handles auto-select)
  requestForm.vendor_address_id = null;
}

function handleVendorCreated(vendor: Vendor) {
  selectedVendor.value = vendor;
  requestForm.vendor_name = '';
}

function handleAddressSelected(address: Address | null) {
  // Address selection is handled via v-model, this is for additional logic if needed
}

function handleAddressCreated(address: Address) {
  // Refresh vendor to get updated addresses
  if (requestForm.vendor_id) {
    vendorsStore.fetchVendor(requestForm.vendor_id).then(vendor => {
      selectedVendor.value = vendor;
    });
  }
}

// Customer selection handlers
function handleCustomerSelected(customer: Customer | null) {
  selectedCustomer.value = customer;
  if (customer) {
    // Clear the legacy customer fields when using structured customer
    requestForm.customer_name = '';
    requestForm.customer_address = '';
    requestForm.customer_phone = '';
  }
  // Clear address selection when customer changes (CustomerAddressSelect handles auto-select)
  requestForm.customer_address_id = null;
}

function handleCustomerCreated(customer: Customer) {
  selectedCustomer.value = customer;
  requestForm.customer_name = '';
  requestForm.customer_address = '';
  requestForm.customer_phone = '';
}

function handleCustomerAddressSelected(address: Address | null) {
  // Address selection is handled via v-model, this is for additional logic if needed
  // Populate phone from address if available
  if (address?.phone && !requestForm.customer_phone) {
    requestForm.customer_phone = address.phone;
  }
}

function handleCustomerAddressCreated(address: Address) {
  // Refresh customer to get updated addresses
  if (requestForm.customer_id) {
    customersStore.fetchCustomer(requestForm.customer_id).then(customer => {
      selectedCustomer.value = customer;
    });
  }
}

// Get button style for a location based on its colors and selection state
function getLocationButtonStyle(location: any, isSelected: boolean): Record<string, string> {
  const style: Record<string, string> = {};

  if (isSelected) {
    // When selected, use the location's colors (or defaults)
    style.backgroundColor = location.background_color || '#1976D2';
    style.color = location.text_color || '#FFFFFF';
  } else {
    // When not selected, use muted version or location colors at lower opacity
    if (location.background_color) {
      style.backgroundColor = location.background_color + '33'; // 20% opacity
      style.color = location.background_color;
      style.border = `1px solid ${location.background_color}`;
    } else {
      style.backgroundColor = '#E0E0E0';
      style.color = '#424242';
    }
  }

  return style;
}

// Watch for items changes to auto-expand section
watch(() => requestForm.items.length, (newLength) => {
  if (newLength > 0) {
    createItemsExpanded.value = true;
  }
});

// Watch for request type changes to set smart defaults
watch(() => requestForm.request_type_id, (newTypeId) => {
  const typeName = getRequestTypeName(newTypeId);
  const userHomeLocationId = authStore.user?.home_location_id || null;

  if (typeName === 'pickup') {
    // For pickup: picking up from vendor, delivering TO user's shop
    requestForm.receiving_location_id = userHomeLocationId;
    requestForm.origin_location_id = null;
    // Clear customer details since we're delivering to our shop
    requestForm.customer_name = '';
    requestForm.customer_address = '';
    requestForm.customer_phone = '';
    requestForm.customer_id = null;
    requestForm.customer_address_id = null;
    selectedCustomer.value = null;
  } else if (typeName === 'delivery') {
    // For delivery: picking up FROM user's shop, delivering to customer
    requestForm.origin_location_id = userHomeLocationId;
    requestForm.receiving_location_id = null;
    // Clear vendor since we're picking up from our shop
    requestForm.vendor_name = '';
    requestForm.vendor_id = null;
    requestForm.vendor_address_id = null;
    selectedVendor.value = null;
  } else if (typeName === 'transfer') {
    // For transfer: picking up from another shop, delivering TO user's shop
    requestForm.receiving_location_id = userHomeLocationId;
    requestForm.origin_location_id = null;
    // Clear vendor and customer details
    requestForm.vendor_name = '';
    requestForm.vendor_id = null;
    requestForm.vendor_address_id = null;
    selectedVendor.value = null;
    requestForm.customer_name = '';
    requestForm.customer_address = '';
    requestForm.customer_phone = '';
    requestForm.customer_id = null;
    requestForm.customer_address_id = null;
    selectedCustomer.value = null;
  } else if (typeName === 'return') {
    // For return: picking up FROM user's shop, returning to vendor
    requestForm.origin_location_id = userHomeLocationId;
    requestForm.receiving_location_id = null;
    // Clear customer details since we're returning to vendor
    requestForm.customer_name = '';
    requestForm.customer_address = '';
    requestForm.customer_phone = '';
    requestForm.customer_id = null;
    requestForm.customer_address_id = null;
    selectedCustomer.value = null;
  }
});

// Form validation
const {
  registerField,
  updateField,
  touchField,
  getError,
  handleSubmit,
  reset: resetValidation,
  isSubmitting,
} = useFormValidation();

// Register validation rules
registerField('request_type_id', [
  validationRules.required('Please select a request type'),
]);

registerField('urgency_id', [
  validationRules.required('Please select an urgency level'),
]);

registerField('details', [
  validationRules.required('Please provide details about this request'),
]);

registerField('customer_phone', [
  validationRules.phone(),
]);

// Draft state
const {
  hasDraft,
  loadDraft,
  clearDraft,
  formatDraftAge,
} = useDraftState(requestForm, {
  key: 'parts-request-create',
  excludeFields: [], // No sensitive fields in this form
});

const columns = [
  { name: 'reference_number', label: 'Reference #', field: 'reference_number', align: 'left' as const, sortable: true },
  { name: 'type', label: 'Type', field: 'request_type', align: 'left' as const },
  { name: 'from_to', label: 'From / To', field: 'from_to', align: 'left' as const },
  { name: 'details', label: 'Details', field: 'details', align: 'left' as const },
  { name: 'urgency', label: 'Urgency', field: 'urgency', align: 'center' as const },
  { name: 'status', label: 'Status', field: 'status', align: 'center' as const },
  { name: 'assigned_run', label: 'Assigned Run', field: 'run_instance', align: 'left' as const },
  { name: 'requested_by', label: 'Requested By', field: 'requested_by', align: 'left' as const },
];

function can(ability: string): boolean {
  return authStore.can(ability);
}

function getTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    pickup: 'Pickup',
    delivery: 'Delivery',
    transfer: 'Transfer',
    return: 'Return',
  };
  return labels[type] || type;
}

function getTypeColor(type: string): string {
  const colors: Record<string, string> = {
    pickup: 'blue',
    delivery: 'green',
    transfer: 'orange',
    return: 'red',
  };
  return colors[type] || 'grey';
}

function getStatusLabel(status: string): string {
  return status.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    new: 'blue',
    assigned: 'cyan',
    en_route_pickup: 'amber',
    picked_up: 'orange',
    en_route_dropoff: 'purple',
    delivered: 'positive',
    canceled: 'grey',
    problem: 'negative',
    return: 'red',
  };
  return colors[status] || 'grey';
}

function getUrgencyColor(urgency: string): string {
  const colors: Record<string, string> = {
    first_available: 'teal',
    today: 'orange',
    saturday: 'purple',
    next_business_day: 'blue',
  };
  return colors[urgency] || 'grey';
}

function getUrgencyLabel(urgency: string): string {
  const labels: Record<string, string> = {
    first_available: 'First Available Run',
    today: 'Today',
    saturday: 'Saturday',
    next_business_day: 'Next Business Day',
  };
  return labels[urgency] || urgency;
}

function getOriginText(request: PartsRequest): string {
  // For return requests: origin is the shop (origin_location), destination is the vendor
  if (request.request_type?.name === 'return') {
    return request.origin_location?.name || 'Unknown Shop';
  }
  // For other requests: origin is vendor or origin_location
  if (request.vendor) {
    return request.vendor.name;
  }
  return request.vendor_name || request.origin_location?.name || request.origin_address || 'Unknown';
}

function getDestinationText(request: PartsRequest): string {
  // For return requests: destination is the vendor (where we're returning TO)
  if (request.request_type?.name === 'return') {
    if (request.vendor) {
      return request.vendor.name;
    }
    return request.vendor_name || 'Unknown Vendor';
  }
  // For other requests: destination is customer or receiving_location
  return request.customer_name || request.receiving_location?.name || request.customer_address || 'Unknown';
}

function formatDateTime(dateString: string): string {
  return new Date(dateString).toLocaleString();
}

function formatTime(timeString: string | undefined | null): string {
  if (!timeString) return ''
  // Handle HH:mm:ss or HH:mm format
  const parts = timeString.split(':')
  const hours = parts[0] ?? '0'
  const minutes = parts[1] ?? '00'
  const hour = parseInt(hours, 10)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${minutes} ${ampm}`
}

function getEventDisplayName(eventType: string): string {
  const names: Record<string, string> = {
    created: 'Created',
    assigned: 'Assigned',
    unassigned: 'Unassigned',
    started: 'Started',
    arrived_pickup: 'Arrived at Pickup',
    picked_up: 'Picked Up',
    departed_pickup: 'Departed Pickup',
    arrived_dropoff: 'Arrived at Dropoff',
    delivered: 'Delivered',
    canceled: 'Canceled',
    problem_reported: 'Problem Reported',
    note_added: 'Note Added',
  };
  return names[eventType] || eventType;
}

// Pending document helpers
function triggerPendingFileInput() {
  pendingFileInputRef.value?.click();
}

function handlePendingFileSelect(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = input.files;
  if (files) {
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      if (file) {
        pendingDocuments.value.push({
          file,
          description: '',
        });
      }
    }
    // Auto-expand section when files are added
    if (pendingDocuments.value.length > 0) {
      createDocumentsExpanded.value = true;
    }
  }
  // Reset input so same file can be selected again
  input.value = '';
}

function removePendingDocument(index: number) {
  pendingDocuments.value.splice(index, 1);
}

function getPendingDocIcon(file: File): string {
  const type = file.type;
  if (type.startsWith('image/')) return 'image';
  if (type === 'application/pdf') return 'picture_as_pdf';

  const ext = file.name.split('.').pop()?.toLowerCase();
  switch (ext) {
    case 'doc':
    case 'docx':
      return 'description';
    case 'xls':
    case 'xlsx':
      return 'table_chart';
    case 'txt':
      return 'article';
    default:
      return 'insert_drive_file';
  }
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Pending image helpers
function triggerPendingImageInput() {
  pendingImageInputRef.value?.click();
}

function handlePendingImageSelect(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = input.files;
  if (files) {
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      if (file && file.type.startsWith('image/')) {
        // Create preview URL
        const reader = new FileReader();
        reader.onload = (e) => {
          pendingImages.value.push({
            file,
            preview: e.target?.result as string,
          });
          // Auto-expand section when images are added
          createPhotosExpanded.value = true;
        };
        reader.readAsDataURL(file);
      }
    }
  }
  // Reset input so same file can be selected again
  input.value = '';
}

function removePendingImage(index: number) {
  pendingImages.value.splice(index, 1);
  // If carousel is open and we deleted the current image, adjust
  if (showPendingCarousel.value) {
    if (pendingImages.value.length === 0) {
      showPendingCarousel.value = false;
    } else {
      pendingCarouselIndex.value = Math.min(pendingCarouselIndex.value, pendingImages.value.length - 1);
      pendingCarouselSlide.value = pendingCarouselIndex.value;
    }
  }
}

function openPendingCarousel(index: number) {
  pendingCarouselIndex.value = index;
  pendingCarouselSlide.value = index;
  showPendingCarousel.value = true;
}

function onPendingSlideChange(newSlide: string | number) {
  pendingCarouselIndex.value = typeof newSlide === 'number' ? newSlide : parseInt(newSlide, 10);
}

function removePendingImageFromCarousel() {
  const indexToRemove = pendingCarouselIndex.value;
  pendingImages.value.splice(indexToRemove, 1);

  if (pendingImages.value.length === 0) {
    showPendingCarousel.value = false;
  } else {
    // Move to next or previous image
    pendingCarouselIndex.value = Math.min(indexToRemove, pendingImages.value.length - 1);
    pendingCarouselSlide.value = pendingCarouselIndex.value;
  }
}

async function fetchRequests() {
  const params: any = {
    page: pagination.value.page,
    per_page: pagination.value.rowsPerPage,
  };

  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.status) params.status = filters.value.status;
  if (filters.value.urgency) params.urgency = filters.value.urgency;
  if (filters.value.assigned_runner) params.assigned_runner = filters.value.assigned_runner;
  if (filters.value.unassigned) params.unassigned = 'true';

  const result = await partsRequestsStore.fetchRequests(params);
  if (result) {
    pagination.value.rowsNumber = result.total;
  }
}

const debouncedFetch = debounce(() => {
  fetchRequests();
}, 500);

function onTableRequest(props: any) {
  pagination.value.page = props.pagination.page;
  pagination.value.rowsPerPage = props.pagination.rowsPerPage;
  fetchRequests();
}

function onRowClick(evt: Event, row: PartsRequest) {
  viewRequest(row);
}

function openCreateDialog() {
  // Reset validation first
  resetValidation();

  // Set default urgency to "first_available" if available
  const firstAvailable = lookups.value.urgency_levels?.find((u: { name: string }) => u.name === 'first_available');

  // Set default request type to "pickup"
  const pickupType = lookups.value.request_types?.find((t: { name: string }) => t.name === 'pickup');

  // Get user's home location ID for defaults
  const userHomeLocationId = authStore.user?.home_location_id || null;

  // Set form defaults
  const defaults = {
    // Default to pickup type
    request_type_id: pickupType?.id || null,
    urgency_id: firstAvailable?.id || null,
    not_before_datetime: null,
    origin_location_id: null,
    vendor_name: '',
    vendor_id: null,
    vendor_address_id: null,
    // Default receiving location to user's home shop (for pickup requests)
    receiving_location_id: userHomeLocationId,
    customer_id: null,
    customer_address_id: null,
    customer_name: '',
    customer_address: '',
    customer_phone: '',
    details: '',
    special_instructions: '',
    slack_notify_pickup: false,
    slack_notify_delivery: false,
    slack_channel: '',
    items: [] as LocalItem[],
  };

  Object.assign(requestForm, defaults);

  // Reset vendor selection state
  selectedVendor.value = null;

  // Reset customer selection state
  selectedCustomer.value = null;

  // Reset pending documents and images
  pendingDocuments.value = [];
  pendingImages.value = [];

  // Reset expansion states (default collapsed)
  createItemsExpanded.value = false;
  createDocumentsExpanded.value = false;
  createPhotosExpanded.value = false;

  // Sync default values to validation system so they're recognized on submit
  updateField('request_type_id', defaults.request_type_id);
  updateField('urgency_id', defaults.urgency_id);
  updateField('receiving_location_id', defaults.receiving_location_id);

  showCreateDialog.value = true;
}

async function submitCreateForm() {
  await handleSubmit(async () => {
    const newRequest = await partsRequestsStore.createRequest(requestForm);

    // Upload pending documents if any
    if (pendingDocuments.value.length > 0 && newRequest?.id) {
      for (const doc of pendingDocuments.value) {
        try {
          await partsRequestsStore.uploadDocument(newRequest.id, doc.file, doc.description || undefined);
        } catch (error) {
          console.error('Failed to upload document:', doc.file.name, error);
          // Continue uploading other documents even if one fails
        }
      }
      pendingDocuments.value = [];
    }

    // Upload pending images if any
    if (pendingImages.value.length > 0 && newRequest?.id) {
      for (const img of pendingImages.value) {
        try {
          await partsRequestsStore.uploadImage(newRequest.id, img.file, { source: 'requester' });
        } catch (error) {
          console.error('Failed to upload image:', img.file.name, error);
          // Continue uploading other images even if one fails
        }
      }
      pendingImages.value = [];
    }

    showCreateDialog.value = false;
    clearDraft();
    await fetchRequests();
  });
}

function openAssignDialog(request: PartsRequest) {
  selectedRequest.value = request;
  selectedRunnerId.value = null;
  showAssignDialog.value = true;
}

async function assignRunner() {
  if (!selectedRequest.value || !selectedRunnerId.value) return;

  try {
    await partsRequestsStore.assignRunner(selectedRequest.value.id, selectedRunnerId.value);
    showAssignDialog.value = false;
    fetchRequests();
  } catch (error) {
    // Error handled by store
  }
}

async function unassignRunner(request: PartsRequest) {
  try {
    await partsRequestsStore.unassignRunner(request.id);
    fetchRequests();
  } catch (error) {
    // Error handled by store
  }
}

async function viewRequest(request: PartsRequest) {
  // Reset expansion states and counts
  itemsExpanded.value = false;
  documentsExpanded.value = false;
  photosExpanded.value = false;
  runnerPhotosExpanded.value = false;
  notesExpanded.value = false;
  itemsCount.value = 0;
  documentsCount.value = 0;
  photosCount.value = 0;
  runnerPhotosCount.value = 0;
  notesCount.value = 0;

  viewingRequest.value = await partsRequestsStore.fetchRequest(request.id);
  showViewDialog.value = true;
}

// Handlers for expansion item counts - expand when data exists
function onItemsCountChanged(count: number) {
  itemsCount.value = count;
  if (count > 0) itemsExpanded.value = true;
}

function onDocumentsCountChanged(count: number) {
  documentsCount.value = count;
  if (count > 0) documentsExpanded.value = true;
}

function onPhotosCountChanged(count: number) {
  photosCount.value = count;
  if (count > 0) photosExpanded.value = true;
}

function onRunnerPhotosCountChanged(count: number) {
  runnerPhotosCount.value = count;
  if (count > 0) runnerPhotosExpanded.value = true;
}

function onNotesCountChanged(count: number) {
  notesCount.value = count;
  if (count > 0) notesExpanded.value = true;
}

async function openDocuments(request: PartsRequest) {
  // Opens same view dialog - documents are now on the same page
  viewingRequest.value = await partsRequestsStore.fetchRequest(request.id);
  showViewDialog.value = true;
}

async function viewTimeline(request: PartsRequest) {
  timeline.value = await partsRequestsStore.fetchTimeline(request.id);
  showTimelineDialog.value = true;
}

async function viewPhotos(request: PartsRequest) {
  photos.value = await partsRequestsStore.fetchPhotos(request.id);
  showPhotosDialog.value = true;
}

/**
 * Auto-assign request to next available run
 */
async function assignToNextRun(request: PartsRequest) {
  assigningToNextRun.value = true;
  try {
    const result = await partsRequestsStore.assignToNextAvailableRun(request.id);

    if (result.saturdayPrompt) {
      // Show Saturday prompt dialog
      const data: { requestId: number; saturdayDate: string; saturdayTime?: string; routeId?: number } = {
        requestId: request.id,
        saturdayDate: result.saturdayDate || '',
      };
      if (result.saturdayTime) data.saturdayTime = result.saturdayTime;
      if (result.routeId) data.routeId = result.routeId;
      saturdayPromptData.value = data;
      showSaturdayPromptDialog.value = true;
      return;
    }

    if (result.needsManualAssignment) {
      // User was notified via store notification
      return;
    }

    if (result.success && result.data) {
      // Update the viewing request with the new data
      viewingRequest.value = result.data;
      // Refresh the list
      await fetchRequests();
    }
  } catch (error) {
    console.error('Failed to assign to next run:', error);
  } finally {
    assigningToNextRun.value = false;
  }
}

/**
 * Handle Saturday prompt response - user chooses Saturday or next business day
 */
async function handleSaturdayChoice(useSaturday: boolean) {
  if (!saturdayPromptData.value) return;

  showSaturdayPromptDialog.value = false;

  // TODO: Call backend with the user's choice
  // For now, if user chooses Saturday, we accept; otherwise need separate endpoint
  // The backend auto-assignment already assigned to Saturday, so:
  // - If useSaturday: refresh to show assignment
  // - If !useSaturday: would need to unassign and find next weekday run

  if (useSaturday) {
    // Refresh to show the Saturday assignment
    if (viewingRequest.value) {
      viewingRequest.value = await partsRequestsStore.fetchRequest(viewingRequest.value.id);
    }
    await fetchRequests();
    $q.notify({
      type: 'positive',
      message: 'Request assigned to Saturday run',
    });
  } else {
    // For now, inform user that weekday assignment needs manual selection
    $q.notify({
      type: 'info',
      message: 'Please manually assign to a weekday run',
    });
  }

  saturdayPromptData.value = null;
}

async function loadRunners() {
  try {
    const response = await api.get('/users', { params: { active: true } });
    runners.value = response.data.filter((u: any) => u.role === 'runner_driver');
  } catch (error) {
    console.error('Failed to load runners', error);
  }
}

async function loadLocations() {
  try {
    const response = await api.get('/locations', { params: { per_page: 100 } });
    locations.value = response.data.data;
  } catch (error) {
    console.error('Failed to load locations', error);
  }
}

onMounted(async () => {
  await partsRequestsStore.fetchLookups();
  // Set default urgency to "first_available" if available
  const firstAvailable = lookups.value.urgency_levels?.find((u: { name: string }) => u.name === 'first_available');
  if (firstAvailable && !requestForm.urgency_id) {
    requestForm.urgency_id = firstAvailable.id;
  }
  await fetchRequests();
  await loadRunners();
  await loadLocations();
});
</script>

<style scoped>
/* Clickable table rows */
.clickable-rows :deep(tbody tr) {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.clickable-rows :deep(tbody tr:hover) {
  background-color: rgba(0, 0, 0, 0.04);
}

/* Simple spacing for view dialog content children */
.view-dialog-content > * {
  margin-bottom: 16px;
}

.view-dialog-content > *:last-child {
  margin-bottom: 0;
}

/* Custom row classes using gap instead of negative margins (q-gutter uses negative margins) */
.chip-row {
  gap: 4px;
}

.from-to-row {
  gap: 8px;
}

.info-row {
  gap: 16px;
}

/* Pending images grid for create form */
.pending-images-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

@media (max-width: 400px) {
  .pending-images-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.pending-image-thumb {
  position: relative;
  border-radius: 4px;
  overflow: hidden;
  cursor: pointer;
}

.pending-image-thumb:hover {
  opacity: 0.9;
}

.pending-image-thumb .remove-image-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  z-index: 1;
}

/* Pending images preview popup */
.pending-preview-card {
  width: 90vw;
  max-width: 500px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}

.pending-carousel-container {
  position: relative;
  flex: 1;
  min-height: 0;
}

.pending-preview-carousel {
  height: 300px;
}

@media (min-height: 600px) {
  .pending-preview-carousel {
    height: 350px;
  }
}

@media (min-height: 800px) {
  .pending-preview-carousel {
    height: 450px;
  }
}

.pending-carousel-image {
  max-width: 100%;
  max-height: 100%;
}

:deep(.pending-preview-carousel .q-carousel__navigation) {
  bottom: 8px;
}

:deep(.pending-preview-carousel .q-carousel__navigation-icon) {
  font-size: 8px;
}

:deep(.pending-preview-carousel .q-carousel__arrow) {
  font-size: 16px;
}
</style>
