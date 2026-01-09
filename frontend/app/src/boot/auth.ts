import { defineBoot } from '#q-app/wrappers';
import { useAuthStore } from 'stores/auth';

// Initialize auth state on app startup
// This ensures user data and abilities are fetched if a token exists in localStorage
export default defineBoot(({ router }) => {
  const authStore = useAuthStore();

  // Initialize auth (fetch user data if token exists)
  authStore.initializeAuth();

  // Add navigation guard to ensure auth is initialized before protected routes
  router.beforeEach((to, _from, next) => {
    // Skip auth check for public routes
    const publicRoutes = ['/login', '/register', '/runner/login'];
    if (publicRoutes.includes(to.path)) {
      next();
      return;
    }

    // If we have a token but no user data, wait for fetch to complete
    if (authStore.token && !authStore.user) {
      // fetchUser is already called by initializeAuth, but we should wait
      // The 401 interceptor in axios will redirect to login if token is invalid
      next();
      return;
    }

    // If no token, redirect to login
    if (!authStore.isAuthenticated) {
      next('/login');
      return;
    }

    next();
  });
});
