# Mobile-First Form System Guide

This guide explains how to use the mobile-first form components and composables in the ServcoApp Quasar application.

## Overview

The mobile form system consists of:
- **Composables**: Reusable logic for validation and draft state
- **Components**: Pre-built form elements optimized for mobile
- **Patterns**: Best practices for creating mobile-friendly forms

## Components

### MobileFormDialog

A dialog wrapper optimized for mobile with automatic responsive layout.

**Features:**
- Responsive sizing (full screen on mobile, max-width on desktop)
- Draft state banner
- Full-width action buttons on mobile
- Proper touch targets (44px minimum)

**Usage:**

```vue
<template>
  <MobileFormDialog
    v-model="showDialog"
    title="Create Parts Request"
    submit-label="Create Request"
    :loading="isSubmitting"
    :has-draft="hasDraft"
    :draft-age="formatDraftAge()"
    @submit="handleSubmit"
    @load-draft="loadDraft"
    @discard-draft="clearDraft"
  >
    <!-- Form fields go here -->
    <MobileFormField ... />
    <MobileSelect ... />
  </MobileFormDialog>
</template>

<script setup lang="ts">
import MobileFormDialog from 'src/components/MobileFormDialog.vue';
import MobileFormField from 'src/components/MobileFormField.vue';
import MobileSelect from 'src/components/MobileSelect.vue';
</script>
```

**Props:**
- `modelValue` (boolean): Dialog visibility
- `title` (string): Dialog title
- `submitLabel` (string): Submit button text (default: "Submit")
- `loading` (boolean): Show loading state
- `persistent` (boolean): Prevent closing by clicking outside
- `maximized` (boolean): Force full screen
- `singleColumn` (boolean): Force single column layout (default: false, uses grid)
- `hasDraft` (boolean): Show draft banner
- `draftAge` (string): Draft age text (e.g., "5 minutes ago")
- `showDraftBanner` (boolean): Show/hide draft banner (default: true)

**Layout:**
- Single column on mobile (< 600px)
- 2-column grid on tablet and desktop (≥ 600px)
- Override with `singleColumn` prop for always single-column forms

### MobileFormField

A unified input component that handles text, email, phone, number, password, textarea, date, and time inputs.

**Features:**
- Proper input modes for mobile keyboards
- Stack labels (always visible)
- Inline validation
- Minimum 44px touch targets
- Date/time pickers optimized for mobile

**Usage:**

```vue
<MobileFormField
  name="customer_name"
  v-model="form.customer_name"
  label="Customer Name"
  type="text"
  :error="getError('customer_name')"
  @blur="touchField('customer_name')"
  required
  icon="person"
  hint="Enter the customer's full name"
/>

<MobileFormField
  name="phone"
  v-model="form.phone"
  label="Phone Number"
  type="tel"
  :error="getError('phone')"
  @blur="touchField('phone')"
  icon="phone"
/>

<MobileFormField
  name="email"
  v-model="form.email"
  label="Email Address"
  type="email"
  :error="getError('email')"
  @blur="touchField('email')"
  icon="email"
/>

<MobileFormField
  name="delivery_date"
  v-model="form.delivery_date"
  label="Delivery Date"
  type="date"
  :error="getError('delivery_date')"
  @blur="touchField('delivery_date')"
/>

<MobileFormField
  name="notes"
  v-model="form.notes"
  label="Notes"
  type="textarea"
  :rows="6"
  :error="getError('notes')"
  @blur="touchField('notes')"
/>
```

**Props:**
- `name` (string, required): Field name (used for validation)
- `modelValue` (any, required): Field value
- `label` (string, required): Field label
- `type` (string): Input type - 'text', 'email', 'tel', 'number', 'password', 'textarea', 'date', 'time'
- `hint` (string): Help text
- `error` (string): Error message
- `icon` (string): Quasar icon name
- `readonly` (boolean): Read-only mode
- `disable` (boolean): Disabled state
- `required` (boolean): Required field
- `autofocus` (boolean): Auto-focus on mount
- `rows` (number): Rows for textarea (default: 4)

**Input Modes (automatic):**
- `email`: Email keyboard
- `tel`: Phone keyboard with numbers
- `number`: Numeric keyboard
- `text`: Standard keyboard

### MobileSelect

A select/dropdown component with automatic search for large lists.

