<template>
  <div class="runner-login flex flex-center" style="min-height: 100vh">
    <div class="login-container">
      <div class="text-h4 text-center q-mb-lg text-weight-medium">
        Parts Runner
      </div>

      <div class="pin-display q-mb-lg">
        <div class="pin-dots flex justify-center q-gutter-md">
          <div
            v-for="i in maxLength"
            :key="i"
            class="pin-dot"
            :class="{ filled: pin.length >= i }"
          />
        </div>
      </div>

      <div v-if="error" class="text-negative text-center q-mb-md">
        {{ error }}
      </div>

      <div class="keypad">
        <div class="keypad-row">
          <q-btn
            v-for="num in [1, 2, 3]"
            :key="num"
            :label="String(num)"
            class="keypad-btn"
            flat
            rounded
            @click="addDigit(num)"
            :disable="loading"
          />
        </div>
        <div class="keypad-row">
          <q-btn
            v-for="num in [4, 5, 6]"
            :key="num"
            :label="String(num)"
            class="keypad-btn"
            flat
            rounded
            @click="addDigit(num)"
            :disable="loading"
          />
        </div>
        <div class="keypad-row">
          <q-btn
            v-for="num in [7, 8, 9]"
            :key="num"
            :label="String(num)"
            class="keypad-btn"
            flat
            rounded
            @click="addDigit(num)"
            :disable="loading"
          />
        </div>
        <div class="keypad-row">
          <q-btn
            label="Clear"
            class="keypad-btn keypad-btn-action"
            flat
            rounded
            @click="clearPin"
            :disable="loading"
          />
          <q-btn
            label="0"
            class="keypad-btn"
            flat
            rounded
            @click="addDigit(0)"
            :disable="loading"
          />
          <q-btn
            icon="backspace"
            class="keypad-btn keypad-btn-action"
            flat
            rounded
            @click="backspace"
            :disable="loading || pin.length === 0"
          />
        </div>
      </div>

      <div v-if="loading" class="text-center q-mt-lg">
        <q-spinner-dots size="40px" color="primary" />
      </div>

      <div class="text-center q-mt-xl">
        <q-btn
          flat
          label="Admin Login"
          color="grey-6"
          @click="goToAdminLogin"
          size="sm"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useRunnerAuthStore } from 'stores/runnerAuth';
import { useQuasar } from 'quasar';

const router = useRouter();
const authStore = useRunnerAuthStore();
const $q = useQuasar();

const pin = ref('');
const maxLength = 8;
const minLength = 4;
const loading = ref(false);
const error = ref('');

const addDigit = (digit: number) => {
  if (pin.value.length < maxLength) {
    pin.value += String(digit);
    error.value = '';
  }
};

const backspace = () => {
  if (pin.value.length > 0) {
    pin.value = pin.value.slice(0, -1);
  }
};

const clearPin = () => {
  pin.value = '';
  error.value = '';
};

const submitPin = async () => {
  if (pin.value.length < minLength) {
    return;
  }

  loading.value = true;
  error.value = '';

  const result = await authStore.loginWithPin(pin.value);

  loading.value = false;

  if (result.success) {
    $q.notify({
      type: 'positive',
      message: `Welcome, ${authStore.userName}!`,
      position: 'top',
    });
    await router.push('/runner/home');
  } else {
    error.value = result.error || 'Invalid PIN';
    pin.value = '';
  }
};

const goToAdminLogin = () => {
  router.push('/login');
};

// Auto-submit when PIN reaches minimum length
watch(pin, (newPin) => {
  if (newPin.length >= minLength) {
    void submitPin();
  }
});
</script>

<style scoped>
.runner-login {
  background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%);
}

.login-container {
  width: 100%;
  max-width: 320px;
  padding: 24px;
}

.text-h4 {
  color: white;
}

.pin-display {
  padding: 20px;
}

.pin-dots {
  gap: 16px;
}

.pin-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.3);
  transition: background-color 0.2s ease;
}

.pin-dot.filled {
  background-color: white;
}

.keypad {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.keypad-row {
  display: flex;
  justify-content: center;
  gap: 12px;
}

.keypad-btn {
  width: 72px;
  height: 72px;
  font-size: 24px;
  font-weight: 500;
  background-color: rgba(255, 255, 255, 0.15);
  color: white;
}

.keypad-btn:hover {
  background-color: rgba(255, 255, 255, 0.25);
}

.keypad-btn-action {
  font-size: 14px;
}

.text-negative {
  color: #ffcdd2;
}
</style>
