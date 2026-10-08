<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { tribunalService } from '@/services/tribunalService';
import type { TribunalDecision, TribunalCaseReport } from '@/types/tribunal';

const props = defineProps<{
  caseId: number;
  caseStatus: string;
}>();

const router = useRouter();

const loading = ref(false);
const error = ref<string | null>(null);
const decision = ref<TribunalDecision | null>(null);
const isPending = ref(true);

// Official Report State
const reports = ref<TribunalCaseReport[]>([]);
const activeReport = ref<TribunalCaseReport | null>(null);
const canAccessReports = ref(false);
const loadingReports = ref(false);
const generatingReport = ref(false);
const downloadingPdf = ref(false);

const loadDecision = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await tribunalService.getTribunalDecision(props.caseId);
    if (res.is_pending || !res.data) {
      isPending.value = true;
      decision.value = null;
    } else {
      isPending.value = false;
      decision.value = res.data;
      // Load reports if decision is published
      await loadReports();
    }
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load tribunal decision.';
  } finally {
    loading.value = false;
  }
};

const loadReports = async () => {
  loadingReports.value = true;
  try {
    const res = await tribunalService.getCaseReports(props.caseId);
    reports.value = res.reports || [];
    activeReport.value = res.active_report || null;
    canAccessReports.value = true;
  } catch (err: any) {
    if (err?.response?.status === 403) {
      canAccessReports.value = false;
    }
  } finally {
    loadingReports.value = false;
  }
};

const handleGenerateReport = async (regenerate: boolean = false) => {
  if (regenerate) {
    const confirmed = await Swal.fire({
      title: 'Regenerate Official Report?',
      text: 'This will issue a new report version and mark the previous version superseded. The audit trail will be preserved.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Regenerate Report',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#2563eb',
    });
    if (!confirmed.isConfirmed) return;
  }

  generatingReport.value = true;
  try {
    const res = await tribunalService.generateFinalCaseReport(props.caseId, regenerate);
    activeReport.value = res.report;
    await loadReports();

    Swal.fire({
      icon: 'success',
      title: regenerate ? 'Report Regenerated' : 'Official Report Generated',
      text: `Report ${res.report.report_number} has been created and cryptographically signed.`,
      timer: 3000,
      showConfirmButton: false,
    });
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Generation Failed',
      text: err?.response?.data?.message || 'Could not generate official report.',
    });
  } finally {
    generatingReport.value = false;
  }
};

const handleDownloadPdf = async () => {
  if (!activeReport.value) return;

  downloadingPdf.value = true;
  try {
    await tribunalService.downloadReportPdf(
      activeReport.value.id,
      `${activeReport.value.report_number}.pdf`
    );
    // Reload report details to reflect incremented download count
    await loadReports();
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Download Failed',
      text: err?.response?.data?.message || 'Could not download the official report PDF.',
    });
  } finally {
    downloadingPdf.value = false;
  }
};

const handleVerifyReport = () => {
  if (!activeReport.value) return;
  router.push(`/tribunal/reports/verify/${activeReport.value.verification_code}`);
};

onMounted(() => {
  loadDecision();
});

const formatOutcome = (outcome: string) => {
  switch (outcome) {
    case 'complaint_upheld': return 'Complaint Upheld';
    case 'complaint_partially_upheld': return 'Complaint Partially Upheld';
    case 'complaint_not_upheld': return 'Complaint Not Upheld';
    case 'dismissed': return 'Dismissed';
    default: return outcome;
  }
};

const formatConclusion = (conclusion: string) => {
  switch (conclusion) {
    case 'established': return 'Established';
    case 'not_established': return 'Not Established';
    case 'partially_established': return 'Partially Established';
    case 'not_applicable': return 'Not Applicable';
    default: return conclusion;
  }
};
</script>

