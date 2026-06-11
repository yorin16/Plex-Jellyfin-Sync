<template>
  <div>
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;">
      <router-link to="/profiles" class="btn btn-ghost" style="padding: 6px 12px;">← Profiles</router-link>
      <h1 class="page-title" style="margin: 0;">Comparison</h1>
      <div style="margin-left: auto; display: flex; gap: 8px;">
        <router-link :to="`/profiles/${profileId}/settings`" class="btn btn-ghost">Settings</router-link>
        <router-link :to="`/profiles/${profileId}/queue`" class="btn btn-ghost">Queue</router-link>
        <button class="btn btn-ghost" @click="load" :disabled="loading">
          {{ loading ? 'Loading…' : '↺ Refresh' }}
        </button>
      </div>
    </div>

    <div v-if="error" style="color: #f87171; margin-bottom: 20px;">{{ error }}</div>

    <!-- Tabs -->
    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span class="tab-count">{{ tabCounts[tab.key] }}</span>
      </button>
    </div>

    <!-- Missing from destination -->
    <div v-if="activeTab === 'missing'">
      <div v-if="onlyInSource.length === 0" class="card empty-card">All movies are already on the destination. 🎉</div>
      <template v-else>
        <div class="toolbar">
          <label>
            <input type="checkbox" @change="toggleAll" :checked="selectedIds.size === onlyInSource.length" />
            Select all
          </label>
          <span v-if="selectedIds.size > 0" style="color: #94a3b8; font-size: 13px;">
            {{ formatBytes(selectedSize) }}
          </span>
          <button
            class="btn btn-primary"
            :disabled="selectedIds.size === 0"
            @click="queueSelected"
          >
            Queue {{ selectedIds.size > 0 ? selectedIds.size : '' }} Selected
          </button>
        </div>

        <div class="card" style="padding: 0;">
          <table>
            <thead>
              <tr>
                <th style="width: 40px;"></th>
                <th>Title</th>
                <th>Year</th>
                <th>Size</th>
                <th>Codec</th>
                <th>Resolution</th>
                <th>HDR</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in onlyInSource" :key="item.id">
                <td>
                  <input
                    type="checkbox"
                    :checked="selectedIds.has(item.id)"
                    :disabled="item.validation?.status === 'rejected' && !overriddenIds.has(item.id)"
                    @change="toggleSelect(item.id)"
                  />
                </td>
                <td style="font-weight: 500;">{{ item.title }}</td>
                <td>{{ item.year }}</td>
                <td style="white-space: nowrap;">{{ formatBytes(item.fileSize) }}</td>
                <td>{{ item.codec || '—' }}</td>
                <td>{{ item.resolution || '—' }}</td>
                <td>
                  <span v-if="item.hdrType" class="badge badge-yellow">{{ item.hdrType }}</span>
                  <span v-else style="color: #64748b;">—</span>
                </td>
                <td>
                  <span
                    v-if="item.validation?.status !== 'allowed'"
                    class="badge"
                    :class="item.validation?.status === 'rejected' ? 'badge-red' : 'badge-yellow'"
                    :title="item.validation?.triggeredRules?.map(r => r.ruleType + ' ' + r.operator + ' ' + r.value).join(', ')"
                  >
                    {{ item.validation?.status }}
                  </span>
                  <span v-else class="badge badge-green">OK</span>
                </td>
                <td>
                  <div style="display: flex; gap: 6px; flex-wrap: nowrap;">
                    <button
                      v-if="item.validation?.status === 'rejected' && !overriddenIds.has(item.id)"
                      class="btn btn-ghost"
                      style="font-size: 12px; padding: 4px 10px;"
                      @click="overriddenIds.add(item.id)"
                    >
                      Force
                    </button>
                    <button
                      class="btn btn-primary"
                      style="font-size: 12px; padding: 4px 10px;"
                      :disabled="item.validation?.status === 'rejected' && !overriddenIds.has(item.id)"
                      @click="queueOne(item.id)"
                    >
                      Queue
                    </button>
                    <button
                      class="btn btn-ghost"
                      style="font-size: 12px; padding: 4px 10px;"
                      @click="ignore(item.id)"
                    >
                      Ignore
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- Only in destination -->
    <div v-if="activeTab === 'destOnly'">
      <div v-if="onlyInDest.length === 0" class="card empty-card">No items only in destination.</div>
      <div v-else class="card" style="padding: 0;">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Year</th>
              <th>Size</th>
              <th>Codec</th>
              <th>Resolution</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in onlyInDest" :key="item.id">
              <td style="font-weight: 500;">{{ item.title }}</td>
              <td>{{ item.year }}</td>
              <td>{{ formatBytes(item.fileSize) }}</td>
              <td>{{ item.codec || '—' }}</td>
              <td>{{ item.resolution || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Potential matches -->
    <div v-if="activeTab === 'potential'">
      <div v-if="potentialMatches.length === 0" class="card empty-card">No potential matches to review.</div>
      <div v-for="pair in potentialMatches" :key="pair.source.id + '-' + pair.destination.id" class="card match-pair">
        <div class="match-side">
          <div class="match-label">Source</div>
          <div class="match-title">{{ pair.source.title }} ({{ pair.source.year }})</div>
          <div class="match-meta">{{ pair.source.codec }} · {{ pair.source.resolution }}</div>
        </div>
        <div class="match-actions">
          <button class="btn btn-primary" style="font-size: 12px;" @click="acceptMatch(pair)">✓ Match</button>
          <div style="color: #64748b; font-size: 12px; text-align: center;">vs</div>
          <button class="btn btn-ghost" style="font-size: 12px;">✗ Dismiss</button>
        </div>
        <div class="match-side">
          <div class="match-label">Destination</div>
          <div class="match-title">{{ pair.destination.title }} ({{ pair.destination.year }})</div>
          <div class="match-meta">{{ pair.destination.codec }} · {{ pair.destination.resolution }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { comparison, jobs, overrides } from '@/api'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const profileId = Number(route.params.id)

const { show: toast } = useToast()
const loading = ref(false)
const error = ref(null)
const onlyInSource = ref([])
const onlyInDest = ref([])
const potentialMatches = ref([])
const activeTab = ref('missing')
const selectedIds = reactive(new Set())
const overriddenIds = reactive(new Set())

const tabs = [
  { key: 'missing',   label: 'Missing from Destination' },
  { key: 'destOnly',  label: 'Only in Destination' },
  { key: 'potential', label: 'Potential Matches' },
]

const tabCounts = computed(() => ({
  missing:   onlyInSource.value.length,
  destOnly:  onlyInDest.value.length,
  potential: potentialMatches.value.length,
}))

const selectedSize = computed(() =>
  onlyInSource.value
    .filter(i => selectedIds.has(i.id))
    .reduce((sum, i) => sum + (i.fileSize ?? 0), 0)
)

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await comparison.get(profileId)
    onlyInSource.value = data.onlyInSource
    onlyInDest.value = data.onlyInDestination
    potentialMatches.value = data.potentialMatches
    selectedIds.clear()
  } catch (e) {
    error.value = 'Failed to load comparison: ' + (e.response?.data?.error ?? e.message)
  } finally {
    loading.value = false
  }
}

function toggleAll(e) {
  if (e.target.checked) {
    onlyInSource.value
      .filter(i => i.validation?.status !== 'rejected' || overriddenIds.has(i.id))
      .forEach(i => selectedIds.add(i.id))
  } else {
    selectedIds.clear()
  }
}

function toggleSelect(id) {
  selectedIds.has(id) ? selectedIds.delete(id) : selectedIds.add(id)
}

async function queueOne(id) {
  await jobs.queue(profileId, [id])
  toast('Added to queue')
}

async function queueSelected() {
  const count = selectedIds.size
  await jobs.queue(profileId, [...selectedIds])
  selectedIds.clear()
  toast(`${count} movie${count !== 1 ? 's' : ''} added to queue`)
}

async function ignore(id) {
  await overrides.ignore(profileId, { sourceMediaId: id })
  onlyInSource.value = onlyInSource.value.filter(i => i.id !== id)
}

async function acceptMatch(pair) {
  await overrides.create(profileId, {
    sourceMediaId: pair.source.id,
    destinationMediaId: pair.destination.id,
  })
  potentialMatches.value = potentialMatches.value.filter(
    p => p.source.id !== pair.source.id
  )
}

function formatBytes(bytes) {
  if (!bytes) return '—'
  const gb = bytes / 1e9
  if (gb >= 1) return gb.toFixed(2) + ' GB'
  return (bytes / 1e6).toFixed(1) + ' MB'
}

onMounted(load)
</script>

<style scoped>
.tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid #2d3148; }
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
.tab-count {
  background: #2d3148;
  border-radius: 10px;
  padding: 1px 7px;
  font-size: 11px;
}

.toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; }

.empty-card { text-align: center; color: #64748b; padding: 40px; }

.match-pair {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 16px;
  align-items: center;
  margin-bottom: 12px;
}
.match-side {}
.match-label { font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.match-title { font-weight: 600; font-size: 15px; }
.match-meta { font-size: 12px; color: #94a3b8; margin-top: 4px; }
.match-actions { display: flex; flex-direction: column; align-items: center; gap: 8px; }
</style>
