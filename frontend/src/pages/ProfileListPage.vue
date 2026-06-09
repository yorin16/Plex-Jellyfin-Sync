<template>
  <div>
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
      <h1 class="page-title" style="margin: 0;">Profiles</h1>
      <button class="btn btn-primary" @click="showCreate = true">+ New Profile</button>
    </div>

    <div v-if="items.length === 0" class="card" style="text-align: center; color: #64748b; padding: 40px;">
      No profiles yet. Create one to get started.
    </div>

    <div class="profiles-grid">
      <div v-for="p in items" :key="p.id" class="card profile-card">
        <div class="profile-name">{{ p.name }}</div>
        <div class="profile-date">Created {{ formatDate(p.createdAt) }}</div>
        <div class="profile-actions">
          <router-link :to="`/profiles/${p.id}/compare`" class="btn btn-primary">Compare</router-link>
          <router-link :to="`/profiles/${p.id}/queue`" class="btn btn-ghost">Queue</router-link>
          <router-link :to="`/profiles/${p.id}/settings`" class="btn btn-ghost">Settings</router-link>
          <button class="btn btn-danger" @click="removeProfile(p.id)">Delete</button>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
      <div class="modal card">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;">New Profile</h2>
        <div class="form-group">
          <label>Profile Name</label>
          <input v-model="newName" placeholder="e.g. RV Sync" @keyup.enter="create" />
        </div>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <button class="btn btn-ghost" @click="showCreate = false">Cancel</button>
          <button class="btn btn-primary" :disabled="!newName.trim()" @click="create">Create</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { profiles } from '@/api'

const items = ref([])
const showCreate = ref(false)
const newName = ref('')

async function load() {
  items.value = await profiles.list()
}

async function create() {
  if (!newName.value.trim()) return
  const p = await profiles.create({ name: newName.value.trim() })
  items.value.push(p)
  newName.value = ''
  showCreate.value = false
}

async function removeProfile(id) {
  if (!confirm('Delete this profile and all its data?')) return
  await profiles.remove(id)
  items.value = items.value.filter(p => p.id !== id)
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString()
}

onMounted(load)
</script>

<style scoped>
.profiles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}
.profile-card { display: flex; flex-direction: column; gap: 8px; }
.profile-name { font-size: 18px; font-weight: 600; }
.profile-date { font-size: 12px; color: #64748b; }
.profile-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.6);
  display: flex; align-items: center; justify-content: center;
  z-index: 100;
}
.modal { width: 400px; max-width: 90vw; }
</style>
