<template>
  <InfoPageShell
    icon="bi-shield-exclamation"
    eyebrow="Internal Tribunal Oversight"
    :title="report ? `Report #${report.report_number}` : 'Report Details'"
    :subtitle="report ? report.subject : 'View submission details, investigation status, and attached evidence.'"
  >
    <!-- Navigation / Action Bar -->
    <div class="details-nav-bar mb-3">
      <RouterLink to="/internal-tribunal" class="btn ghost">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to My Misconduct Reports
      </RouterLink>
      <RouterLink to="/submit_case/report-misconduct" class="btn primary">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> Report Misconduct
      </RouterLink>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="panel text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-2 mb-0">Loading confidential report details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="panel text-center py-4">
      <i class="bi bi-exclamation-triangle text-danger fs-3 d-block mb-2" aria-hidden="true"></i>
      <p class="text-danger fw-semibold mb-3">{{ errorMessage }}</p>
      <div class="d-flex justify-content-center gap-2">
        <button type="button" class="btn ghost btn-sm" @click="fetchDetails">
          <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Retry
        </button>
        <RouterLink to="/internal-tribunal" class="btn primary btn-sm">
          Return to My Reports
        </RouterLink>
      </div>
    </div>

    <!-- Report Content -->
    <div v-else-if="report" class="report-details-stack">
      <!-- Status & Progress Timeline Card -->
      <section class="panel timeline-panel">
        <div class="panel-header-row">
          <div>
            <h3 class="panel-title">Investigation Status</h3>
            <p class="panel-subtitle">Current workflow stage of this misconduct report.</p>
          </div>
          <InternalReportStatusBadge :status="report.status" size="md" />
        </div>

        <div class="timeline-stepper">
          <!-- Step 1: Submitted -->
          <div class="timeline-step is-complete">
            <div class="step-marker">
              <i class="bi bi-check-lg" aria-hidden="true"></i>
            </div>
            <div class="step-content">
              <span class="step-title">Submitted</span>
              <span class="step-meta">{{ formatDateTime(report.created_at) }}</span>
            </div>
          </div>

          <!-- Step 2: Under Review -->
          <div
            class="timeline-step"
            :class="{
              'is-complete': isStagePassed(['UnderReview', 'NeedsMoreInformation', 'Valid', 'Invalid', 'Closed']),
              'is-current': report.status === 'UnderReview',
              'is-pending': report.status === 'Submitted'
            }"
          >
            <div class="step-marker">
              <i
                v-if="isStagePassed(['NeedsMoreInformation', 'Valid', 'Invalid', 'Closed'])"
                class="bi bi-check-lg"
                aria-hidden="true"
              ></i>
              <i v-else-if="report.status === 'UnderReview'" class="bi bi-search" aria-hidden="true"></i>
              <span v-else>2</span>
            </div>
            <div class="step-content">
              <span class="step-title">Under Review</span>
              <span class="step-meta">
                {{ report.status === 'Submitted' ? 'In queue' : 'Assigned to oversight' }}
              </span>
            </div>
          </div>

          <!-- Step 3: Conclusion / Information Request -->
          <div
            class="timeline-step"
            :class="{
              'is-complete is-success': report.status === 'Valid',
              'is-complete is-closed': report.status === 'Closed' || report.status === 'Invalid',
              'is-warning': report.status === 'NeedsMoreInformation',
              'is-pending': report.status === 'Submitted' || report.status === 'UnderReview'
            }"
          >
            <div class="step-marker">
              <i v-if="report.status === 'Valid'" class="bi bi-check2-all" aria-hidden="true"></i>
              <i v-else-if="report.status === 'NeedsMoreInformation'" class="bi bi-question-lg" aria-hidden="true"></i>
              <i v-else-if="report.status === 'Closed' || report.status === 'Invalid'" class="bi bi-archive" aria-hidden="true"></i>
              <span v-else>3</span>
            </div>
            <div class="step-content">
              <span class="step-title">
                {{ formatStageTitle(report.status) }}
              </span>
              <span class="step-meta">
                {{ report.status === 'Submitted' || report.status === 'UnderReview' ? 'Pending conclusion' : 'Final resolution' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Needs More Information Alert Banner -->
        <div v-if="report.status === 'NeedsMoreInformation'" class="info-request-alert mt-3">
          <div class="alert-icon" aria-hidden="true">
            <i class="bi bi-info-circle-fill"></i>
          </div>
          <div class="alert-body">
            <h4 class="alert-title">Additional Information Requested</h4>
            <p class="alert-text">
              The oversight administration has reviewed this report and requires supplementary clarification or corroborating evidence. If you have additional documents, please contact support or file a supplementary submission referencing <strong>{{ report.report_number }}</strong>.
            </p>
          </div>
        </div>

        <!-- Decision Reason Banner (if provided) -->
        <div v-if="report.decision_reason" class="decision-reason-box mt-3">
          <div class="decision-header">
            <i class="bi bi-chat-left-quote-fill" aria-hidden="true"></i>
            <span class="decision-label">Administrative Resolution Notice</span>
          </div>
          <p class="decision-text">{{ report.decision_reason }}</p>
        </div>
      </section>

      <!-- Overview & Reported Member Grid -->
      <div class="report-grid">
        <!-- Overview Card -->
        <section class="panel">
          <h3 class="panel-title mb-3">Report Overview</h3>
          <dl class="data-pair-list">
            <div class="data-pair">
              <dt>Report Reference</dt>
              <dd class="font-mono text-primary fw-bold">{{ report.report_number }}</dd>
            </div>
            <div class="data-pair">
              <dt>Category</dt>
              <dd><span class="category-pill">{{ report.category }}</span></dd>
            </div>
            <div class="data-pair">
              <dt>Severity Level</dt>
              <dd>
                <span class="severity-pill" :class="`severity-${report.severity.toLowerCase()}`">
                  {{ report.severity }}
                </span>
              </dd>
            </div>
            <div class="data-pair">
              <dt>Date Submitted</dt>
              <dd>{{ formatDateTime(report.created_at) }}</dd>
            </div>
            <div class="data-pair">
              <dt>Last Activity</dt>
              <dd>{{ formatDateTime(report.updated_at) }}</dd>
            </div>
          </dl>
        </section>

        <!-- Reported Member Card -->
        <section class="panel">
          <h3 class="panel-title mb-3">Reported Member</h3>
          <div class="reported-profile-card">
            <img
              v-if="report.reported_user?.profile_image"
              :src="report.reported_user.profile_image"
              alt="avatar"
              class="profile-avatar"
              @error="onAvatarError"
            />
            <div v-else class="profile-avatar-initial">
              {{ report.reported_user?.name?.charAt(0).toUpperCase() || 'U' }}
            </div>
            <div class="profile-meta">
              <div class="profile-name-row">
                <span class="profile-name">{{ report.reported_user?.name }}</span>
                <span v-if="report.reported_user?.is_jury_panel" class="badge-jury-panel">
                  Jury Panel
                </span>
              </div>
              <span class="profile-handle">@{{ report.reported_user?.username }}</span>
            </div>
          </div>
          <p class="text-muted text-xs mt-3 mb-0">
            Platform member identified in the alleged misconduct complaint.
          </p>
        </section>
      </div>

      <!-- Description / Incident Details Card -->
      <section class="panel">
        <h3 class="panel-title mb-1">Incident Description</h3>
        <p class="panel-subtitle mb-3">Detailed statement submitted with this report.</p>
        <div class="description-body">
          {{ report.description }}
        </div>
      </section>

      <!-- Attached Evidence Vault Card -->
      <section class="panel">
        <div class="panel-header-row mb-3">
          <div>
            <h3 class="panel-title">
              Attached Evidence Files ({{ report.evidence?.length || 0 }})
            </h3>
            <p class="panel-subtitle">Documents uploaded to substantiate this report.</p>
          </div>
        </div>

        <div v-if="!report.evidence || report.evidence.length === 0" class="empty-evidence">
          <i class="bi bi-file-earmark-lock text-muted fs-3 mb-2" aria-hidden="true"></i>
          <p class="text-muted mb-0">No evidence documents attached to this report.</p>
        </div>

        <div v-else class="evidence-grid">
          <div v-for="ev in report.evidence" :key="ev.id" class="evidence-card">
            <div class="evidence-icon-wrap" aria-hidden="true">
              <i class="bi bi-file-earmark-arrow-down"></i>
            </div>
            <div class="evidence-info">
              <span class="evidence-filename" :title="ev.original_name">{{ ev.original_name }}</span>
              <span class="evidence-meta">
                {{ formatFileSize(ev.size) }} &bull; {{ ev.mime_type || 'File' }}
              </span>
            </div>
            <button
              type="button"
              class="btn ghost btn-sm evidence-download-btn"
              :disabled="downloadingId === ev.id"
              @click="downloadEvidence(ev.id, ev.original_name)"
            >
              <span v-if="downloadingId === ev.id" class="spinner-border spinner-border-sm" role="status"></span>
              <i v-else class="bi bi-download" aria-hidden="true"></i>
              <span>Download</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Confidentiality Assurance Card -->
      <div class="confidentiality-footer-card">
        <div class="icon-wrap" aria-hidden="true">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div>
          <h4 class="notice-title">Confidential Whistleblower Safety</h4>
          <p class="notice-desc">
            Evidence is stored privately and is accessible only to authorized users. The reported member does not receive access to your identity or your submission documentation.
          </p>
        </div>
      </div>
    </div>
  </InfoPageShell>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import InternalReportStatusBadge from '@/components/internalTribunal/InternalReportStatusBadge.vue';
import userPng from '@/assets/user.png';
import { internalReportService } from '@/services/internalReportService';
import type { InternalReportItem } from '@/types/internalReport';

const route = useRoute();
const reportId = Number(route.params.id);

const report = ref<InternalReportItem | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const downloadingId = ref<number | null>(null);

const fetchDetails = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    report.value = await internalReportService.getReport(reportId);
  } catch (err: any) {
    if (err.response?.status === 403) {
      errorMessage.value = 'Access denied. You may only view reports submitted by your own account.';
    } else if (err.response?.status === 404) {
      errorMessage.value = 'Report not found. The requested misconduct report does not exist or has been archived.';
    } else {
      errorMessage.value = 'Failed to load report details. Please check your connection and try again.';
    }
  } finally {
    isLoading.value = false;
  }
};

