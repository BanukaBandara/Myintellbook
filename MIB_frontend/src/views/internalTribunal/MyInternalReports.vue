<template>
  <InfoPageShell
    icon="bi-folder2-open"
    eyebrow="Confidential Oversight"
    title="My Misconduct Reports"
    subtitle="Track reports you have submitted and review their current status and administrative outcomes."
  >
    <!-- Top Action Bar -->
    <div class="reports-header-actions mb-3">
      <RouterLink to="/submit_case/report-misconduct" class="btn primary">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> Report Misconduct
      </RouterLink>
      <RouterLink to="/submit_case" class="btn ghost">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Tribunal
      </RouterLink>
    </div>

    <!-- Summary Metrics -->
    <div v-if="!isLoading && reports.length > 0" class="metrics-grid mb-3">
      <div class="metric-card">
        <span class="metric-num text-primary">{{ meta?.total || reports.length }}</span>
        <span class="metric-label">Total Reports</span>
      </div>
      <div class="metric-card">
        <span class="metric-num text-amber">{{ countByStatus(['UnderReview', 'Submitted']) }}</span>
        <span class="metric-label">Under Review</span>
      </div>
      <div class="metric-card">
        <span class="metric-num text-orange">{{ countByStatus(['NeedsMoreInformation']) }}</span>
        <span class="metric-label">Needs Info</span>
      </div>
      <div class="metric-card">
        <span class="metric-num text-green">{{ countByStatus(['Valid', 'Closed', 'Invalid']) }}</span>
        <span class="metric-label">Resolved / Concluded</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="panel text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-2 mb-0">Loading your submitted reports...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="panel text-center py-4">
      <i class="bi bi-exclamation-circle text-danger fs-3 d-block mb-2" aria-hidden="true"></i>
      <p class="text-danger fw-semibold mb-3">{{ errorMessage }}</p>
      <button type="button" class="btn ghost" @click="fetchReports(1)">
        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Retry
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="reports.length === 0" class="panel empty-reports-panel">
      <div class="empty-icon-wrap" aria-hidden="true">
        <i class="bi bi-shield-check"></i>
      </div>
      <h3 class="panel-title mb-2">No Misconduct Reports Submitted</h3>
      <p class="text-muted max-w-md mx-auto mb-4">
        You have not submitted any internal misconduct reports. If you encounter policy violations, impersonation, or platform abuse, you can file a confidential report at any time.
      </p>
      <RouterLink to="/submit_case/report-misconduct" class="btn primary">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> Report Misconduct
      </RouterLink>
    </div>

    <!-- Reports List -->
    <div v-else class="reports-container">
      <ul class="reports-list">
        <li v-for="rep in reports" :key="rep.id" class="report-item-card">
          <div class="report-card-top">
            <div class="report-header-left">
              <span class="report-number-badge">
                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                {{ rep.report_number }}
              </span>
              <span class="category-pill">{{ rep.category }}</span>
            </div>
            <InternalReportStatusBadge :status="rep.status" size="sm" />
          </div>

          <h3 class="report-subject">{{ rep.subject }}</h3>

          <div class="report-card-meta">
            <div class="reported-party-info">
              <span class="meta-label">Reported Member:</span>
              <div class="member-chip">
                <img
                  v-if="rep.reported_user?.profile_image"
                  :src="rep.reported_user.profile_image"
                  alt="avatar"
                  class="member-avatar"
                  @error="onAvatarError"
                />
                <span v-else class="member-avatar-initial">
                  {{ rep.reported_user?.name?.charAt(0).toUpperCase() || 'U' }}
                </span>
                <span class="member-name">{{ rep.reported_user?.name }}</span>
                <span class="member-handle">(@{{ rep.reported_user?.username }})</span>
                <span v-if="rep.reported_user?.is_jury_panel" class="badge-jury-panel">
                  Jury Panel
                </span>
              </div>
            </div>

            <div class="report-date-info">
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              <span>Submitted on {{ formatDate(rep.created_at) }}</span>
            </div>
          </div>

          <div class="report-card-foot">
            <RouterLink :to="`/internal-tribunal/${rep.id}`" class="btn ghost btn-sm">
              View Details <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </RouterLink>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="pagination-bar">
        <span class="pagination-info">
          Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)
        </span>
        <div class="pagination-actions">
          <button
            type="button"
            class="btn ghost btn-sm"
            :disabled="meta.current_page <= 1"
            @click="fetchReports(meta.current_page - 1)"
          >
            <i class="bi bi-chevron-left" aria-hidden="true"></i> Previous
          </button>
          <button
            type="button"
            class="btn ghost btn-sm"
            :disabled="meta.current_page >= meta.last_page"
            @click="fetchReports(meta.current_page + 1)"
          >
            Next <i class="bi bi-chevron-right" aria-hidden="true"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Confidentiality Guarantee Notice -->
    <div class="confidentiality-footer-card mt-3">
      <div class="icon-wrap" aria-hidden="true">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
      <div>
        <h4 class="notice-title">Confidential Whistleblower Protection</h4>
        <p class="notice-desc">
          Your identity and submission records are exclusively accessible to platform Super Administrators. Reported parties receive zero identification details, and evidence is stored privately and is accessible only to authorized users.
        </p>
      </div>
    </div>
  </InfoPageShell>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import InternalReportStatusBadge from '@/components/internalTribunal/InternalReportStatusBadge.vue';
