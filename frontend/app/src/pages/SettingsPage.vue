<template>
  <q-page padding>
    <div class="row q-col-gutter-md">
      <div class="col-12">
        <div class="text-h4 q-mb-md">Settings</div>
      </div>

      <!-- Color Scheme Section -->
      <div class="col-12 col-md-8">
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">Color Schemes</div>

            <!-- Preset Color Schemes -->
            <div class="q-mb-lg">
              <div class="text-subtitle2 q-mb-sm">Preset Themes</div>
              <div class="row q-col-gutter-sm">
                <div
                  v-for="preset in presetSchemes"
                  :key="preset.name"
                  class="col-6 col-sm-4 col-md-3"
                >
                  <q-card
                    flat
                    bordered
                    class="cursor-pointer scheme-card"
                    :class="{ 'selected-scheme': currentScheme === preset.name }"
                    @click="applyScheme(preset)"
                  >
                    <q-card-section class="q-pa-sm">
                      <div class="scheme-preview row q-mb-xs">
                        <div
                          class="scheme-color"
                          :style="{ backgroundColor: preset.colors.primary }"
                        />
                        <div
                          class="scheme-color"
                          :style="{ backgroundColor: preset.colors.secondary }"
                        />
                        <div
                          class="scheme-color"
                          :style="{ backgroundColor: preset.colors.accent }"
                        />
                      </div>
                      <div class="text-caption text-center">{{ preset.name }}</div>
                    </q-card-section>
                  </q-card>
                </div>
              </div>
            </div>

            <q-separator class="q-my-lg" />

            <!-- Custom Color Schemes -->
            <div>
              <div class="text-subtitle2 q-mb-sm">Custom Themes</div>

              <div class="row q-col-gutter-sm q-mb-md">
                <div
                  v-for="custom in customSchemes"
                  :key="custom.name"
                  class="col-6 col-sm-4 col-md-3"
                >
                  <q-card
                    flat
                    bordered
                    class="cursor-pointer scheme-card"
                    :class="{ 'selected-scheme': currentScheme === custom.name }"
                    @click="applyScheme(custom)"
                  >
                    <q-card-section class="q-pa-sm">
                      <div class="scheme-preview row q-mb-xs">
                        <div
                          class="scheme-color"
                          :style="{ backgroundColor: custom.colors.primary }"
                        />
                        <div
                          class="scheme-color"
                          :style="{ backgroundColor: custom.colors.secondary }"
                        />
                        <div
                          class="scheme-color"
                          :style="{ backgroundColor: custom.colors.accent }"
                        />
                      </div>
                      <div class="row items-center justify-between">
                        <div class="text-caption">{{ custom.name }}</div>
                        <q-btn
                          flat
                          dense
                          round
                          size="xs"
                          icon="delete"
                          @click.stop="deleteCustomScheme(custom.name)"
                        />
                      </div>
                    </q-card-section>
                  </q-card>
                </div>
              </div>

              <q-btn
                outline
                color="primary"
                icon="add"
                label="Create Custom Theme"
                @click="showCreateDialog = true"
              />
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Current Theme Preview -->
      <div class="col-12 col-md-4">
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">Current Theme Preview</div>

            <div class="q-mb-md">
              <q-btn color="primary" label="Primary" class="full-width q-mb-sm" />
              <q-btn color="secondary" label="Secondary" class="full-width q-mb-sm" />
              <q-btn color="accent" label="Accent" class="full-width q-mb-sm" />
              <q-btn color="positive" label="Positive" class="full-width q-mb-sm" />
              <q-btn color="negative" label="Negative" class="full-width q-mb-sm" />
              <q-btn color="warning" label="Warning" class="full-width" />
            </div>

            <q-separator class="q-my-md" />

            <div>
              <q-input
                outlined
                v-model="sampleText"
                label="Sample Input"
                class="q-mb-sm"
              />
              <q-checkbox v-model="sampleCheck" label="Sample Checkbox" />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Create Custom Theme Dialog -->
    <q-dialog v-model="showCreateDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Create Custom Theme</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-input
            outlined
            v-model="newScheme.name"
            label="Theme Name"
            class="q-mb-md"
            :rules="[val => !!val || 'Name is required']"
          />

          <div class="q-mb-sm">
            <div class="text-subtitle2 q-mb-xs">Primary Color</div>
            <div class="row items-center q-gutter-sm">
              <input
                type="color"
                v-model="newScheme.colors.primary"
                class="color-picker"
              />
              <q-input
                outlined
                dense
                v-model="newScheme.colors.primary"
                style="flex: 1"
              />
            </div>
          </div>

          <div class="q-mb-sm">
            <div class="text-subtitle2 q-mb-xs">Secondary Color</div>
            <div class="row items-center q-gutter-sm">
              <input
                type="color"
                v-model="newScheme.colors.secondary"
                class="color-picker"
              />
              <q-input
                outlined
                dense
                v-model="newScheme.colors.secondary"
                style="flex: 1"
              />
            </div>
          </div>

          <div class="q-mb-sm">
            <div class="text-subtitle2 q-mb-xs">Accent Color</div>
            <div class="row items-center q-gutter-sm">
              <input
                type="color"
                v-model="newScheme.colors.accent"
                class="color-picker"
              />
              <q-input
                outlined
                dense
                v-model="newScheme.colors.accent"
                style="flex: 1"
              />
            </div>
          </div>

          <div class="q-mb-sm">
            <div class="text-subtitle2 q-mb-xs">Positive Color</div>
            <div class="row items-center q-gutter-sm">
              <input
                type="color"
                v-model="newScheme.colors.positive"
                class="color-picker"
              />
              <q-input
                outlined
                dense
                v-model="newScheme.colors.positive"
                style="flex: 1"
              />
            </div>
          </div>

          <div class="q-mb-sm">
            <div class="text-subtitle2 q-mb-xs">Negative Color</div>
            <div class="row items-center q-gutter-sm">
              <input
                type="color"
                v-model="newScheme.colors.negative"
                class="color-picker"
              />
              <q-input
                outlined
                dense
                v-model="newScheme.colors.negative"
                style="flex: 1"
              />
            </div>
          </div>

          <div>
            <div class="text-subtitle2 q-mb-xs">Warning Color</div>
            <div class="row items-center q-gutter-sm">
              <input
                type="color"
                v-model="newScheme.colors.warning"
                class="color-picker"
              />
              <q-input
                outlined
                dense
                v-model="newScheme.colors.warning"
                style="flex: 1"
              />
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" color="grey" v-close-popup />
          <q-btn
            flat
            label="Create"
            color="primary"
            @click="createCustomScheme"
            :disable="!newScheme.name"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useQuasar, setCssVar } from 'quasar';

