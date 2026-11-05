import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from './authGuard'
import RoutePlaceholderView from '../views/RoutePlaceholderView.vue'
import MeView from '../views/MeView.vue'
import AuthView from '../views/AuthView.vue'
import TripFormView from '../views/TripFormView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
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
      component: RoutePlaceholderView,
      props: {
        title: 'Mój dziennik',
        message: 'Tutaj pojawią się Twoje podróże.',
      },
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
      component: RoutePlaceholderView,
      props: (route) => ({
        title: 'Szczegóły podróży',
        message: `Szczegóły podróży #${route.params.id} (PR-25).`,
      }),
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach((to) => {
  return createAuthGuard(useAuthStore())(to)
})
export default router
