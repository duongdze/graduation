import apiClient from './apiClient'

export const chatApi = {
  // ── Conversations ─────────────────────────────────────────

  /**
   * List conversations for current user.
   * @param {{ per_page?, page? }} params
   */
  getConversations(params = {}) {
    return apiClient.get('/conversations', { params })
  },

  /**
   * Create a new conversation (direct or group).
   * @param {{ type?, reference_type?, reference_id?, title?, participant_ids }} payload
   */
  createConversation(payload) {
    return apiClient.post('/conversations', payload)
  },

  // ── Messages ──────────────────────────────────────────────

  /**
   * List messages in a conversation (newest first).
   * @param {string} conversationId - UUID
   * @param {{ per_page?, page? }} params
   */
  getMessages(conversationId, params = {}) {
    return apiClient.get(`/conversations/${conversationId}/messages`, { params })
  },

  /**
   * Send a message in a conversation.
   * @param {string} conversationId
   * @param {{ content }} payload
   */
  sendMessage(conversationId, payload) {
    return apiClient.post(`/conversations/${conversationId}/messages`, payload)
  },

  /**
   * Mark conversation as read (update last_read_at).
   * @param {string} conversationId
   */
  markConversationRead(conversationId) {
    return apiClient.post(`/conversations/${conversationId}/read`)
  },
}

export default chatApi