const downloadEvidence = async (evidenceId: number, filename: string) => {
  downloadingId.value = evidenceId;
  try {
    const blob = await internalReportService.downloadEvidence(evidenceId);
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch {
    Swal.fire({
      icon: 'error',
      title: 'Download Failed',
      text: 'Unable to securely download the evidence file. Please try again.',
    });
  } finally {
    downloadingId.value = null;
  }
};

const isStagePassed = (targetStatuses: string[]) => {
  if (!report.value) return false;
  return targetStatuses.includes(report.value.status);
};

const formatStageTitle = (status: string) => {
  switch (status) {
    case 'Valid':
      return 'Validated & Concluded';
    case 'Invalid':
      return 'Deemed Invalid';
    case 'NeedsMoreInformation':
      return 'Info Requested';
    case 'Closed':
      return 'Report Closed';
    default:
      return 'Conclusion';
  }
};

const formatDateTime = (dateStr: string) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatFileSize = (bytes: number) => {
  if (!bytes) return '0 B';
  const mb = bytes / (1024 * 1024);
  if (mb >= 1) {
    return `${mb.toFixed(2)} MB`;
  }
  const kb = bytes / 1024;
  return `${kb.toFixed(1)} KB`;
};

const onAvatarError = (event: Event) => {
  const target = event.target as HTMLImageElement;
  if (target && target.src !== userPng) {
    target.src = userPng;
  }
};

onMounted(() => {
  fetchDetails();
});
</script>

<style scoped>
.details-nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.report-details-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.panel {
  padding: 24px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

.panel-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.panel-title {
  margin: 0;
  font-size: 16px;
  font-weight: 750;
  color: var(--ds-text);
  line-height: 1.3;
}

.panel-subtitle {
  margin: 3px 0 0;
  font-size: 12.5px;
  color: var(--ds-text-muted);
}

/* Timeline Stepper */
.timeline-stepper {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-top: 20px;
  position: relative;
}

.timeline-step {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: var(--ds-surface-muted);
  border: 1px solid var(--ds-border);
  border-radius: 14px;
  transition: all 0.2s ease;
}

.step-marker {
  display: grid;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  place-items: center;
  font-size: 13px;
  font-weight: 700;
  background: #fff;
  color: var(--ds-text-muted);
  border: 1px solid var(--ds-border);
  flex-shrink: 0;
}

.step-content {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.step-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ds-text-secondary);
  line-height: 1.25;
}

.step-meta {
  font-size: 11px;
  color: var(--ds-text-muted);
  margin-top: 2px;
}

/* Stepper States */
.timeline-step.is-complete {
  background: #f0fdf4;
  border-color: #bbf7d0;
}
.timeline-step.is-complete .step-marker {
  background: #059669;
  color: #fff;
  border-color: #059669;
}
.timeline-step.is-complete .step-title {
  color: #065f46;
}

.timeline-step.is-current {
  background: var(--ds-primary-soft);
  border-color: var(--ds-primary-200);
}
.timeline-step.is-current .step-marker {
  background: var(--ds-primary);
  color: #fff;
  border-color: var(--ds-primary);
}
.timeline-step.is-current .step-title {
  color: var(--ds-primary-hover);
}

.timeline-step.is-warning {
  background: #fff7ed;
  border-color: #fed7aa;
}
.timeline-step.is-warning .step-marker {
  background: #ea580c;
  color: #fff;
  border-color: #ea580c;
}
.timeline-step.is-warning .step-title {
  color: #9a3412;
}

.timeline-step.is-closed {
  background: #f8fafc;
  border-color: #e2e8f0;
}
.timeline-step.is-closed .step-marker {
  background: #64748b;
  color: #fff;
  border-color: #64748b;
}

/* Alerts / Decision Box */
.info-request-alert {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 14px;
}

.alert-icon {
  color: #ea580c;
  font-size: 20px;
  flex-shrink: 0;
  margin-top: 1px;
}

.alert-title {
  margin: 0 0 2px;
  font-size: 13.5px;
  font-weight: 750;
  color: #9a3412;
}

.alert-text {
  margin: 0;
  font-size: 12.5px;
  color: #7c2d12;
  line-height: 1.5;
}

.decision-reason-box {
  padding: 14px 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
}

.decision-header {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ds-primary);
  font-size: 12.5px;
  font-weight: 700;
  margin-bottom: 6px;
}

