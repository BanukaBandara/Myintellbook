<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import AdminInternalReportStatusBadge from '@/components/internalTribunal/AdminInternalReportStatusBadge.vue';
import { adminInternalReportService } from '@/services/adminInternalReportService';
import type { AdminInternalReportItem, InternalPenaltyType, InternalReportStatus, RestrictedFeature } from '@/types/internalReport';

const route = useRoute();
const router = useRouter();
const reportId = Number(route.params.id);

const report = ref<AdminInternalReportItem | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

// Modals State
const showStatusModal = ref(false);
const statusForm = ref<{
  status: InternalReportStatus;
  notes: string;
  decision_reason: string;
}>({
  status: 'UnderReview',
  notes: '',
  decision_reason: '',
});
const isUpdatingStatus = ref(false);

const showPenaltyModal = ref(false);
const penaltyForm = ref<{
  action_type: InternalPenaltyType;
  penalty_value: RestrictedFeature | string;
  restriction_duration_type: 'temporary' | 'permanent';
  duration_days: number;
  reason: string;
  notes: string;
  content_type: 'testament_note';
  content_id: number | null;
}>({
  action_type: 'Warning',
  penalty_value: 'community_posting',
  restriction_duration_type: 'temporary',
  duration_days: 7,
  reason: '',
  notes: '',
  content_type: 'testament_note',
  content_id: null,
});
const isApplyingPenalty = ref(false);
const downloadingId = ref<number | null>(null);

const fetchDossier = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    report.value = await adminInternalReportService.getReport(reportId);
    if (report.value) {
      statusForm.value.status = report.value.status;
      statusForm.value.notes = report.value.admin_notes || '';
      statusForm.value.decision_reason = report.value.decision_reason || '';
    }
  } catch (err: any) {
    errorMessage.value =
      err.response?.status === 404
        ? 'Report dossier not found. The record may have been archived or does not exist.'
        : 'Failed to load report dossier. Please check your credentials or network connection.';
  } finally {
    isLoading.value = false;
  }
};

const handleUpdateStatus = async () => {
  isUpdatingStatus.value = true;
  try {
    const updated = await adminInternalReportService.updateStatus(reportId, {
      status: statusForm.value.status,
      notes: statusForm.value.notes || undefined,
      decision_reason: statusForm.value.decision_reason || undefined,
    });
    report.value = updated;
    showStatusModal.value = false;
    Swal.fire({
      icon: 'success',
      title: 'Status Updated',
      text: `Report status successfully updated to ${statusForm.value.status}.`,
      timer: 2000,
      showConfirmButton: false,
    });
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Update Failed',
      text: err.response?.data?.message || 'Failed to update report status.',
    });
  } finally {
    isUpdatingStatus.value = false;
  }
};

const handleApplyPenalty = async () => {
  if (!penaltyForm.value.reason.trim()) {
    Swal.fire({
      icon: 'warning',
      title: 'Reason Required',
      text: 'Please provide a formal justification for this administrative action.',
    });
    return;
  }

  isApplyingPenalty.value = true;
  try {
    const payload: any = {
      action_type: penaltyForm.value.action_type,
      reason: penaltyForm.value.reason.trim(),
      notes: penaltyForm.value.notes.trim() || undefined,
    };
    if (penaltyForm.value.action_type === 'Temporary Suspension') {
      payload.duration_days = Number(penaltyForm.value.duration_days) || 7;
    } else if (penaltyForm.value.action_type === 'Feature Restriction') {
      payload.penalty_value = penaltyForm.value.penalty_value;
      payload.restriction_duration_type = penaltyForm.value.restriction_duration_type;
      if (penaltyForm.value.restriction_duration_type === 'temporary') {
        payload.duration_days = Number(penaltyForm.value.duration_days) || 7;
      }
    } else if (penaltyForm.value.action_type === 'Professional Eligibility Suspension') {
      payload.restriction_duration_type = penaltyForm.value.restriction_duration_type;
      if (penaltyForm.value.restriction_duration_type === 'temporary') {
        payload.duration_days = Number(penaltyForm.value.duration_days) || 7;
      }
    } else if (penaltyForm.value.action_type === 'Jury Panel Deactivation') {
      payload.restriction_duration_type = penaltyForm.value.restriction_duration_type;
      if (penaltyForm.value.restriction_duration_type === 'temporary') {
        payload.duration_days = Number(penaltyForm.value.duration_days) || 7;
      }
    } else if (penaltyForm.value.action_type === 'HIP / Score Penalty') {
      const pts = Number(penaltyForm.value.penalty_value);
      if (isNaN(pts) || pts < 1 || pts > 36825) {
        Swal.fire({
          icon: 'warning',
          title: 'Invalid Points',
          text: 'Penalty points must be between 1.00 and 36,825.00 with at most 2 decimal places.',
        });
        isApplyingPenalty.value = false;
        return;
      }
      payload.penalty_value = penaltyForm.value.penalty_value;
    } else if (penaltyForm.value.action_type === 'Content Removal') {
      const cid = Number(penaltyForm.value.content_id);
      if (!cid || cid <= 0 || !Number.isInteger(cid)) {
        Swal.fire({
          icon: 'warning',
          title: 'Valid Note ID Required',
          text: 'Please enter a valid positive integer Note ID for content removal.',
        });
        isApplyingPenalty.value = false;
        return;
      }
      payload.content_type = penaltyForm.value.content_type || 'testament_note';
      payload.content_id = cid;
    }

    const res = await adminInternalReportService.applyPenalty(reportId, payload);
    report.value = res.report;
    showPenaltyModal.value = false;
    penaltyForm.value.reason = '';
    penaltyForm.value.notes = '';
    penaltyForm.value.duration_days = 7;
    penaltyForm.value.penalty_value = 'community_posting';
    penaltyForm.value.restriction_duration_type = 'temporary';
    penaltyForm.value.content_type = 'testament_note';
    penaltyForm.value.content_id = null;

    Swal.fire({
      icon: 'success',
      title: 'Sanction Applied',
      text: `${res.action_type} successfully recorded and sanitized notification sent to the reported user.`,
    });
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err.response?.data?.message || 'Failed to apply disciplinary action.',
    });
  } finally {
    isApplyingPenalty.value = false;
  }
};

