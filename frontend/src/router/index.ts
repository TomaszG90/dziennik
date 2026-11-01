import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from './authGuard'
import RoutePlaceholderView from '../views/RoutePlaceholderView.vue'
import MeView from '../views/MeView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'

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
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
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
    {
      path: '/me',
      name: 'me',
      component: MeView,
    },
  ],
})

router.beforeEach((to) => {
  return createAuthGuard(useAuthStore())(to)
})
export default router
