import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from './authGuard'
import MeView from '../views/MeView.vue'
import AuthView from '../views/AuthView.vue'
import TripFormView from '../views/TripFormView.vue'
import TripDetailsView from '../views/TripDetailsView.vue'
import UserMapView from '../views/UserMapView.vue'
import DashboardView from '../views/DashboardView.vue'
import PublicProfilesView from '../views/PublicProfilesView.vue'
import PublicProfileView from '../views/PublicProfileView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/users',
      name: 'public-profiles',
      component: PublicProfilesView,
    },
    {
      path: '/users/:id',
      name: 'public-profile',
      component: PublicProfileView,
    },
    {
      path: '/auth',
      name: 'auth',
      component: AuthView,
      meta: { guestOnly: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: AuthView,
    },
    {
      path: '/login',
      redirect: (to) => ({
        name: 'auth',
        query: { ...to.query, mode: 'login' },
      }),
    },
    {
      path: '/register',
      redirect: (to) => ({
        name: 'auth',
        query: { ...to.query, mode: 'register' },
      }),
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true },
    },
    {
      path: '/map',
      name: 'user-map',
      component: UserMapView,
      meta: { requiresAuth: true },
    },
    {
      path: '/me',
      name: 'me',
      component: MeView,
      meta: { requiresAuth: true },
    },
    {
      path: '/trips/new',
      name: 'trip-create',
      component: TripFormView,
      meta: { requiresAuth: true },
    },
    {
      path: '/trips/:id/edit',
      name: 'trip-edit',
      component: TripFormView,
      meta: { requiresAuth: true },
    },
    {
      path: '/trips/:id',
      name: 'trip-details',
      component: TripDetailsView,
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach((to) => {
  return createAuthGuard(useAuthStore())(to)
})
export default router
