import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from './authGuard'
import RoutePlaceholderView from '../views/RoutePlaceholderView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/login',
      name: 'login',
      component: RoutePlaceholderView,
      props: {
        title: 'Logowanie',
        message: 'Formularz logowania pojawi się w kolejnym zadaniu.',
      },
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: RoutePlaceholderView,
      props: {
        title: 'Rejestracja',
        message: 'Formularz rejestracji pojawi się w kolejnym zadaniu.',
      },
      meta: { guestOnly: true },
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
  ],
})

router.beforeEach((to) => {
  return createAuthGuard(useAuthStore())(to)
})
export default router
