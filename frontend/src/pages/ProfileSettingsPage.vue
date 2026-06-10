<template>
  <div v-if="profile">
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 32px;">
      <router-link to="/profiles" class="btn btn-ghost" style="padding: 6px 12px;">← Back</router-link>
      <h1 class="page-title" style="margin: 0;">{{ profile.name }} — Settings</h1>
    </div>

    <!-- Connections -->
    <section class="section">
      <div class="section-header">
        <h2>Connections</h2>
        <button class="btn btn-primary" @click="openAddConn">+ Add Connection</button>
      </div>

      <div v-if="conns.length === 0" class="card empty-card">No connections yet.</div>

      <div v-for="c in conns" :key="c.id" class="card conn-card">
        <div class="conn-info">
          <span class="badge" :class="c.role === 'source' ? 'badge-green' : 'badge-blue'">{{ c.role }}</span>
          <span class="badge badge-gray">{{ c.type }}</span>
          <span style="font-weight: 600;">{{ c.url }}</span>
          <span v-if="c.lastScannedAt" style="font-size: 12px; color: #64748b;">Scanned {{ formatDate(c.lastScannedAt) }}</span>
        </div>
        <div class="conn-actions">
          <button class="btn btn-ghost" @click="testConn(c)" :disabled="testing[c.id]">
            {{ testing[c.id] ? 'Testing…' : 'Test' }}
          </button>
          <span v-if="testResults[c.id] !== undefined" :class="testResults[c.id] ? 'badge badge-green' : 'badge badge-red'">
            {{ testResults[c.id] ? 'OK' : 'Failed' }}
          </span>
          <button class="btn btn-ghost" @click="loadLibraries(c)">Libraries</button>
          <button class="btn btn-danger" @click="deleteConn(c.id)">Delete</button>
        </div>
        <div v-if="libraryMap[c.id]" class="library-list">
          <div
            v-for="lib in libraryMap[c.id]"
            :key="lib.id"
            class="library-item"
            :class="{ selected: c.libraryId === lib.id }"
            @click="selectLibrary(c, lib.id)"
          >
            {{ lib.title }} <span style="font-size: 11px; color: #64748b;">#{{ lib.id }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Transfer Config -->
    <section class="section">
      <h2>Transfer Configuration</h2>
      <div class="card">
        <div class="form-group">
          <label>Method</label>
          <select v-model="tc.method">
            <option value="sftp">SFTP</option>
            <option value="local">Local Filesystem</option>
          </select>
        </div>
        <template v-if="tc.method === 'sftp'">
          <div class="form-row">
            <div class="form-group">
              <label>Host</label>
              <input v-model="tc.config.host" placeholder="192.168.1.x" />
            </div>
            <div class="form-group" style="max-width: 100px;">
              <label>Port</label>
              <input v-model="tc.config.port" type="number" placeholder="22" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Username</label>
              <input v-model="tc.config.username" />
            </div>
            <div class="form-group">
              <label>Password</label>
              <input v-model="tc.config.password" type="password" placeholder="Leave empty if using key" />
            </div>
          </div>
          <div class="form-group">
            <label>SSH Key Path (optional)</label>
            <input v-model="tc.config.key_path" placeholder="/path/to/id_rsa" />
          </div>
        </template>
        <div class="form-group">
          <label>Destination Base Path</label>
          <input v-model="tc.config.dest_base_path" placeholder="/media/jellyfin/movies" />
        </div>
        <button class="btn btn-primary" @click="saveTransferConfig" :disabled="savingTc">
          {{ savingTc ? 'Saving…' : 'Save Transfer Config' }}
        </button>
      </div>
    </section>

    <!-- Validation Rules -->
    <section class="section">
      <div class="section-header">
        <h2>Validation Rules</h2>
        <button class="btn btn-primary" @click="openAddRule">+ Add Rule</button>
      </div>
      <div v-if="rules.length === 0" class="card empty-card">No rules. All movies will be allowed.</div>
      <div v-for="r in rules" :key="r.id" class="card rule-card">
        <span class="badge badge-gray">{{ r.ruleType }}</span>
        <span style="color: #94a3b8;">{{ r.operator }}</span>
        <span style="font-weight: 500;">"{{ r.value }}"</span>
        <span class="badge" :class="r.action === 'reject' ? 'badge-red' : 'badge-yellow'">{{ r.action }}</span>
        <button class="btn btn-danger" style="margin-left: auto;" @click="deleteRule(r.id)">Delete</button>
      </div>
    </section>

    <!-- Scan -->
    <section class="section">
      <h2>Library Scan</h2>
      <div class="card">
        <p style="color: #94a3b8; margin-bottom: 16px;">
          Scan both Plex and Jellyfin libraries and update the local cache. Required before comparing.
        </p>
        <button class="btn btn-primary" @click="runScan" :disabled="scanning">
          {{ scanning ? 'Scanning…' : 'Scan Now' }}
        </button>
        <div v-if="scanResults.length" style="margin-top: 16px;">
          <div v-for="r in scanResults" :key="r.connectionId" style="margin-bottom: 6px;">
            <span class="badge" :class="r.success ? 'badge-green' : 'badge-red'">{{ r.role }} ({{ r.type }})</span>
            <span v-if="r.success" style="margin-left: 8px; color: #94a3b8;">{{ r.itemsScanned }} items</span>
            <span v-else style="margin-left: 8px; color: #f87171;">{{ r.error }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Add Connection Modal -->
    <div v-if="showAddConn" class="modal-overlay" @click.self="showAddConn = false">
      <div class="modal card">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;">Add Connection</h2>
        <div class="form-group">
          <label>Role</label>
          <select v-model="newConn.role">
            <option value="source">Source (Plex)</option>
            <option value="destination">Destination (Jellyfin)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Type</label>
          <select v-model="newConn.type">
            <option value="plex">Plex</option>
            <option value="jellyfin">Jellyfin</option>
          </select>
        </div>
        <div class="form-group">
          <label>URL</label>
          <input v-model="newConn.url" placeholder="http://192.168.1.x:32400" />
        </div>
        <div class="form-group">
          <label>API Token</label>
          <input v-model="newConn.apiToken" placeholder="X-Plex-Token or Jellyfin API key" />
        </div>
        <div class="form-group">
          <label>Library Root Path (what the media server reports, e.g. /movies)</label>
          <input v-model="newConn.libraryRoot" placeholder="/movies" />
        </div>
        <div class="form-group">
          <label>Local Path (inside Docker container, e.g. /var/media/source/films)</label>
          <input v-model="newConn.localPath" placeholder="/var/media/source" />
        </div>
        <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px;">
          <button class="btn btn-ghost" @click="showAddConn = false">Cancel</button>
          <button class="btn btn-primary" @click="addConn">Add</button>
        </div>
      </div>
    </div>

    <!-- Add Rule Modal -->
    <div v-if="showAddRule" class="modal-overlay" @click.self="showAddRule = false">
      <div class="modal card">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 20px;">Add Validation Rule</h2>
        <div class="form-group">
          <label>Property</label>
          <select v-model="newRule.ruleType">
            <option value="codec">Codec</option>
            <option value="hdr_type">HDR Type</option>
            <option value="resolution">Resolution</option>
            <option value="file_size_max">File Size (bytes)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Operator</label>
          <select v-model="newRule.operator">
            <option value="equals">equals</option>
            <option value="not_equals">not equals</option>
            <option value="contains">contains</option>
            <option value="gt">greater than</option>
            <option value="lt">less than</option>
          </select>
        </div>
        <div class="form-group">
          <label>Value</label>
          <input v-model="newRule.value" placeholder="e.g. av1, Dolby Vision, 2160p" />
        </div>
        <div class="form-group">
          <label>Action</label>
          <select v-model="newRule.action">
            <option value="reject">Reject (block transfer)</option>
            <option value="flag">Flag (warn only)</option>
          </select>
        </div>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <button class="btn btn-ghost" @click="showAddRule = false">Cancel</button>
          <button class="btn btn-primary" @click="addRule">Add Rule</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { profiles, connections, scan, transferConfig, validationRules } from '@/api'

const route = useRoute()
const profileId = Number(route.params.id)

const profile = ref(null)
const conns = ref([])
const rules = ref([])
const tc = reactive({ method: 'sftp', config: { host: '', port: 22, username: '', password: '', key_path: '', dest_base_path: '' } })
const scanning = ref(false)
const scanResults = ref([])
const savingTc = ref(false)
const testing = reactive({})
const testResults = reactive({})
const libraryMap = reactive({})
const showAddConn = ref(false)
const showAddRule = ref(false)

const newConn = reactive({ role: 'source', type: 'plex', url: '', apiToken: '', libraryRoot: '', localPath: '' })
const newRule = reactive({ ruleType: 'codec', operator: 'equals', value: '', action: 'reject' })

async function load() {
  [profile.value, conns.value, rules.value] = await Promise.all([
    profiles.get(profileId),
    connections.list(profileId),
    validationRules.list(profileId),
  ])
  const cfg = await transferConfig.get(profileId).catch(() => null)
  if (cfg) {
    tc.method = cfg.method
    Object.assign(tc.config, cfg.config)
    if (tc.config.password === '***') tc.config.password = ''
  }
}

async function testConn(c) {
  testing[c.id] = true
  const result = await connections.test(c.id).catch(() => ({ success: false }))
  testResults[c.id] = result.success
  testing[c.id] = false
}

async function loadLibraries(c) {
  try {
    libraryMap[c.id] = await connections.libraries(c.id)
  } catch (e) {
    alert('Failed to load libraries: ' + (e.response?.data?.error ?? e.message))
  }
}

async function selectLibrary(c, libId) {
  await connections.update(c.id, { libraryId: libId })
  c.libraryId = libId
}

async function deleteConn(id) {
  await connections.remove(id)
  conns.value = conns.value.filter(c => c.id !== id)
}

function openAddConn() {
  Object.assign(newConn, { role: 'source', type: 'plex', url: '', apiToken: '', libraryRoot: '', localPath: '' })
  showAddConn.value = true
}

async function addConn() {
  const c = await connections.create(profileId, { ...newConn })
  conns.value.push(c)
  showAddConn.value = false
}

async function saveTransferConfig() {
  savingTc.value = true
  await transferConfig.save(profileId, { method: tc.method, config: { ...tc.config } })
  savingTc.value = false
}

async function runScan() {
  scanning.value = true
  scanResults.value = []
  const result = await scan.trigger(profileId).catch(e => ({ results: [{ success: false, error: e.message }] }))
  scanResults.value = result.results
  scanning.value = false
  await load()
}

function openAddRule() {
  Object.assign(newRule, { ruleType: 'codec', operator: 'equals', value: '', action: 'reject' })
  showAddRule.value = true
}

async function addRule() {
  const r = await validationRules.create(profileId, { ...newRule })
  rules.value.push(r)
  showAddRule.value = false
}

async function deleteRule(id) {
  await validationRules.remove(id)
  rules.value = rules.value.filter(r => r.id !== id)
}

function formatDate(iso) {
  return new Date(iso).toLocaleString()
}

onMounted(load)
</script>

<style scoped>
.section { margin-bottom: 40px; }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.section-header h2 { font-size: 16px; font-weight: 600; }
h2 { font-size: 16px; font-weight: 600; margin-bottom: 16px; }

.empty-card { color: #64748b; text-align: center; padding: 24px; }

.conn-card { margin-bottom: 12px; }
.conn-info { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.conn-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.library-list { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; }
.library-item {
  padding: 6px 12px;
  background: #0f1117;
  border: 1px solid #2d3148;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
}
.library-item:hover { border-color: #7c6af7; }
.library-item.selected { border-color: #7c6af7; background: #252840; }

.rule-card { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }

.form-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.6);
  display: flex; align-items: center; justify-content: center;
  z-index: 100;
}
.modal { width: 460px; max-width: 90vw; }
</style>
