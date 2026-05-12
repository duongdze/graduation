import apiClient from './apiClient'

export const userApi = {
  /**
   * List users with optional filters.
   * @param {{ status?, search?, per_page?, page? }} params
   */
  getUsers(params = {}) {
    return apiClient.get('/users', { params })
  },

  /**
   * Get single user detail.
   * @param {string} id - UUID
   */
  getUser(id) {
    return apiClient.get(`/users/${id}`)
  },

  /**
   * Create a new user (admin).
   * @param {{ full_name, email, phone?, password, password_confirmation, status? }} payload
   */
  createUser(payload) {
    return apiClient.post('/users', payload)
  },

  /**
   * Update user info (admin).
   * @param {string} id
   * @param {{ full_name?, email?, phone?, password?, status? }} payload
   */
  updateUser(id, payload) {
    return apiClient.put(`/users/${id}`, payload)
  },

  /**
   * Delete user (soft delete).
   * @param {string} id
   */
  deleteUser(id) {
    return apiClient.delete(`/users/${id}`)
  },

  /**
   * Lock user account.
   * @param {string} id
   */
  lockUser(id) {
    return apiClient.patch(`/users/${id}/lock`)
  },

  /**
   * Unlock user account.
   * @param {string} id
   */
  unlockUser(id) {
    return apiClient.patch(`/users/${id}/unlock`)
  },
}

export default userApi
