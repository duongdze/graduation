import apiClient from './apiClient'

export const bookingApi = {
  /**
   * List bookings with optional filters.
   * Non-admin users see only their own bookings.
   * @param {{ status?, court_id?, cluster_id?, customer_id?, date?, per_page?, page? }} params
   */
  getBookings(params = {}) {
    return apiClient.get('/bookings', { params })
  },

  /**
   * Get single booking detail.
   * @param {string} id - UUID
   */
  getBooking(id) {
    return apiClient.get(`/bookings/${id}`)
  },

  /**
   * Create a new booking.
   * Price is auto-resolved from PriceSlot/HolidayPrice if not provided.
   * @param {{ court_id, booking_date, start_time, end_time, customer_id?, source?, base_price?, total_price?, walk_in_name?, walk_in_phone?, note? }} payload
   */
  createBooking(payload) {
    return apiClient.post('/bookings', payload)
  },

  /**
   * Cancel a booking.
   * @param {string} id
   * @param {{ cancel_reason? }} payload
   */
  cancelBooking(id, payload = {}) {
    return apiClient.patch(`/bookings/${id}/cancel`, payload)
  },

  /**
   * Confirm a pending_payment booking → paid.
   * @param {string} id
   */
  confirmBooking(id) {
    return apiClient.patch(`/bookings/${id}/confirm`)
  },

  /**
   * Check-in a paid booking.
   * @param {string} id
   */
  checkInBooking(id) {
    return apiClient.patch(`/bookings/${id}/check-in`)
  },

  /**
   * Complete a booking.
   * @param {string} id
   */
  completeBooking(id) {
    return apiClient.patch(`/bookings/${id}/complete`)
  },
}

export default bookingApi