<template>
  <div class="party-decision-tab">
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading Decision...</span>
      </div>
      <p class="text-muted mt-2">Checking Tribunal decision status...</p>
    </div>

    <div v-else-if="error" class="alert alert-danger rounded-4 shadow-sm" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" />{{ error }}
    </div>

    <!-- PENDING DECISION STATE -->
    <div v-else-if="isPending || !decision" class="card border-0 shadow-sm rounded-4 bg-white p-5 text-center">
      <div class="rounded-circle bg-primary bg-opacity-10 text-primary mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
        <i class="bi bi-hourglass-split fs-2" />
      </div>
      <h4 class="fw-bold text-dark mb-2">Tribunal Decision Pending</h4>
      <p class="text-muted mx-auto mb-4" style="max-width: 520px;">
        The hearing for this dispute has concluded and the assigned Jury Panel is currently deliberating. The official Tribunal decision will be published here upon completion.
      </p>

      <!-- Before Final Decision: Official Report Availability Notice -->
      <div class="alert alert-light border rounded-4 mx-auto mb-4 p-3 text-secondary small d-flex align-items-center justify-content-center gap-2" style="max-width: 520px;">
        <i class="bi bi-info-circle-fill text-primary" />
        <span>Official report becomes available after the Tribunal publishes its final decision.</span>
      </div>

      <div class="d-inline-flex align-items-center justify-content-center gap-2 p-2 px-3 bg-light rounded-pill mx-auto text-muted small">
        <i class="bi bi-shield-check text-primary" />
        Current Procedural Status: <strong>{{ props.caseStatus.replace('_', ' ').toUpperCase() }}</strong>
      </div>
    </div>

    <!-- PUBLISHED DECISION VIEW -->
    <div v-else class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
      <!-- Formal Document Header -->
      <div class="p-4 bg-dark text-white border-bottom">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <span class="badge bg-warning text-dark text-uppercase px-3 py-1 fw-bold rounded-pill mb-2">
              Official Tribunal Decision
            </span>
            <h3 class="fw-bold mb-1 font-monospace">{{ decision.decision_number }}</h3>
            <div class="text-light opacity-75 small">
              Published on: <strong>{{ decision.published_at }}</strong> &bull;
              Adjudicated by: <strong>{{ decision.jury_panel?.panel_name || 'Jury Panel' }}</strong> ({{ decision.jury_panel?.panel_code || 'JP' }})
            </div>
          </div>

          <div class="text-end">
            <span
              class="badge fs-6 px-4 py-2 rounded-pill fw-bold text-uppercase"
              :class="{
                'bg-success': decision.outcome === 'complaint_upheld',
                'bg-warning text-dark': decision.outcome === 'complaint_partially_upheld',
                'bg-danger': decision.outcome === 'complaint_not_upheld',
                'bg-secondary': decision.outcome === 'dismissed',
              }"
            >
              {{ formatOutcome(decision.outcome || '') }}
            </span>
          </div>
        </div>
      </div>

      <div class="p-4 p-md-5">
        <!-- OFFICIAL REPORT GENERATION & DOWNLOAD CARD -->
        <div class="card border-0 shadow-sm rounded-4 bg-light mb-5 overflow-hidden border">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                  <i class="bi bi-file-earmark-pdf-fill fs-3" />
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 text-dark">Official Tribunal Case Report</h5>
                    <span v-if="activeReport" class="badge bg-success rounded-pill px-2 py-1 small">
                      <i class="bi bi-patch-check-fill me-1" />Available &bull; v{{ activeReport.version }}
                    </span>
                    <span v-else class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 small">
                      Available for Generation
                    </span>
                  </div>
                  <p class="text-muted small mb-0 mt-1">
                    Certified PDF document featuring official letterhead, page watermarks, QR verification, and SHA-256 cryptographic proof.
                  </p>
                </div>
              </div>

              <!-- Action buttons -->
              <div class="d-flex flex-wrap gap-2">
                <!-- If Report is Generated -->
                <template v-if="activeReport">
                  <button
                    type="button"
                    class="btn btn-primary rounded-pill px-4 fw-semibold d-flex align-items-center gap-2 shadow-sm"
                    :disabled="downloadingPdf"
                    @click="handleDownloadPdf"
                  >
                    <span v-if="downloadingPdf" class="spinner-border spinner-border-sm" role="status" />
                    <i v-else class="bi bi-download" />
                    <span>{{ downloadingPdf ? 'Downloading...' : 'Download PDF' }}</span>
                  </button>

                  <button
                    type="button"
                    class="btn btn-outline-secondary rounded-pill px-3 fw-semibold d-flex align-items-center gap-2"
                    @click="handleVerifyReport"
                  >
                    <i class="bi bi-qr-code" />
                    <span>Verify Report</span>
                  </button>

                  <button
                    v-if="canAccessReports"
                    type="button"
                    class="btn btn-outline-primary btn-sm rounded-pill px-3 d-flex align-items-center gap-1"
                    :disabled="generatingReport"
                    title="Regenerate updated report version"
                    @click="handleGenerateReport(true)"
                  >
                    <span v-if="generatingReport" class="spinner-border spinner-border-sm" role="status" />
                    <i v-else class="bi bi-arrow-repeat" />
                    <span>Regenerate</span>
                  </button>
                </template>

                <!-- If Report is Not Yet Generated -->
                <template v-else-if="canAccessReports">
                  <button
                    type="button"
                    class="btn btn-primary rounded-pill px-4 fw-semibold d-flex align-items-center gap-2 shadow-sm"
                    :disabled="generatingReport"
                    @click="handleGenerateReport(false)"
                  >
                    <span v-if="generatingReport" class="spinner-border spinner-border-sm" role="status" />
                    <i v-else class="bi bi-file-earmark-check" />
                    <span>{{ generatingReport ? 'Generating Report...' : 'Generate Official Report' }}</span>
                  </button>
                </template>

                <!-- Unauthorized User Notice -->
                <div v-else class="text-muted small align-self-center fst-italic">
                  Report generation is restricted to authorized case participants.
                </div>
              </div>
            </div>

            <!-- Report Metadata Details (When active report exists) -->
            <div v-if="activeReport" class="bg-white rounded-3 p-3 border mt-3">
              <div class="row g-3 small">
                <div class="col-6 col-md-3">
                  <span class="text-muted d-block">Report Number</span>
                  <strong class="font-monospace text-primary">{{ activeReport.report_number }}</strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted d-block">Verification Code</span>
                  <strong class="font-monospace text-dark">{{ activeReport.verification_code }}</strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted d-block">Issued Date</span>
                  <span class="text-dark">{{ activeReport.issued_at.substring(0, 10) }}</span>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted d-block">Authenticity Status</span>
                  <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                    <i class="bi bi-shield-check me-1" />Authentic &bull; Valid
                  </span>
                </div>
              </div>

              <!-- Previous versions notice if multiple reports exist -->
              <div v-if="reports.length > 1" class="border-top mt-3 pt-2 small text-muted d-flex justify-content-between align-items-center">
                <span>
                  <i class="bi bi-clock-history me-1" />Version history: {{ reports.length }} versions generated (Current: v{{ activeReport.version }}).
                </span>
                <span class="badge bg-light text-secondary border">Audit Preserved</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Appeal Window Notice -->
        <div v-if="decision.appeal_deadline" class="alert alert-info rounded-4 p-3 d-flex align-items-center justify-content-between gap-3 mb-4">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history fs-4 text-info" />
            <div>
              <strong>Appeal Window Open</strong>
              <div class="small text-muted">
                Parties may seek formal procedural appeal review until <strong>{{ decision.appeal_deadline }}</strong>.
              </div>
            </div>
          </div>
          <span class="badge bg-light text-dark border font-monospace px-3 py-2">
            Deadline: {{ decision.appeal_deadline.substring(0, 10) }}
          </span>
        </div>

        <!-- Section 1: Executive Summary -->
        <div class="mb-5">
          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
            <i class="bi bi-card-text me-2 text-primary" />1. Executive Summary
          </h5>
          <div class="p-3 bg-light rounded-3 text-secondary lh-lg text-break">
            {{ decision.summary }}
          </div>
        </div>

        <!-- Section 2: Findings of Fact -->
        <div class="mb-5">
          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
            <i class="bi bi-check2-all me-2 text-primary" />2. Findings of Fact &amp; Issue Determinations
          </h5>

          <div v-if="!decision.findings?.length" class="text-muted small">
            No public findings recorded.
          </div>

          <div v-else class="d-flex flex-column gap-3">
            <div
              v-for="finding in decision.findings"
              :key="finding.id"
              class="card border rounded-4 p-3 bg-light shadow-none"
            >
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-dark font-monospace">{{ finding.finding_number }}</span>
                  <span class="badge bg-primary text-uppercase">{{ finding.finding_type }}</span>
                  <span
                    class="badge rounded-pill"
                    :class="{
                      'bg-success': finding.conclusion === 'established',
                      'bg-danger': finding.conclusion === 'not_established',
                      'bg-warning text-dark': finding.conclusion === 'partially_established',
                      'bg-secondary': finding.conclusion === 'not_applicable',
                    }"
                  >
                    {{ formatConclusion(finding.conclusion) }}
                  </span>
                </div>
              </div>

              <h6 v-if="finding.title" class="fw-bold text-dark mb-2">{{ finding.title }}</h6>
              <div class="text-secondary lh-base mb-2">
                {{ finding.finding_text }}
              </div>

              <!-- References -->
              <div v-if="finding.evidence?.length || finding.hearing_entries?.length" class="small text-muted border-top pt-2 mt-1">
                <strong>Cited In Support:</strong>
                <span v-for="ev in finding.evidence" :key="ev.id" class="badge bg-white text-dark border me-1">
                  <i class="bi bi-paperclip me-1" />{{ ev.evidence_number }}
                </span>
                <span v-for="h in finding.hearing_entries" :key="h.id" class="badge bg-white text-dark border me-1">
                  <i class="bi bi-mic me-1" />Record Entry #{{ h.sequence_number }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 3: Reasoning & Adjudication -->
        <div class="mb-5">
          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
            <i class="bi bi-journal-check me-2 text-primary" />3. Tribunal Reasoning &amp; Analysis
          </h5>
          <div class="p-4 bg-light rounded-3 text-secondary lh-lg text-break" style="white-space: pre-line;">
            {{ decision.reasoning }}
          </div>
        </div>

        <!-- Section 4: Orders & Remedies -->
        <div class="mb-4">
          <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
            <i class="bi bi-hammer me-2 text-primary" />4. Remedies &amp; Tribunal Orders
          </h5>

          <div v-if="!decision.orders?.length" class="text-muted small p-3 bg-light rounded-3">
            No specific compliance remedies or sanctions ordered in this determination.
          </div>

          <div v-else class="d-flex flex-column gap-3">
            <div
              v-for="order in decision.orders"
              :key="order.id"
              class="card border rounded-4 p-3 bg-light shadow-none"
            >
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-dark font-monospace">{{ order.order_number }}</span>
                  <span class="badge bg-warning text-dark text-uppercase">{{ order.order_type.replace('_', ' ') }}</span>
                  <span v-if="order.target_side" class="badge bg-primary text-capitalize">
                    Target: {{ order.target_side }}
                  </span>
                </div>
                <div v-if="order.deadline_at" class="small text-muted font-monospace">
                  Compliance Deadline: {{ order.deadline_at.substring(0, 10) }}
                </div>
              </div>
              <h6 class="fw-bold text-dark mb-1">{{ order.title }}</h6>
              <div class="text-secondary small lh-base">
                {{ order.description }}
              </div>
            </div>
          </div>
        </div>

        <!-- Certification Footer -->
        <div class="border-top pt-4 text-center text-muted small">
          <i class="bi bi-shield-lock-fill text-primary me-1" />
          This document is an authentic certified determination rendered by the Myintellibook Tribunal under the governing procedural rules.
        </div>
      </div>
    </div>
  </div>
</template>
