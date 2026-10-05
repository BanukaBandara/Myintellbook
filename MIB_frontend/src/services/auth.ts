import instance from '@/assets/axios';

export type AuthUser = {
    id: number;
    email: string;
    is_admin?: boolean;
    is_profile_completed?: boolean;
    profile?: Record<string, unknown> | null;
};

export async function fetchAuthUser(): Promise<AuthUser | null> {
    try {
        const response = await instance.get('/user');
        return response.status === 200 && response.data?.data ? response.data.data : null;
    } catch (error) {
        return null;
    }
}

export async function checkAuth() {
    try {
      const response = await instance.get('/user');
      return response.status;
    } catch (error) {
      return false;
    }
  }

export function isProfileCompleted(user: Partial<AuthUser> | null | undefined): boolean {
    return user?.is_profile_completed === true || (user?.profile != null && user?.is_profile_completed !== false);
}

/** Where to send a user right after login: onboarding only when no profile exists yet. */
export function routeAfterLogin(user: Partial<AuthUser> | null | undefined): string {
    return isProfileCompleted(user) ? '/home' : '/basicDetails-fill';
}