**Features:**
- Search enabled when options > 10 (configurable)
- Dialog behavior on mobile (full screen)
- Multi-select with chips
- Clearable option
- Minimum 44px touch targets

**Usage:**

```vue
<MobileSelect
  name="location"
  v-model="form.location_id"
  label="Service Location"
  :options="locations"
  option-value="id"
  option-label="name"
  :error="getError('location')"
  @blur="touchField('location')"
  icon="location_on"
  clearable
  required
/>

<MobileSelect
  name="roles"
  v-model="form.role_ids"
  label="User Roles"
  :options="roles"
  option-value="id"
  option-label="display_name"
  multiple
  icon="security"
/>
```

**Props:**
- `name` (string, required): Field name
- `modelValue` (any, required): Selected value(s)
- `label` (string, required): Field label
- `options` (array, required): Options array
- `optionValue` (string): Property for value (default: 'id')
- `optionLabel` (string): Property for label (default: 'name')
- `hint` (string): Help text
- `error` (string): Error message
- `icon` (string): Quasar icon name
- `readonly` (boolean): Read-only mode
- `disable` (boolean): Disabled state
- `required` (boolean): Required field
- `multiple` (boolean): Allow multiple selections
- `clearable` (boolean): Show clear button
- `searchThreshold` (number): Show search when options exceed this (default: 10)

## Composables

### useFormValidation

Handles form validation with inline error display and auto-scroll to errors.

**Features:**
- Blur and submit validation modes
- Auto-scroll to first error
- Built-in validation rules
- Custom validation support

**Usage:**

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useFormValidation, validationRules } from 'src/composables/useFormValidation';

const form = ref({
  customer_name: '',
  email: '',
  phone: '',
  quantity: null,
});

const {
  registerField,
  updateField,
  touchField,
  getError,
  hasError,
  handleSubmit,
  reset,
  isSubmitting,
} = useFormValidation();

// Register fields with validation rules
registerField('customer_name', [
  validationRules.required('Customer name is required'),
  validationRules.minLength(2, 'Name must be at least 2 characters'),
]);

registerField('email', [
  validationRules.required(),
  validationRules.email(),
]);

registerField('phone', [
  validationRules.required(),
  validationRules.phone(),
]);

registerField('quantity', [
  validationRules.required('Quantity is required'),
  validationRules.min(1, 'Quantity must be at least 1'),
  validationRules.max(999, 'Quantity cannot exceed 999'),
]);

const submitForm = async () => {
  await handleSubmit(async () => {
    // This only runs if validation passes
    await api.post('/parts-requests', form.value);
    showDialog.value = false;
    reset();
  });
};
</script>

<template>
  <MobileFormField
    name="customer_name"
    v-model="form.customer_name"
    label="Customer Name"
    :error="getError('customer_name')"
    @update:model-value="updateField('customer_name', $event)"
    @blur="touchField('customer_name')"
  />
</template>
```

**Built-in Validation Rules:**

```typescript
validationRules.required(message?)
validationRules.email(message?)
validationRules.phone(message?)
validationRules.minLength(min, message?)
validationRules.maxLength(max, message?)
validationRules.min(min, message?)
validationRules.max(max, message?)
validationRules.custom(validationFn, message)
```

**Custom Validation:**

```typescript
registerField('custom_field', [
  validationRules.custom(
    (value) => value.includes('special'),
    'Value must include "special"'
  ),
]);
```

### useDraftState

Handles automatic draft saving to localStorage.

**Features:**
- Auto-save with debounce (500ms default)
- Draft age tracking
- Load/discard functionality
- Exclude sensitive fields

**Usage:**

```vue
<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useDraftState } from 'src/composables/useDraftState';

const form = reactive({
  customer_name: '',
  phone: '',
  email: '',
  notes: '',
  password: '', // Will be excluded
});

const {
  hasDraft,
  loadDraft,
  clearDraft,
  formatDraftAge,
} = useDraftState(form, {
  key: 'parts-request-create',
  debounceMs: 500,
  excludeFields: ['password'], // Don't save sensitive data
});

const handleLoadDraft = () => {
  loadDraft();
  // Draft is now loaded into form
};

const handleDiscardDraft = () => {
  clearDraft();
};

const handleSuccess = () => {
  clearDraft(); // Clear draft after successful submit
};
</script>

