import request from './index'

/**
 * 安装统计 API
 */
const installationApi = {
  list(params) {
    return request.get('/installations', { params })
  },
  stats(params) {
    return request.get('/installations/stats', { params })
  },
  detail(id) {
    return request.get(`/installations/${id}`)
  },
  remove(id) {
    return request.delete(`/installations/${id}`)
  },
  restore(id) {
    return request.post(`/installations/${id}/restore`)
  },
  batchDelete(ids) {
    return request.post('/installations/batch-delete', { ids })
  },
  exportUrl(params = {}) {
    const q = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => {
      if (v !== '' && v !== null && v !== undefined) q.set(k, v)
    })
    const token = localStorage.getItem('access_token')
    if (token) q.set('token', token)
    return `/api/installations/export?${q.toString()}`
  }
}

export default installationApi
