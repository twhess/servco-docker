<template>
  <q-layout view="hHh LpR fFf">
    <q-header elevated class="bg-white text-grey-8" height-hint="64">
      <q-toolbar class="GNL__toolbar" style="height: 64px">
        <q-btn
          flat
          dense
          round
          @click="toggleLeftDrawer"
          aria-label="Menu"
          icon="menu"
          class="q-mr-sm"
        />

        <q-toolbar-title
          v-if="$q.screen.gt.xs"
          shrink
          class="text-weight-bold"
        >
          ServcoApp
        </q-toolbar-title>

        <q-space />

        <q-input
          class="GNL__toolbar-input"
          outlined
          dense
          v-model="search"
          placeholder="Search"
        >
          <template v-slot:prepend>
            <q-icon name="search" />
          </template>
        </q-input>

        <div v-if="$q.screen.gt.xs" class="q-ml-md q-gutter-xs row items-center">
          <q-btn
            flat
            dense
            no-caps
            icon="add"
            label="Create"
            class="text-grey-8"
          >
            <q-menu>
              <q-list style="min-width: 150px">
                <q-item clickable v-close-popup>
                  <q-item-section avatar>
                    <q-icon name="folder" />
                  </q-item-section>
                  <q-item-section>New Folder</q-item-section>
                </q-item>
                <q-item clickable v-close-popup>
                  <q-item-section avatar>
                    <q-icon name="collections" />
                  </q-item-section>
                  <q-item-section>New Album</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>

          <q-btn
            flat
            dense
            no-caps
            icon="upload"
            label="Upload"
            class="text-grey-8"
            @click="handleUpload"
          />
        </div>

        <q-space />

        <div class="q-gutter-sm row items-center no-wrap">
          <q-btn round dense flat icon="apps" />
          <q-btn round dense flat icon="notifications" />
          <q-btn round flat>
            <q-avatar size="26px" color="primary" text-color="white">
              <img v-if="userAvatarUrl" :src="userAvatarUrl">
              <span v-else>{{ userInitials }}</span>
            </q-avatar>
            <q-menu>
              <q-list style="min-width: 150px">
                <q-item clickable v-close-popup @click="router.push('/profile')">
                  <q-item-section avatar>
                    <q-icon name="person" />
                  </q-item-section>
                  <q-item-section>Profile</q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable v-close-popup @click="handleLogout">
                  <q-item-section avatar>
                    <q-icon name="logout" />
                  </q-item-section>
                  <q-item-section>Logout</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      :width="sidebarCollapsed ? 64 : 240"
      :breakpoint="500"
      bordered
      class="bg-grey-2 sidebar-drawer"
    >
      <!-- Collapse toggle button straddling the right border -->
      <q-btn
        round
        dense
        unelevated
        :icon="sidebarCollapsed ? 'chevron_right' : 'chevron_left'"
        size="xs"
        color="grey-4"
        text-color="grey-7"
        class="sidebar-collapse-btn"
        @click="toggleSidebarCollapse"
      >
        <q-tooltip>{{ sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar' }}</q-tooltip>
      </q-btn>

      <q-scroll-area class="fit">
        <q-list padding class="text-grey-8">
          <q-item
            clickable
            v-ripple
            :active="menuItem === 'photos'"
            @click="menuItem = 'photos'"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="photo" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Photos</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Photos
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            :active="menuItem === 'albums'"
            @click="menuItem = 'albums'"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="collections" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Albums</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Albums
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            :active="menuItem === 'shared'"
            @click="menuItem = 'shared'"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="people" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Shared</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Shared
            </q-item-section>
          </q-item>

          <q-separator class="q-my-md" />

          <q-item
            clickable
            v-ripple
            to="/users"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="group" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Users</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Users
            </q-item-section>
          </q-item>

          <q-item
            v-if="authStore.can('roles.view_all')"
            clickable
            v-ripple
            to="/roles"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="admin_panel_settings" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Roles & Permissions</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Roles & Permissions
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/locations"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="location_on" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Locations</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Locations
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/parts-requests"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="local_shipping" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Parts Requests</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Parts Requests
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/vendors"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="store" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Vendors</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Vendors
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/customers"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="business" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Customers</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Customers
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/runs-dashboard"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="dashboard" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Runs Dashboard</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Runs Dashboard
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/routes"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="route" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Routes</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Routes
            </q-item-section>
          </q-item>

          <q-item
            v-if="authStore.user?.role === 'runner_driver'"
            clickable
            v-ripple
            to="/runner-dashboard"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="assignment" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">My Jobs</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              My Jobs
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/profile"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="person" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Profile</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Profile
            </q-item-section>
          </q-item>

          <q-separator class="q-my-md" />

          <q-item
            clickable
            v-ripple
            :active="menuItem === 'trash'"
            @click="menuItem = 'trash'"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="delete" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Trash</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Trash
            </q-item-section>
          </q-item>

          <q-separator class="q-my-md" />

          <q-item
            clickable
            v-ripple
            to="/closed-dates"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="event_busy" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Closed Dates</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Closed Dates
            </q-item-section>
          </q-item>

          <q-item
            clickable
            v-ripple
            to="/settings"
            active-class="bg-blue-1 text-blue-9"
          >
            <q-item-section avatar>
              <q-icon name="settings" />
              <q-tooltip v-if="sidebarCollapsed" anchor="center right" self="center left">Settings</q-tooltip>
            </q-item-section>
            <q-item-section v-if="!sidebarCollapsed">
              Settings
            </q-item-section>
          </q-item>

        </q-list>
      </q-scroll-area>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from 'stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const SIDEBAR_COLLAPSED_KEY = 'sidebar_collapsed';

const leftDrawerOpen = ref(false);
const sidebarCollapsed = ref(false);
const search = ref('');
const menuItem = ref('photos');

// Load collapsed state from localStorage on mount
onMounted(() => {
  const saved = localStorage.getItem(SIDEBAR_COLLAPSED_KEY);
  if (saved !== null) {
    sidebarCollapsed.value = saved === 'true';
  }
});

// Toggle sidebar collapsed state and persist
function toggleSidebarCollapse() {
  sidebarCollapsed.value = !sidebarCollapsed.value;
  localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(sidebarCollapsed.value));
}

const userAvatarUrl = computed(() => {
  if (authStore.user?.avatar) {
    return `http://localhost:8080/storage/${authStore.user.avatar}`;
  }
  return null;
});

const userInitials = computed(() => {
  const user = authStore.user;
  if (!user) return '?';

  const firstName = user.first_name || user.preferred_name || '';
  const lastName = user.last_name || '';

  if (firstName && lastName) {
    return `${firstName[0]}${lastName[0]}`.toUpperCase();
  } else if (firstName) {
    return firstName.charAt(0).toUpperCase();
  } else if (user.username) {
    return user.username.charAt(0).toUpperCase();
  }

  return '?';
});

function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value;
}

async function handleLogout() {
  await authStore.logout();
  await router.push('/login');
}

function handleUpload() {
  // Create a hidden file input
  const input = document.createElement('input');
  input.type = 'file';
  input.multiple = true;
  input.accept = 'image/*,video/*';

  input.onchange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;
    if (files && files.length > 0) {
      // Handle file upload here
      console.log('Files selected:', files);
      // You can add upload logic here
    }
  };

  input.click();
}
</script>

<style lang="sass">
.GNL
  &__toolbar
    height: 64px
  &__toolbar-input
    width: 40%
    max-width: 600px

.sidebar-drawer
  overflow: visible !important

.sidebar-collapse-btn
  position: absolute
  top: 12px
  right: -12px
  z-index: 1
  width: 24px
  height: 24px
  min-width: 24px
  min-height: 24px
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15)
  &:hover
    background-color: #e0e0e0 !important
</style>
