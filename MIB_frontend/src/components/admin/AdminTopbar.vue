<template>
  <header class="admin-topbar">
    <div class="topbar-left">
      <h1 class="page-title">{{ currentTitle }}</h1>
    </div>

    <div class="topbar-right">
      <div class="portal-badge">
        <span class="status-dot"></span>
        <span>SUPER ADMIN</span>
      </div>

      <div class="user-action-group">
        <span class="admin-email">{{ adminEmail }}</span>
        <button
          type="button"
          class="signout-button"
          title="Sign out of Admin Portal"
          @click="handleLogout"
        >
          <i class="pi pi-sign-out"></i>
          <span>Logout</span>
        </button>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { adminAuth } from '@/services/adminAuth';

const route = useRoute();
const router = useRouter();

const currentTitle = computed(() => (route.meta.title as string) || 'Admin Portal');
const adminUser = computed(() => adminAuth.getStoredAdmin());
const adminEmail = computed(() => adminUser.value?.email || 'admin@myintellibook.com');

const handleLogout = async () => {
  await adminAuth.logout();
  router.push('/admin/login');
};
</script>

<style scoped>
.admin-topbar {
  height: 64px;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 28px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  z-index: 30;
}

.topbar-left {
  display: flex;
  align-items: center;
}

.page-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.01em;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.portal-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  font-size: 0.72rem;
  font-weight: 700;
  color: #334155;
  letter-spacing: 0.05em;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
}

.user-action-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.admin-email {
  font-size: 0.82rem;
  color: #64748b;
  font-weight: 500;
}

.signout-button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.signout-button:hover {
  background: #fee2e2;
  color: #b91c1c;
}
</style>
