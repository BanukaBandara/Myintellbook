import axios from "axios";
import { clearRequestCache } from '@/services/requestCache';

const instance = axios.create({
    baseURL: 'http://127.0.0.1:8000/api',
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

// Any successful write can change cached profile data, so drop the request cache.
instance.interceptors.response.use(response => {
    const method = (response.config.method ?? 'get').toLowerCase();
    if (method !== 'get' || MUTATING_GET.test(response.config.url ?? '')) {
        clearRequestCache();
    }
    return response;
});

  export default instance;
