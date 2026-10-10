import instance from '@/assets/axios';

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
}

export interface AdminLoginResponse {
  code: number;
  status: boolean;
  token?: string;
  user?: AdminUser;
  message: string;
}

export const adminAuth = {
  async login(email: string, password: string): Promise<AdminLoginResponse> {
    try {
      const response = await instance.post<AdminLoginResponse>('/admin/login', {
        email,
        password,
      });

      if (response.data.status && response.data.token) {
        // Dedicated admin authentication session
        localStorage.setItem('adminToken', response.data.token);
        if (response.data.user) {
          localStorage.setItem('adminUser', JSON.stringify(response.data.user));
        }
      }

      return response.data;
    } catch (error: any) {
      if (error?.response?.data) {
        return error.response.data;
      }
      return {
        code: 500,
        status: false,
        message: 'Admin authentication request failed.',
      };
    }
  },

  async me(): Promise<AdminUser | null> {
    try {
      const response = await instance.get('/admin/me');
      if (response.data.status && response.data.data) {
        localStorage.setItem('adminUser', JSON.stringify(response.data.data));
        return response.data.data;
      }
      return null;
    } catch {
      return null;
    }
  },

  async logout(): Promise<void> {
    try {
      await instance.post('/admin/logout');
    } catch {
      // Ignore network errors on logout
    } finally {
      localStorage.removeItem('adminToken');
      localStorage.removeItem('adminUser');
    }
  },

  isAuthenticated(): boolean {
    const token = localStorage.getItem('adminToken');
    if (!token) return false;
    try {
      const user = JSON.parse(localStorage.getItem('adminUser') || '{}');
      return Boolean(user?.is_admin);
    } catch {
      return false;
    }
  },

  getStoredAdmin(): AdminUser | null {
    try {
      const user = JSON.parse(localStorage.getItem('adminUser') || '{}');
      return user?.is_admin ? user : null;
    } catch {
      return null;
    }
  },
};
