import { defineBoot } from '#q-app/wrappers';
import axios, { type AxiosInstance, type AxiosError } from 'axios';

declare module 'vue' {
  interface ComponentCustomProperties {
    $axios: AxiosInstance;
    $api: AxiosInstance;
  }
}

// Be careful when using SSR for cross-request state pollution
// due to creating a Singleton instance here;
// If any client changes this (global) instance, it might be a
// good idea to move this instance creation inside of the
// "export default () => {}" function below (which runs individually
// for each client)
// Use environment variable for API base URL, fallback to relative /api for production
const apiBaseUrl = process.env.API_BASE_URL || '/api';
const api = axios.create({ baseURL: apiBaseUrl });

// Add request interceptor to include auth token
api.interceptors.request.use(
  (config) => {
    // Check for runner token first (for /runner/* routes), then regular auth token
    const runnerToken = localStorage.getItem('runner_auth_token');
    const authToken = localStorage.getItem('auth_token');

    // Use runner token for runner API endpoints
    if (config.url?.startsWith('/runner') && runnerToken) {
      config.headers.Authorization = `Bearer ${runnerToken}`;
    } else if (authToken) {
      config.headers.Authorization = `Bearer ${authToken}`;
    }

    return config;
  },
  (error: Error) => {
    return Promise.reject(error);
  }
);

// Add response interceptor to handle 401 errors
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      const url = error.config?.url || '';

      // Check if this is a runner API call
      if (url.startsWith('/runner')) {
        localStorage.removeItem('runner_auth_token');
        window.location.href = '/runner/login';
      } else {
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default defineBoot(({ app }) => {
  // for use inside Vue files (Options API) through this.$axios and this.$api

  app.config.globalProperties.$axios = axios;
  // ^ ^ ^ this will allow you to use this.$axios (for Vue Options API form)
  //       so you won't necessarily have to import axios in each vue file

  app.config.globalProperties.$api = api;
  // ^ ^ ^ this will allow you to use this.$api (for Vue Options API form)
  //       so you can easily perform requests against your app's API
});

export { api };
