import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    component: () => import('pages/LoginPage.vue'),
  },
  {
    path: '/register',
    component: () => import('pages/RegisterPage.vue'),
  },

  // Runner Mobile Interface Routes (uses BlankLayout)
  {
    path: '/runner',
    component: () => import('layouts/BlankLayout.vue'),
    children: [
      {
        path: 'login',
        name: 'runner-login',
        component: () => import('pages/runner/RunnerLoginPage.vue'),
      },
      {
        path: 'home',
        name: 'runner-home',
        component: () => import('pages/runner/RunnerHomePage.vue'),
        meta: { requiresRunnerAuth: true },
      },
      {
        path: 'run/:runId',
        name: 'runner-run',
        component: () => import('pages/runner/RunnerRunPage.vue'),
        meta: { requiresRunnerAuth: true },
      },
    ],
  },
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('pages/IndexPage.vue') },
      { path: 'users', component: () => import('pages/UsersPage.vue') },
      { path: 'roles', component: () => import('pages/RolesPage.vue') },
      { path: 'profile', component: () => import('pages/ProfilePage.vue') },
      { path: 'settings', component: () => import('pages/SettingsPage.vue') },
      { path: 'locations', component: () => import('pages/LocationsPage.vue') },
      { path: 'locations/:id', component: () => import('pages/LocationDetailPage.vue') },
      { path: 'parts-requests', component: () => import('pages/PartsRequestsPage.vue') },
      { path: 'runner-dashboard', component: () => import('pages/RunnerDashboardPage.vue') },
      { path: 'runs-dashboard', component: () => import('pages/RunsDashboardPage.vue') },
      { path: 'routes', component: () => import('pages/RoutesPage.vue') },
      { path: 'routes/:id', component: () => import('pages/RouteDetailPage.vue') },
      { path: 'shop/staging', component: () => import('pages/ShopTransferStagingPage.vue') },
      { path: 'closed-dates', component: () => import('pages/ClosedDatesPage.vue') },
      { path: 'vendors', component: () => import('pages/VendorsPage.vue') },
      { path: 'customers', component: () => import('pages/CustomersPage.vue') },
      { path: 'emails', component: () => import('pages/EmailsPage.vue') },
      { path: 'gemini', component: () => import('pages/GeminiPage.vue') },
    ],
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue'),
  },
];

export default routes;
