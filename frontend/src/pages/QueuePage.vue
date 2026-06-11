<template>
  <div>
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;">
      <router-link to="/profiles" class="btn btn-ghost" style="padding: 6px 12px;">← Profiles</router-link>
      <h1 class="page-title" style="margin: 0;">Transfer Queue</h1>
      <div style="margin-left: auto; display: flex; gap: 8px;">
        <router-link :to="`/profiles/${profileId}/compare`" class="btn btn-ghost">Compare</router-link>
        <button class="btn btn-ghost" @click="load(); loadDiskUsage()">↺ Refresh</button>
        <button class="btn btn-danger" style="font-size: 13px;" @click="clearFinished">Clear finished</button>
      </div>
    </div>

    <!-- Disk usage -->
    <div v-if="diskUsage" class="disk-usage-bar">
      <div class="disk-usage-info">
        <span>Destination</span>
        <span>{{ formatBytes(diskUsage.used) }} used of {{ formatBytes(diskUsage.total) }}</span>
        <span style="color: #64748b;">{{ formatBytes(diskUsage.free) }} free</span>
      </div>
      <div class="disk-bar">
        <div class="disk-bar-fill" :style="{ width: Math.round(diskUsage.used / diskUsage.total * 100) + '%', background: diskUsage.used / diskUsage.total > 0.9 ? '#f87171' : diskUsage.used / diskUsage.total > 0.75 ? '#fbbf24' : '#7c6af7' }"></div>
      </div>
    </div>
    <div v-else-if="diskUsageError" style="font-size: 12px; color: #f87171; margin-bottom: 16px;">
      Disk usage unavailable: {{ diskUsageError }}
    </div>

    <!-- Filter tabs -->
    <div class="tabs" style="margin-bottom: 20px;">
      <button
        v-for="s in statusFilters"
        :key="s.value"
        class="tab-btn"
        :class="{ active: activeStatus === s.value }"
        @click="activeStatus = s.value"
      >
        {{ s.label }}
        <span class="tab-count">{{ countByStatus[s.value] ?? 0 }}</span>
      </button>
    </div>

    <div v-if="filteredJobs.length === 0" class="card" style="text-align: center; color: #64748b; padding: 40px;">
      No {{ activeStatus }} transfers.
    </div>

    <div v-else class="card" style="padding: 0;">
      <table>
        <thead>
          <tr>
            <th>Movie</th>
            <th>Progress</th>
            <th>Speed</th>
            <th>Status</th>
            <th>Queued</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="job in filteredJobs" :key="job.id">
            <td>
              <div style="font-weight: 500;">{{ job.sourceMedia.title }}</div>
              <div style="font-size: 12px; color: #64748b;">
                {{ job.sourceMedia.year }} · {{ job.sourceMedia.codec }} · {{ job.sourceMedia.resolution }}
                <span v-if="job.sourceMedia.hdrType"> · {{ job.sourceMedia.hdrType }}</span>
              </div>
            </td>
            <td style="min-width: 200px;">
              <template v-if="job.status === 'running' || job.status === 'completed'">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div class="progress-bar" style="flex: 1;">
                    <div class="progress-bar-fill" :style="{ width: job.progressPercent + '%' }"></div>
                  </div>
                  <span style="font-size: 12px; color: #94a3b8; min-width: 42px;">{{ job.progressPercent }}%</span>
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                  {{ formatBytes(job.bytesCopied) }} / {{ formatBytes(job.totalBytes) }}
                </div>
              </template>
              <span v-else style="color: #64748b; font-size: 12px;">—</span>
            </td>
            <td style="white-space: nowrap; color: #94a3b8;">
              {{ job.transferSpeedBps ? formatSpeed(job.transferSpeedBps) : '—' }}
            </td>
            <td>
              <span class="badge" :class="statusBadge(job.status)">{{ job.status }}</span>
            </td>
            <td style="font-size: 12px; color: #64748b; white-space: nowrap;">
              {{ formatDate(job.queuedAt) }}
            </td>
            <td>
              <div style="display: flex; gap: 6px;">
                <button
                  v-if="job.status === 'queued'"
                  class="btn btn-danger"
                  style="font-size: 12px; padding: 4px 10px;"
                  @click="cancel(job.id)"
                >
                  Cancel
                </button>
                <span v-if="job.status === 'failed'" class="error-msg" :title="job.errorMessage">
                  {{ (job.errorMessage ?? '').substring(0, 40) }}{{ (job.errorMessage ?? '').length > 40 ? '…' : '' }}
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { jobs, transferConfig } from '@/api'

const route = useRoute()
const profileId = Number(route.params.id)

const allJobs = ref([])
const activeStatus = ref('all')
const diskUsage = ref(null)
const diskUsageError = ref(null)
let interval = null

const statusFilters = [
  { value: 'all',       label: 'All' },
  { value: 'queued',    label: 'Queued' },
  { value: 'running',   label: 'Running' },
  { value: 'completed', label: 'Completed' },
  { value: 'failed',    label: 'Failed' },
]

const countByStatus = computed(() => {
  const c = { all: allJobs.value.length }
  for (const j of allJobs.value) {
    c[j.status] = (c[j.status] ?? 0) + 1
  }
  return c
})

const filteredJobs = computed(() =>
  activeStatus.value === 'all'
    ? allJobs.value
    : allJobs.value.filter(j => j.status === activeStatus.value)
)

async function load() {
  allJobs.value = await jobs.list(profileId).catch(() => [])
}

async function loadDiskUsage() {
  diskUsageError.value = null
  try {
    diskUsage.value = await transferConfig.diskUsage(profileId)
  } catch (e) {
    diskUsageError.value = e.response?.data?.error ?? e.message
    diskUsage.value = null
  }
}

async function cancel(id) {
  const updated = await jobs.cancel(id)
  const idx = allJobs.value.findIndex(j => j.id === id)
  if (idx !== -1) allJobs.value[idx] = updated
}

async function clearFinished() {
  await jobs.clearFinished(profileId)
  await load()
}

function statusBadge(s) {
  return {
    queued: 'badge-gray',
    running: 'badge-blue',
    completed: 'badge-green',
    failed: 'badge-red',
    cancelled: 'badge-gray',
  }[s] ?? 'badge-gray'
}

function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const gb = bytes / 1e9
  if (gb >= 1) return gb.toFixed(2) + ' GB'
  return (bytes / 1e6).toFixed(1) + ' MB'
}

function formatSpeed(bps) {
  return (bps / 1e6).toFixed(1) + ' MB/s'
}

function formatDate(iso) {
  return new Date(iso).toLocaleString()
}

onMounted(() => {
  load()
  loadDiskUsage()
  interval = setInterval(load, 3000)
})
onUnmounted(() => clearInterval(interval))
</script>

<style scoped>
.tabs { display: flex; gap: 4px; border-bottom: 1px solid #2d3148; }
.tab-btn {
  padding: 10px 16px;
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.tab-btn.active { color: #e2e8f0; border-bottom-color: #7c6af7; }
.tab-count { background: #2d3148; border-radius: 10px; padding: 1px 7px; font-size: 11px; }
.error-msg { font-size: 12px; color: #f87171; max-width: 200px; }
.disk-usage-bar { margin-bottom: 20px; }
.disk-usage-info { display: flex; gap: 16px; font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
.disk-usage-info span:first-child { color: #e2e8f0; font-weight: 500; }
.disk-bar { height: 6px; background: #2d3148; border-radius: 3px; overflow: hidden; }
.disk-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
</style>
