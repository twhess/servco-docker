<template>
  <div class="flex flex-center bg-grey-2" style="min-height: 100vh;">
    <q-card class="login-card" style="width: 400px; max-width: 90vw">
      <q-card-section>
        <div class="text-h5 text-center q-mb-md">Login</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit.prevent="handleLogin">
          <q-input
            v-model="login"
            type="text"
            label="Email or Username"
            outlined
            :rules="[
              (val) => (val && val.length > 0) || 'Email or username is required',
            ]"
            class="q-mb-md"
          />

          <q-input
            v-model="password"
            :type="isPwd ? 'password' : 'text'"
            label="Password"
            outlined
            :rules="[(val) => (val && val.length > 0) || 'Password is required']"
            class="q-mb-md"
          >
            <template v-slot:append>
              <q-icon
                :name="isPwd ? 'visibility_off' : 'visibility'"
                class="cursor-pointer"
                @click="isPwd = !isPwd"
              />
            </template>
          </q-input>

          <div v-if="errorMessage" class="text-negative q-mb-md">
            {{ errorMessage }}
          </div>

          <q-btn
            type="submit"
            label="Login"
            color="primary"
            class="full-width q-mb-md"
            :loading="loading"
          />

          <div class="text-center">
            <q-btn
              flat
              label="Don't have an account? Register"
              color="primary"
              @click="$router.push('/register')"
            />
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from 'stores/auth';
import { useQuasar } from 'quasar';

const router = useRouter();
const authStore = useAuthStore();
const $q = useQuasar();

const login = ref('');
const password = ref('');
const isPwd = ref(true);
const loading = ref(false);
const errorMessage = ref('');

const handleLogin = async () => {
  loading.value = true;
  errorMessage.value = '';

  const result = await authStore.login(login.value, password.value);

  loading.value = false;

  if (result.success) {
    $q.notify({
      type: 'positive',
      message: 'Login successful!',
      position: 'top',
    });
    await router.push('/');
  } else {
    errorMessage.value = result.error || 'Login failed. Please try again.';
  }
};
</script>

<style scoped>
.login-card {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
</style>
