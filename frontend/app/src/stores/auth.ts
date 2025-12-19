import { defineStore } from 'pinia';
import { api } from 'boot/axios';
import type { AxiosError } from 'axios';

interface User {
  id: number;
  username: string;
  email: string;
  role?: string;
  avatar?: string;
  employee_id?: string;
  first_name?: string;
  last_name?: string;
  preferred_name?: string;
  phone_number?: string;
  pin_code?: string;
  home_shop?: string;
  personal_email?: string;
  slack_id?: string;
  dext_email?: string;
  address?: string;
  address_line_1?: string;
  address_line_2?: string;
  city?: string;
  state?: string;
  zip?: string;
  paytype?: string;
}

interface RegisterData {
  username: string;
  email: string;
  password: string;
  password_confirmation: string;
  employee_id?: string;
  first_name?: string;
  last_name?: string;
  preferred_name?: string;
  phone_number?: string;
  pin_code?: string;
  home_shop?: string;
  personal_email?: string;
  slack_id?: string;
  dext_email?: string;
  address?: string;
  address_line_1?: string;
  address_line_2?: string;
  city?: string;
  state?: string;
  zip?: string;
  paytype?: string;
}

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  abilities: Record<string, boolean>;
}

interface ApiErrorResponse {
  message?: string;
  errors?: Record<string, string[]>;
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: localStorage.getItem('auth_token'),
    isAuthenticated: !!localStorage.getItem('auth_token'),
    abilities: {},
  }),

  actions: {
    async login(login: string, password: string) {
      try {
        const response = await api.post('/login', { login, password });
        this.token = response.data.token;
        this.user = response.data.user;
        this.abilities = response.data.abilities || {};
        this.isAuthenticated = true;

        localStorage.setItem('auth_token', response.data.token);
        api.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        return {
          success: false,
          error: axiosError.response?.data?.message || 'Login failed'
        };
      }
    },

    async register(data: RegisterData) {
      try {
        const response = await api.post('/register', data);
        this.token = response.data.token;
        this.user = response.data.user;
        this.isAuthenticated = true;

        localStorage.setItem('auth_token', response.data.token);
        api.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;

        return { success: true };
      } catch (error) {
        const axiosError = error as AxiosError<ApiErrorResponse>;
        console.error('Registration error:', axiosError.response?.data);

        // Handle Laravel validation errors
        const validationErrors = axiosError.response?.data?.errors;
        let errorMessage = 'Registration failed';

        if (validationErrors) {
          errorMessage = Object.values(validationErrors).flat().join(', ');
        } else if (axiosError.response?.data?.message) {
          errorMessage = axiosError.response.data.message;
        }

        return {
          success: false,
          error: errorMessage
        };
      }
    },

    async logout() {
      try {
        if (this.token) {
          await api.post('/logout');
        }
      } catch (error) {
        console.error('Logout error:', error);
      } finally {
        this.user = null;
        this.token = null;
        this.isAuthenticated = false;
        localStorage.removeItem('auth_token');
        delete api.defaults.headers.common['Authorization'];
      }
    },

    async fetchUser() {
      try {
        const response = await api.get('/user');
        this.user = response.data.user;
        this.abilities = response.data.abilities || {};
        return { success: true };
      } catch {
        await this.logout();
        return { success: false };
      }
    },

    can(ability: string): boolean {
      return this.abilities[ability] === true;
    },

    initializeAuth() {
      const token = localStorage.getItem('auth_token');
      if (token) {
        this.token = token;
        this.isAuthenticated = true;
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        void this.fetchUser();
      }
    },
  },
});
