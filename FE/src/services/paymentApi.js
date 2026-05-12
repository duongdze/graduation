import apiClient from './apiClient'

export const paymentApi = {
  // ── Payments ──────────────────────────────────────────────

  /**
   * List payments with optional filters.
   * @param {{ status?, booking_id?, per_page?, page? }} params
   */
  getPayments(params = {}) {
    return apiClient.get('/payments', { params })
  },

  /**
   * Get single payment detail.
   * @param {string} id - UUID
   */
  getPayment(id) {
    return apiClient.get(`/payments/${id}`)
  },

  /**
   * Create a payment record for a booking.
   * @param {{ booking_id, amount, method, gateway_txn_id?, gateway_response? }} payload
   */
  createPayment(payload) {
    return apiClient.post('/payments', payload)
  },

  /**
   * Mark payment as paid (admin/staff).
   * @param {string} id
   */
  markPaymentPaid(id) {
    return apiClient.patch(`/payments/${id}/mark-paid`)
  },

  /**
   * Mark payment as failed.
   * @param {string} id
   */
  markPaymentFailed(id) {
    return apiClient.patch(`/payments/${id}/mark-failed`)
  },

  // ── Refunds ───────────────────────────────────────────────

  /**
   * List refunds.
   * @param {{ status?, booking_id?, per_page?, page? }} params
   */
  getRefunds(params = {}) {
    return apiClient.get('/refunds', { params })
  },

  /**
   * Get single refund detail.
   * @param {string} id
   */
  getRefund(id) {
    return apiClient.get(`/refunds/${id}`)
  },

  /**
   * Create refund request.
   * @param {{ booking_id, payment_id, amount, reason? }} payload
   */
  createRefund(payload) {
    return apiClient.post('/refunds', payload)
  },

  /**
   * Approve a pending refund.
   * @param {string} id
   */
  approveRefund(id) {
    return apiClient.patch(`/refunds/${id}/approve`)
  },

  /**
   * Reject a pending refund.
   * @param {string} id
   */
  rejectRefund(id) {
    return apiClient.patch(`/refunds/${id}/reject`)
  },

  // ── Finance ───────────────────────────────────────────────

  /**
   * List venue fee ledgers.
   * @param {{ cluster_id?, status?, per_page?, page? }} params
   */
  getVenueFeeLedgers(params = {}) {
    return apiClient.get('/venue-fee-ledgers', { params })
  },

  /**
   * List platform fee configs.
   * @param {{ per_page?, page? }} params
   */
  getPlatformFeeConfigs(params = {}) {
    return apiClient.get('/platform-fee-configs', { params })
  },

  /**
   * Create a new platform fee config.
   * @param {{ fee_percent, description?, effective_from? }} payload
   */
  createPlatformFeeConfig(payload) {
    return apiClient.post('/platform-fee-configs', payload)
  },
}

export default paymentApi
