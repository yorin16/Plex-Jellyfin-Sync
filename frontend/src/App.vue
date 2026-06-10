<template>
  <div class="app-layout">
    <nav class="sidebar">
      <div class="sidebar-brand">Media Sync</div>
      <router-link to="/" class="nav-link">Dashboard</router-link>
      <router-link to="/profiles" class="nav-link">Profiles</router-link>
    </nav>
    <main class="content">
      <router-view />
    </main>
  </div>

  <div class="toast-container">
    <div
      v-for="t in toasts"
      :key="t.id"
      class="toast"
      :class="'toast-' + t.type"
    >
      {{ t.message }}
    </div>
  </div>
</template>

<script setup>
import { useToast } from '@/composables/useToast'
const { toasts } = useToast()
</script>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #0f1117;
  color: #e2e8f0;
  font-size: 14px;
}

.app-layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 200px;
  background: #1a1d27;
  border-right: 1px solid #2d3148;
  padding: 24px 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex-shrink: 0;
}

.sidebar-brand {
  font-weight: 700;
  font-size: 16px;
  color: #7c6af7;
  padding: 0 20px 20px;
  border-bottom: 1px solid #2d3148;
  margin-bottom: 8px;
}

.nav-link {
  display: block;
  padding: 10px 20px;
  color: #94a3b8;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}

.nav-link:hover,
.nav-link.router-link-active {
  background: #252840;
  color: #e2e8f0;
}

.content {
  flex: 1;
  padding: 32px;
  overflow-y: auto;
}

/* Utility classes */
.page-title { font-size: 22px; font-weight: 600; margin-bottom: 24px; }
.card { background: #1a1d27; border: 1px solid #2d3148; border-radius: 8px; padding: 20px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.badge-green  { background: #064e3b; color: #6ee7b7; }
.badge-yellow { background: #78350f; color: #fcd34d; }
.badge-red    { background: #7f1d1d; color: #fca5a5; }
.badge-blue   { background: #1e3a5f; color: #93c5fd; }
.badge-gray   { background: #1e293b; color: #94a3b8; }

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: opacity 0.15s;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary  { background: #7c6af7; color: #fff; }
.btn-danger   { background: #dc2626; color: #fff; }
.btn-ghost    { background: transparent; border: 1px solid #2d3148; color: #94a3b8; }
.btn:hover:not(:disabled) { opacity: 0.85; }

input, select {
  background: #0f1117;
  border: 1px solid #2d3148;
  color: #e2e8f0;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 13px;
  width: 100%;
  outline: none;
}
input:focus, select:focus { border-color: #7c6af7; }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; color: #94a3b8; margin-bottom: 6px; }

.toast-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 9999;
}
.toast {
  padding: 12px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0,0,0,0.4);
  animation: toast-in 0.2s ease;
}
.toast-success { background: #064e3b; color: #6ee7b7; border: 1px solid #065f46; }
.toast-error   { background: #7f1d1d; color: #fca5a5; border: 1px solid #991b1b; }
.toast-info    { background: #1e3a5f; color: #93c5fd; border: 1px solid #1e40af; }
@keyframes toast-in {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
