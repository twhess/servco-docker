<template>
  <q-avatar :size="size" :class="avatarClass">
    <!-- Custom uploaded image -->
    <img v-if="avatarUrl" :src="avatarUrl" />

    <!-- Preset: Initials with color -->
    <span
      v-else-if="presetData?.type === 'initials'"
      :class="`bg-${presetData.color} text-white flex flex-center`"
      :style="initialsStyle"
    >
      {{ computedInitials }}
    </span>

    <!-- Preset: Icon -->
    <template v-else-if="presetData?.type === 'icon'">
      <div
        :class="`bg-${presetData.color} text-white flex flex-center`"
        style="width: 100%; height: 100%"
      >
        <q-icon :name="presetData.name" :size="iconSize" />
      </div>
    </template>

    <!-- Preset: Solid color -->
    <template v-else-if="presetData?.type === 'solid'">
      <div
        :class="`bg-${presetData.color}`"
        style="width: 100%; height: 100%"
      />
    </template>

    <!-- Default: Initials fallback -->
    <span
      v-else
      class="bg-primary text-white flex flex-center"
      :style="initialsStyle"
    >
      {{ computedInitials }}
    </span>
  </q-avatar>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  avatar?: string | null;
  firstName?: string;
  lastName?: string;
  preferredName?: string;
  username?: string;
  size?: string;
  avatarClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
  size: '40px',
  avatarClass: '',
});

// Parse numeric size for font calculations
const numericSize = computed(() => {
  const match = props.size.match(/^(\d+)/);
  return match ? parseInt(match[1], 10) : 40;
});

const initialsStyle = computed(() => ({
  width: '100%',
  height: '100%',
  fontSize: `${Math.round(numericSize.value * 0.4)}px`,
}));

const iconSize = computed(() => `${Math.round(numericSize.value * 0.55)}px`);

const computedInitials = computed(() => {
  const firstName = props.firstName || props.preferredName || '';
  const lastName = props.lastName || '';

  if (firstName && lastName) {
    return `${firstName[0]}${lastName[0]}`.toUpperCase();
  } else if (firstName) {
    return firstName.charAt(0).toUpperCase();
  } else if (props.username) {
    return props.username.charAt(0).toUpperCase();
  }

  return '?';
});

// Storage base URL - use backend server URL in dev, relative path in prod
const storageBaseUrl = computed(() => {
  const apiBaseUrl = process.env.API_BASE_URL || '/api';
  // If API_BASE_URL is a full URL (like http://localhost:8080/api), extract the base
  if (apiBaseUrl.startsWith('http')) {
    return apiBaseUrl.replace(/\/api$/, '');
  }
  // In production, storage is served from the same origin
  return '';
});

const avatarUrl = computed(() => {
  if (!props.avatar) return null;

  // Preset avatars are rendered differently
  if (props.avatar.startsWith('preset:')) {
    return null;
  }

  return `${storageBaseUrl.value}/storage/${props.avatar}`;
});

const presetData = computed(() => {
  if (!props.avatar || !props.avatar.startsWith('preset:')) {
    return null;
  }

  const preset = props.avatar.replace('preset:', '');
  const parts = preset.split(':');

  if (parts[0] === 'initials') {
    return { type: 'initials', color: parts[1] };
  } else if (parts[0] === 'icon') {
    return { type: 'icon', name: parts[1], color: parts[2] };
  } else if (parts[0] === 'solid') {
    return { type: 'solid', color: parts[1] };
  }

  return null;
});
</script>