<template>
  <MobileFormDialog
    v-model="showDialog"
    :has-draft="hasDraft"
    :draft-age="formatDraftAge()"
    @load-draft="handleLoadDraft"
    @discard-draft="handleDiscardDraft"
  >
    <!-- Fields auto-save as user types -->
  </MobileFormDialog>
</template>
```

## Complete Example

Here's a complete example combining all features:

```vue
<template>
  <MobileFormDialog
    v-model="showCreateDialog"
    title="Create Parts Request"
    submit-label="Create Request"
    :loading="isSubmitting"
    :has-draft="hasDraft"
    :draft-age="formatDraftAge()"
    @submit="submitForm"
    @load-draft="loadDraft"
    @discard-draft="clearDraft"
  >
    <MobileFormField
      name="title"
      v-model="form.title"
      label="Request Title"
      type="text"
      :error="getError('title')"
      @update:model-value="updateField('title', $event)"
      @blur="touchField('title')"
      required
      icon="title"
      hint="Brief description of what's needed"
    />

    <MobileSelect
      name="type"
      v-model="form.type_id"
      label="Request Type"
      :options="requestTypes"
      option-value="id"
      option-label="name"
      :error="getError('type')"
      @update:model-value="updateField('type', $event)"
      @blur="touchField('type')"
      required
      icon="category"
    />

    <MobileSelect
      name="urgency"
      v-model="form.urgency_level_id"
      label="Urgency Level"
      :options="urgencyLevels"
      option-value="id"
      option-label="name"
      :error="getError('urgency')"
      @update:model-value="updateField('urgency', $event)"
      @blur="touchField('urgency')"
      required
      icon="priority_high"
    />

    <MobileFormField
      name="customer_name"
      v-model="form.customer_name"
      label="Customer Name"
      type="text"
      :error="getError('customer_name')"
      @update:model-value="updateField('customer_name', $event)"
      @blur="touchField('customer_name')"
      icon="person"
    />

    <MobileFormField
      name="customer_phone"
      v-model="form.customer_phone"
      label="Customer Phone"
      type="tel"
      :error="getError('customer_phone')"
      @update:model-value="updateField('customer_phone', $event)"
      @blur="touchField('customer_phone')"
      icon="phone"
    />

    <MobileFormField
      name="notes"
      v-model="form.notes"
      label="Additional Notes"
      type="textarea"
      :rows="4"
      :error="getError('notes')"
      @update:model-value="updateField('notes', $event)"
      @blur="touchField('notes')"
      icon="notes"
      hint="Any special instructions or details"
    />
  </MobileFormDialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useFormValidation, validationRules } from 'src/composables/useFormValidation';
import { useDraftState } from 'src/composables/useDraftState';
import MobileFormDialog from 'src/components/MobileFormDialog.vue';
import MobileFormField from 'src/components/MobileFormField.vue';
import MobileSelect from 'src/components/MobileSelect.vue';
import { api } from 'src/boot/axios';

const showCreateDialog = ref(false);
const requestTypes = ref([]);
const urgencyLevels = ref([]);

const form = reactive({
  title: '',
  type_id: null,
  urgency_level_id: null,
  customer_name: '',
  customer_phone: '',
  notes: '',
});

// Validation
const {
  registerField,
  updateField,
  touchField,
  getError,
  handleSubmit,
  reset: resetValidation,
  isSubmitting,
} = useFormValidation();

registerField('title', [
  validationRules.required('Title is required'),
  validationRules.minLength(3, 'Title must be at least 3 characters'),
]);

registerField('type', [
  validationRules.required('Please select a request type'),
]);

