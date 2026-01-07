import { defineStore } from 'pinia';
import { api } from 'boot/axios';
import type { AxiosError } from 'axios';

interface RunnerUser {
  id: number;
  name: string;
  email: string;
  home_location?: {
    id: number;
    name: string;
  } | null;
  alert_on_leave_with_open: boolean;
  alert_popup_enabled: boolean;
}

interface RunnerAuthState {
  user: RunnerUser | null;
  token: string | null;
  isAuthenticated: boolean;
  abilities: string[];
  loading: boolean;
  error: string | null;
}

interface ApiErrorResponse {
  message?: string;
  errors?: Record<string, string[]>;
}

const RUNNER_TOKEN_KEY = 'runner_auth_token';

export const useRunnerAuthStore = defineStore('runnerAuth', {
  state: (): RunnerAuthState => ({
    user: null,
    token: localStorage.getItem(RUNNER_TOKEN_KEY),
    isAuthenticated: !!localStorage.getItem(RUNNER_TOKEN_KEY),
    abilities: [],
    loading: false,
    error: null,
  }),

  getters: {
    userName: (state) => state.user?.name ?? 'Runner',
    homeLocationName: (state) => state.user?.home_location?.name ?? null,
  },

  actions: {
    /**
     * Authenticate using a PIN code.
     */
    async loginWithPin(pin: string) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/runner/auth/pin', { pin });

        this.token = response.data.token;
        this.user = response.data.user;
        this.abilities = response.data.abilities || [];
        this.isAuthenticated = true;

        localStorage.setItem(RUNNER_TOKEN_KEY, response.data.token);
        api.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        const message =
          axiosError.response?.data?.errors?.pin?.[0] ||
          axiosError.response?.data?.message ||
          'Invalid PIN';

        this.error = message;
        return { success: false, error: message };
      } finally {
        this.loading = false;
      }
    },

    /**
     * Logout and revoke the current token.
     */
    async logout() {
      try {
        if (this.token) {
          await api.post('/runner/auth/logout');
        }
      } catch (error) {
        console.error('Runner logout error:', error);
      } finally {
        this.clearAuth();
      }
    },

    /**
     * Clear authentication state without API call.
     */
    clearAuth() {
      this.user = null;
      this.token = null;
      this.isAuthenticated = false;
      this.abilities = [];
      localStorage.removeItem(RUNNER_TOKEN_KEY);
      delete api.defaults.headers.common['Authorization'];
    },

    /**
     * Fetch current user profile.
     */
    async fetchUser() {
      if (!this.token) {
        return { success: false };
      }

      try {
        const response = await api.get('/runner/auth/me');
        this.user = response.data.user;
        this.abilities = response.data.abilities || [];
        return { success: true };
      } catch (error) {
        // Token is invalid, clear auth
        this.clearAuth();
        return { success: false };
      }
    },

    /**
     * Initialize auth state from stored token.
     */
    async initializeAuth() {
      const token = localStorage.getItem(RUNNER_TOKEN_KEY);
      if (token) {
        this.token = token;
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        const result = await this.fetchUser();
        if (result.success) {
          this.isAuthenticated = true;
        } else {
          this.clearAuth();
        }
      }
    },

    /**
     * Check if a specific ability is present.
     */
    hasAbility(ability: string): boolean {
      return this.abilities.includes(ability);
    },
  },
});
