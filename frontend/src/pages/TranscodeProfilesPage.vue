<template>
  <div>
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
      <router-link to="/profiles" class="btn btn-ghost" style="padding: 6px 12px;">← Profiles</router-link>
      <h1 class="page-title" style="margin: 0;">Transcode Profiles</h1>
      <button class="btn btn-primary" style="margin-left: auto;" @click="openNew">+ New Profile</button>
    </div>

    <div v-if="profiles.length === 0" class="card" style="text-align: center; color: #64748b; padding: 40px;">
      No transcode profiles yet. Create one to start compressing movies before transfer.
    </div>

    <div v-else class="card" style="padding: 0;">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Codec</th>
            <th>Video bitrate</th>
            <th>Max height</th>
            <th>HDR→SDR</th>
            <th>Lossless audio</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in profiles" :key="p.id">
            <td style="font-weight: 500;">{{ p.name }}</td>
            <td><code style="font-size: 12px;">{{ p.videoCodec }}</code></td>
            <td>{{ p.videoBitrateKbps.toLocaleString() }} kbps</td>
            <td>{{ p.maxHeight ? p.maxHeight + 'p' : 'unchanged' }}</td>
            <td>
              <span class="badge" :class="p.hdrToSdr ? 'badge-green' : 'badge-gray'">
                {{ p.hdrToSdr ? 'yes' : 'no' }}
              </span>
            </td>
            <td style="font-size: 12px; color: #94a3b8;">
              {{ p.losslessAudioCodec.toUpperCase() }} {{ p.losslessAudioBitrateKbps }} kbps
            </td>
            <td>
              <div style="display: flex; gap: 6px;">
                <button class="btn btn-ghost" style="font-size: 12px; padding: 4px 10px;" @click="openEdit(p)">Edit</button>
                <button class="btn btn-danger" style="font-size: 12px; padding: 4px 10px;" @click="remove(p.id)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="modal-backdrop" @click.self="modal = null">
      <div class="modal">
        <h2 style="margin: 0 0 20px;">{{ modal.id ? 'Edit Profile' : 'New Transcode Profile' }}</h2>

        <div class="form-grid">
          <label>Name
            <input class="input" v-model="modal.name" placeholder="e.g. HEVC 1080p SDR" />
          </label>
          <label>Video codec
            <select class="input" v-model="modal.videoCodec">
              <option value="hevc_qsv">hevc_qsv (H.265 QuickSync)</option>
              <option value="h264_qsv">h264_qsv (H.264 QuickSync)</option>
              <option value="libx265">libx265 (H.265 software)</option>
              <option value="libx264">libx264 (H.264 software)</option>
            </select>
          </label>
          <label>Video bitrate (kbps)
            <input class="input" type="number" v-model.number="modal.videoBitrateKbps" min="500" max="50000" />
          </label>
          <label>Max height (px)
            <input class="input" type="number" v-model.number="modal.maxHeight" placeholder="e.g. 1080 (blank = no resize)" min="240" max="2160" />
          </label>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" v-model="modal.hdrToSdr" />
            Convert HDR → SDR (vpp_qsv tonemap)
          </label>
          <label>Lossless audio codec
            <select class="input" v-model="modal.losslessAudioCodec">
              <option value="eac3">EAC3 / Dolby Digital Plus (recommended)</option>
              <option value="aac">AAC (max compatibility)</option>
            </select>
          </label>
          <label>Lossless audio bitrate (kbps)
            <input class="input" type="number" v-model.number="modal.losslessAudioBitrateKbps" min="128" max="2048" />
          </label>
        </div>

        <p style="font-size: 12px; color: #64748b; margin-top: 12px;">
          Lossy audio tracks (AC3, AAC, standard DTS) are always copied untouched.
          Only TrueHD, Atmos, DTS-HD MA and PCM are transcoded using the settings above.
        </p>

        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;">
          <button class="btn btn-ghost" @click="modal = null">Cancel</button>
          <button class="btn btn-primary" @click="save">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { transcodeProfiles } from '@/api'

const profiles = ref([])
const modal    = ref(null)

async function load() {
  profiles.value = await transcodeProfiles.list()
}

function openNew() {
  modal.value = {
    id: null,
    name: '',
    videoCodec: 'hevc_qsv',
    videoBitrateKbps: 5000,
    maxHeight: 1080,
    hdrToSdr: true,
    losslessAudioCodec: 'eac3',
    losslessAudioBitrateKbps: 768,
  }
}

function openEdit(p) {
  modal.value = { ...p }
}

async function save() {
  const data = { ...modal.value }
  if (!data.maxHeight) data.maxHeight = null

  if (data.id) {
    const updated = await transcodeProfiles.update(data.id, data)
    const idx = profiles.value.findIndex(p => p.id === data.id)
    if (idx !== -1) profiles.value[idx] = updated
  } else {
    const created = await transcodeProfiles.create(data)
    profiles.value.push(created)
  }
  modal.value = null
}

async function remove(id) {
  await transcodeProfiles.remove(id)
  profiles.value = profiles.value.filter(p => p.id !== id)
}

onMounted(load)
</script>

<style scoped>
.modal-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.6);
  display: flex; align-items: center; justify-content: center;
  z-index: 100;
}
.modal {
  background: #1e2035;
  border: 1px solid #2d3148;
  border-radius: 10px;
  padding: 28px;
  width: 480px;
  max-width: 95vw;
}
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-grid label { display: flex; flex-direction: column; gap: 5px; font-size: 13px; color: #94a3b8; }
.input {
  background: #13142a; border: 1px solid #2d3148; border-radius: 6px;
  color: #e2e8f0; padding: 8px 10px; font-size: 14px;
}
.input:focus { outline: none; border-color: #7c6af7; }
</style>