registerField('urgency', [
  validationRules.required('Please select an urgency level'),
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
} = useDraftState(form, {
  key: 'parts-request-create',
});

const submitForm = async () => {
  await handleSubmit(async () => {
    const response = await api.post('/parts-requests', form);
    showCreateDialog.value = false;
    clearDraft();
    resetValidation();
    // Reset form
    Object.keys(form).forEach(key => {
      form[key] = '';
    });
  });
};

onMounted(async () => {
  // Load lookup data
  const [typesRes, urgencyRes] = await Promise.all([
    api.get('/parts-requests/lookups'),
    api.get('/parts-requests/lookups'),
  ]);
  requestTypes.value = typesRes.data.types;
  urgencyLevels.value = urgencyRes.data.urgency_levels;
});
</script>
```

## Best Practices

### 1. Always Use data-field-name

The `data-field-name` attribute is automatically added by `MobileFormField` and `MobileSelect`. This enables auto-scroll to errors.

### 2. Connect Validation to Fields

Always wire up validation events:

```vue
<MobileFormField
  name="field_name"
  v-model="form.field_name"
  :error="getError('field_name')"
  @update:model-value="updateField('field_name', $event)"
  @blur="touchField('field_name')"
/>
```

### 3. Use Appropriate Input Types

Match the input type to the data:
- `type="tel"` for phone numbers (shows numeric keypad)
- `type="email"` for emails (shows @ key)
- `type="number"` for numbers (shows numeric keypad)
- `type="date"` for dates (shows date picker)
- `type="time"` for times (shows time picker)

### 4. Enable Search for Large Lists

`MobileSelect` automatically enables search when options exceed the threshold (default 10):

```vue
<MobileSelect
  :options="allLocations"
  :search-threshold="5"  <!-- Lower threshold -->
/>
```

### 5. Clear Drafts on Success

Always clear the draft after successful form submission:

```typescript
const submitForm = async () => {
  await handleSubmit(async () => {
    await api.post('/endpoint', form);
    clearDraft(); // Important!
    showDialog.value = false;
  });
};
```

### 6. Single vs Grid Layout

Use single column for:
- Long forms (>10 fields)
- Complex fields (textareas, multi-selects)
- When fields are related sequentially

Use grid layout (default) for:
- Short forms (<10 fields)
- Simple fields (text inputs, selects)
- When fields can be grouped in pairs

```vue
<!-- Force single column -->
<MobileFormDialog :single-column="true">
  ...
</MobileFormDialog>
```

### 7. Loading States

Always show loading state during submission:

```vue
<MobileFormDialog
  :loading="isSubmitting"
  @submit="submitForm"
>
```

The `isSubmitting` ref is provided by `useFormValidation`.

### 8. Exclude Sensitive Fields from Drafts

Never save passwords or sensitive data:

```typescript
useDraftState(form, {
  key: 'user-registration',
  excludeFields: ['password', 'password_confirmation', 'ssn', 'credit_card'],
});
```

## Migration Guide

To migrate existing forms to the mobile system:

1. **Replace q-dialog with MobileFormDialog:**

```diff
- <q-dialog v-model="showDialog">
-   <q-card style="min-width: 600px">
-     <q-card-section>
-       <div class="text-h6">Title</div>
-     </q-card-section>
+ <MobileFormDialog
+   v-model="showDialog"
+   title="Title"
+   @submit="handleSubmit"
+ >
```

2. **Replace q-input with MobileFormField:**

```diff
- <q-input
-   v-model="form.name"
-   label="Name"
-   filled
- />
+ <MobileFormField
+   name="name"
+   v-model="form.name"
+   label="Name"
+   :error="getError('name')"
+   @blur="touchField('name')"
+ />
```

3. **Replace q-select with MobileSelect:**

```diff
- <q-select
-   v-model="form.location_id"
-   :options="locations"
-   label="Location"
-   filled
- />
+ <MobileSelect
+   name="location"
+   v-model="form.location_id"
+   :options="locations"
+   label="Location"
+   :error="getError('location')"
+   @blur="touchField('location')"
+ />
```

4. **Add validation composable:**

```typescript
const {
  registerField,
  updateField,
  touchField,
  getError,
  handleSubmit,
  isSubmitting,
} = useFormValidation();

registerField('name', [validationRules.required()]);
registerField('location', [validationRules.required()]);
```

5. **Optionally add draft state:**

```typescript
const { hasDraft, loadDraft, clearDraft, formatDraftAge } = useDraftState(form, {
  key: 'my-form-key',
});
```

## Troubleshooting

### Validation not showing

Make sure you:
1. Registered the field: `registerField('field_name', rules)`
2. Connected blur event: `@blur="touchField('field_name')"`
3. Passed error prop: `:error="getError('field_name')"`

### Auto-scroll not working

Ensure `data-field-name` attribute is set (automatic with MobileFormField/MobileSelect).

### Draft not saving

Check:
1. Form data is reactive (use `ref()` or `reactive()`)
2. Field is not in `excludeFields`
3. localStorage is available
4. No console errors

### Search not appearing in select

The search only appears when options exceed the threshold (default 10). Lower the threshold:

```vue
<MobileSelect :search-threshold="5" />
```