const handleReversePenalty = async (penaltyId: number) => {
  const { value: reason } = await Swal.fire({
    title: 'Reverse Administrative Action?',
    input: 'textarea',
    inputLabel: 'Reason for Reversal',
    inputPlaceholder: 'Explain why this action is being reversed...',
    inputValidator: (val) => {
      if (!val || val.trim().length < 5) {
        return 'Please provide a reversal reason (min 5 characters).';
      }
    },
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: 'Confirm Reversal',
  });

  if (!reason) return;

  try {
    await adminInternalReportService.reversePenalty(penaltyId, reason);
    await fetchDossier();
    Swal.fire({
      icon: 'success',
      title: 'Action Reversed',
      text: 'The penalty was reversed and audited.',
    });
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Reversal Failed',
      text: err.response?.data?.message || 'Failed to reverse penalty.',
    });
  }
};

const downloadEvidence = async (evidenceId: number, filename: string) => {
  downloadingId.value = evidenceId;
  try {
    const blob = await adminInternalReportService.downloadEvidence(evidenceId);
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
      text: 'Unable to stream evidence file.',
    });
  } finally {
    downloadingId.value = null;
  }
};

const formatDateTime = (dateStr?: string | null): string => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatFileSize = (bytes: number): string => {
  if (!bytes) return '0 B';
  const mb = bytes / (1024 * 1024);
  if (mb >= 1) return `${mb.toFixed(2)} MB`;
  const kb = bytes / 1024;
  return `${kb.toFixed(1)} KB`;
};

onMounted(() => {
  fetchDossier();
});
</script>

