<template>
  <aside class="admin-sidebar" :class="{ 'is-collapsed': isCollapsed }">
    <div class="sidebar-header">
      <div class="brand-lockup">
        <div class="brand-icon">
          <i class="pi pi-shield"></i>
        </div>
        <div v-if="!isCollapsed" class="brand-info">
          <span class="brand-title">MyIntelliBook</span>
          <span class="brand-badge">ADMIN PORTAL</span>
        </div>
      </div>
      <button
        type="button"
        class="collapse-toggle"
        :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="emit('toggle-collapse')"
      >
        <i :class="isCollapsed ? 'pi pi-chevron-right' : 'pi pi-chevron-left'"></i>
      </button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title" v-if="!isCollapsed">SYSTEM MANAGEMENT</div>

      <router-link
        to="/admin/dashboard"
        class="nav-link"
        :class="{ active: currentPath === '/admin/dashboard' }"
      >
        <i class="pi pi-th-large nav-icon"></i>
        <span v-if="!isCollapsed" class="nav-text">Dashboard</span>
      </router-link>

      <router-link
        to="/admin/tribunal/jury-panels"
        class="nav-link"
        :class="{ active: currentPath.startsWith('/admin/tribunal/jury-panels') }"
      >
        <i class="pi pi-users nav-icon"></i>
        <span v-if="!isCollapsed" class="nav-text">Jury Panels</span>
      </router-link>

      <router-link
        to="/admin/professional-verifications"
        class="nav-link"
        :class="{ active: currentPath.startsWith('/admin/professional-verifications') }"
      >
        <i class="pi pi-verified nav-icon"></i>
        <span v-if="!isCollapsed" class="nav-text">Professional Verifications</span>
      </router-link>

      <router-link
        to="/admin/internal-reports"
        class="nav-link"
        :class="{ active: currentPath.startsWith('/admin/internal-reports') }"
      >
        <i class="pi pi-exclamation-triangle nav-icon"></i>
        <span v-if="!isCollapsed" class="nav-text">Internal Reports</span>
      </router-link>
    </nav>

    <div class="sidebar-footer">
      <div v-if="!isCollapsed" class="admin-profile-pill">
        <div class="profile-avatar">
          <i class="pi pi-user"></i>
        </div>
        <div class="profile-details">
          <span class="profile-name">{{ adminName }}</span>
          <span class="profile-role">Super Administrator</span>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { adminAuth } from '@/services/adminAuth';

defineProps<{
  isCollapsed: boolean;
}>();

const emit = defineEmits<{
  (e: 'toggle-collapse'): void;
}>();

const route = useRoute();
const currentPath = computed(() => route.path);

const adminUser = computed(() => adminAuth.getStoredAdmin());
const adminName = computed(() => adminUser.value?.name || adminUser.value?.email || 'Administrator');
</script>

<style scoped>
.admin-sidebar {
  width: 260px;
  min-width: 260px;
  height: 100vh;
  background: #0f172a;
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  transition: width 220ms ease, min-width 220ms ease;
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  position: relative;
  z-index: 40;
}

.admin-sidebar.is-collapsed {
  width: 72px;
  min-width: 72px;
}

.sidebar-header {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.brand-lockup {
  display: flex;
  align-items: center;
  gap: 12px;
  overflow: hidden;
}

.brand-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.15rem;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
}

.brand-info {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
  white-space: nowrap;
}

.brand-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: #f8fafc;
  letter-spacing: -0.01em;
}

.brand-badge {
  font-size: 0.65rem;
  font-weight: 700;
  color: #93c5fd;
  letter-spacing: 0.08em;
  margin-top: 2px;
}

.collapse-toggle {
  background: transparent;
  border: none;
  color: #94a3b8;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 150ms ease;
}

.collapse-toggle:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #f8fafc;
}

.sidebar-nav {
  flex: 1;
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  overflow-y: auto;
}

.nav-section-title {
  font-size: 0.68rem;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.06em;
  padding: 8px 12px 4px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 8px;
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 500;
  transition: all 160ms ease;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.06);
  color: #f8fafc;
}

.nav-link.active {
  background: #1e293b;
  color: #60a5fa;
  box-shadow: inset 3px 0 0 #3b82f6;
  font-weight: 600;
}

.nav-icon {
  font-size: 1.05rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.sidebar-footer {
  padding: 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.admin-profile-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(255, 255, 255, 0.04);
  padding: 8px 10px;
  border-radius: 8px;
  overflow: hidden;
}

.profile-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.profile-details {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  line-height: 1.2;
}

.profile-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: #f1f5f9;
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
}

.profile-role {
  font-size: 0.7rem;
  color: #64748b;
}
</style>
