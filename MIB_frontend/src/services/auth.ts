import axios from 'axios';
import instance from '@/assets/axios';
import { cached } from '@/services/requestCache';

export type AuthUser = {
    id: number;
    email: string;
    is_admin?: boolean;
    is_profile_completed?: boolean;
    profile?: Record<string, unknown> | null;
};

/**
 * 'unauthenticated' only when there is no token or the server answers 401.
 * 'unknown' covers timeouts, network drops and 5xx: the session may well be valid,
 * so callers must NOT redirect or log out on it (that is what caused the redirect loop).
 */
export type AuthState =
    | { status: 'authenticated'; user: AuthUser }
    | { status: 'unauthenticated' }
    | { status: 'unknown'; error: unknown };

const SESSION_KEYS = ['userToken', 'userData'];

export function clearSession(): void {
    SESSION_KEYS.forEach(key => localStorage.removeItem(key));
}

export async function getAuthState(): Promise<AuthState> {
    if (!localStorage.getItem('userToken')) {
        return { status: 'unauthenticated' };
    }

    try {
        // Shared and cached briefly: the router guard and the front page ask on the same navigation,
        // and every page switch would otherwise wait on another /user round trip.
        const response = await cached('auth-user', 60_000, () => instance.get('/user'),
            res => (res as { status?: number })?.status === 200);
        const user = response.data?.data;
        return user
            ? { status: 'authenticated', user }
            : { status: 'unknown', error: new Error('Unexpected /user response') };
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 401) {
            return { status: 'unauthenticated' };
        }
        return { status: 'unknown', error };
    }
}

/** Kept for existing callers: the HTTP status when authenticated, otherwise false. */
export async function checkAuth(): Promise<number | false> {
    const state = await getAuthState();
    return state.status === 'authenticated' ? 200 : false;
}

export function isProfileCompleted(user: Partial<AuthUser> | null | undefined): boolean {
    return user?.is_profile_completed === true || (user?.profile != null && user?.is_profile_completed !== false);
}

/** Where to send a user right after login: onboarding only when no profile exists yet. */
export function routeAfterLogin(user: Partial<AuthUser> | null | undefined): string {
    return isProfileCompleted(user) ? '/home' : '/basicDetails-fill';
}
