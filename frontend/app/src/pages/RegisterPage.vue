<template>
  <div class="flex flex-center bg-grey-2" style="min-height: 100vh;">
    <q-card class="register-card" style="width: 600px; max-width: 90vw">
      <q-card-section>
        <div class="text-h5 text-center q-mb-md">Register</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit.prevent="handleRegister">
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-6">
              <q-input
                v-model="username"
                type="text"
                label="Username"
                outlined
                :rules="[(val) => (val && val.length > 0) || 'Username is required']"
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="employeeId"
                type="text"
                label="Employee ID"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="firstName"
                type="text"
                label="First Name"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="lastName"
                type="text"
                label="Last Name"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="preferredName"
                type="text"
                label="Preferred Name"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="phoneNumber"
                type="text"
                label="Phone Number"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="email"
                type="email"
                label="Email"
                outlined
                :rules="[
                  (val) => (val && val.length > 0) || 'Email is required',
                  (val) => /.+@.+\..+/.test(val) || 'Invalid email format',
                ]"
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="personalEmail"
                type="email"
                label="Personal Email"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="dextEmail"
                type="email"
                label="Dext Email"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="pinCode"
                type="text"
                label="PIN Code"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-select
                v-model="homeLocationId"
                label="Home Shop Location"
                outlined
                :options="locations"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                clearable
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="slackId"
                type="text"
                label="Slack ID"
                outlined
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="paytype"
                type="text"
                label="Pay Type"
                outlined
              />
            </div>

            <div class="col-12">
              <q-input
                v-model="address"
                type="textarea"
                label="Address"
                outlined
                rows="2"
              />
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="password"
                :type="isPwd ? 'password' : 'text'"
                label="Password"
                outlined
                :rules="[
                  (val) => (val && val.length > 0) || 'Password is required',
                  (val) => val.length >= 8 || 'Password must be at least 8 characters',
                ]"
              >
                <template v-slot:append>
                  <q-icon
                    :name="isPwd ? 'visibility_off' : 'visibility'"
                    class="cursor-pointer"
                    @click="isPwd = !isPwd"
                  />
                </template>
              </q-input>
            </div>

            <div class="col-12 col-sm-6">
              <q-input
                v-model="passwordConfirmation"
                :type="isPwdConfirm ? 'password' : 'text'"
                label="Confirm Password"
                outlined
                :rules="[
                  (val) => (val && val.length > 0) || 'Please confirm your password',
                  (val) => val === password || 'Passwords do not match',
                ]"
              >
                <template v-slot:append>
                  <q-icon
                    :name="isPwdConfirm ? 'visibility_off' : 'visibility'"
                    class="cursor-pointer"
                    @click="isPwdConfirm = !isPwdConfirm"
                  />
                </template>
              </q-input>
            </div>
          </div>

          <div v-if="errorMessage" class="text-negative q-mt-md q-mb-md">
            {{ errorMessage }}
          </div>

          <q-btn
            type="submit"
            label="Register"
            color="primary"
            class="full-width q-mt-md q-mb-md"
            :loading="loading"
          />

          <div class="text-center">
            <q-btn
              flat
              label="Already have an account? Login"
              color="primary"
              @click="$router.push('/login')"
            />
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from 'stores/auth';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';

const router = useRouter();
const authStore = useAuthStore();
const $q = useQuasar();

const username = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const employeeId = ref('');
const firstName = ref('');
const lastName = ref('');
const preferredName = ref('');
const phoneNumber = ref('');
const pinCode = ref('');
const homeShop = ref('');
const homeLocationId = ref<number | null>(null);
const personalEmail = ref('');
const slackId = ref('');
const dextEmail = ref('');
const address = ref('');
const paytype = ref('');
const locations = ref<any[]>([]);
const isPwd = ref(true);
const isPwdConfirm = ref(true);
const loading = ref(false);
const errorMessage = ref('');

async function loadLocations() {
  try {
    const response = await api.get('/locations', { params: { per_page: 100 } });
    locations.value = response.data.data;
  } catch (error) {
    console.error('Failed to load locations', error);
  }
}

const handleRegister = async () => {
  loading.value = true;
  errorMessage.value = '';

  const result = await authStore.register({
    username: username.value,
    email: email.value,
    password: password.value,
    password_confirmation: passwordConfirmation.value,
    employee_id: employeeId.value,
    first_name: firstName.value,
    last_name: lastName.value,
    preferred_name: preferredName.value,
    phone_number: phoneNumber.value,
    pin_code: pinCode.value,
    home_shop: homeShop.value,
    home_location_id: homeLocationId.value,
    personal_email: personalEmail.value,
    slack_id: slackId.value,
    dext_email: dextEmail.value,
    address: address.value,
    paytype: paytype.value,
  });

  loading.value = false;

  if (result.success) {
    $q.notify({
      type: 'positive',
      message: 'Registration successful!',
      position: 'top',
    });
    await router.push('/');
  } else {
    errorMessage.value = result.error || 'Registration failed. Please try again.';
  }
};

onMounted(() => {
  void loadLocations();
});
</script>

<style scoped>
.register-card {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
</style>
