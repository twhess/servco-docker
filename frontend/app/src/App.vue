<template>
  <router-view />
</template>

<script setup lang="ts">
import { useTheme } from 'src/composables/useTheme';

useTheme();
</script>
