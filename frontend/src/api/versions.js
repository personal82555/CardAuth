import request from './index'

/**
 * 项目版本/安装包 API
 */
const versionApi = {
  list(projectId, params) {
    return request.get(`/projects/${projectId}/versions`, { params })
  },
  detail(projectId, id) {
    return request.get(`/projects/${projectId}/versions/${id}`)
  },
  create(projectId, formData) {
    return request.post(`/projects/${projectId}/versions`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 300000
    })
  },
  update(id, data) {
    return request.put(`/versions/${id}`, data)
  },
  toggleForce(id, isForce) {
    return request.put(`/versions/${id}/force`, { is_force: isForce })
  },
  setLatest(id) {
    return request.put(`/versions/${id}/latest`)
  },
  remove(id) {
    return request.delete(`/versions/${id}`)
  }
}

export default versionApi
