<template>
  <div class="address-display" :class="{ 'address-display--compact': compact }">
    <!-- Compact mode: single line -->
    <template v-if="compact">
      <div class="address-line">
        <q-icon v-if="showIcon" name="place" size="xs" class="q-mr-xs" />
        <span v-if="address.label" class="address-label">{{ address.label }}: </span>
        <span>{{ address.one_line_address }}</span>
      </div>
    </template>

    <!-- Full mode: multi-line -->
    <template v-else>
      <div v-if="address.label" class="address-label text-weight-medium">
        {{ address.label }}
        <q-badge v-if="isPrimary" color="primary" class="q-ml-xs">Primary</q-badge>
      </div>

      <div v-if="address.company_name" class="address-company">
        {{ address.company_name }}
      </div>

      <div v-if="address.attention" class="address-attention text-caption">
        ATTN: {{ address.attention }}
      </div>

      <div class="address-street">
        {{ address.line1 }}
      </div>

      <div v-if="address.line2" class="address-street">
        {{ address.line2 }}
      </div>

      <div class="address-city-state">
        {{ address.city }}, {{ address.state }} {{ address.postal_code }}
      </div>

      <div v-if="address.country && address.country !== 'US'" class="address-country">
        {{ address.country }}
      </div>

      <div v-if="showContact && (address.phone || address.email)" class="address-contact q-mt-xs">
        <div v-if="address.phone" class="address-phone">
          <q-icon name="phone" size="xs" class="q-mr-xs" />
          <a :href="'tel:' + address.phone">{{ formatPhone(address.phone) }}</a>
        </div>
        <div v-if="address.email" class="address-email">
          <q-icon name="email" size="xs" class="q-mr-xs" />
          <a :href="'mailto:' + address.email">{{ address.email }}</a>
        </div>
      </div>

      <div v-if="showInstructions && address.instructions" class="address-instructions q-mt-sm">
        <q-icon name="info" size="xs" class="q-mr-xs" />
        <span class="text-caption text-grey-8">{{ address.instructions }}</span>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Address } from 'src/types/vendors';

interface Props {
  address: Address;
  compact?: boolean;
  showIcon?: boolean;
  showContact?: boolean;
  showInstructions?: boolean;
  isPrimary?: boolean;
}

withDefaults(defineProps<Props>(), {
  compact: false,
  showIcon: false,
  showContact: true,
  showInstructions: true,
  isPrimary: false,
});

const formatPhone = (phone: string): string => {
  // Simple formatting for US phone numbers
  const cleaned = phone.replace(/\D/g, '');
  if (cleaned.length === 10) {
    return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`;
  }
  return phone;
};
</script>

<style scoped lang="scss">
.address-display {
  font-size: 14px;
  line-height: 1.4;

  &--compact {
    font-size: 13px;
  }

  .address-label {
    color: $primary;
  }

  .address-company {
    font-weight: 500;
  }

  .address-attention {
    color: $grey-7;
  }

  .address-contact {
    font-size: 13px;

    a {
      color: $primary;
      text-decoration: none;

      &:hover {
        text-decoration: underline;
      }
    }
  }

  .address-instructions {
    background: $grey-2;
    padding: 8px;
    border-radius: 4px;
    font-style: italic;
  }
}
</style>
