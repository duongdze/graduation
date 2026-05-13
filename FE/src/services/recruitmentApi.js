import apiClient from './apiClient'

export const recruitmentApi = {
  // ── Player Posts ───────────────────────────────────────────

  /**
   * List player recruitment posts.
   * @param {{ status?, sport_type?, play_date?, per_page?, page? }} params
   */
  getPlayerPosts(params = {}) {
    return apiClient.get('/player-posts', { params })
  },

  /**
   * Get single player post with participants.
   * @param {string} id - UUID
   */
  getPlayerPost(id) {
    return apiClient.get(`/player-posts/${id}`)
  },

  /**
   * Create a player recruitment post.
   * @param {{ title, sport_type, play_date, start_time, end_time?, needed_players, max_players, description?, court_type_id?, venue_cluster_id?, booking_id?, location_name?, latitude?, longitude?, skill_level?, gender_preference?, cost_per_player?, is_auto_approve? }} payload
   */
  createPlayerPost(payload) {
    return apiClient.post('/player-posts', payload)
  },

  /**
   * Update a player post (author only).
   * @param {string} id
   * @param {object} payload
   */
  updatePlayerPost(id, payload) {
    return apiClient.put(`/player-posts/${id}`, payload)
  },

  /**
   * Delete a player post (author only).
   * @param {string} id
   */
  deletePlayerPost(id) {
    return apiClient.delete(`/player-posts/${id}`)
  },

  // ── Participation ─────────────────────────────────────────

  /**
   * Join a player post.
   * @param {string} postId
   * @param {{ message? }} payload
   */
  joinPlayerPost(postId, payload = {}) {
    return apiClient.post(`/player-posts/${postId}/join`, payload)
  },

  /**
   * Leave a player post.
   * @param {string} postId
   */
  leavePlayerPost(postId) {
    return apiClient.delete(`/player-posts/${postId}/leave`)
  },

  /**
   * Approve a participant (author only).
   * @param {string} postId
   * @param {string} participantId
   */
  approveParticipant(postId, participantId) {
    return apiClient.patch(`/player-posts/${postId}/participants/${participantId}/approve`)
  },

  /**
   * Reject a participant (author only).
   * @param {string} postId
   * @param {string} participantId
   */
  rejectParticipant(postId, participantId) {
    return apiClient.patch(`/player-posts/${postId}/participants/${participantId}/reject`)
  },
}

export default recruitmentApi
