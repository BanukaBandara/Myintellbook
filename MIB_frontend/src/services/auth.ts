import instance from '@/assets/axios';

export async function checkAuth() {
  try {
    const response = await instance.get('/user');
    if (response.data?.data) {
      localStorage.setItem('userData', JSON.stringify(response.data.data));
    }
    return response.status;
  } catch (error) {
    return false;
  }
}

export function isJuryPanelUser(): boolean {
  try {
    const raw = localStorage.getItem('userData');
    if (!raw) return false;
    const u = JSON.parse(raw);
    return Boolean(u?.is_jury_panel);
  } catch {
    return false;
  }
}