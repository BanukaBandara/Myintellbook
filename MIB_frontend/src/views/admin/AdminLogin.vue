<template>
  <div class="admin-login-wrapper">
    <div class="admin-login-card">
      <div class="card-header">
        <div class="shield-badge">
          <i class="pi pi-shield"></i>
        </div>
        <h1 class="portal-heading">MyIntelliBook</h1>
        <p class="portal-subheading">SUPER ADMINISTRATOR PORTAL</p>
      </div>

      <div v-if="errorMessage" class="error-banner">
        <i class="pi pi-exclamation-circle"></i>
        <span>{{ errorMessage }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="login-form">
        <div class="form-group">
          <label for="admin-email">Admin Email</label>
          <div class="input-with-icon">
            <i class="pi pi-envelope input-icon"></i>
            <input
              id="admin-email"
              v-model="email"
              type="email"
              required
              autocomplete="email"
              placeholder="admin@myintellibook.com"
              :disabled="isLoading"
            />
          </div>
        </div>

        <div class="form-group">
          <label for="admin-password">Password</label>
          <div class="input-with-icon">
            <i class="pi pi-lock input-icon"></i>
            <input
              id="admin-password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="current-password"
              placeholder="••••••••••••"
              :disabled="isLoading"
            />
            <button
              type="button"
              class="eye-btn"
              @click="showPassword = !showPassword"
              tabindex="-1"
            >
              <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
            </button>
          </div>
        </div>

        <button
          type="submit"
          class="submit-button"
          :disabled="isLoading"
        >
          <i v-if="isLoading" class="pi pi-spin pi-spinner"></i>
          <span>{{ isLoading ? 'Authenticating...' : 'Sign In to Admin Portal' }}</span>
        </button>
      </form>

      <div class="card-footer">
        <p class="security-notice">
          <i class="pi pi-lock"></i>
          Restricted access. All access attempts are monitored and recorded.
        </p>
        <router-link to="/login" class="back-link">
          ← Return to standard consumer login
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { adminAuth } from '@/services/adminAuth';

const router = useRouter();

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');

const handleSubmit = async () => {
  errorMessage.value = '';
  isLoading.value = true;

  try {
    const result = await adminAuth.login(email.value, password.value);

    if (result.status && result.token) {
      router.push('/admin/dashboard');
    } else {
      errorMessage.value = result.message || 'Authentication failed. Please check credentials.';
    }
  } catch (err: any) {
    errorMessage.value = err?.message || 'An error occurred during authentication.';
  } finally {
    isLoading.value = false;
  }
};
</script>

<style scoped>
.admin-login-wrapper {
  min-height: 100vh;
  width: 100vw;
  background: radial-gradient(circle at 50% 20%, #1e293b 0%, #0f172a 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.admin-login-card {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border-radius: 16px;
  padding: 36px 32px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.card-header {
  text-align: center;
  margin-bottom: 28px;
}

.shield-badge {
  width: 52px;
  height: 52px;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  color: #60a5fa;
  border-radius: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-bottom: 16px;
  box-shadow: 0 8px 16px rgba(15, 23, 42, 0.2);
}

.portal-heading {
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.02em;
}

.portal-subheading {
  font-size: 0.72rem;
  font-weight: 700;
  color: #2563eb;
  letter-spacing: 0.12em;
  margin-top: 6px;
  margin-bottom: 0;
}

.error-banner {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}

.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  font-size: 0.95rem;
  pointer-events: none;
}

.input-with-icon input {
  width: 100%;
  padding: 10px 12px 10px 38px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #0f172a;
  background: #f8fafc;
  transition: all 150ms ease;
}

.input-with-icon input:focus {
  outline: none;
  border-color: #2563eb;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.eye-btn {
  position: absolute;
  right: 10px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px;
  font-size: 0.95rem;
}

.eye-btn:hover {
  color: #475569;
}

.submit-button {
  width: 100%;
  padding: 12px;
  background: #0f172a;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.92rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 6px;
  transition: all 160ms ease;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
}

.submit-button:hover:not(:disabled) {
  background: #1e293b;
  transform: translateY(-1px);
}

.submit-button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.card-footer {
  margin-top: 26px;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.security-notice {
  font-size: 0.72rem;
  color: #64748b;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.back-link {
  font-size: 0.8rem;
  color: #475569;
  text-decoration: none;
  font-weight: 500;
  transition: color 150ms ease;
}

.back-link:hover {
  color: #2563eb;
}
</style>