.decision-text {
  margin: 0;
  font-size: 13px;
  color: var(--ds-text);
  line-height: 1.5;
}

/* Report Grid */
.report-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}

.data-pair-list {
  display: grid;
  gap: 10px;
  margin: 0;
}

.data-pair {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--ds-surface-subtle);
}

.data-pair:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.data-pair dt {
  color: var(--ds-text-muted);
  font-weight: 550;
}

.data-pair dd {
  margin: 0;
  font-weight: 600;
  color: var(--ds-text);
}

.category-pill {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--ds-text-secondary);
  background: var(--ds-surface-muted);
  padding: 2px 8px;
  border-radius: 999px;
}

.severity-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  text-transform: capitalize;
}

.severity-low { background: #f0fdf4; color: #166534; }
.severity-medium { background: #eff6ff; color: #1e40af; }
.severity-high { background: #fff7ed; color: #9a3412; }
.severity-critical { background: #fef2f2; color: #991b1b; }

/* Reported Profile Card */
.reported-profile-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: var(--ds-surface-muted);
  border: 1px solid var(--ds-border);
  border-radius: 14px;
}

.profile-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-avatar-initial {
  display: grid;
  width: 44px;
  height: 44px;
  font-size: 16px;
  font-weight: 700;
  color: var(--ds-primary);
  background: var(--ds-primary-soft);
  place-items: center;
  border-radius: 50%;
}

.profile-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.profile-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.profile-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ds-text);
}

.profile-handle {
  font-size: 12px;
  color: var(--ds-text-muted);
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

/* Incident Description */
.description-body {
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--ds-text);
  white-space: pre-wrap;
  word-break: break-word;
  padding: 14px;
  background: var(--ds-surface-muted);
  border-radius: 12px;
  border: 1px solid var(--ds-border);
}

/* Evidence Vault */
.empty-evidence {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
  background: var(--ds-surface-muted);
  border-radius: 12px;
  text-align: center;
  font-size: 13px;
}

.evidence-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}

.evidence-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 14px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 14px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.evidence-icon-wrap {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  color: var(--ds-primary);
  background: var(--ds-primary-soft);
  border-radius: 10px;
  font-size: 16px;
  flex-shrink: 0;
}

.evidence-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.evidence-filename {
  font-size: 12.5px;
  font-weight: 650;
  color: var(--ds-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.evidence-meta {
  font-size: 11px;
  color: var(--ds-text-muted);
}

.evidence-download-btn {
  flex-shrink: 0;
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

/* Button Classes */
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
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .timeline-stepper {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .details-nav-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .details-nav-bar .btn {
    width: 100%;
  }
}
</style>
