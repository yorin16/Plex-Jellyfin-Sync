<template>
  <div>
    <h1 class="page-title">Dashboard</h1>

    <div class="stats-grid">
      <div class="card stat-card">
        <div class="stat-value">{{ counts.running }}</div>
        <div class="stat-label">Active Transfers</div>
      </div>
      <div class="card stat-card">
        <div class="stat-value">{{ counts.queued }}</div>
        <div class="stat-label">Queued</div>
      </div>
      <div class="card stat-card">
        <div class="stat-value">{{ counts.completed }}</div>
        <div class="stat-label">Completed</div>
      </div>
      <div class="card stat-card stat-card--failed">
        <div class="stat-value">{{ counts.failed }}</div>
        <div class="stat-label">Failed</div>
      </div>
    </div>

    <div v-if="activeJobs.length" style="margin-top: 32px;">
      <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Active Transfers</h2>
      <div class="card" style="padding: 0;">
        <table>
          <thead>
            <tr>
              <th>Movie</th>
              <th>Progress</th>
              <th>Speed</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="job in activeJobs" :key="job.id">
              <td>{{ job.sourceMedia.title }} ({{ job.sourceMedia.year }})</td>
              <td style="min-width: 200px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div class="progress-bar" style="flex: 1;">
                    <div class="progress-bar-fill" :style="{ width: job.progressPercent + '%' }"></div>
                  </div>
                  <span style="font-size: 12px; color: #94a3b8; min-width: 42px;">{{ job.progressPercent }}%</span>
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                  {{ formatBytes(job.bytesCopied) }} / {{ formatBytes(job.totalBytes) }}
                </div>
              </td>
              <td style="color: #94a3b8; white-space: nowrap;">
                {{ job.transferSpeedBps ? formatSpeed(job.transferSpeedBps) : '—' }}
              </td>
              <td><span class="badge badge-blue">{{ job.status }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="!activeJobs.length && counts.queued === 0" style="margin-top: 48px; text-align: center; color: #64748b;">
      No active transfers. Go to a profile to start syncing.
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { dashboard } from '@/api'

const counts = ref({ running: 0, queued: 0, completed: 0, failed: 0, cancelled: 0 })
const activeJobs = ref([])
let interval = null

async function load() {
  try {
    const data = await dashboard.get()
    counts.value = data.counts
    activeJobs.value = data.activeJobs
  } catch {}
}

function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const gb = bytes / 1e9
  if (gb >= 1) return gb.toFixed(2) + ' GB'
  const mb = bytes / 1e6
  if (mb >= 1) return mb.toFixed(1) + ' MB'
  return (bytes / 1e3).toFixed(0) + ' KB'
}

function formatSpeed(bps) {
  const mbps = bps / 1e6
  return mbps.toFixed(1) + ' MB/s'
}

onMounted(() => {
  load()
  interval = setInterval(load, 3000)
})
onUnmounted(() => clearInterval(interval))
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.stat-card {
  text-align: center;
  padding: 24px;
}
.stat-value {
  font-size: 36px;
  font-weight: 700;
  color: #7c6af7;
}
.stat-card--failed .stat-value { color: #f87171; }
.stat-label {
  font-size: 12px;
  color: #64748b;
  margin-top: 4px;
}
</style>