const $q = useQuasar();

interface ColorScheme {
  name: string;
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    positive: string;
    negative: string;
    warning: string;
  };
}

const currentScheme = ref('Default');
const sampleText = ref('Sample text');
const sampleCheck = ref(true);
const showCreateDialog = ref(false);

const presetSchemes = ref<ColorScheme[]>([
  {
    name: 'Default',
    colors: {
      primary: '#1976D2',
      secondary: '#26A69A',
      accent: '#9C27B0',
      positive: '#21BA45',
      negative: '#C10015',
      warning: '#F2C037',
    },
  },
  {
    name: 'Ocean',
    colors: {
      primary: '#0077BE',
      secondary: '#00B4D8',
      accent: '#90E0EF',
      positive: '#06D6A0',
      negative: '#EF476F',
      warning: '#FFD166',
    },
  },
  {
    name: 'Forest',
    colors: {
      primary: '#2D6A4F',
      secondary: '#52B788',
      accent: '#95D5B2',
      positive: '#40916C',
      negative: '#E63946',
      warning: '#F4A261',
    },
  },
  {
    name: 'Sunset',
    colors: {
      primary: '#E63946',
      secondary: '#F77F00',
      accent: '#FCBF49',
      positive: '#06D6A0',
      negative: '#D62828',
      warning: '#F4A261',
    },
  },
  {
    name: 'Purple',
    colors: {
      primary: '#7209B7',
      secondary: '#B5179E',
      accent: '#F72585',
      positive: '#06D6A0',
      negative: '#DC2F02',
      warning: '#FFB703',
    },
  },
  {
    name: 'Dark Mode',
    colors: {
      primary: '#BB86FC',
      secondary: '#03DAC6',
      accent: '#CF6679',
      positive: '#4CAF50',
      negative: '#F44336',
      warning: '#FF9800',
    },
  },
]);

const customSchemes = ref<ColorScheme[]>([]);

