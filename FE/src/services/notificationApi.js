import apiClient from './apiClient'

export const notificationApi = {
  /**
   * List notifications for current user.
   * @param {{ is_read?, per_page?, page? }} params
   */
  getNotifications(params = {}) {
    return apiClient.get('/notifications', { params })
  },

  /**
   * Mark a single notification as read.
   * @param {string} id - UUID
   */
  markAsRead(id) {
    return apiClient.patch(`/notifications/${id}/read`)
  },

  /**
   * Mark all unread notifications as read.
   */
  markAllAsRead() {
    return apiClient.patch('/notifications/read-all')
  },
}

export default notificationApi
