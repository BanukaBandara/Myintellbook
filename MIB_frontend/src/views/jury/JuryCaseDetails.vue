<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useJuryPanelStore } from '@/stores/juryPanel';
import { tribunalService } from '@/services/tribunalService';
import JuryHearingTab from '@/components/tribunal/JuryHearingTab.vue';
import JuryDeliberationTab from '@/components/tribunal/JuryDeliberationTab.vue';
import type { TribunalCaseMessage, TribunalMediation } from '@/types/tribunal';

const route = useRoute();
const router = useRouter();
const juryStore = useJuryPanelStore();
const caseId = Number(route.params.id);

const activeTab = ref<'overview' | 'parties' | 'response' | 'evidence' | 'case_room' | 'mediation' | 'hearing' | 'deliberation' | 'timeline'>('overview');
const downloadingEvidenceId = ref<number | null>(null);

// Case Room State
const caseRoomMessages = ref<TribunalCaseMessage[]>([]);
const loadingRoom = ref(false);
const roomError = ref<string | null>(null);
const newMessageBody = ref('');
const sendingMessage = ref(false);

const showNoticeModal = ref(false);
const noticeBody = ref('');
const postingNotice = ref(false);

const showQuestionModal = ref(false);
const questionTarget = ref<'complainant' | 'respondent' | 'both'>('complainant');
const questionBody = ref('');
const postingQuestion = ref(false);

// Mediation State
const mediationData = ref<TribunalMediation | null>(null);
const loadingMediation = ref(false);
const mediationError = ref<string | null>(null);
const offeringMediation = ref(false);

const showEndMediationModal = ref(false);
const endMediationReason = ref('');
const endingMediation = ref(false);

onMounted(async () => {
  if (isNaN(caseId)) {
    router.push('/jury/cases');
    return;
  }
  try {
    await juryStore.fetchCaseDetail(caseId);
  } catch {
    // handled by store error
  }
});

const switchTab = (tab: 'overview' | 'parties' | 'response' | 'evidence' | 'case_room' | 'mediation' | 'hearing' | 'deliberation' | 'timeline') => {
  activeTab.value = tab;
  if (tab === 'case_room') {
    fetchRoomMessages();
  } else if (tab === 'mediation') {
    fetchMediation();
  }
};

// Case Room Actions
const fetchRoomMessages = async () => {
  loadingRoom.value = true;
  roomError.value = null;
  try {
    const res = await tribunalService.getJuryCaseRoomMessages(caseId);
    caseRoomMessages.value = res.data || [];
  } catch (err: any) {
    roomError.value = err?.response?.data?.message || 'Failed to load case room messages.';
  } finally {
    loadingRoom.value = false;
  }
};

const handleSendMessage = async () => {
  if (!newMessageBody.value.trim() || sendingMessage.value) return;
  sendingMessage.value = true;
  try {
    await tribunalService.sendJuryCaseRoomMessage(caseId, {
      body: newMessageBody.value.trim(),
    });
    newMessageBody.value = '';
    await fetchRoomMessages();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to send message.');
  } finally {
    sendingMessage.value = false;
  }
};

const handlePostNotice = async () => {
  if (!noticeBody.value.trim() || postingNotice.value) return;
  postingNotice.value = true;
  try {
    await tribunalService.postJuryProceduralNotice(caseId, {
      body: noticeBody.value.trim(),
    });
    noticeBody.value = '';
    showNoticeModal.value = false;
    await fetchRoomMessages();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to post procedural notice.');
  } finally {
    postingNotice.value = false;
  }
};

const handlePostQuestion = async () => {
  if (!questionBody.value.trim() || postingQuestion.value) return;
  postingQuestion.value = true;
  try {
    await tribunalService.askJuryQuestion(caseId, {
      body: questionBody.value.trim(),
      target_side: questionTarget.value,
    });
    questionBody.value = '';
    showQuestionModal.value = false;
    await fetchRoomMessages();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to post question.');
  } finally {
    postingQuestion.value = false;
  }
};

// Mediation Actions
const fetchMediation = async () => {
  loadingMediation.value = true;
  mediationError.value = null;
  try {
    const res = await tribunalService.getJuryMediation(caseId);
    mediationData.value = res.data || null;
  } catch (err: any) {
    mediationError.value = err?.response?.data?.message || 'Failed to load mediation status.';
  } finally {
    loadingMediation.value = false;
  }
};

const handleOfferMediation = async () => {
  if (offeringMediation.value) return;
  if (!confirm('Offer voluntary mediation to both parties? If both parties consent, the case will enter the Mediation phase.')) {
    return;
  }
  offeringMediation.value = true;
  try {
    await tribunalService.offerJuryMediation(caseId);
    await fetchMediation();
    await juryStore.fetchCaseDetail(caseId);
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to offer mediation.');
  } finally {
    offeringMediation.value = false;
  }
};

const handleEndMediation = async () => {
  if (!endMediationReason.value.trim() || endingMediation.value) return;
  endingMediation.value = true;
  try {
    await tribunalService.endJuryMediation(caseId, {
      reason: endMediationReason.value.trim(),
    });
    endMediationReason.value = '';
    showEndMediationModal.value = false;
    await fetchMediation();
    await juryStore.fetchCaseDetail(caseId);
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to conclude mediation.');
  } finally {
    endingMediation.value = false;
  }
};

const formatStatus = (status?: string): string => {
  if (!status) return '-';
  if (status === 'jury_selection') return 'Awaiting Jury Panel Assignment';
  return status
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatDate = (dateStr?: string | null): string => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const formatDateTime = (dateStr?: string | null): string => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};

