import apiClient from './apiClient'

export const rbacApi = {
  // ── Roles ─────────────────────────────────────────────────

  /**
   * List roles with optional search.
   * @param {{ search?, per_page?, page? }} params
   */
  getRoles(params = {}) {
    return apiClient.get('/roles', { params })
  },

  /**
   * Create a new role.
   * @param {{ name, display_name?, description?, is_system? }} payload
   */
  createRole(payload) {
    return apiClient.post('/roles', payload)
  },

  /**
   * Update role.
   * @param {string} id
   * @param {{ name?, display_name?, description? }} payload
   */
  updateRole(id, payload) {
    return apiClient.put(`/roles/${id}`, payload)
  },

  /**
   * Delete role.
   * @param {string} id
   */
  deleteRole(id) {
    return apiClient.delete(`/roles/${id}`)
  },

  /**
   * Sync permissions to a role.
   * @param {string} roleId
   * @param {{ permission_ids?, permission_codes? }} payload
   */
  syncRolePermissions(roleId, payload) {
    return apiClient.post(`/roles/${roleId}/permissions/sync`, payload)
  },

  // ── Permissions ───────────────────────────────────────────

  /**
   * List all permissions.
   * @param {{ group_name?, search?, per_page?, page? }} params
   */
  getPermissions(params = {}) {
    return apiClient.get('/permissions', { params })
  },

  /**
   * Get single permission.
   * @param {number} id
   */
  getPermission(id) {
    return apiClient.get(`/permissions/${id}`)
  },

  /**
   * Create permission.
   * @param {{ code, name, group_name }} payload
   */
  createPermission(payload) {
    return apiClient.post('/permissions', payload)
  },

  /**
   * Update permission.
   * @param {number} id
   * @param {{ code?, name?, group_name? }} payload
   */
  updatePermission(id, payload) {
    return apiClient.put(`/permissions/${id}`, payload)
  },

  /**
   * Delete permission.
   * @param {number} id
   */
  deletePermission(id) {
    return apiClient.delete(`/permissions/${id}`)
  },

  // ── User Roles & Revokes ──────────────────────────────────

  /**
   * Sync roles for a user.
   * @param {string} userId
   * @param {{ roles: Array<{ role_id?, name?, scope_type?, scope_id? }> }} payload
   */
  syncUserRoles(userId, payload) {
    return apiClient.post(`/users/${userId}/roles/sync`, payload)
  },

  /**
   * Revoke a specific permission from a user.
   * @param {string} userId
   * @param {{ permission_id?, permission_code?, scope_type?, scope_id?, reason? }} payload
   */
  revokeUserPermission(userId, payload) {
    return apiClient.post(`/users/${userId}/permissions/revoke`, payload)
  },

  /**
   * Remove a permission revoke from a user.
   * @param {string} userId
   * @param {number} permissionId
   * @param {{ scope_type?, scope_id? }} params
   */
  removeUserPermissionRevoke(userId, permissionId, params = {}) {
    return apiClient.delete(`/users/${userId}/permissions/revoke/${permissionId}`, { params })
  },
}

export default rbacApi
