<template>
  <div class="admin-layout-shell">
    <AdminSidebar
      :is-collapsed="isSidebarCollapsed"
      @toggle-collapse="isSidebarCollapsed = !isSidebarCollapsed"
    />

    <div class="admin-main-container">
      <AdminTopbar />

      <main class="admin-page-content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';

const isSidebarCollapsed = ref(false);
</script>

<style scoped>
.admin-layout-shell {
  display: flex;
  width: 100vw;
  height: 100vh;
  height: 100dvh;
  background: #f8fafc;
  overflow: hidden;
  font-family: inherit;
}

.admin-main-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  height: 100%;
  overflow: hidden;
}

.admin-page-content {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 24px 28px;
  background: #f8fafc;
}
</style>
