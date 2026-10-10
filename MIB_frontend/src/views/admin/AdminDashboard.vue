<template>
  <div class="admin-dashboard">
    <div class="dashboard-header">
      <div>
        <h2 class="dashboard-title">System Overview</h2>
        <p class="dashboard-subtitle">Monitor institutional operations, Jury Panels, and professional verifications.</p>
      </div>
      <div class="header-actions">
        <button type="button" class="action-btn" @click="fetchStats" :disabled="loading">
          <i class="pi pi-refresh" :class="{ 'pi-spin': loading }"></i>
          <span>Refresh Metrics</span>
        </button>
      </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="stats-grid">
      <!-- Users Card -->
      <div class="stat-card">
        <div class="card-top">
          <span class="card-label">Total Users</span>
          <div class="card-icon bg-blue">
            <i class="pi pi-users"></i>
          </div>
        </div>
        <div class="card-value">{{ stats.users?.total ?? '—' }}</div>
        <div class="card-footer-note">Registered consumer accounts</div>
      </div>

      <!-- Professional Verifications Card -->
      <div class="stat-card">
        <div class="card-top">
          <span class="card-label">Pending Verifications</span>
          <div class="card-icon bg-amber">
            <i class="pi pi-verified"></i>
          </div>
        </div>
        <div class="card-value">{{ stats.verifications?.pending ?? '—' }}</div>
        <div class="card-footer-note">
          <span class="highlight">{{ stats.verifications?.verified_lawyers ?? 0 }}</span> verified attorneys
        </div>
      </div>

      <!-- Jury Panels Card -->
      <div class="stat-card">
        <div class="card-top">
          <span class="card-label">Active Jury Panels</span>
          <div class="card-icon bg-emerald">
            <i class="pi pi-shield"></i>
          </div>
        </div>
        <div class="card-value">{{ stats.jury_panels?.active ?? '—' }}</div>
        <div class="card-footer-note">
          <span>{{ stats.jury_panels?.inactive ?? 0 }} inactive panels</span>
        </div>
      </div>

      <!-- Tribunal Cases Card -->
      <div class="stat-card">
        <div class="card-top">
          <span class="card-label">Tribunal Cases</span>
          <div class="card-icon bg-indigo">
            <i class="pi pi-file"></i>
          </div>
        </div>
        <div class="card-value">{{ stats.tribunal_cases?.total ?? '—' }}</div>
        <div class="card-footer-note">
          <span>{{ stats.tribunal_cases?.waiting_jury ?? 0 }} awaiting jury</span>
        </div>
      </div>
    </div>

    <!-- Detailed Status Cards -->
    <div class="details-grid">
      <!-- Tribunal Pipeline -->
      <div class="detail-card">
        <div class="detail-header">
          <div class="detail-title-group">
            <i class="pi pi-chart-bar title-icon"></i>
            <h3>Tribunal Case Pipeline</h3>
          </div>
        </div>
        <div class="pipeline-list">
          <div class="pipeline-item">
            <span class="status-name">Awaiting Jury Assignment</span>
            <span class="status-count badge-amber">{{ stats.tribunal_cases?.waiting_jury ?? 0 }}</span>
          </div>
          <div class="pipeline-item">
            <span class="status-name">In Hearing Sessions</span>
            <span class="status-count badge-blue">{{ stats.tribunal_cases?.in_hearing ?? 0 }}</span>
          </div>
          <div class="pipeline-item">
            <span class="status-name">In Deliberation</span>
            <span class="status-count badge-purple">{{ stats.tribunal_cases?.in_deliberation ?? 0 }}</span>
          </div>
          <div class="pipeline-item">
            <span class="status-name">Decided / Concluded</span>
            <span class="status-count badge-green">{{ stats.tribunal_cases?.completed ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="detail-card">
        <div class="detail-header">
          <div class="detail-title-group">
            <i class="pi pi-bolt title-icon"></i>
            <h3>Administrative Quick Links</h3>
          </div>
        </div>
        <div class="quick-links">
          <router-link to="/admin/tribunal/jury-panels" class="quick-link-item">
            <div class="link-icon bg-indigo">
              <i class="pi pi-users"></i>
            </div>
            <div class="link-info">
              <span class="link-title">Manage Jury Panels</span>
              <span class="link-desc">Create, view, activate, and deactivate Jury Panel credentials</span>
            </div>
            <i class="pi pi-chevron-right link-arrow"></i>
          </router-link>

          <router-link to="/admin/professional-verifications" class="quick-link-item">
            <div class="link-icon bg-amber">
              <i class="pi pi-verified"></i>
            </div>
            <div class="link-info">
              <span class="link-title">Professional Verifications</span>
              <span class="link-desc">Review submitted legal credentials, approve, reject, or suspend</span>
            </div>
            <i class="pi pi-chevron-right link-arrow"></i>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import instance from '@/assets/axios';

const loading = ref(false);
const stats = ref<any>({
  users: { total: 0 },
  verifications: { pending: 0, verified_lawyers: 0 },
  jury_panels: { active: 0, inactive: 0, total: 0 },
  tribunal_cases: { total: 0, waiting_jury: 0, in_hearing: 0, in_deliberation: 0, completed: 0 },
});

const fetchStats = async () => {
  loading.value = true;
  try {
    const response = await instance.get('/admin/dashboard/stats');
    if (response.data?.status && response.data?.data) {
      stats.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load admin stats', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchStats();
});
</script>

<style scoped>
.admin-dashboard {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.dashboard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.dashboard-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.02em;
}

.dashboard-subtitle {
  font-size: 0.85rem;
  color: #64748b;
  margin: 4px 0 0;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.action-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.stat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.card-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.card-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}

.bg-blue { background: #eff6ff; color: #2563eb; }
.bg-amber { background: #fffbeb; color: #d97706; }
.bg-emerald { background: #ecfdf5; color: #059669; }
.bg-indigo { background: #eef2ff; color: #4f46e5; }

.card-value {
  font-size: 1.85rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  margin-bottom: 8px;
}

.card-footer-note {
  font-size: 0.78rem;
  color: #64748b;
}

.highlight {
  font-weight: 600;
  color: #0f172a;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  gap: 20px;
}

.detail-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 22px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.detail-header {
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.detail-title-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.title-icon {
  color: #3b82f6;
  font-size: 1.1rem;
}

.detail-title-group h3 {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.pipeline-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pipeline-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #edf2f7;
}

.status-name {
  font-size: 0.85rem;
  font-weight: 500;
  color: #334155;
}

.status-count {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 9999px;
}

.badge-amber { background: #fef3c7; color: #92400e; }
.badge-blue { background: #dbeafe; color: #1e40af; }
.badge-purple { background: #f3e8ff; color: #6b21a8; }
.badge-green { background: #d1fae5; color: #065f46; }

.quick-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.quick-link-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  text-decoration: none;
  background: #ffffff;
  transition: all 160ms ease;
}

.quick-link-item:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  transform: translateX(2px);
}

.link-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.link-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.link-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
}

.link-desc {
  font-size: 0.76rem;
  color: #64748b;
}

.link-arrow {
  color: #94a3b8;
  font-size: 0.85rem;
}
</style>
