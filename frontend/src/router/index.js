import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      component: () => import('@/pages/DashboardPage.vue'),
    },
    {
      path: '/profiles',
      component: () => import('@/pages/ProfileListPage.vue'),
    },
    {
      path: '/profiles/:id/settings',
      component: () => import('@/pages/ProfileSettingsPage.vue'),
    },
    {
      path: '/profiles/:id/compare',
      component: () => import('@/pages/ComparisonPage.vue'),
    },
    {
      path: '/profiles/:id/queue',
      component: () => import('@/pages/QueuePage.vue'),
    },
    {
      path: '/transcode-profiles',
      component: () => import('@/pages/TranscodeProfilesPage.vue'),
    },
  ],
})

export default router
