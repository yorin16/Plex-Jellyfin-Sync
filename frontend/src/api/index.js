import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
})

export const profiles = {
  list: ()          => api.get('/profiles').then(r => r.data),
  get: (id)         => api.get(`/profiles/${id}`).then(r => r.data),
  create: (data)    => api.post('/profiles', data).then(r => r.data),
  update: (id, data)=> api.put(`/profiles/${id}`, data).then(r => r.data),
  remove: (id)      => api.delete(`/profiles/${id}`),
}

export const connections = {
  list: (profileId)        => api.get(`/profiles/${profileId}/connections`).then(r => r.data),
  create: (profileId, data)=> api.post(`/profiles/${profileId}/connections`, data).then(r => r.data),
  update: (id, data)       => api.put(`/connections/${id}`, data).then(r => r.data),
  remove: (id)             => api.delete(`/connections/${id}`),
  test: (id)               => api.post(`/connections/${id}/test`).then(r => r.data),
  libraries: (id)          => api.get(`/connections/${id}/libraries`).then(r => r.data),
}

export const scan = {
  trigger: (profileId) => api.post(`/profiles/${profileId}/scan`).then(r => r.data),
  status: (profileId)  => api.get(`/profiles/${profileId}/scan/status`).then(r => r.data),
}

export const comparison = {
  get: (profileId) => api.get(`/profiles/${profileId}/comparison`).then(r => r.data),
}

export const overrides = {
  create: (profileId, data) => api.post(`/profiles/${profileId}/overrides`, data).then(r => r.data),
  remove: (id)              => api.delete(`/overrides/${id}`),
  ignore: (profileId, data) => api.post(`/profiles/${profileId}/ignore`, data).then(r => r.data),
  listIgnored: (profileId)  => api.get(`/profiles/${profileId}/ignored`).then(r => r.data),
  unignore: (id)            => api.delete(`/ignored/${id}`),
}

export const jobs = {
  list: (profileId, statuses) => {
    const params = statuses ? { status: statuses.join(',') } : {}
    return api.get(`/profiles/${profileId}/jobs`, { params }).then(r => r.data)
  },
  queue: (profileId, mediaIds, transcodeProfileId = null) =>
    api.post(`/profiles/${profileId}/jobs`, { mediaIds, transcodeProfileId }).then(r => r.data),
  cancel: (id)                  => api.delete(`/jobs/${id}`).then(r => r.data),
  clearFinished: (profileId)    => api.delete(`/profiles/${profileId}/jobs/finished`).then(r => r.data),
}

export const transferConfig = {
  get: (profileId)        => api.get(`/profiles/${profileId}/transfer-config`).then(r => r.data),
  save: (profileId, data) => api.put(`/profiles/${profileId}/transfer-config`, data).then(r => r.data),
  diskUsage: (profileId)  => api.get(`/profiles/${profileId}/transfer-config/disk-usage`).then(r => r.data),
}

export const validationRules = {
  list: (profileId)        => api.get(`/profiles/${profileId}/rules`).then(r => r.data),
  create: (profileId, data)=> api.post(`/profiles/${profileId}/rules`, data).then(r => r.data),
  remove: (id)             => api.delete(`/rules/${id}`),
}

export const dashboard = {
  get: () => api.get('/dashboard').then(r => r.data),
}

export const transcodeProfiles = {
  list:   ()         => api.get('/transcode-profiles').then(r => r.data),
  create: (data)     => api.post('/transcode-profiles', data).then(r => r.data),
  update: (id, data) => api.put(`/transcode-profiles/${id}`, data).then(r => r.data),
  remove: (id)       => api.delete(`/transcode-profiles/${id}`),
}

export const transcodeJobs = {
  list:         (profileId) => api.get(`/profiles/${profileId}/transcode-jobs`).then(r => r.data),
  cancel:       (id)        => api.delete(`/transcode-jobs/${id}`).then(r => r.data),
  clearFinished:(profileId) => api.delete(`/profiles/${profileId}/transcode-jobs/finished`).then(r => r.data),
}
