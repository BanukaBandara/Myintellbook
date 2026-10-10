import axios from 'axios';
import instance from '@/assets/axios';
import { cached } from '@/services/requestCache';

export type AuthUser = {
    id: number;
    email: string;
    is_admin?: boolean;
    is_jury_panel?: boolean;
    is_profile_completed?: boolean;
    profile?: Record<string, unknown> | null;
};

/**
 * 'unauthenticated' only when there is no token or the server answers 401.
 * 'unknown' covers timeouts, network drops and 5xx: the session may well be valid,
 * so callers must NOT redirect or log out on it.
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
        // Shared and cached briefly: the router guard and the front page may request
        // the authenticated user during the same navigation.
        const response = await cached(
            'auth-user',
            60_000,
            () => instance.get('/user'),
            res => (res as { status?: number })?.status === 200
        );

        const user = response.data?.data as AuthUser | undefined;

        if (user) {
            // Keep local user data synchronized because Jury Panel routing and other
            // existing consumers read this value from localStorage.
            localStorage.setItem('userData', JSON.stringify(user));

            return {
                status: 'authenticated',
                user,
            };
        }

        return {
            status: 'unknown',
            error: new Error('Unexpected /user response'),
        };
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 401) {
            return { status: 'unauthenticated' };
        }

        return {
            status: 'unknown',
            error,
        };
    }
}

/** Kept for existing callers: the HTTP status when authenticated, otherwise false. */
export async function checkAuth(): Promise<number | false> {
    const state = await getAuthState();
    return state.status === 'authenticated' ? 200 : false;
}

export function isJuryPanelUser(): boolean {
    try {
        const raw = localStorage.getItem('userData');
        if (!raw) return false;

        const user = JSON.parse(raw);
        return Boolean(user?.is_jury_panel);
    } catch {
        return false;
    }
}

export function isProfileCompleted(
    user: Partial<AuthUser> | null | undefined
): boolean {
    return (
        user?.is_profile_completed === true ||
        (user?.profile != null && user?.is_profile_completed !== false)
    );
}

/** Where to send a user right after login: onboarding only when no profile exists yet. */
export function routeAfterLogin(
    user: Partial<AuthUser> | null | undefined
): string {
    if (user?.is_jury_panel) {
        return '/jury';
    }

    return isProfileCompleted(user) ? '/home' : '/basicDetails-fill';
}