<template>
  <div class="container-fluid py-4 px-4">
    <!-- Header / Breadcrumbs & Navigation -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm rounded-pill px-3"
            @click="router.push('/admin/internal-reports')"
          >
            <i class="bi bi-arrow-left me-1" /> Back to Internal Reports
          </button>
        </div>
        <div class="d-flex align-items-center gap-3 mt-2">
          <h2 class="fw-bold mb-0 text-primary font-mono">
            {{ report?.report_number || 'Internal Report' }}
          </h2>
          <AdminInternalReportStatusBadge v-if="report" :status="report.status" size="lg" />
        </div>
      </div>

      <!-- Header Action Buttons -->
      <div v-if="report" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <button
          type="button"
          class="btn btn-outline-primary rounded-pill px-3 shadow-2xs"
          @click="showStatusModal = true"
        >
          <i class="bi bi-arrow-repeat me-1" /> Change Status
        </button>
        <button
          type="button"
          class="btn btn-danger rounded-pill px-3 shadow-sm"
          @click="showPenaltyModal = true"
        >
          <i class="bi bi-shield-slash me-1" /> Apply Action / Sanction
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="card border-0 shadow-sm rounded-4 text-center py-5">
      <div class="spinner-border text-primary mx-auto mb-2" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted small mb-0">Loading confidential report dossier...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="card border-0 shadow-sm rounded-4 text-center py-4 p-3 border-start border-danger border-4">
      <i class="bi bi-exclamation-triangle text-danger fs-3 d-block mb-2" />
      <p class="text-danger fw-semibold mb-2">{{ errorMessage }}</p>
      <div>
        <button
          type="button"
          class="btn btn-outline-secondary btn-sm rounded-pill px-3"
          @click="fetchDossier"
        >
          <i class="bi bi-arrow-clockwise me-1" /> Retry
        </button>
      </div>
    </div>

    <!-- Dossier Content -->
    <div v-else-if="report" class="dossier-layout">
      <!-- Parties Row -->
      <div class="row g-3 mb-4">
        <!-- Protected Reporter Card -->
        <div class="col-12 col-md-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3 px-4">
              <span class="fw-bold small text-uppercase text-muted">
                <i class="bi bi-shield-shaded me-1.5 text-primary" />Complainant / Reporter
              </span>
              <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1">
                Protected Whistleblower
              </span>
            </div>
            <div class="card-body p-4">
              <div class="row g-2 small">
                <div class="col-4 text-muted">Full Name:</div>
                <div class="col-8 fw-semibold text-dark">{{ report.reporter?.name }}</div>

                <div class="col-4 text-muted">Email:</div>
                <div class="col-8 font-mono text-dark">{{ report.reporter?.email }}</div>

                <div class="col-4 text-muted">Account ID:</div>
                <div class="col-8 font-mono text-dark">#{{ report.reporter?.id }}</div>

                <div class="col-4 text-muted">Username:</div>
                <div class="col-8 text-dark">@{{ report.reporter?.username }}</div>
              </div>
              <div class="alert alert-light border small text-muted p-2.5 mt-3 mb-0 rounded-3">
                <i class="bi bi-lock-fill me-1 text-primary" />
                Confidential identity strictly sealed from reported party view.
              </div>
            </div>
          </div>
        </div>

        <!-- Reported Member Card -->
        <div class="col-12 col-md-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3 px-4">
              <span class="fw-bold small text-uppercase text-muted">
                <i class="bi bi-person-fill-exclamation me-1.5 text-danger" />Reported Member
              </span>
              <span
                v-if="report.reported_user?.is_jury_panel"
                class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill px-2.5 py-1"
              >
                Jury Panel Account
              </span>
              <span v-else class="badge bg-light text-secondary border rounded-pill px-2.5 py-1">
                Platform Member
              </span>
            </div>
            <div class="card-body p-4">
              <div class="row g-2 small">
                <div class="col-4 text-muted">Full Name:</div>
                <div class="col-8 fw-semibold text-dark">{{ report.reported_user?.name }}</div>

                <div class="col-4 text-muted">Email:</div>
                <div class="col-8 font-mono text-dark">{{ report.reported_user?.email }}</div>

                <div class="col-4 text-muted">Account ID:</div>
                <div class="col-8 font-mono text-dark">#{{ report.reported_user?.id }}</div>

                <div class="col-4 text-muted">Username:</div>
                <div class="col-8 text-dark">@{{ report.reported_user?.username }}</div>

                <div v-if="report.reported_user?.hip_score !== undefined" class="col-4 text-muted">
                  Current HIP:
                </div>
                <div v-if="report.reported_user?.hip_score !== undefined" class="col-8 font-mono fw-bold text-dark">
                  {{ Number(report.reported_user.hip_score).toLocaleString() }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Report Summary Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-header bg-transparent border-bottom py-3 px-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <span class="fw-bold small text-uppercase text-muted">Incident Dossier</span>
              <h4 class="fw-bold text-dark mb-0 mt-1">{{ report.subject }}</h4>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill">
                {{ report.category }}
              </span>
              <span
                class="badge rounded-pill px-2.5 py-1 text-uppercase"
                :class="{
                  'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25': report.severity === 'critical' || report.severity === 'high',
                  'bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-50': report.severity === 'medium',
                  'bg-info bg-opacity-10 text-info border border-info border-opacity-25': report.severity === 'low'
                }"
              >
                Severity: {{ report.severity }}
              </span>
            </div>
          </div>
        </div>

        <div class="card-body p-4">
          <!-- Timestamps strip -->
          <div class="row g-3 pb-3 mb-3 border-bottom small text-muted">
            <div class="col-12 col-sm-4">
              <span class="text-secondary fw-semibold">Submitted On:</span>
              <div class="text-dark">{{ formatDateTime(report.created_at) }}</div>
            </div>
            <div v-if="report.reviewed_at" class="col-12 col-sm-4">
              <span class="text-secondary fw-semibold">Last Reviewed:</span>
              <div class="text-dark">{{ formatDateTime(report.reviewed_at) }} by {{ report.reviewer_name }}</div>
            </div>
            <div v-if="report.closed_at" class="col-12 col-sm-4">
              <span class="text-secondary fw-semibold">Closed On:</span>
              <div class="text-dark">{{ formatDateTime(report.closed_at) }}</div>
            </div>
          </div>

          <!-- Incident Description -->
          <div class="mb-4">
            <h6 class="fw-bold small text-uppercase text-muted mb-2">Complainant Narrative</h6>
            <div class="p-3 bg-light rounded-3 text-dark small leading-relaxed whitespace-pre-wrap border">
              {{ report.description }}
            </div>
          </div>

          <!-- Internal Admin Notes / Official Decision Alerts -->
          <div v-if="report.admin_notes || report.decision_reason" class="row g-3">
            <div v-if="report.admin_notes" class="col-12 col-md-6">
              <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-50 rounded-3 small">
                <div class="fw-bold text-warning-emphasis mb-1">
                  <i class="bi bi-shield-lock-fill me-1" />Super Admin Internal Notes (Private)
                </div>
                <div class="text-dark">{{ report.admin_notes }}</div>
              </div>
            </div>

            <div v-if="report.decision_reason" class="col-12 col-md-6">
              <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 small">
                <div class="fw-bold text-primary mb-1">
                  <i class="bi bi-chat-quote-fill me-1" />Official Resolution Notice (Published)
                </div>
                <div class="text-dark">{{ report.decision_reason }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Evidence Vault Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
          <span class="fw-bold small text-uppercase text-muted">
            <i class="bi bi-paperclip me-1.5 text-primary" />Attached Evidence Vault ({{ report.evidence?.length || 0 }})
          </span>
          <span class="text-muted small">SHA-256 Validated</span>
        </div>
        <div class="card-body p-4">
          <div v-if="!report.evidence || report.evidence.length === 0" class="text-center py-3 text-muted small">
            <i class="bi bi-file-earmark-lock fs-3 d-block mb-1" />
            No evidence documents were attached with this submission.
          </div>
          <div v-else class="row g-3">
            <div v-for="ev in report.evidence" :key="ev.id" class="col-12 col-md-6 col-lg-4">
              <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between gap-2 shadow-2xs">
                <div class="d-flex align-items-center gap-2.5 min-w-0">
                  <div class="p-2 rounded-2 bg-primary bg-opacity-10 text-primary flex-shrink-0">
                    <i class="bi bi-file-earmark-arrow-down fs-5" />
                  </div>
                  <div class="min-w-0">
                    <div class="fw-semibold text-dark text-truncate small" :title="ev.original_name">
                      {{ ev.original_name }}
                    </div>
                    <div class="text-muted font-mono text-2xs">
                      {{ formatFileSize(ev.size) }} &bull; {{ ev.sha256.substring(0, 8) }}...
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary rounded-pill px-3 flex-shrink-0"
                  :disabled="downloadingId === ev.id"
                  @click="downloadEvidence(ev.id, ev.original_name)"
                >
                  <span v-if="downloadingId === ev.id" class="spinner-border spinner-border-sm" role="status" />
                  <span v-else><i class="bi bi-download me-1" />Get</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Applied Sanctions & Actions Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
          <span class="fw-bold small text-uppercase text-muted">
            <i class="bi bi-gavel me-1.5 text-danger" />Disciplinary Actions & Sanctions ({{ report.penalties?.length || 0 }})
          </span>
          <button
            type="button"
            class="btn btn-sm btn-danger rounded-pill px-3 shadow-2xs"
            @click="showPenaltyModal = true"
          >
            <i class="bi bi-plus-circle me-1" /> Apply Sanction
          </button>
        </div>

        <div class="card-body p-4">
          <div v-if="!report.penalties || report.penalties.length === 0" class="text-center py-3 text-muted small">
            <i class="bi bi-shield-check fs-3 d-block mb-1 text-success" />
            No disciplinary actions have been applied for this report yet.
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="p in report.penalties"
              :key="p.id"
              class="p-3 border rounded-3 mb-3 bg-light d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3"
            >
              <div class="small">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1 fw-bold">
                    {{ p.action_type }}
                  </span>
                  <span
                    v-if="p.penalty_value"
                    class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-50 font-mono rounded-pill px-2.5 py-1"
                  >
                    {{ p.action_type === 'HIP / Score Penalty' ? `-${Number(p.penalty_value).toLocaleString()} pts` : (p.action_type === 'Content Removal' ? `Item: ${p.penalty_value}` : p.penalty_value) }}
                  </span>
                  <span class="text-muted">&bull;</span>
                  <span class="text-muted">{{ formatDateTime(p.applied_at) }} by {{ p.applied_by_name }}</span>
                </div>

                <div class="text-dark"><strong>Reason:</strong> {{ p.reason }}</div>
                <div v-if="p.notes" class="text-muted fst-italic"><strong>Notes:</strong> {{ p.notes }}</div>

                <div v-if="p.starts_at || p.ends_at" class="text-secondary small mt-1">
                  <span v-if="p.starts_at">Starts: {{ formatDateTime(p.starts_at) }}</span>
                  <span v-if="p.starts_at && p.ends_at" class="mx-1">&bull;</span>
                  <span v-if="p.ends_at">Expires: {{ formatDateTime(p.ends_at) }}</span>
                  <span v-else-if="!p.ends_at">(Indefinite)</span>
                </div>

                <div v-if="p.reversed_at" class="text-danger fw-semibold small mt-1">
                  <i class="bi bi-arrow-counterclockwise me-1" />
                  [Reversed on {{ formatDateTime(p.reversed_at) }}]
                </div>
              </div>

              <div v-if="!p.reversed_at" class="flex-shrink-0">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-2xs"
                  @click="handleReversePenalty(p.id)"
                >
                  <i class="bi bi-arrow-counterclockwise me-1" /> Reverse Action
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Transitions & Audits Row -->
      <div class="row g-3 mb-4">
        <!-- Review Transitions -->
        <div class="col-12 col-md-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-transparent border-bottom py-3 px-4">
              <span class="fw-bold small text-uppercase text-muted">
                <i class="bi bi-clock-history me-1.5 text-primary" />Review Transitions
              </span>
            </div>
            <div class="card-body p-4">
              <div v-if="!report.reviews || report.reviews.length === 0" class="text-muted small text-center py-2">
                No status transitions recorded yet.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="rev in report.reviews"
                  :key="rev.id"
                  class="p-2.5 bg-light border rounded-3 mb-2 small"
                >
                  <div class="fw-semibold text-dark">
                    {{ rev.from_status }} &rarr; {{ rev.to_status }}
                  </div>
                  <div class="text-muted text-2xs">
                    {{ formatDateTime(rev.created_at) }} by {{ rev.reviewer_name }}
                  </div>
                  <div v-if="rev.notes" class="text-secondary fst-italic text-2xs mt-1">
                    {{ rev.notes }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Immutable Audit Trail -->
        <div class="col-12 col-md-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-transparent border-bottom py-3 px-4">
              <span class="fw-bold small text-uppercase text-muted">
                <i class="bi bi-journal-text me-1.5 text-primary" />Audit Trail
              </span>
            </div>
            <div class="card-body p-4">
              <div v-if="!report.audits || report.audits.length === 0" class="text-muted small text-center py-2">
                No audit events recorded.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="aud in report.audits"
                  :key="aud.id"
                  class="p-2.5 bg-light border rounded-3 mb-2 small"
                >
                  <div class="fw-semibold text-dark">{{ aud.action }}</div>
                  <div class="text-muted text-2xs">
                    {{ formatDateTime(aud.created_at) }} &bull; {{ aud.performer_name }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 1: Update Status Modal -->
    <div
      v-if="showStatusModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background-color: rgba(0, 0, 0, 0.5);"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
          <div class="modal-header border-bottom py-3 px-4">
            <h5 class="modal-title fw-bold text-dark">Change Report Status</h5>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="showStatusModal = false"
            />
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label small fw-semibold text-dark">Workflow Status</label>
              <select v-model="statusForm.status" class="form-select">
                <option value="Submitted">Submitted</option>
                <option value="UnderReview">Under Review</option>
                <option value="NeedsMoreInformation">Needs More Information</option>
                <option value="Valid">Valid</option>
                <option value="Invalid">Invalid</option>
                <option value="Closed">Closed</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold text-dark">
                Internal Admin Notes (Private)
              </label>
              <textarea
                v-model="statusForm.notes"
                rows="3"
                placeholder="Confidential notes visible only to Super Admins..."
                class="form-control"
              />
            </div>

            <div>
              <label class="form-label small fw-semibold text-dark">
                Official Decision Reason (Visible to Reporter)
              </label>
              <textarea
                v-model="statusForm.decision_reason"
                rows="2"
                placeholder="Resolution summary visible to reporter if concluded..."
                class="form-control"
              />
            </div>
          </div>

          <div class="modal-footer border-top py-2 px-4">
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-3"
              @click="showStatusModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4 shadow-sm"
              :disabled="isUpdatingStatus"
              @click="handleUpdateStatus"
            >
              <span v-if="isUpdatingStatus" class="spinner-border spinner-border-sm me-1" role="status" />
              <span>{{ isUpdatingStatus ? 'Saving...' : 'Save Status' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 2: Apply Action / Penalty Modal -->
    <div
      v-if="showPenaltyModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background-color: rgba(0, 0, 0, 0.5);"
    >
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
          <div class="modal-header border-bottom py-3 px-4">
            <h5 class="modal-title fw-bold text-danger">
              <i class="bi bi-shield-slash me-1.5" />Apply Administrative Action / Sanction
            </h5>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="showPenaltyModal = false"
            />
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label small fw-semibold text-dark">Sanction Type <span class="text-danger">*</span></label>
              <select v-model="penaltyForm.action_type" class="form-select">
                <option value="Warning">Warning</option>
                <option value="Formal Warning">Formal Warning</option>
                <template v-if="report?.reported_user?.is_jury_panel">
                  <option value="Jury Panel Deactivation">Jury Panel Deactivation</option>
                </template>
                <template v-else>
                  <option value="Profile Correction Required">Profile Correction Required</option>
                  <option value="Temporary Suspension">Temporary Suspension</option>
                  <option value="Permanent Suspension">Permanent Suspension</option>
                  <option value="Feature Restriction">Feature Restriction</option>
                  <option value="Verification Revoked">Verification Revoked</option>
                  <option value="Professional Eligibility Suspension">Professional Eligibility Suspension</option>
                  <option value="HIP / Score Penalty">HIP / Score Penalty</option>
                  <option value="Content Removal">Content Removal</option>
                </template>
              </select>
            </div>

            <!-- Verification Revoked Notice -->
            <div
              v-if="penaltyForm.action_type === 'Verification Revoked'"
              class="alert alert-danger border-0 rounded-3 small mb-3"
            >
              <strong>Notice:</strong> This action suspends the professional's verification, terminates active legal representation assignments, and deactivates client-lawyer representation chats. Re-verification requires formal review via the Professional Verifications portal.
            </div>

            <!-- Professional Eligibility Suspension Configuration -->
            <div v-if="penaltyForm.action_type === 'Professional Eligibility Suspension'" class="p-3 bg-light border rounded-3 mb-3">
              <label class="form-label small fw-semibold text-dark mb-1">
                Eligibility Suspension Duration Mode <span class="text-danger">*</span>
              </label>
              <div class="d-flex gap-4 mb-2">
                <div class="form-check">
                  <input
                    id="eligibility-temp"
                    v-model="penaltyForm.restriction_duration_type"
                    type="radio"
                    value="temporary"
                    class="form-check-input"
                  />
                  <label for="eligibility-temp" class="form-check-label small">Temporary (Specific days)</label>
                </div>
                <div class="form-check">
                  <input
                    id="eligibility-perm"
                    v-model="penaltyForm.restriction_duration_type"
                    type="radio"
                    value="permanent"
                    class="form-check-input"
                  />
                  <label for="eligibility-perm" class="form-check-label small">Permanent (Indefinite)</label>
                </div>
              </div>

              <div v-if="penaltyForm.restriction_duration_type === 'temporary'">
                <label class="form-label small fw-semibold text-dark mb-1">
                  Suspension Duration (Days) <span class="text-danger">*</span>
                </label>
                <input
                  v-model.number="penaltyForm.duration_days"
                  type="number"
                  min="1"
                  max="365"
                  class="form-control"
                  placeholder="7"
                />
                <span class="text-muted text-2xs mt-1 d-block">
                  Enter 1 to 365 days. Prevents new representation requests. Existing active cases continue undisturbed.
                </span>
              </div>
              <div v-else class="text-muted text-2xs">
                Permanent: Prevents new representation requests indefinitely until manually reversed by Super Admin.
              </div>
            </div>

            <!-- Jury Panel Deactivation Configuration -->
            <div v-if="penaltyForm.action_type === 'Jury Panel Deactivation'" class="p-3 bg-light border rounded-3 mb-3">
              <div class="alert alert-secondary border-0 rounded-3 small mb-3">
                <strong>Notice:</strong> Jury Panel Deactivation removes this institutional panel from receiving new case assignments and blocks operational actions on active cases. Historical case records and judgments remain preserved.
              </div>

              <label class="form-label small fw-semibold text-dark mb-1">
                Deactivation Duration Mode <span class="text-danger">*</span>
              </label>
              <div class="d-flex gap-4 mb-2">
                <div class="form-check">
                  <input
                    id="jury-temp"
                    v-model="penaltyForm.restriction_duration_type"
                    type="radio"
                    value="temporary"
                    class="form-check-input"
                  />
                  <label for="jury-temp" class="form-check-label small">Temporary (Specific days)</label>
                </div>
                <div class="form-check">
                  <input
                    id="jury-perm"
                    v-model="penaltyForm.restriction_duration_type"
                    type="radio"
                    value="permanent"
                    class="form-check-input"
                  />
                  <label for="jury-perm" class="form-check-label small">Permanent (Indefinite)</label>
                </div>
              </div>

              <div v-if="penaltyForm.restriction_duration_type === 'temporary'">
                <label class="form-label small fw-semibold text-dark mb-1">
                  Deactivation Duration (Days) <span class="text-danger">*</span>
                </label>
                <input
                  v-model.number="penaltyForm.duration_days"
                  type="number"
                  min="1"
                  max="365"
                  class="form-control"
                  placeholder="7"
                />
                <span class="text-muted text-2xs mt-1 d-block">
                  Enter 1 to 365 days. The panel cannot receive new cases or operate on assigned cases during this period.
                </span>
              </div>
            </div>

            <!-- HIP / Score Penalty Configuration -->
            <div v-if="penaltyForm.action_type === 'HIP / Score Penalty'" class="p-3 bg-light border rounded-3 mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom small">
                <span class="text-muted">Reported Member Current HIP:</span>
                <span class="font-mono fw-bold text-dark fs-6">
                  {{ Number(report?.reported_user?.hip_score ?? 0).toLocaleString() }}
                </span>
              </div>

              <label class="form-label small fw-semibold text-dark mb-1">
                Penalty Points Deduction <span class="text-danger">*</span>
              </label>
              <input
                v-model="penaltyForm.penalty_value"
                type="text"
                placeholder="e.g. 1000.00"
                class="form-control font-mono mb-2"
              />
              <span class="text-muted text-2xs d-block mb-2">
                Minimum: 1.00 &bull; Maximum: 36,825.00 &bull; Permanent deduction until administrative reversal.
              </span>

              <!-- Quick Presets -->
              <div class="d-flex align-items-center gap-1.5 flex-wrap">
                <span class="text-muted text-2xs me-1">Quick Presets:</span>
                <button
                  v-for="chip in [500, 1000, 2500, 5000, 12275]"
                  :key="chip"
                  type="button"
                  class="btn btn-outline-secondary btn-sm py-0 px-2 text-2xs font-mono rounded"
                  @click="penaltyForm.penalty_value = chip.toString()"
                >
                  -{{ chip.toLocaleString() }}
                </button>
              </div>
            </div>

            <!-- Temporary Suspension Duration -->
            <div v-if="penaltyForm.action_type === 'Temporary Suspension'" class="mb-3">
              <label class="form-label small fw-semibold text-dark mb-1">
                Suspension Duration (Days) <span class="text-danger">*</span>
              </label>
              <input
                v-model.number="penaltyForm.duration_days"
                type="number"
                min="1"
                max="365"
                class="form-control"
                placeholder="7"
              />
              <span class="text-muted text-2xs mt-1 d-block">
                Enter 1 to 365 days. Existing tokens will be revoked immediately and login blocked until expiry.
              </span>
            </div>

            <!-- Permanent Suspension Warning Notice -->
            <div
              v-if="penaltyForm.action_type === 'Permanent Suspension'"
              class="alert alert-danger border-0 rounded-3 small mb-3"
            >
              <strong>Notice:</strong> Permanent suspension revokes all active tokens immediately and blocks account access indefinitely.
            </div>

            <!-- Feature Restriction Configuration -->
            <div v-if="penaltyForm.action_type === 'Feature Restriction'" class="p-3 bg-light border rounded-3 mb-3">
              <div class="mb-3">
                <label class="form-label small fw-semibold text-dark mb-1">
                  Restricted Feature <span class="text-danger">*</span>
                </label>
                <select v-model="penaltyForm.penalty_value" class="form-select">
                  <option value="tribunal_participation">Tribunal Participation (Cases, evidence, mediation, hearing)</option>
                  <option value="community_posting">Community Posting (Resource notes, comments)</option>
                  <option value="daily_question_access">Daily Question Access (Answering daily questions)</option>
                  <option value="exam_access">Exam Access (Taking exams & submitting answers)</option>
                  <option value="profile_editing">Profile Editing (General info, experience, education, skills, photos)</option>
                </select>
              </div>

              <label class="form-label small fw-semibold text-dark mb-1">
                Restriction Duration Mode <span class="text-danger">*</span>
              </label>
              <div class="d-flex gap-4 mb-2">
                <div class="form-check">
                  <input
                    id="feature-temp"
                    v-model="penaltyForm.restriction_duration_type"
                    type="radio"
                    value="temporary"
                    class="form-check-input"
                  />
                  <label for="feature-temp" class="form-check-label small">Temporary (Specific days)</label>
                </div>
                <div class="form-check">
                  <input
                    id="feature-perm"
                    v-model="penaltyForm.restriction_duration_type"
                    type="radio"
                    value="permanent"
                    class="form-check-input"
                  />
                  <label for="feature-perm" class="form-check-label small">Permanent (Indefinite)</label>
                </div>
              </div>

              <div v-if="penaltyForm.restriction_duration_type === 'temporary'">
                <label class="form-label small fw-semibold text-dark mb-1">
                  Restriction Duration (Days) <span class="text-danger">*</span>
                </label>
                <input
                  v-model.number="penaltyForm.duration_days"
                  type="number"
                  min="1"
                  max="365"
                  class="form-control"
                  placeholder="7"
                />
                <span class="text-muted text-2xs mt-1 d-block">
                  Enter 1 to 365 days. The user can still log in and use other features, but access to this feature will be blocked.
                </span>
              </div>
            </div>

            <!-- Content Removal Configuration -->
            <div v-if="penaltyForm.action_type === 'Content Removal'" class="p-3 bg-light border rounded-3 mb-3">
              <div class="alert alert-secondary border-0 rounded-3 small mb-3">
                <strong>Notice:</strong> The selected note must belong to the reported user. Removal hides the note while preserving it for audit. Reversal restores it.
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold text-dark mb-1">
                  Content Type <span class="text-danger">*</span>
                </label>
                <select v-model="penaltyForm.content_type" class="form-select">
                  <option value="testament_note">Community Resource Note</option>
                </select>
              </div>

              <div>
                <label class="form-label small fw-semibold text-dark mb-1">
                  Content ID (Note ID) <span class="text-danger">*</span>
                </label>
                <input
                  v-model.number="penaltyForm.content_id"
                  type="number"
                  min="1"
                  class="form-control font-mono"
                  placeholder="e.g. 12"
                />
                <span class="text-muted text-2xs mt-1 d-block">
                  Enter the numeric ID of the Community Resource Note authored by this user.
                </span>
              </div>
            </div>

            <!-- Formal Justification / Reason -->
            <div class="mb-3">
              <label class="form-label small fw-semibold text-dark mb-1">
                Formal Justification / Reason <span class="text-danger">*</span>
              </label>
              <textarea
                v-model="penaltyForm.reason"
                rows="3"
                placeholder="Official policy violation justification (included in sanitized notice dispatched to member)..."
                class="form-control"
              />
            </div>

            <!-- Internal Admin Notes -->
            <div>
              <label class="form-label small fw-semibold text-dark mb-1">Internal Admin Notes (Private)</label>
              <textarea
                v-model="penaltyForm.notes"
                rows="2"
                placeholder="Confidential administrative context..."
                class="form-control"
              />
            </div>
          </div>

          <div class="modal-footer border-top py-2 px-4">
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-3"
              @click="showPenaltyModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-danger rounded-pill px-4 shadow-sm"
              :disabled="isApplyingPenalty"
              @click="handleApplyPenalty"
            >
              <span v-if="isApplyingPenalty" class="spinner-border spinner-border-sm me-1" role="status" />
              <span>{{ isApplyingPenalty ? 'Applying...' : 'Apply Sanction & Notify' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-mono {
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
.text-2xs {
  font-size: 10.5px;
}
.shadow-2xs {
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.bg-purple-subtle {
  background-color: #f3e8ff !important;
}
.text-purple {
  color: #7e22ce !important;
}
.border-purple-subtle {
  border-color: #e9d5ff !important;
}
.whitespace-pre-wrap {
  white-space: pre-wrap;
  word-break: break-word;
}
.min-w-0 {
  min-width: 0;
}
</style>