const formatFileSize = (bytes?: number | null): string => {
  if (!bytes) return '-';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};

const formatPosition = (pos?: string | null): string => {
  if (!pos) return 'Pending Response';
  switch (pos) {
    case 'accept':
      return 'Accepted Claim';
    case 'deny':
      return 'Denied Claim';
    case 'partially_accept':
      return 'Partially Accepted Claim';
    default:
      return formatStatus(pos);
  }
};

const getStatusBadgeClass = (status?: string): string => {
  switch (status) {
    case 'evidence_collection':
      return 'bg-primary text-white';
    case 'jury_selection':
      return 'bg-info text-dark';
    case 'hearing':
    case 'deliberation':
      return 'bg-warning text-dark';
    case 'decided':
    case 'closed':
    case 'settled':
      return 'bg-success text-white';
    default:
      return 'bg-secondary text-white';
  }
};

const handleDownloadEvidence = async (evidenceId: number, filename?: string) => {
  downloadingEvidenceId.value = evidenceId;
  try {
    await tribunalService.downloadEvidence(caseId, evidenceId, filename);
  } catch (err) {
    console.error('Failed to download evidence:', err);
  } finally {
    downloadingEvidenceId.value = null;
  }
};
</script>

<template>
  <div class="jury-case-details pb-5">
    <!-- Back Navigation & Case Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
      <button
        type="button"
        class="btn btn-outline-secondary btn-sm rounded-pill px-3"
        @click="router.push('/jury/cases')"
      >
        <i class="bi bi-arrow-left me-1" />Back to Assigned Cases
      </button>

      <div v-if="juryStore.currentCase" class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill px-3 py-1" :class="getStatusBadgeClass(juryStore.currentCase.status)">
          {{ formatStatus(juryStore.currentCase.status) }}
        </span>
        <span class="text-muted small">
          Severity: <strong class="text-uppercase text-dark">{{ juryStore.currentCase.severity }}</strong>
        </span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="juryStore.loadingCase" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading case...</span>
      </div>
      <p class="text-muted small mt-2">Loading full case dossier...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="juryStore.caseError" class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
      <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle d-inline-block mx-auto mb-3">
        <i class="bi bi-shield-x display-4" />
      </div>
      <h4 class="fw-bold text-dark mb-2">Access Restricted</h4>
      <p class="text-muted max-w-500 mx-auto mb-4">
        {{ juryStore.caseError }}
      </p>
      <div>
        <button
          type="button"
          class="btn btn-primary rounded-pill px-4"
          @click="router.push('/jury/cases')"
        >
          Return to Assigned Cases
        </button>
      </div>
    </div>

    <!-- Main Case Content -->
    <div v-else-if="juryStore.currentCase">
      <!-- Case Header Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-body p-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fs-5 fw-bold font-monospace text-primary">
                  {{ juryStore.currentCase.case_number }}
                </span>
                <span class="badge bg-light text-dark border">
                  {{ juryStore.currentCase.category }}
                </span>
              </div>
              <h3 class="fw-bold text-dark mb-2">
                {{ juryStore.currentCase.title }}
              </h3>
              <p class="text-muted small mb-0">
                Submitted on {{ formatDate(juryStore.currentCase.submitted_at) }} &bull;
                Assigned to <strong>{{ juryStore.currentCase.assigned_panel.panel_code }} — {{ juryStore.currentCase.assigned_panel.panel_name }}</strong>
              </p>
            </div>

            <!-- Panel Role Indicator -->
            <div class="bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-4 p-3 text-end">
              <div class="text-primary small text-uppercase fw-semibold">Adjudicating Panel</div>
              <div class="fw-bold text-dark fs-6">Tribunal Jury Panel</div>
              <div class="small text-muted font-monospace">{{ juryStore.currentCase.assigned_panel.panel_code }}</div>
            </div>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-light px-4 border-top">
          <ul class="nav nav-tabs border-0">
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'overview', 'text-muted': activeTab !== 'overview' }"
                @click="switchTab('overview')"
              >
                <i class="bi bi-file-earmark-text me-1" />Overview
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'parties', 'text-muted': activeTab !== 'parties' }"
                @click="switchTab('parties')"
              >
                <i class="bi bi-people me-1" />Parties &amp; Representation
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'response', 'text-muted': activeTab !== 'response' }"
                @click="switchTab('response')"
              >
                <i class="bi bi-reply me-1" />Respondent Response
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'evidence', 'text-muted': activeTab !== 'evidence' }"
                @click="switchTab('evidence')"
              >
                <i class="bi bi-paperclip me-1" />Evidence
                <span class="badge bg-secondary rounded-pill ms-1">
                  {{ juryStore.currentCase.evidence.length }}
                </span>
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'case_room', 'text-muted': activeTab !== 'case_room' }"
                @click="switchTab('case_room')"
              >
                <i class="bi bi-chat-square-text me-1" />Case Room
                <span v-if="caseRoomMessages.length > 0" class="badge bg-primary rounded-pill ms-1">
                  {{ caseRoomMessages.length }}
                </span>
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'mediation', 'text-muted': activeTab !== 'mediation' }"
                @click="switchTab('mediation')"
              >
                <i class="bi bi-shield-check me-1" />Mediation
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'hearing', 'text-muted': activeTab !== 'hearing' }"
                @click="switchTab('hearing')"
              >
                <i class="bi bi-mic me-1" />Hearing
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'deliberation', 'text-muted': activeTab !== 'deliberation' }"
                @click="switchTab('deliberation')"
              >
                <i class="bi bi-journal-text me-1" />Deliberation &amp; Decision
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link border-0 py-3 px-3 fw-semibold"
                :class="{ 'active text-primary border-bottom border-primary border-3 bg-transparent': activeTab === 'timeline', 'text-muted': activeTab !== 'timeline' }"
                @click="switchTab('timeline')"
              >
                <i class="bi bi-clock-history me-1" />Timeline
              </button>
            </li>
          </ul>
        </div>
      </div>

      <!-- Tab 1: Overview -->
      <div v-if="activeTab === 'overview'" class="row g-4">
        <div class="col-12 col-lg-8">
          <!-- Description -->
          <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-body p-4">
              <h5 class="fw-bold text-dark mb-3">Case Description &amp; Claims</h5>
              <div class="p-3 bg-light rounded-3 text-secondary lh-lg mb-0 text-break">
                {{ juryStore.currentCase.description || 'No description provided.' }}
              </div>
            </div>
          </div>

          <!-- Requested Resolution -->
          <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-body p-4">
              <h5 class="fw-bold text-dark mb-3">Requested Resolution</h5>
              <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 text-primary-emphasis lh-lg text-break">
                <i class="bi bi-bullseye me-2 text-primary" />
                {{ juryStore.currentCase.requested_resolution || 'No specific resolution requested.' }}
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <!-- Assignment Information -->
          <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-header bg-white border-bottom p-3">
              <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-shield-check me-2 text-primary" />Assignment Details
              </h6>
            </div>
            <div class="card-body p-3">
              <ul class="list-unstyled mb-0 small">
                <li class="mb-2 d-flex justify-content-between">
                  <span class="text-muted">Assigned Panel:</span>
                  <span class="fw-bold font-monospace">{{ juryStore.currentCase.assigned_panel.panel_code }}</span>
                </li>
                <li class="mb-2 d-flex justify-content-between">
                  <span class="text-muted">Panel Name:</span>
                  <span class="fw-semibold">{{ juryStore.currentCase.assigned_panel.panel_name }}</span>
                </li>
                <li class="mb-2 d-flex justify-content-between">
                  <span class="text-muted">Assignment Method:</span>
                  <span class="badge bg-light text-dark border text-capitalize">
                    {{ juryStore.currentCase.assigned_panel.assignment_method }}
                  </span>
                </li>
                <li class="d-flex justify-content-between">
                  <span class="text-muted">Assigned At:</span>
                  <span>{{ formatDate(juryStore.currentCase.assigned_panel.assigned_at) }}</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Mediation Status -->
          <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-header bg-white border-bottom p-3">
              <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-chat-heart me-2 text-info" />Mediation Status
              </h6>
            </div>
            <div class="card-body p-3">
              <div v-if="juryStore.currentCase.mediation_summary" class="small">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Status:</span>
                  <span class="badge bg-info text-dark">
                    {{ formatStatus(juryStore.currentCase.mediation_summary.status) }}
                  </span>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="text-muted">Started:</span>
                  <span>{{ formatDate(juryStore.currentCase.mediation_summary.started_at) }}</span>
                </div>
              </div>
              <div v-else class="text-muted small">
                No active mediation recorded for this dispute. Proceeding in tribunal adjudicative track.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: Parties & Representation -->
      <div v-if="activeTab === 'parties'" class="row g-4">
        <!-- Complainant -->
        <div class="col-12 col-md-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-white border-bottom p-3">
              <span class="badge bg-primary text-white rounded-pill px-3 py-1 me-2">Complainant</span>
              <span class="fw-bold text-dark">Filing Party</span>
            </div>
            <div class="card-body p-4">
              <div class="d-flex align-items-center mb-4">
                <div class="rounded-circle bg-light p-3 text-primary me-3">
                  <i class="bi bi-person fs-3" />
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">
                    {{ juryStore.currentCase.parties.complainant.name }}
                  </h5>
                  <span class="text-muted small">Party ID: #{{ juryStore.currentCase.parties.complainant.id }}</span>
                </div>
              </div>

              <!-- Legal Representative -->
              <div class="border-top pt-3">
                <h6 class="fw-bold text-dark small text-uppercase mb-2">Legal Representation</h6>
                <div v-if="juryStore.currentCase.representation.complainant_lawyer" class="p-3 bg-light rounded-3 small">
                  <div class="fw-bold text-dark mb-1">
                    <i class="bi bi-briefcase me-1 text-primary" />
                    {{ juryStore.currentCase.representation.complainant_lawyer.representative_name }}
                  </div>
                  <div v-if="juryStore.currentCase.representation.complainant_lawyer.bar_number" class="text-muted">
                    Bar Number: {{ juryStore.currentCase.representation.complainant_lawyer.bar_number }}
                    ({{ juryStore.currentCase.representation.complainant_lawyer.jurisdiction || 'Jurisdiction Verified' }})
                  </div>
                  <div class="text-muted mt-1">
                    Appointed: {{ formatDate(juryStore.currentCase.representation.complainant_lawyer.assigned_at) }}
                  </div>
                </div>
                <div v-else class="text-muted small p-3 bg-light rounded-3">
                  <i class="bi bi-info-circle me-1" />Self-represented (no legal counsel assigned).
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Respondent -->
        <div class="col-12 col-md-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-header bg-white border-bottom p-3">
              <span class="badge bg-warning text-dark rounded-pill px-3 py-1 me-2">Respondent</span>
              <span class="fw-bold text-dark">Responding Party</span>
            </div>
            <div class="card-body p-4">
              <div class="d-flex align-items-center mb-4">
                <div class="rounded-circle bg-light p-3 text-warning me-3">
                  <i class="bi bi-person fs-3" />
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">
                    {{ juryStore.currentCase.parties.respondent.name }}
                  </h5>
                  <span class="text-muted small">Party ID: #{{ juryStore.currentCase.parties.respondent.id }}</span>
                </div>
              </div>

              <!-- Legal Representative -->
              <div class="border-top pt-3">
                <h6 class="fw-bold text-dark small text-uppercase mb-2">Legal Representation</h6>
                <div v-if="juryStore.currentCase.representation.respondent_lawyer" class="p-3 bg-light rounded-3 small">
                  <div class="fw-bold text-dark mb-1">
                    <i class="bi bi-briefcase me-1 text-primary" />
                    {{ juryStore.currentCase.representation.respondent_lawyer.representative_name }}
                  </div>
                  <div v-if="juryStore.currentCase.representation.respondent_lawyer.bar_number" class="text-muted">
                    Bar Number: {{ juryStore.currentCase.representation.respondent_lawyer.bar_number }}
                    ({{ juryStore.currentCase.representation.respondent_lawyer.jurisdiction || 'Jurisdiction Verified' }})
                  </div>
                  <div class="text-muted mt-1">
                    Appointed: {{ formatDate(juryStore.currentCase.representation.respondent_lawyer.assigned_at) }}
                  </div>
                </div>
                <div v-else class="text-muted small p-3 bg-light rounded-3">
                  <i class="bi bi-info-circle me-1" />Self-represented (no legal counsel assigned).
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 3: Response -->
      <div v-if="activeTab === 'response'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
          <div v-if="juryStore.currentCase.response">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
              <div>
                <span class="badge bg-light text-dark border me-2">Formal Position</span>
                <span class="fw-bold fs-5 text-dark">
                  {{ formatPosition(juryStore.currentCase.response.position) }}
                </span>
              </div>
              <div class="text-muted small">
                Submitted on {{ formatDateTime(juryStore.currentCase.response.submitted_at) }}
              </div>
            </div>

            <div class="border-top pt-3">
              <h6 class="fw-bold text-dark mb-2">Statement of Defense / Response</h6>
              <div class="p-3 bg-light rounded-3 text-secondary lh-lg mb-0 text-break">
                {{ juryStore.currentCase.response.response_text || 'No response statement text provided.' }}
              </div>
            </div>
          </div>

          <div v-else class="text-center py-5">
            <i class="bi bi-clock-history text-muted display-4 d-block mb-3" />
            <h5 class="fw-semibold text-dark">Awaiting Formal Response</h5>
            <p class="text-muted small mb-0">
              The respondent has not yet filed their formal defense or statement.
            </p>
          </div>
        </div>
      </div>

      <!-- Tab 4: Evidence -->
      <div v-if="activeTab === 'evidence'">
        <!-- Evidence Notice -->
        <div class="alert alert-info border-0 rounded-4 shadow-sm mb-4 d-flex align-items-center">
          <i class="bi bi-info-circle-fill fs-4 me-3 text-info" />
          <div class="small">
            <strong>Evidence Review Access:</strong> As the assigned Jury Panel, you have full access to inspect,
            examine, and securely download all evidence files submitted by the parties.
          </div>
        </div>

        <div v-if="juryStore.currentCase.evidence.length === 0" class="card border-0 shadow-sm rounded-4 bg-white text-center py-5">
          <i class="bi bi-folder text-muted display-4 d-block mb-3" />
          <h5 class="fw-semibold text-dark">No Evidence Submitted Yet</h5>
          <p class="text-muted small mb-0">
            Case is currently in evidence collection stage.
          </p>
        </div>

        <div v-else class="row g-3">
          <div v-for="item in juryStore.currentCase.evidence" :key="item.id" class="col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
              <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                  <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span class="badge bg-light text-primary border font-monospace fw-bold">
                        {{ item.evidence_number }}
                      </span>
                      <span class="badge bg-light text-dark border">
                        {{ item.category }}
                      </span>
                      <span class="badge rounded-pill bg-secondary">
                        {{ item.status }}
                      </span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">{{ item.title }}</h5>
                    <div class="text-muted small">
                      Uploaded by <strong>{{ item.uploaded_by.name }}</strong> on {{ formatDate(item.created_at) }}
                    </div>
                  </div>

                  <!-- Secure Download Button -->
                  <div>
                    <button
                      type="button"
                      class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm"
                      :disabled="downloadingEvidenceId === item.id"
                      @click="handleDownloadEvidence(item.id, item.original_filename)"
                    >
                      <i class="bi bi-download me-1" />
                      {{ downloadingEvidenceId === item.id ? 'Downloading...' : 'Download File' }}
                    </button>
                  </div>
                </div>

                <p v-if="item.description" class="text-secondary small mb-3">
                  {{ item.description }}
                </p>

                <!-- File Info Bar -->
                <div class="p-2 bg-light rounded-3 d-flex flex-wrap align-items-center gap-3 small text-muted">
                  <span>
                    <i class="bi bi-file-earmark me-1" />
                    <strong>{{ item.original_filename }}</strong>
                  </span>
                  <span>Size: {{ formatFileSize(item.file_size) }}</span>
                  <span>Type: {{ item.mime_type }}</span>
                </div>

                <!-- Challenges if any -->
                <div v-if="item.challenges && item.challenges.length > 0" class="mt-3 border-top pt-3">
                  <h6 class="fw-bold text-danger small mb-2">
                    <i class="bi bi-exclamation-triangle me-1" />
                    Evidence Challenges ({{ item.challenges.length }})
                  </h6>
                  <div
                    v-for="ch in item.challenges"
                    :key="ch.id"
                    class="p-2 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 mb-2 small"
                  >
                    <div class="d-flex justify-content-between mb-1">
                      <span class="fw-bold text-danger">Challenger: {{ ch.challenger_name }}</span>
                      <span class="badge bg-danger">{{ ch.status }}</span>
                    </div>
                    <div class="text-dark">{{ ch.reason }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 5: Case Room -->
      <div v-if="activeTab === 'case_room'">
        <!-- Case Room Header Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
              <div>
                <h5 class="fw-bold text-dark mb-1">
                  <i class="bi bi-chat-square-text text-primary me-2" />Shared Tribunal Case Room
                </h5>
                <p class="text-muted small mb-0">
                  Transparent procedural record visible to Complainant, Respondent, Legal Counsel, and the Tribunal Jury Panel.
                </p>
              </div>

              <!-- Jury Actions -->
              <div class="d-flex flex-wrap gap-2">
                <button
                  type="button"
                  class="btn btn-outline-primary btn-sm rounded-pill px-3"
                  :disabled="loadingRoom"
                  @click="fetchRoomMessages"
                >
                  <i class="bi bi-arrow-clockwise me-1" />Refresh
                </button>
                <button
                  type="button"
                  class="btn btn-warning text-dark btn-sm rounded-pill px-3 fw-semibold"
                  @click="showNoticeModal = true"
                >
                  <i class="bi bi-megaphone me-1" />Post Procedural Notice
                </button>
                <button
                  type="button"
                  class="btn btn-info text-dark btn-sm rounded-pill px-3 fw-semibold"
                  @click="showQuestionModal = true"
                >
                  <i class="bi bi-question-circle me-1" />Ask Targeted Question
                </button>
              </div>
            </div>

            <!-- Transparency & Privacy Notice -->
            <div class="alert alert-info border-0 rounded-3 mt-3 mb-0 small d-flex align-items-start gap-2">
              <i class="bi bi-shield-lock-fill fs-5 mt-n1 text-primary" />
              <div>
                <strong>Strict Confidentiality Rule:</strong> Private lawyer-client chats are completely isolated and never accessible to the Jury Panel. All communications posted in this room are transparently visible to both parties and counsel. Direct or private channels with individual parties are strictly prohibited.
              </div>
            </div>
          </div>
        </div>

        <!-- Room Messages Stream -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
          <div class="card-body p-4">
            <!-- Loading -->
            <div v-if="loadingRoom" class="text-center py-5">
              <div class="spinner-border spinner-border-sm text-primary" role="status" />
              <span class="text-muted small ms-2">Loading case room dialogue...</span>
            </div>

            <!-- Error -->
            <div v-else-if="roomError" class="alert alert-danger rounded-3 text-center my-3">
              {{ roomError }}
            </div>

            <!-- Empty -->
            <div v-else-if="caseRoomMessages.length === 0" class="text-center py-5 text-muted">
              <i class="bi bi-chat-dots display-5 d-block mb-2 text-secondary opacity-50" />
              <p class="mb-0">No messages or procedural notices in this room yet.</p>
              <small class="text-muted">Use the action buttons above or post a shared message below to start.</small>
            </div>

            <!-- Messages List -->
            <div v-else class="case-room-feed d-flex flex-column gap-3">
              <div
                v-for="msg in caseRoomMessages"
                :key="msg.id"
                class="message-card p-3 rounded-4 border transition-all"
                :class="{
                  'border-warning bg-warning bg-opacity-10': msg.message_type === 'procedural_notice',
                  'border-info bg-info bg-opacity-10': msg.message_type === 'adjudicator_question',
                  'border-primary bg-primary bg-opacity-10': msg.sender_case_role === 'jury_panel' && msg.message_type === 'message',
                  'bg-light border-light-subtle': msg.sender_case_role !== 'jury_panel' && msg.message_type === 'message',
                }"
              >
                <!-- Message Top Bar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                  <div class="d-flex align-items-center gap-2">
                    <span
                      class="badge rounded-pill px-2 py-1"
                      :class="{
                        'bg-dark text-white': msg.sender_case_role === 'jury_panel',
                        'bg-primary text-white': msg.sender_case_role === 'complainant' || msg.sender_case_role === 'complainant_representative',
                        'bg-secondary text-white': msg.sender_case_role === 'respondent' || msg.sender_case_role === 'respondent_representative',
                      }"
                    >
                      {{ msg.sender_role_label }}
                    </span>
                    <strong class="text-dark">{{ msg.sender_name }}</strong>

                    <!-- Type Badges -->
                    <span v-if="msg.message_type === 'procedural_notice'" class="badge bg-warning text-dark border">
                      <i class="bi bi-megaphone-fill me-1" />Procedural Notice
                    </span>
                    <span v-else-if="msg.message_type === 'adjudicator_question'" class="badge bg-info text-dark border">
                      <i class="bi bi-question-circle-fill me-1" />Jury Panel Question
                    </span>

                    <!-- Target Side -->
                    <span v-if="msg.target_side" class="badge bg-light text-dark border">
                      Target: <strong class="text-capitalize">{{ msg.target_side === 'both' ? 'Both Parties' : msg.target_side }}</strong>
                    </span>
                  </div>

                  <span class="text-muted small font-monospace">
                    {{ formatDateTime(msg.created_at) }}
                  </span>
                </div>

                <!-- Message Body -->
                <div class="text-dark lh-base text-break mb-2 ps-1">
                  {{ msg.body }}
                </div>

                <!-- Related Evidence Reference if present -->
                <div v-if="msg.related_evidence" class="mt-2 pt-2 border-top border-light-subtle small">
                  <span class="text-muted me-1"><i class="bi bi-paperclip" />Referenced Evidence:</span>
                  <strong class="text-primary">{{ msg.related_evidence.evidence_number }} — {{ msg.related_evidence.title }}</strong>
                </div>

                <!-- Threaded Responses to Question -->
                <div v-if="msg.responses && msg.responses.length > 0" class="mt-3 pt-3 border-top border-info border-opacity-25 ps-3">
                  <h6 class="text-muted small fw-bold mb-2">
                    <i class="bi bi-reply-all me-1 text-info" />Responses ({{ msg.responses.length }}):
                  </h6>
                  <div
                    v-for="resp in msg.responses"
                    :key="resp.id"
                    class="p-2 bg-white rounded-3 border mb-2 small"
                  >
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <span class="fw-bold text-dark">
                        <span class="badge bg-secondary text-white me-1">{{ resp.sender_role_label }}</span>
                        {{ resp.sender_name }}
                      </span>
                      <span class="text-muted small font-monospace">{{ formatDateTime(resp.created_at) }}</span>
                    </div>
                    <div class="text-secondary ps-1">{{ resp.body }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Send Shared Message Input -->
            <div class="mt-4 pt-4 border-top">
              <h6 class="fw-bold text-dark mb-2">
                <i class="bi bi-pencil-square text-primary me-1" />Post Shared Jury Panel Message
              </h6>
              <div class="input-group">
                <textarea
                  v-model="newMessageBody"
                  class="form-control"
                  rows="2"
                  placeholder="Type a shared room message to both parties and counsel..."
                  :disabled="sendingMessage"
                />
                <button
                  type="button"
                  class="btn btn-primary px-4 fw-semibold"
                  :disabled="sendingMessage || !newMessageBody.trim()"
                  @click="handleSendMessage"
                >
                  <span v-if="sendingMessage" class="spinner-border spinner-border-sm me-1" />
                  <i v-else class="bi bi-send me-1" />Send
                </button>
              </div>
              <small class="text-muted">
                Sent officially as <strong>Tribunal Jury Panel ({{ juryStore.currentCase.assigned_panel.panel_code }})</strong>.
              </small>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 6: Mediation -->
      <div v-if="activeTab === 'mediation'">
        <!-- Mediation Status & Oversight Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
              <div>
                <h5 class="fw-bold text-dark mb-1">
                  <i class="bi bi-shield-check text-primary me-2" />Voluntary Mediation &amp; Settlement Oversight
                </h5>
                <p class="text-muted small mb-0">
                  The Tribunal Jury Panel oversees party negotiations procedurally. Binding acceptance and proposals remain strictly with principal parties.
                </p>
              </div>

              <!-- Jury Mediation Actions -->
              <div class="d-flex flex-wrap gap-2">
                <button
                  type="button"
                  class="btn btn-outline-primary btn-sm rounded-pill px-3"
                  :disabled="loadingMediation"
                  @click="fetchMediation"
                >
                  <i class="bi bi-arrow-clockwise me-1" />Refresh
                </button>

                <!-- Offer Mediation button (if none or ended) -->
                <button
                  v-if="!mediationData || mediationData.status === 'failed' || mediationData.status === 'declined'"
                  type="button"
                  class="btn btn-success text-white btn-sm rounded-pill px-3 fw-semibold"
                  :disabled="offeringMediation"
                  @click="handleOfferMediation"
                >
                  <span v-if="offeringMediation" class="spinner-border spinner-border-sm me-1" />
                  <i v-else class="bi bi-heart-handshake me-1" />Offer Mediation
                </button>

                <!-- End Mediation button (if active or awaiting consent) -->
                <button
                  v-if="mediationData && (mediationData.status === 'active' || mediationData.status === 'awaiting_consent' || mediationData.status === 'offered')"
                  type="button"
                  class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold"
                  @click="showEndMediationModal = true"
                >
                  <i class="bi bi-x-circle me-1" />End Mediation as Unsuccessful
                </button>
              </div>
            </div>

            <!-- Loading -->
            <div v-if="loadingMediation" class="text-center py-5">
              <div class="spinner-border spinner-border-sm text-primary" role="status" />
              <span class="text-muted small ms-2">Loading mediation oversight data...</span>
            </div>

            <!-- Error -->
            <div v-else-if="mediationError" class="alert alert-danger rounded-3 text-center my-3">
              {{ mediationError }}
            </div>

            <!-- No Mediation Active -->
            <div v-else-if="!mediationData" class="p-4 bg-light rounded-4 text-center">
              <i class="bi bi-chat-heart display-6 text-muted mb-2 d-block" />
              <h6 class="fw-bold text-dark">No Mediation Process Underway</h6>
              <p class="text-muted small max-w-500 mx-auto mb-3">
                Voluntary mediation has not been initiated for this dispute. As the assigned Jury Panel, you may offer structured mediation to both parties.
              </p>
              <button
                type="button"
                class="btn btn-primary rounded-pill px-4"
                :disabled="offeringMediation"
                @click="handleOfferMediation"
              >
                <span v-if="offeringMediation" class="spinner-border spinner-border-sm me-1" />
                <i v-else class="bi bi-heart-handshake me-1" />Offer Voluntary Mediation
              </button>
            </div>

            <!-- Active Mediation Details -->
            <div v-else>
              <!-- Mediation Overview Banner -->
              <div class="p-3 bg-light rounded-4 mb-4 border d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                  <div class="small text-muted text-uppercase fw-semibold">Current Mediation Status</div>
                  <div class="d-flex align-items-center gap-2 mt-1">
                    <span
                      class="badge rounded-pill px-3 py-1 fs-6"
                      :class="{
                        'bg-warning text-dark': mediationData.status === 'offered' || mediationData.status === 'awaiting_consent',
                        'bg-primary text-white': mediationData.status === 'active',
                        'bg-success text-white': mediationData.status === 'settled',
                        'bg-danger text-white': mediationData.status === 'failed' || mediationData.status === 'declined',
                      }"
                    >
                      {{ formatStatus(mediationData.status) }}
                    </span>
                    <span class="text-muted small">
                      Offered by: <strong>{{ mediationData.initiator_name }}</strong>
                    </span>
                  </div>
                </div>

                <div v-if="mediationData.failure_reason" class="text-danger small">
                  <strong>Reason:</strong> {{ mediationData.failure_reason }}
                </div>
              </div>

              <!-- Parties' Consent State -->
              <div class="row g-3 mb-4">
                <div
                  v-for="consent in mediationData.consents"
                  :key="consent.id"
                  class="col-12 col-md-6"
                >
                  <div class="p-3 border rounded-4 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <strong class="text-dark text-capitalize">{{ consent.side }} Consent</strong>
                      <span
                        class="badge rounded-pill px-2.5 py-1"
                        :class="{
                          'bg-success text-white': consent.response === 'accepted',
                          'bg-warning text-dark': consent.response === 'pending',
                          'bg-danger text-white': consent.response === 'declined',
                        }"
                      >
                        {{ formatStatus(consent.response) }}
                      </span>
                    </div>
                    <div class="small text-muted">
                      Party: <strong>{{ consent.user_name }}</strong>
                    </div>
                    <div v-if="consent.responded_at" class="small text-muted">
                      Responded: {{ formatDateTime(consent.responded_at) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Settlement Proposals History (Read-Only) -->
              <div class="mb-4">
                <h6 class="fw-bold text-dark mb-3">
                  <i class="bi bi-clock-history me-1 text-primary" />Settlement Proposals History (Read-Only)
                </h6>

                <div v-if="!mediationData.proposals || mediationData.proposals.length === 0" class="text-muted small py-3 text-center bg-light rounded-3">
                  No settlement proposals have been submitted yet.
                </div>

                <div v-else class="d-flex flex-column gap-3">
                  <div
                    v-for="prop in mediationData.proposals"
                    :key="prop.id"
                    class="p-3 border rounded-4 bg-white"
                  >
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                      <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-secondary text-white">Version {{ prop.version_number }}</span>
                        <strong class="text-dark">Proposed by {{ prop.proposer_name }} ({{ prop.proposed_by_side }})</strong>
                      </div>
                      <div class="d-flex align-items-center gap-2">
                        <span
                          class="badge rounded-pill"
                          :class="{
                            'bg-success text-white': prop.status === 'accepted',
                            'bg-warning text-dark': prop.status === 'pending',
                            'bg-danger text-white': prop.status === 'rejected',
                            'bg-secondary text-white': prop.status === 'countered' || prop.status === 'superseded',
                          }"
                        >
                          {{ formatStatus(prop.status) }}
                        </span>
                        <span class="text-muted small font-monospace">{{ formatDateTime(prop.created_at) }}</span>
                      </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 text-secondary lh-base mb-2">
                      {{ prop.terms }}
                    </div>

                    <div class="small text-muted">
                      Complainant: <span :class="prop.accepted_by_complainant ? 'text-success fw-bold' : 'text-muted'">{{ prop.accepted_by_complainant ? 'Accepted' : 'Pending' }}</span> &bull;
                      Respondent: <span :class="prop.accepted_by_respondent ? 'text-success fw-bold' : 'text-muted'">{{ prop.accepted_by_respondent ? 'Accepted' : 'Pending' }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Finalized Agreement if Settled -->
              <div v-if="mediationData.settlement_agreement" class="p-4 border border-success rounded-4 bg-success bg-opacity-10 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="fw-bold text-success mb-0">
                    <i class="bi bi-file-earmark-check-fill me-1" />Finalized Settlement Agreement
                  </h6>
                  <span class="font-monospace fw-bold text-dark">{{ mediationData.settlement_agreement.agreement_number }}</span>
                </div>
                <div class="p-3 bg-white rounded-3 text-dark lh-base mb-2">
                  {{ mediationData.settlement_agreement.terms_snapshot }}
                </div>
                <div class="small text-muted">
                  Finalized at: {{ formatDateTime(mediationData.settlement_agreement.finalized_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Hearing (Step 5) -->
      <div v-if="activeTab === 'hearing'">
        <JuryHearingTab
          :case-id="caseId"
          :case-status="juryStore.currentCase.status"
          :case-number="juryStore.currentCase.case_number"
          @case-updated="juryStore.fetchCaseDetail(caseId)"
        />
      </div>

      <!-- Tab: Deliberation (Step 6) -->
      <div v-if="activeTab === 'deliberation'">
        <JuryDeliberationTab
          :case-id="caseId"
          :case-status="juryStore.currentCase.status"
          :case-number="juryStore.currentCase.case_number"
          @case-updated="juryStore.fetchCaseDetail(caseId)"
        />
      </div>

      <!-- Tab 7: Timeline -->
      <div v-if="activeTab === 'timeline'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
          <h5 class="fw-bold text-dark mb-3">Case History &amp; Proceedings Timeline</h5>

          <div v-if="juryStore.currentCase.timeline.length === 0" class="text-muted small py-4 text-center">
            No events recorded yet.
          </div>

          <div v-else class="timeline-list">
            <div
              v-for="evt in juryStore.currentCase.timeline"
              :key="evt.id"
              class="d-flex gap-3 mb-3 pb-3 border-bottom"
            >
              <div class="text-primary pt-1">
                <i class="bi bi-dot fs-2" />
              </div>
              <div class="flex-grow-1">
                <div class="d-flex flex-wrap justify-content-between">
                  <strong class="text-dark">{{ formatStatus(evt.event_type) }}</strong>
                  <span class="text-muted small">{{ formatDateTime(evt.created_at) }}</span>
                </div>
                <div class="small text-muted">
                  Recorded by: <strong>{{ evt.actor_name }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: Post Procedural Notice -->
    <div
      v-if="showNoticeModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0, 0, 0, 0.5);"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-bottom px-4 pt-4 pb-2">
            <h5 class="modal-title fw-bold text-dark">
              <i class="bi bi-megaphone-fill text-warning me-2" />Post Procedural Notice
            </h5>
            <button type="button" class="btn-close" @click="showNoticeModal = false" />
          </div>
          <div class="modal-body px-4 py-3">
            <p class="text-muted small">
              Procedural notices are formal directions issued by the Tribunal Jury Panel. All parties and counsel will be officially notified.
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold text-dark small">Notice Text</label>
              <textarea
                v-model="noticeBody"
                class="form-control"
                rows="4"
                placeholder="e.g. Both parties must complete remaining evidence submissions before 10 October 2026."
              />
            </div>
          </div>
          <div class="modal-footer border-top px-4 py-3 bg-light rounded-bottom-4">
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-3"
              @click="showNoticeModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-warning text-dark rounded-pill px-4 fw-semibold"
              :disabled="postingNotice || !noticeBody.trim()"
              @click="handlePostNotice"
            >
              <span v-if="postingNotice" class="spinner-border spinner-border-sm me-1" />
              <i v-else class="bi bi-check-circle me-1" />Issue Notice
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 2: Ask Targeted Question -->
    <div
      v-if="showQuestionModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0, 0, 0, 0.5);"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-bottom px-4 pt-4 pb-2">
            <h5 class="modal-title fw-bold text-dark">
              <i class="bi bi-question-circle-fill text-info me-2" />Ask Targeted Question
            </h5>
            <button type="button" class="btn-close" @click="showQuestionModal = false" />
          </div>
          <div class="modal-body px-4 py-3">
            <p class="text-muted small">
              The targeted party (and their active legal counsel) will be invited to respond formally in this Case Room.
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold text-dark small">Target Recipient</label>
              <select v-model="questionTarget" class="form-select">
                <option value="complainant">Complainant Side</option>
                <option value="respondent">Respondent Side</option>
                <option value="both">Both Parties</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold text-dark small">Question Content</label>
              <textarea
                v-model="questionBody"
                class="form-control"
                rows="4"
                placeholder="e.g. Respondent, please confirm the date of the disputed communication."
              />
            </div>
          </div>
          <div class="modal-footer border-top px-4 py-3 bg-light rounded-bottom-4">
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-3"
              @click="showQuestionModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-info text-dark rounded-pill px-4 fw-semibold"
              :disabled="postingQuestion || !questionBody.trim()"
              @click="handlePostQuestion"
            >
              <span v-if="postingQuestion" class="spinner-border spinner-border-sm me-1" />
              <i v-else class="bi bi-send me-1" />Submit Question
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 3: End Mediation as Unsuccessful -->
    <div
      v-if="showEndMediationModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0, 0, 0, 0.5);"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-bottom px-4 pt-4 pb-2">
            <h5 class="modal-title fw-bold text-danger">
              <i class="bi bi-x-circle-fill text-danger me-2" />Conclude Mediation as Failed
            </h5>
            <button type="button" class="btn-close" @click="showEndMediationModal = false" />
          </div>
          <div class="modal-body px-4 py-3">
            <p class="text-muted small">
              Concluding mediation will restore the case to its prior procedural status. Provide the formal rationale below.
            </p>
            <div class="mb-3">
              <label class="form-label fw-semibold text-dark small">Reason for Failure</label>
              <textarea
                v-model="endMediationReason"
                class="form-control"
                rows="3"
                placeholder="e.g. Parties could not agree on basic settlement framework."
              />
            </div>
          </div>
          <div class="modal-footer border-top px-4 py-3 bg-light rounded-bottom-4">
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-3"
              @click="showEndMediationModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-danger rounded-pill px-4 fw-semibold"
              :disabled="endingMediation || !endMediationReason.trim()"
              @click="handleEndMediation"
            >
              <span v-if="endingMediation" class="spinner-border spinner-border-sm me-1" />
              <i v-else class="bi bi-slash-circle me-1" />Conclude Mediation
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-monospace {
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
}
.max-w-500 {
  max-width: 500px;
}
</style>