import userPng from '@/assets/user.png';
import { internalReportService } from '@/services/internalReportService';
import type { InternalReportItem } from '@/types/internalReport';

const reports = ref<InternalReportItem[]>([]);
const meta = ref<any>(null);
const isLoading = ref(true);
const errorMessage = ref('');

const fetchReports = async (page = 1) => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const res = await internalReportService.getMyReports(page);
    reports.value = res.data;
    meta.value = res.meta;
  } catch {
    errorMessage.value = 'Failed to load your submitted misconduct reports. Please try again.';
  } finally {
    isLoading.value = false;
  }
};

const countByStatus = (statusList: string[]) => {
  return reports.value.filter((r) => statusList.includes(r.status)).length;
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const onAvatarError = (event: Event) => {
  const target = event.target as HTMLImageElement;
  if (target && target.src !== userPng) {
    target.src = userPng;
  }
};

onMounted(() => {
  fetchReports(1);
});
</script>

<style scoped>
.reports-header-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
}

.metric-card {
  display: flex;
  flex-direction: column;
  padding: 12px 14px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 14px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.metric-num {
  font-size: 20px;
  font-weight: 800;
  line-height: 1.2;
}

.metric-num.text-primary { color: var(--ds-primary); }
.metric-num.text-amber { color: #d97706; }
.metric-num.text-orange { color: #ea580c; }
.metric-num.text-green { color: #059669; }

.metric-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 2px;
}

.panel {
  padding: 24px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

.empty-reports-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 40px 20px;
}

.empty-icon-wrap {
  display: grid;
  width: 52px;
  height: 52px;
  font-size: 24px;
  color: var(--ds-primary);
  place-items: center;
  background: var(--ds-primary-soft);
  border-radius: 50%;
  margin-bottom: 14px;
}

.reports-list {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.report-item-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 18px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.report-item-card:hover {
  border-color: var(--ds-primary-200);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.report-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.report-header-left {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.report-number-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: monospace;
  font-weight: 700;
  font-size: 13px;
  color: var(--ds-primary-hover);
  background: var(--ds-primary-soft);
  padding: 3px 8px;
  border-radius: 8px;
}

.category-pill {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--ds-text-secondary);
  background: var(--ds-surface-muted);
  padding: 2px 8px;
  border-radius: 999px;
}

.report-subject {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
  color: var(--ds-text);
  line-height: 1.35;
}

.report-card-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding-top: 8px;
  border-top: 1px solid var(--ds-surface-subtle);
}

.reported-party-info {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.meta-label {
  font-size: 12px;
  color: var(--ds-text-muted);
}

.member-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
}

.member-avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  object-fit: cover;
}

.member-avatar-initial {
  display: grid;
  width: 22px;
  height: 22px;
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-primary);
  place-items: center;
  background: var(--ds-primary-soft);
  border-radius: 50%;
}

.member-name {
  font-weight: 650;
  color: var(--ds-text);
}

.member-handle {
  color: var(--ds-text-muted);
  font-size: 11.5px;
}

.badge-jury-panel {
  padding: 1px 6px;
  font-size: 10px;
  font-weight: 700;
  color: #7c3aed;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  border-radius: 999px;
}

.report-date-info {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--ds-text-muted);
}

.report-card-foot {
  display: flex;
  justify-content: flex-end;
  padding-top: 4px;
}

/* Pagination */
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 6px 0;
  flex-wrap: wrap;
}

.pagination-info {
  font-size: 12.5px;
  color: var(--ds-text-muted);
}

.pagination-actions {
  display: flex;
  gap: 6px;
}

/* Confidentiality Notice */
.confidentiality-footer-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 18px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 16px;
}

.confidentiality-footer-card .icon-wrap {
  color: #15803d;
  font-size: 20px;
  margin-top: 1px;
  flex-shrink: 0;
}

.notice-title {
  margin: 0 0 2px;
  font-size: 13.5px;
  font-weight: 750;
  color: #166534;
}

.notice-desc {
  margin: 0;
  font-size: 12px;
  color: #14532d;
  line-height: 1.5;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 38px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 650;
  text-decoration: none;
  border-radius: 11px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn.btn-sm {
  min-height: 32px;
  padding: 5px 12px;
  font-size: 12px;
  border-radius: 9px;
}

.btn.primary {
  color: #fff;
  background: var(--ds-primary);
  border: 0;
  box-shadow: var(--ds-shadow-sm);
}

.btn.primary:hover {
  background: var(--ds-primary-hover);
}

.btn.ghost {
  color: var(--ds-text-secondary);
  background: #fff;
  border: 1px solid var(--ds-border);
}

.btn.ghost:hover {
  color: var(--ds-primary-hover);
  background: var(--ds-primary-soft);
  border-color: var(--ds-primary-200);
}

.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

@media (max-width: 575px) {
  .reports-header-actions {
    flex-direction: column;
    align-items: stretch;
  }
  .reports-header-actions .btn {
    width: 100%;
  }
  .report-card-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
}
</style>
