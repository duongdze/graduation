import apiClient from './apiClient'

export const venueApi = {
  // ── Venue Clusters ────────────────────────────────────────

  /**
   * List venue clusters with optional filters.
   * @param {{ status?, city?, owner_id?, per_page?, page? }} params
   */
  getVenueClusters(params = {}) {
    return apiClient.get('/venue-clusters', { params })
  },

  /**
   * Get venue cluster detail with courts, pricing, media.
   * @param {string} id - UUID
   */
  getVenueCluster(id) {
    return apiClient.get(`/venue-clusters/${id}`)
  },

  /**
   * Create venue cluster.
   * owner_id auto-assigned to current user if omitted.
   * @param {{ name, slug, address, description?, phone_contact?, ward?, district?, city?, latitude?, longitude?, amenities?, status? }} payload
   */
  createVenueCluster(payload) {
    return apiClient.post('/venue-clusters', payload)
  },

  /**
   * Update venue cluster.
   * @param {string} id
   * @param {object} payload
   */
  updateVenueCluster(id, payload) {
    return apiClient.put(`/venue-clusters/${id}`, payload)
  },

  /**
   * Delete venue cluster (soft delete).
   * @param {string} id
   */
  deleteVenueCluster(id) {
    return apiClient.delete(`/venue-clusters/${id}`)
  },

  /**
   * Approve a pending venue cluster (admin).
   * @param {string} id
   */
  approveVenueCluster(id) {
    return apiClient.patch(`/venue-clusters/${id}/approve`)
  },

  /**
   * Reject a pending venue cluster (admin).
   * @param {string} id
   * @param {{ reject_reason }} payload
   */
  rejectVenueCluster(id, payload) {
    return apiClient.patch(`/venue-clusters/${id}/reject`, payload)
  },

  // ── Venue Courts ──────────────────────────────────────────

  /**
   * List venue courts with optional filters.
   * @param {{ cluster_id?, court_type_id?, status?, per_page?, page? }} params
   */
  getVenueCourts(params = {}) {
    return apiClient.get('/venue-courts', { params })
  },

  /**
   * Get venue court detail.
   * @param {string} id - UUID
   */
  getVenueCourt(id) {
    return apiClient.get(`/venue-courts/${id}`)
  },

  /**
   * Create venue court.
   * @param {{ cluster_id, court_type_id, name, status?, sort_order?, description? }} payload
   */
  createVenueCourt(payload) {
    return apiClient.post('/venue-courts', payload)
  },

  /**
   * Update venue court.
   * @param {string} id
   * @param {object} payload
   */
  updateVenueCourt(id, payload) {
    return apiClient.put(`/venue-courts/${id}`, payload)
  },

  /**
   * Delete venue court.
   * @param {string} id
   */
  deleteVenueCourt(id) {
    return apiClient.delete(`/venue-courts/${id}`)
  },

  // ── Court Types ───────────────────────────────────────────

  /**
   * List court types.
   * @param {{ is_active?, per_page?, page? }} params
   */
  getCourtTypes(params = {}) {
    return apiClient.get('/court-types', { params })
  },

  /**
   * Get court type detail.
   * @param {number} id
   */
  getCourtType(id) {
    return apiClient.get(`/court-types/${id}`)
  },

  /**
   * Create court type.
   * @param {{ name, description?, player_count?, is_active? }} payload
   */
  createCourtType(payload) {
    return apiClient.post('/court-types', payload)
  },

  /**
   * Update court type.
   * @param {number} id
   * @param {object} payload
   */
  updateCourtType(id, payload) {
    return apiClient.put(`/court-types/${id}`, payload)
  },

  /**
   * Delete court type.
   * @param {number} id
   */
  deleteCourtType(id) {
    return apiClient.delete(`/court-types/${id}`)
  },

  // ── Media ─────────────────────────────────────────────────

  /**
   * Upload a media file (polymorphic).
   * @param {FormData} formData - must contain: file, mediable_type, mediable_id, collection?, sort_order?
   */
  uploadMedia(formData) {
    return apiClient.post('/media/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  /**
   * Delete a media record + file.
   * @param {string} id - UUID
   */
  deleteMedia(id) {
    return apiClient.delete(`/media/${id}`)
  },
}

export default venueApi
