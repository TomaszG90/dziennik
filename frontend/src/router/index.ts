import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from './authGuard'
import RoutePlaceholderView from '../views/RoutePlaceholderView.vue'
import MeView from '../views/MeView.vue'
import AuthView from '../views/AuthView.vue'

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
  ],
})

router.beforeEach((to) => {
  return createAuthGuard(useAuthStore())(to)
})
export default router
