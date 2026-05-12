import apiClient from './apiClient'

export const authApi = {
  /**
   * Register a new user account.
   * @param {{ full_name, email, phone?, password, password_confirmation, bio?, preferred_sports?, preferred_position? }} payload
   */
  register(payload) {
    return apiClient.post('/auth/register', payload)
  },

  /**
   * Login with email + password.
   * @param {{ email, password, device_name? }} payload
   */
  login(payload) {
    return apiClient.post('/auth/login', payload)
  },

  /**
   * Logout current session (revoke current token).
   */
  logout() {
    return apiClient.post('/auth/logout')
  },

  /**
   * Get authenticated user profile with roles & permissions.
   */
  getCurrentUser() {
    return apiClient.get('/auth/me')
  },

  /**
   * Update own profile fields.
   * @param {{ full_name?, phone?, bio?, preferred_sports?, preferred_position? }} payload
   */
  updateProfile(payload) {
    return apiClient.put('/profile', payload)
  },

  /**
   * Change own password.
   * @param {{ current_password, password, password_confirmation }} payload
   */
  updatePassword(payload) {
    return apiClient.put('/profile/password', payload)
  },
}

export default authApi
