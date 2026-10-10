import request from './index'

/**
 * 公告推送 API
 */
const announcementApi = {
  list(params) {
    return request.get('/announcements', { params })
  },
  detail(id) {
    return request.get(`/announcements/${id}`)
  },
  create(data) {
    return request.post('/announcements', data)
  },
  update(id, data) {
    return request.put(`/announcements/${id}`, data)
  },
  remove(id) {
    return request.delete(`/announcements/${id}`)
  },
  batchDelete(ids) {
    return request.post('/announcements/batch-delete', { ids })
  },
  /** 公开接口（客户端/商城） */
  public(params) {
    return request.get('/public/announcements', { params })
  }
}

export default announcementApi