const newScheme = ref<ColorScheme>({
  name: '',
  colors: {
    primary: '#1976D2',
    secondary: '#26A69A',
    accent: '#9C27B0',
    positive: '#21BA45',
    negative: '#C10015',
    warning: '#F2C037',
  },
});

function applyScheme(scheme: ColorScheme) {
  currentScheme.value = scheme.name;

  // Apply colors using Quasar's setCssVar utility
  setCssVar('primary', scheme.colors.primary);
  setCssVar('secondary', scheme.colors.secondary);
  setCssVar('accent', scheme.colors.accent);
  setCssVar('positive', scheme.colors.positive);
  setCssVar('negative', scheme.colors.negative);
  setCssVar('warning', scheme.colors.warning);

  // Save to localStorage
  localStorage.setItem('color_scheme', JSON.stringify({
    name: scheme.name,
    colors: scheme.colors,
  }));

  $q.notify({
    type: 'positive',
    message: `Applied "${scheme.name}" theme`,
    position: 'top',
  });
}

function createCustomScheme() {
  if (!newScheme.value.name) return;

  // Check if name already exists
  const exists = [...presetSchemes.value, ...customSchemes.value].some(
    s => s.name.toLowerCase() === newScheme.value.name.toLowerCase()
  );

  if (exists) {
    $q.notify({
      type: 'negative',
      message: 'A theme with this name already exists',
      position: 'top',
    });
    return;
  }

  const scheme = { ...newScheme.value };
  customSchemes.value.push(scheme);

  // Save custom schemes to localStorage
  localStorage.setItem('custom_color_schemes', JSON.stringify(customSchemes.value));

  // Apply the new scheme
  applyScheme(scheme);

  // Reset form
  newScheme.value = {
    name: '',
    colors: {
      primary: '#1976D2',
      secondary: '#26A69A',
      accent: '#9C27B0',
      positive: '#21BA45',
      negative: '#C10015',
      warning: '#F2C037',
    },
  };

  showCreateDialog.value = false;

  $q.notify({
    type: 'positive',
    message: 'Custom theme created successfully',
    position: 'top',
  });
}

function deleteCustomScheme(name: string) {
  $q.dialog({
    title: 'Confirm',
    message: `Are you sure you want to delete the "${name}" theme?`,
    cancel: true,
    persistent: true,
  }).onOk(() => {
    customSchemes.value = customSchemes.value.filter(s => s.name !== name);
    localStorage.setItem('custom_color_schemes', JSON.stringify(customSchemes.value));

    if (currentScheme.value === name && presetSchemes.value[0]) {
      applyScheme(presetSchemes.value[0]);
    }

    $q.notify({
      type: 'info',
      message: 'Theme deleted',
      position: 'top',
    });
  });
}

function loadSavedScheme() {
  // Load custom schemes
  const savedCustom = localStorage.getItem('custom_color_schemes');
  if (savedCustom) {
    try {
      customSchemes.value = JSON.parse(savedCustom);
    } catch (e) {
      console.error('Failed to load custom schemes:', e);
    }
  }

  // Load and apply saved color scheme
  const savedScheme = localStorage.getItem('color_scheme');
  if (savedScheme) {
    try {
      const scheme = JSON.parse(savedScheme);
      currentScheme.value = scheme.name;

      setCssVar('primary', scheme.colors.primary);
      setCssVar('secondary', scheme.colors.secondary);
      setCssVar('accent', scheme.colors.accent);
      setCssVar('positive', scheme.colors.positive);
      setCssVar('negative', scheme.colors.negative);
      setCssVar('warning', scheme.colors.warning);
    } catch (e) {
      console.error('Failed to load saved scheme:', e);
    }
  }
}

onMounted(() => {
  loadSavedScheme();
});
</script>

<style scoped>
.scheme-card {
  transition: all 0.2s;
}

.scheme-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.selected-scheme {
  border: 2px solid var(--q-primary);
  background-color: rgba(25, 118, 210, 0.05);
}

.scheme-preview {
  height: 40px;
  border-radius: 4px;
  overflow: hidden;
}

.scheme-color {
  flex: 1;
  height: 100%;
}

.color-picker {
  width: 50px;
  height: 40px;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
}

.color-picker::-webkit-color-swatch-wrapper {
  padding: 0;
}

.color-picker::-webkit-color-swatch {
  border: none;
  border-radius: 4px;
}
</style>
