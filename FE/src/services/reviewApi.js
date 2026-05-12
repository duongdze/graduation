import apiClient from './apiClient'

export const reviewApi = {
  // ── Venue Reviews ─────────────────────────────────────────

  /**
   * List venue reviews.
   * @param {{ cluster_id?, customer_id?, is_visible?, per_page?, page? }} params
   */
  getReviews(params = {}) {
    return apiClient.get('/reviews', { params })
  },

  /**
   * Get single review detail.
   * @param {string} id - UUID
   */
  getReview(id) {
    return apiClient.get(`/reviews/${id}`)
  },

  /**
   * Create a review for a completed booking.
   * @param {{ booking_id, rating, comment?, is_visible? }} payload
   */
  createReview(payload) {
    return apiClient.post('/reviews', payload)
  },

  /**
   * Update own review.
   * @param {string} id
   * @param {{ rating?, comment?, is_visible? }} payload
   */
  updateReview(id, payload) {
    return apiClient.put(`/reviews/${id}`, payload)
  },

  /**
   * Delete a review.
   * @param {string} id
   */
  deleteReview(id) {
    return apiClient.delete(`/reviews/${id}`)
  },

  // ── Player Ratings ────────────────────────────────────────

  /**
   * List player ratings.
   * @param {{ rated_user_id?, rater_id?, per_page?, page? }} params
   */
  getPlayerRatings(params = {}) {
    return apiClient.get('/player-ratings', { params })
  },

  /**
   * Rate a player (upsert — updates if existing).
   * @param {{ rated_user_id, post_id?, rating, comment?, tags? }} payload
   */
  createPlayerRating(payload) {
    return apiClient.post('/player-ratings', payload)
  },

  // ── Reports (moderation) ──────────────────────────────────

  /**
   * List reports (admin/moderator).
   * @param {{ status?, per_page?, page? }} params
   */
  getReports(params = {}) {
    return apiClient.get('/reports', { params })
  },

  /**
   * Get single report detail.
   * @param {string} id
   */
  getReport(id) {
    return apiClient.get(`/reports/${id}`)
  },

  /**
   * Create a report (flag content).
   * @param {{ reportable_type, reportable_id, reason, description? }} payload
   * reportable_type: 'user' | 'review' | 'player_post' | 'player_rating'
   */
  createReport(payload) {
    return apiClient.post('/reports', payload)
  },

  /**
   * Mark report as reviewing.
   * @param {string} id
   */
  reviewReport(id) {
    return apiClient.patch(`/reports/${id}/review`)
  },

  /**
   * Resolve a report with action.
   * @param {string} id
   * @param {{ action_taken, action_note? }} payload
   */
  resolveReport(id, payload) {
    return apiClient.patch(`/reports/${id}/resolve`, payload)
  },

  /**
   * Dismiss a report.
   * @param {string} id
   * @param {{ action_taken?, action_note? }} payload
   */
  dismissReport(id, payload = {}) {
    return apiClient.patch(`/reports/${id}/dismiss`, payload)
  },

  // ── Complaints ────────────────────────────────────────────

  /**
   * List complaints.
   * @param {{ status?, per_page?, page? }} params
   */
  getComplaints(params = {}) {
    return apiClient.get('/complaints', { params })
  },

  /**
   * Get single complaint detail.
   * @param {string} id
   */
  getComplaint(id) {
    return apiClient.get(`/complaints/${id}`)
  },

  /**
   * Create a complaint for a booking.
   * @param {{ booking_id, content }} payload
   */
  createComplaint(payload) {
    return apiClient.post('/complaints', payload)
  },

  /**
   * Resolve a complaint (admin/moderator).
   * @param {string} id
   * @param {{ resolve_note }} payload
   */
  resolveComplaint(id, payload) {
    return apiClient.patch(`/complaints/${id}/resolve`, payload)
  },
}

export default reviewApi
