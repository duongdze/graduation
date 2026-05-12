import apiClient from './apiClient'

export const auditApi = {
  /**
   * List audit logs with optional filters (admin only).
   * @param {{ actor_id?, entity_type?, action?, per_page?, page? }} params
   */
  getAuditLogs(params = {}) {
    return apiClient.get('/audit-logs', { params })
  },
}

export default auditApi
