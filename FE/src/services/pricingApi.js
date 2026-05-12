import apiClient from './apiClient'

export const pricingApi = {
  // ── Price Slots ───────────────────────────────────────────

  /**
   * List price slots with optional filters.
   * @param {{ cluster_id?, is_active?, per_page?, page? }} params
   */
  getPriceSlots(params = {}) {
    return apiClient.get('/price-slots', { params })
  },

  /**
   * Get single price slot.
   * @param {string} id - UUID
   */
  getPriceSlot(id) {
    return apiClient.get(`/price-slots/${id}`)
  },

  /**
   * Create price slot.
   * @param {{ cluster_id, start_time, end_time, price, apply_to_days?, is_active? }} payload
   */
  createPriceSlot(payload) {
    return apiClient.post('/price-slots', payload)
  },

  /**
   * Update price slot.
   * @param {string} id
   * @param {object} payload
   */
  updatePriceSlot(id, payload) {
    return apiClient.put(`/price-slots/${id}`, payload)
  },

  /**
   * Delete price slot.
   * @param {string} id
   */
  deletePriceSlot(id) {
    return apiClient.delete(`/price-slots/${id}`)
  },

  // ── Holiday Prices ────────────────────────────────────────

  /**
   * List holiday prices.
   * @param {{ cluster_id?, from?, to?, per_page?, page? }} params
   */
  getHolidayPrices(params = {}) {
    return apiClient.get('/holiday-prices', { params })
  },

  /**
   * Get single holiday price.
   * @param {string} id
   */
  getHolidayPrice(id) {
    return apiClient.get(`/holiday-prices/${id}`)
  },

  /**
   * Create or upsert holiday price.
   * @param {{ cluster_id, holiday_date, label?, price, description? }} payload
   */
  createHolidayPrice(payload) {
    return apiClient.post('/holiday-prices', payload)
  },

  /**
   * Update holiday price.
   * @param {string} id
   * @param {object} payload
   */
  updateHolidayPrice(id, payload) {
    return apiClient.put(`/holiday-prices/${id}`, payload)
  },

  /**
   * Delete holiday price.
   * @param {string} id
   */
  deleteHolidayPrice(id) {
    return apiClient.delete(`/holiday-prices/${id}`)
  },

  // ── Booking Config ────────────────────────────────────────

  /**
   * List booking configs.
   * @param {{ per_page?, page? }} params
   */
  getBookingConfigs(params = {}) {
    return apiClient.get('/booking-configs', { params })
  },

  /**
   * Update booking config for a cluster.
   * @param {string} id - cluster_id (used as PK)
   * @param {{ min_duration_minutes?, max_duration_minutes?, advance_booking_days?, cancel_before_minutes? }} payload
   */
  updateBookingConfig(id, payload) {
    return apiClient.put(`/booking-configs/${id}`, payload)
  },

  // ── Availability ──────────────────────────────────────────

  /**
   * Get available time slots for a court on a given date.
   * @param {string} courtId - venue court UUID
   * @param {{ date, duration_minutes? }} params
   */
  getAvailableSlots(courtId, params) {
    return apiClient.get(`/venue-courts/${courtId}/available-slots`, { params })
  },
}

export default pricingApi
