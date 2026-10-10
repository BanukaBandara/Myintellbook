import axios from "axios";
import Swal from 'sweetalert2';
import { clearRequestCache } from '@/services/requestCache';

// Set per environment: .env.development points at the local Laravel server; production builds
// read VITE_API_BASE_URL from .env.production and fall back to the live API.
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://www.myintellibook.com/api';

const instance = axios.create({
    baseURL: API_BASE_URL,
    timeout: 30000, // 30 seconds: general info and other heavy endpoints can exceed 10s
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    }

    
  });

  instance.interceptors.request.use(
    config => {
      const requestUrl = config.url || '';
      const isAdminApi = requestUrl.startsWith('/admin') || requestUrl.includes('/admin/');
      const isAdminPortal = typeof window !== 'undefined' && window.location.pathname.startsWith('/admin');

      let token: string | null = null;

      if (isAdminApi || isAdminPortal) {
        token = localStorage.getItem('adminToken');
      } else {
        token = localStorage.getItem('userToken');
      }

      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    },
    error => {
      console.error("Request error:", error);
      return Promise.reject(error);
    }
  );

// Some of this API's writes are GETs (e.g. /delete-experiance/{id}, /log-out).
const MUTATING_GET = /(delete|remove|log-?out|follow|accept|reject|cancel|mark)/i;

// Sensitive actions answer 403 + requires_email_verification for unverified accounts.
// Offer to send a fresh link once, instead of each page showing a dead-end error.
let verificationPromptOpen = false;
async function promptEmailVerification(message: string): Promise<void> {
    if (verificationPromptOpen) return;
    verificationPromptOpen = true;
    try {
        const choice = await Swal.fire({
            icon: 'info',
            title: 'Verify your email',
            text: message,
            showCancelButton: true,
            confirmButtonText: 'Send verification email',
            cancelButtonText: 'Not now',
        });
        if (!choice.isConfirmed) return;

        const { data } = await instance.post('/email/verification-notification');
        await Swal.fire({ icon: 'success', title: 'Email sent', text: data?.message ?? 'Check your inbox.' });
    } catch (error) {
        const text = axios.isAxiosError(error)
            ? (error.response?.data?.message ?? 'Please try again later.')
            : 'Please try again later.';
        await Swal.fire({ icon: 'error', title: "Couldn't send the email", text });
    } finally {
        verificationPromptOpen = false;
    }
}

instance.interceptors.response.use(
    response => {
        // Any successful write can change cached profile data, so drop the request cache.
        const method = (response.config.method ?? 'get').toLowerCase();
        if (method !== 'get' || MUTATING_GET.test(response.config.url ?? '')) {
            clearRequestCache();
        }
        return response;
    },
    error => {
        const data = axios.isAxiosError(error) ? error.response?.data : undefined;
        if (data?.requires_email_verification) {
            void promptEmailVerification(data.message ?? 'Please verify your email address to use this feature.');
        }
        // Callers still receive the error and keep their own handling.
        return Promise.reject(error);
    }
);

  export default instance;
