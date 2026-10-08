import axios from "axios";

const instance = axios.create({
    baseURL: 'http://127.0.0.1:8000/api',
    timeout: 10000, // 10 seconds timeout
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

  export default instance;
