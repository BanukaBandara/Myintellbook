<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { useJuryPanelStore } from '@/stores/juryPanel';

const router = useRouter();
const route = useRoute();
const juryStore = useJuryPanelStore();

const initialLoading = ref(true);

onMounted(async () => {
  try {
    await juryStore.fetchMe();
  } catch (err: any) {
    // If not a jury panel account, redirect to home
    if (err?.response?.status === 403 && !juryStore.blockedStatus) {
      Swal.fire({
        icon: 'error',
        title: 'Unauthorized',
        text: 'Access restricted to dedicated Tribunal Jury Panel accounts.',
      });
      router.push('/home');
    }
  } finally {
    initialLoading.value = false;
  }
});

const handleLogout = async () => {
  const result = await Swal.fire({
    title: 'Sign Out?',
    text: 'Are you sure you want to sign out of the Jury Panel Portal?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#a03829',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Sign Out',
  });

  if (result.isConfirmed) {
    await juryStore.logout();
    router.push('/login');
  }
};
</script>

<template>
  <div class="jury-layout d-flex min-vh-100 bg-light">
    <!-- Dedicated Jury Panel Sidebar -->
    <aside class="jury-sidebar d-flex flex-column text-white shadow-sm">
      <!-- Portal Brand -->
      <div class="p-4 border-bottom border-secondary border-opacity-25">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-bank2 fs-3 text-warning" />
          <span class="fw-bold tracking-wider fs-6 text-uppercase">Myintellibook</span>
        </div>
        <div class="small fw-semibold text-warning text-uppercase letter-spacing-1">
          Tribunal Jury Portal
        </div>
      </div>

      <!-- Panel Identity Badge -->
      <div class="p-3 mx-3 my-3 bg-secondary bg-opacity-25 rounded-3 border border-secondary border-opacity-25">
        <div class="text-uppercase small text-white-50 fw-semibold" style="font-size: 0.7rem;">Active Panel</div>
        <div class="fw-bold text-white text-truncate mt-1" :title="juryStore.panelName || 'Loading...'">
          {{ juryStore.panelName || 'Jury Panel' }}
        </div>
        <div class="d-flex align-items-center justify-content-between mt-2">
          <span class="badge bg-dark border border-secondary font-monospace px-2 py-1 text-warning">
            {{ juryStore.panelCode || 'JP-0000' }}
          </span>
          <span
            class="badge rounded-pill px-2 py-1 text-capitalize"
            :class="{
              'bg-success': juryStore.panelStatus === 'active',
              'bg-secondary': juryStore.panelStatus === 'inactive',
              'bg-danger': juryStore.panelStatus === 'suspended'
            }"
          >
            {{ juryStore.panelStatus || 'Active' }}
          </span>
        </div>
      </div>

      <!-- Navigation Links (Disabled when panel is blocked) -->
      <nav class="flex-grow-1 px-3 py-2">
        <ul class="nav nav-pills flex-column gap-2">
          <li class="nav-item">
            <router-link
              to="/jury"
              exact-active-class="active"
              class="nav-link text-white-50 d-flex align-items-center gap-3 py-2 px-3 rounded-3"
              :class="{ disabled: juryStore.isBlocked }"
            >
              <i class="bi bi-speedometer2 fs-5" />
              <span class="fw-medium">Dashboard</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link
              to="/jury/cases"
              active-class="active"
              class="nav-link text-white-50 d-flex align-items-center gap-3 py-2 px-3 rounded-3"
              :class="{ disabled: juryStore.isBlocked }"
            >
              <i class="bi bi-journal-text fs-5" />
              <span class="fw-medium">Assigned Cases</span>
            </router-link>
          </li>
        </ul>
      </nav>

      <!-- Sidebar Footer / Logout -->
      <div class="p-3 border-top border-secondary border-opacity-25">
        <button
          type="button"
          class="btn btn-outline-danger text-white w-100 d-flex align-items-center justify-content-center gap-2 rounded-3 py-2"
          @click="handleLogout"
        >
          <i class="bi bi-box-arrow-right" />
          <span>Sign Out</span>
        </button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="jury-main flex-grow-1 d-flex flex-column">
      <!-- Top Bar -->
      <header class="jury-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between shadow-xs">
        <div class="d-flex align-items-center gap-3">
          <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-3 py-2 font-monospace fw-bold">
            {{ juryStore.panelCode || 'JURY PORTAL' }}
          </span>
          <span class="text-muted small d-none d-md-inline">
            Tribunal Jury Deliberation & Adjudication Environment
          </span>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="text-end d-none d-sm-block">
            <div class="fw-semibold small text-dark">{{ juryStore.panelName }}</div>
            <div class="text-muted" style="font-size: 0.75rem;">Dedicated Tribunal Account</div>
          </div>
          <button
            type="button"
            class="btn btn-light btn-sm border rounded-pill px-3"
            @click="handleLogout"
          >
            <i class="bi bi-box-arrow-right me-1 text-danger" />
            <span class="small">Sign Out</span>
          </button>
        </div>
      </header>

      <!-- Content Body -->
      <main class="jury-body p-4 flex-grow-1 overflow-auto">
        <!-- Initial Loading Spinner -->
        <div v-if="initialLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading Portal...</span>
          </div>
          <p class="text-muted mt-2">Loading Jury Panel Workspace...</p>
        </div>

        <!-- Inactive or Suspended Blocked Notice -->
        <div v-else-if="juryStore.isBlocked" class="container py-5">
          <div class="card border-0 shadow rounded-4 max-w-600 mx-auto text-center p-4">
            <div class="mb-3">
              <i
                v-if="juryStore.blockedStatus === 'suspended'"
                class="bi bi-slash-circle-fill text-danger display-3"
              />
              <i
                v-else
                class="bi bi-pause-circle-fill text-warning display-3"
              />
            </div>
            <h4 class="fw-bold text-dark mb-2">
              Jury Panel {{ juryStore.blockedStatus === 'suspended' ? 'Suspended' : 'Inactive' }}
            </h4>
            <p class="text-muted mb-4">
              {{ juryStore.blockedMessage || 'Your Jury Panel account is currently restricted. Please contact the Tribunal Super Administrator for assistance.' }}
            </p>
            <div class="d-flex justify-content-center gap-2">
              <button
                type="button"
                class="btn btn-outline-secondary rounded-pill px-4"
                @click="juryStore.fetchMe"
              >
                <i class="bi bi-arrow-clockwise me-1" />Retry
              </button>
              <button
                type="button"
                class="btn btn-primary rounded-pill px-4"
                @click="handleLogout"
              >
                Sign Out
              </button>
            </div>
          </div>
        </div>

        <!-- Router View for Active Jury Panel Pages -->
        <router-view v-else />
      </main>
    </div>
  </div>
</template>

<style scoped>
.jury-layout {
  min-height: 100vh;
}

.jury-sidebar {
  width: 270px;
  min-width: 270px;
  background-color: #1a1e24;
}

.jury-header {
  height: 64px;
}

.nav-link {
  transition: all 0.2s ease-in-out;
}

.nav-link:hover {
  background-color: rgba(255, 255, 255, 0.08);
  color: #fff !important;
}

.nav-link.active {
  background-color: #a03829 !important;
  color: #fff !important;
}

.nav-link.disabled {
  opacity: 0.4;
  pointer-events: none;
}

.letter-spacing-1 {
  letter-spacing: 1px;
}

.tracking-wider {
  letter-spacing: 1.5px;
}

.max-w-600 {
  max-width: 600px;
}
</style>
