<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { tribunalService } from '@/services/tribunalService';
import type {
  JuryDeliberationData,
  TribunalDeliberationNote,
  TribunalFinding,
  TribunalDecision,
  TribunalDecisionOrder,
  TribunalDeliberationNoteType,
  TribunalFindingType,
  TribunalFindingConclusion,
  TribunalDecisionOutcome,
  TribunalDecisionOrderType,
} from '@/types/tribunal';

const props = defineProps<{
  caseId: number;
  caseStatus: string;
  caseNumber: string;
}>();

const emit = defineEmits<{
  (e: 'caseUpdated'): void;
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const actionInProgress = ref(false);

const data = ref<JuryDeliberationData | null>(null);

// Active sub-section
const activeSection = ref<'draft' | 'findings' | 'orders' | 'notes' | 'dossier' | 'publish'>('draft');

// Note Modal State
const showNoteModal = ref(false);
const editingNoteId = ref<number | null>(null);
const noteForm = ref<{
  note_type: TribunalDeliberationNoteType;
  body: string;
}>({
  note_type: 'general',
  body: '',
});

// Finding Modal State
const showFindingModal = ref(false);
const editingFindingId = ref<number | null>(null);
const findingForm = ref<{
  finding_type: TribunalFindingType;
  title: string;
  finding_text: string;
  conclusion: TribunalFindingConclusion;
  display_order: number;
  is_public: boolean;
  evidence_ids: number[];
  witness_ids: number[];
  hearing_entry_ids: number[];
}>({
  finding_type: 'fact',
  title: '',
  finding_text: '',
  conclusion: 'established',
  display_order: 1,
  is_public: true,
  evidence_ids: [],
  witness_ids: [],
  hearing_entry_ids: [],
});

// Decision Draft State
const decisionDraft = ref<{
  outcome: TribunalDecisionOutcome | '';
  summary: string;
  reasoning: string;
}>({
  outcome: '',
  summary: '',
  reasoning: '',
});
const savingDraft = ref(false);

// Order Modal State
const showOrderModal = ref(false);
const editingOrderId = ref<number | null>(null);
const orderForm = ref<{
  order_type: TribunalDecisionOrderType;
  title: string;
  description: string;
  target_side: 'complainant' | 'respondent' | 'both' | '';
  deadline_at: string;
}>({
  order_type: 'warning',
  title: '',
  description: '',
  target_side: '',
  deadline_at: '',
});

// Confirmation Modal for Publish
const showPublishModal = ref(false);
const publishing = ref(false);

// Computeds
const isFinal = computed(() => {
  return data.value?.decision?.status === 'final';
});

const isDeliberationOpen = computed(() => {
  return data.value?.deliberation?.status === 'open' && !isFinal.value;
});

const publicFindingsCount = computed(() => {
  return (data.value?.findings || []).filter(f => f.is_public).length;
});

const canPublish = computed(() => {
  if (isFinal.value) return false;
  if (!decisionDraft.value.outcome) return false;
  if (!decisionDraft.value.summary?.trim()) return false;
  if (!decisionDraft.value.reasoning?.trim()) return false;
  if (publicFindingsCount.value === 0) return false;
  if (data.value?.dossier.hearing?.status !== 'completed') return false;
  return true;
});

// Load deliberation data
const loadDeliberation = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await tribunalService.getJuryDeliberation(props.caseId);
    data.value = res.data;
    if (res.data?.decision) {
      decisionDraft.value.outcome = res.data.decision.outcome || '';
      decisionDraft.value.summary = res.data.decision.summary || '';
      decisionDraft.value.reasoning = res.data.decision.reasoning || '';
    }
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load tribunal deliberation workspace.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadDeliberation();
});

// ================= Notes Handlers =================
const openCreateNoteModal = () => {
  editingNoteId.value = null;
  noteForm.value = {
    note_type: 'general',
    body: '',
  };
  showNoteModal.value = true;
};

const openEditNoteModal = (note: TribunalDeliberationNote) => {
  editingNoteId.value = note.id;
  noteForm.value = {
    note_type: note.note_type,
    body: note.body,
  };
  showNoteModal.value = true;
};

const saveNote = async () => {
  if (!noteForm.value.body.trim()) return;
  actionInProgress.value = true;
  try {
    if (editingNoteId.value) {
      await tribunalService.updateJuryDeliberationNote(props.caseId, editingNoteId.value, noteForm.value);
    } else {
      await tribunalService.addJuryDeliberationNote(props.caseId, noteForm.value);
    }
    showNoteModal.value = false;
    await loadDeliberation();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to save deliberation note.');
  } finally {
    actionInProgress.value = false;
  }
};

const deleteNote = async (noteId: number) => {
  if (!confirm('Are you sure you want to delete this private deliberation note?')) return;
  actionInProgress.value = true;
  try {
    await tribunalService.deleteJuryDeliberationNote(props.caseId, noteId);
    await loadDeliberation();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to delete note.');
  } finally {
    actionInProgress.value = false;
  }
};

// ================= Findings Handlers =================
const openCreateFindingModal = () => {
  editingFindingId.value = null;
  findingForm.value = {
    finding_type: 'fact',
    title: '',
    finding_text: '',
    conclusion: 'established',
    display_order: (data.value?.findings.length || 0) + 1,
    is_public: true,
    evidence_ids: [],
    witness_ids: [],
    hearing_entry_ids: [],
  };
  showFindingModal.value = true;
};

const openEditFindingModal = (finding: TribunalFinding) => {
  editingFindingId.value = finding.id;
  findingForm.value = {
    finding_type: finding.finding_type,
    title: finding.title || '',
    finding_text: finding.finding_text,
    conclusion: finding.conclusion,
    display_order: finding.display_order,
    is_public: finding.is_public,
    evidence_ids: (finding.evidence || []).map(e => e.id),
    witness_ids: (finding.witnesses || []).map(w => w.id),
    hearing_entry_ids: (finding.hearing_entries || []).map(h => h.id),
  };
  showFindingModal.value = true;
};

const saveFinding = async () => {
  if (!findingForm.value.finding_text.trim()) return;
  actionInProgress.value = true;
  try {
    const payload = {
      ...findingForm.value,
      title: findingForm.value.title.trim() || null,
    };
    if (editingFindingId.value) {
      await tribunalService.updateJuryFinding(props.caseId, editingFindingId.value, payload);
    } else {
      await tribunalService.addJuryFinding(props.caseId, payload);
    }
    showFindingModal.value = false;
    await loadDeliberation();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to save finding.');
  } finally {
    actionInProgress.value = false;
  }
};

const deleteFinding = async (findingId: number) => {
  if (!confirm('Are you sure you want to delete this finding?')) return;
  actionInProgress.value = true;
  try {
    await tribunalService.deleteJuryFinding(props.caseId, findingId);
    await loadDeliberation();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to delete finding.');
  } finally {
    actionInProgress.value = false;
  }
};

// ================= Decision Draft Handlers =================
const saveDraft = async () => {
  savingDraft.value = true;
  try {
    const payload = {
      outcome: decisionDraft.value.outcome || undefined,
      summary: decisionDraft.value.summary || undefined,
      reasoning: decisionDraft.value.reasoning || undefined,
    };
    await tribunalService.saveJuryDecisionDraft(props.caseId, payload);
    successMessage.value = 'Decision draft saved successfully.';
    setTimeout(() => { successMessage.value = null; }, 3000);
    await loadDeliberation();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to save decision draft.');
  } finally {
    savingDraft.value = false;
  }
};

// ================= Orders Handlers =================
const openCreateOrderModal = () => {
  editingOrderId.value = null;
  orderForm.value = {
    order_type: 'warning',
    title: '',
    description: '',
    target_side: '',
    deadline_at: '',
  };
  showOrderModal.value = true;
};

const openEditOrderModal = (order: TribunalDecisionOrder) => {
  editingOrderId.value = order.id;
  orderForm.value = {
    order_type: order.order_type,
    title: order.title,
    description: order.description,
    target_side: (order.target_side as any) || '',
    deadline_at: order.deadline_at ? order.deadline_at.substring(0, 10) : '',
  };
  showOrderModal.value = true;
};

const saveOrder = async () => {
  if (!orderForm.value.title.trim() || !orderForm.value.description.trim()) return;
  actionInProgress.value = true;
  try {
    const payload = {
      ...orderForm.value,
      target_side: orderForm.value.target_side || null,
      deadline_at: orderForm.value.deadline_at || null,
    };
    if (editingOrderId.value) {
      await tribunalService.updateJuryDecisionOrder(props.caseId, editingOrderId.value, payload);
    } else {
      await tribunalService.addJuryDecisionOrder(props.caseId, payload);
    }
    showOrderModal.value = false;
    await loadDeliberation();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to save order.');
  } finally {
    actionInProgress.value = false;
  }
};

const deleteOrder = async (orderId: number) => {
  if (!confirm('Are you sure you want to delete this remedy order?')) return;
  actionInProgress.value = true;
  try {
    await tribunalService.deleteJuryDecisionOrder(props.caseId, orderId);
    await loadDeliberation();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to delete order.');
  } finally {
    actionInProgress.value = false;
  }
};

// ================= Publish Final Decision Handlers =================
const handlePublishDecision = async () => {
  publishing.value = true;
  try {
    await tribunalService.publishJuryDecision(props.caseId);
    showPublishModal.value = false;
    await loadDeliberation();
    emit('caseUpdated');
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to publish final decision.');
  } finally {
    publishing.value = false;
  }
};

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
  <div class="deliberation-container">
    <!-- Header Banner -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
      <div class="p-4 bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-warning text-dark text-uppercase px-3 py-1 fw-bold rounded-pill">
              <i class="bi bi-shield-lock-fill me-1" />Private Deliberation &amp; Decision
            </span>
            <span v-if="isFinal" class="badge bg-success rounded-pill px-3 py-1">
              Final Decision Published
            </span>
            <span v-else class="badge bg-info text-dark rounded-pill px-3 py-1">
              Deliberation Open
            </span>
          </div>
          <h4 class="fw-bold mb-0">Tribunal Adjudication Workspace &bull; {{ caseNumber }}</h4>
          <div class="text-light small mt-1 opacity-75">
            Active Jury Panel: <strong>{{ data?.deliberation?.jury_panel?.panel_name || 'Assigned Panel' }}</strong>
            ({{ data?.deliberation?.jury_panel?.panel_code || 'JP' }})
          </div>
        </div>

        <div class="d-flex gap-2">
          <button
            v-if="!isFinal"
            type="button"
            class="btn btn-outline-light btn-sm rounded-pill px-3"
            :disabled="savingDraft"
            @click="saveDraft"
          >
            <i class="bi bi-save me-1" />
            {{ savingDraft ? 'Saving Draft...' : 'Save Draft' }}
          </button>
          <button
            v-if="!isFinal"
            type="button"
            class="btn btn-warning btn-sm fw-bold rounded-pill px-4 text-dark shadow-sm"
            @click="activeSection = 'publish'"
          >
            <i class="bi bi-file-earmark-check-fill me-1" />Review &amp; Publish
          </button>
        </div>
      </div>

      <!-- Deliberation Sub-Navigation -->
      <div class="card-body p-2 border-bottom bg-light">
        <ul class="nav nav-pills nav-fill gap-2">
          <li class="nav-item">
            <button
              class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
              :class="{ active: activeSection === 'draft' }"
              @click="activeSection = 'draft'"
            >
              <i class="bi bi-journal-text me-1" />
              Decision Draft
            </button>
          </li>
          <li class="nav-item">
            <button
              class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
              :class="{ active: activeSection === 'findings' }"
              @click="activeSection = 'findings'"
            >
              <i class="bi bi-check2-circle me-1" />
              Findings &amp; Issues
              <span v-if="data?.findings?.length" class="badge bg-secondary-subtle text-dark ms-1">
                {{ data.findings.length }}
              </span>
            </button>
          </li>
          <li class="nav-item">
            <button
              class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
              :class="{ active: activeSection === 'orders' }"
              @click="activeSection = 'orders'"
            >
              <i class="bi bi-hammer me-1" />
              Remedies &amp; Orders
              <span v-if="data?.decision?.orders?.length" class="badge bg-secondary-subtle text-dark ms-1">
                {{ data.decision.orders.length }}
              </span>
            </button>
          </li>
          <li class="nav-item">
            <button
              class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
              :class="{ active: activeSection === 'notes' }"
              @click="activeSection = 'notes'"
            >
              <i class="bi bi-lock-fill me-1" />
              Private Notes
              <span v-if="data?.deliberation?.notes?.length" class="badge bg-secondary-subtle text-dark ms-1">
                {{ data.deliberation.notes.length }}
              </span>
            </button>
          </li>
          <li class="nav-item">
            <button
              class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
              :class="{ active: activeSection === 'dossier' }"
              @click="activeSection = 'dossier'"
            >
              <i class="bi bi-folder-symlink me-1" />
              Case Dossier &amp; Record
            </button>
          </li>
          <li class="nav-item">
            <button
              class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
              :class="{ active: activeSection === 'publish', 'text-warning': !isFinal && canPublish }"
              @click="activeSection = 'publish'"
            >
              <i class="bi bi-send-check me-1" />
              {{ isFinal ? 'Decision Published' : 'Final Review' }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="successMessage" class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
      <i class="bi bi-check-circle-fill me-2" />{{ successMessage }}
      <button type="button" class="btn-close" @click="successMessage = null" />
    </div>

    <div v-if="error" class="alert alert-danger rounded-4 shadow-sm" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" />{{ error }}
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading Deliberation...</span>
      </div>
      <p class="text-muted mt-2">Opening confidential Tribunal deliberation workspace...</p>
    </div>

    <div v-else-if="data">
      <!-- SECTION 1: DECISION DRAFT -->
      <div v-if="activeSection === 'draft'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="fw-bold text-dark mb-1">
                <i class="bi bi-journal-text me-2 text-primary" />Tribunal Decision Drafting
              </h5>
              <p class="text-muted small mb-0">
                Formulate the panel's official adjudication outcome, summary, and factual/legal reasoning.
              </p>
            </div>
            <div v-if="data.decision?.decision_number" class="text-end">
              <span class="badge bg-secondary font-monospace">{{ data.decision.decision_number }}</span>
              <div class="text-muted small">Status: {{ isFinal ? 'FINAL & LOCKED' : 'DRAFT' }}</div>
            </div>
          </div>

          <div v-if="isFinal" class="alert alert-info rounded-3 mb-4">
            <i class="bi bi-lock-fill me-2" />
            This Tribunal Decision was published on <strong>{{ data.decision?.published_at }}</strong>. The decision and findings are sealed and immutable.
          </div>

          <!-- Outcome Selection -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark">Adjudication Outcome <span class="text-danger">*</span></label>
            <div v-if="isFinal" class="p-3 bg-light rounded-3 fw-bold fs-5 text-primary">
              {{ formatOutcome(decisionDraft.outcome) }}
            </div>
            <select
              v-else
              v-model="decisionDraft.outcome"
              class="form-select form-select-lg rounded-3"
            >
              <option value="" disabled>-- Select Tribunal Adjudication Outcome --</option>
              <option value="complaint_upheld">Complaint Upheld</option>
              <option value="complaint_partially_upheld">Complaint Partially Upheld</option>
              <option value="complaint_not_upheld">Complaint Not Upheld</option>
              <option value="dismissed">Dismissed</option>
            </select>
            <div class="form-text text-muted">
              Choose the formal tribunal determination resolving the claims in dispute.
            </div>
          </div>

          <!-- Executive Summary -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark">Executive Decision Summary <span class="text-danger">*</span></label>
            <div v-if="isFinal" class="p-3 bg-light rounded-3 text-secondary lh-base">
              {{ decisionDraft.summary }}
            </div>
            <textarea
              v-else
              v-model="decisionDraft.summary"
              class="form-control rounded-3"
              rows="3"
              placeholder="Provide a concise 1-3 paragraph summary of the decision visible to parties and public record..."
            />
            <div class="form-text text-muted">A clear, objective summary of the claims, proceedings, and final determination.</div>
          </div>

          <!-- Reasoning -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark">Tribunal Reasoning &amp; Analysis <span class="text-danger">*</span></label>
            <div v-if="isFinal" class="p-3 bg-light rounded-3 text-secondary lh-base text-break" style="white-space: pre-line;">
              {{ decisionDraft.reasoning }}
            </div>
            <textarea
              v-else
              v-model="decisionDraft.reasoning"
              class="form-control rounded-3"
              rows="8"
              placeholder="Provide detailed legal, factual, and credibility reasoning. Detail how evidence and witness testimonies were evaluated to reach the conclusion..."
            />
            <div class="form-text text-muted">Detail the reasoning supporting each determination, citing findings, exhibits, and statements.</div>
          </div>

          <div v-if="!isFinal" class="d-flex justify-content-end gap-2">
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              :disabled="savingDraft"
              @click="saveDraft"
            >
              <i class="bi bi-save me-1" />{{ savingDraft ? 'Saving...' : 'Save Draft Decision' }}
            </button>
          </div>
        </div>
      </div>

      <!-- SECTION 2: FINDINGS OF FACT & ISSUES -->
      <div v-if="activeSection === 'findings'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
              <h5 class="fw-bold text-dark mb-1">
                <i class="bi bi-check2-circle me-2 text-primary" />Findings of Fact &amp; Issue Determinations
              </h5>
              <p class="text-muted small mb-0">
                Formal determinations on disputed factual claims, witness credibility, and case issues.
              </p>
            </div>
            <button
              v-if="!isFinal"
              type="button"
              class="btn btn-outline-primary btn-sm rounded-pill px-3"
              @click="openCreateFindingModal"
            >
              <i class="bi bi-plus-circle me-1" />Add Finding
            </button>
          </div>

          <div v-if="!data.findings?.length" class="text-center py-5 bg-light rounded-4">
            <i class="bi bi-clipboard2-check fs-1 text-muted" />
            <h6 class="fw-bold text-dark mt-2">No Findings Recorded Yet</h6>
            <p class="text-muted small mb-3">At least one public finding of fact is required before publishing a final decision.</p>
            <button
              v-if="!isFinal"
              type="button"
              class="btn btn-primary btn-sm rounded-pill px-4"
              @click="openCreateFindingModal"
            >
              Create First Finding
            </button>
          </div>

          <div v-else class="d-flex flex-column gap-3">
            <div
              v-for="finding in data.findings"
              :key="finding.id"
              class="card border rounded-4 p-3 shadow-none hover-shadow transition"
              :class="{ 'border-primary': finding.is_public, 'border-secondary-subtle': !finding.is_public }"
            >
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
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
                  <span v-if="finding.is_public" class="badge bg-info-subtle text-info-emphasis border">
                    Public Record
                  </span>
                  <span v-else class="badge bg-light text-muted border">
                    Internal Panel Finding
                  </span>
                </div>

                <div v-if="!isFinal" class="d-flex gap-1">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2"
                    @click="openEditFindingModal(finding)"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2"
                    @click="deleteFinding(finding.id)"
                  >
                    Delete
                  </button>
                </div>
              </div>

              <h6 v-if="finding.title" class="fw-bold text-dark mb-2">{{ finding.title }}</h6>
              <div class="p-3 bg-light rounded-3 text-secondary lh-base mb-2">
                {{ finding.finding_text }}
              </div>

              <!-- References -->
              <div v-if="finding.evidence?.length || finding.witnesses?.length || finding.hearing_entries?.length" class="small text-muted mt-1">
                <strong>References:</strong>
                <span v-for="ev in finding.evidence" :key="ev.id" class="badge bg-secondary-subtle text-dark me-1">
                  <i class="bi bi-paperclip me-1" />{{ ev.evidence_number }}
                </span>
                <span v-for="w in finding.witnesses" :key="w.id" class="badge bg-info-subtle text-dark me-1">
                  <i class="bi bi-person me-1" />Witness: {{ w.witness_name }}
                </span>
                <span v-for="h in finding.hearing_entries" :key="h.id" class="badge bg-light text-muted border me-1">
                  <i class="bi bi-mic me-1" />Hearing Entry #{{ h.sequence_number }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3: ORDERS & REMEDIES -->
      <div v-if="activeSection === 'orders'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
              <h5 class="fw-bold text-dark mb-1">
                <i class="bi bi-hammer me-2 text-primary" />Tribunal Orders &amp; Remedies
              </h5>
              <p class="text-muted small mb-0">
                Formulate official tribunal orders, warnings, corrective directives, or compliance requirements.
              </p>
            </div>
            <button
              v-if="!isFinal"
              type="button"
              class="btn btn-outline-primary btn-sm rounded-pill px-3"
              @click="openCreateOrderModal"
            >
              <i class="bi bi-plus-circle me-1" />Add Order / Remedy
            </button>
          </div>

          <div v-if="!data.decision?.orders?.length" class="text-center py-5 bg-light rounded-4">
            <i class="bi bi-card-checklist fs-1 text-muted" />
            <h6 class="fw-bold text-dark mt-2">No Specific Orders Added</h6>
            <p class="text-muted small mb-3">Orders are optional depending on the outcome (e.g. not required if dismissed).</p>
            <button
              v-if="!isFinal"
              type="button"
              class="btn btn-outline-primary btn-sm rounded-pill px-4"
              @click="openCreateOrderModal"
            >
              Add Remedy Order
            </button>
          </div>

          <div v-else class="d-flex flex-column gap-3">
            <div
              v-for="order in data.decision.orders"
              :key="order.id"
              class="card border rounded-4 p-3 shadow-none"
            >
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-dark font-monospace">{{ order.order_number }}</span>
                  <span class="badge bg-warning text-dark text-uppercase">{{ order.order_type.replace('_', ' ') }}</span>
                  <span v-if="order.target_side" class="badge bg-primary text-capitalize">
                    Target: {{ order.target_side }}
                  </span>
                  <span v-if="order.deadline_at" class="badge bg-light text-muted border">
                    Deadline: {{ order.deadline_at.substring(0, 10) }}
                  </span>
                </div>

                <div v-if="!isFinal" class="d-flex gap-1">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2"
                    @click="openEditOrderModal(order)"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2"
                    @click="deleteOrder(order.id)"
                  >
                    Delete
                  </button>
                </div>
              </div>

              <h6 class="fw-bold text-dark mb-1">{{ order.title }}</h6>
              <div class="p-3 bg-light rounded-3 text-secondary lh-base mb-1">
                {{ order.description }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 4: PRIVATE DELIBERATION NOTES -->
      <div v-if="activeSection === 'notes'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
              <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0">
                  <i class="bi bi-shield-lock-fill me-2 text-danger" />Private Panel Deliberation Notes
                </h5>
                <span class="badge bg-danger-subtle text-danger border rounded-pill px-3 py-1">
                  Strictly Confidential
                </span>
              </div>
              <p class="text-muted small mt-1 mb-0">
                Working notes for panel members. These are NEVER visible to complainant, respondent, counsel, or Super Admin.
              </p>
            </div>
            <button
              v-if="isDeliberationOpen"
              type="button"
              class="btn btn-outline-dark btn-sm rounded-pill px-3"
              @click="openCreateNoteModal"
            >
              <i class="bi bi-plus-circle me-1" />Add Working Note
            </button>
          </div>

          <div v-if="!data.deliberation?.notes?.length" class="text-center py-5 bg-light rounded-4">
            <i class="bi bi-lock fs-1 text-muted" />
            <h6 class="fw-bold text-dark mt-2">No Private Deliberation Notes Recorded</h6>
            <p class="text-muted small mb-3">Record internal discussions on evidence analysis, witness credibility, or issues.</p>
            <button
              v-if="isDeliberationOpen"
              type="button"
              class="btn btn-dark btn-sm rounded-pill px-4"
              @click="openCreateNoteModal"
            >
              Add First Note
            </button>
          </div>

          <div v-else class="d-flex flex-column gap-3">
            <div
              v-for="note in data.deliberation.notes"
              :key="note.id"
              class="card border rounded-4 p-3 bg-white"
            >
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-secondary text-uppercase">{{ note.note_type.replace('_', ' ') }}</span>
                  <span class="text-muted small">Recorded: {{ note.created_at }}</span>
                </div>
                <div v-if="isDeliberationOpen" class="d-flex gap-1">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2"
                    @click="openEditNoteModal(note)"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2"
                    @click="deleteNote(note.id)"
                  >
                    Delete
                  </button>
                </div>
              </div>

              <div class="p-3 bg-light rounded-3 text-secondary lh-base text-break">
                {{ note.body }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 5: CASE DOSSIER & RECORD -->
      <div v-if="activeSection === 'dossier'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
          <h5 class="fw-bold text-dark mb-3">
            <i class="bi bi-folder-symlink me-2 text-primary" />Adjudication Case Dossier (Full Record)
          </h5>

          <!-- Claims & Positions -->
          <div class="row g-4 mb-4">
            <div class="col-12 col-md-6">
              <div class="card border rounded-4 p-3 h-100 bg-light">
                <h6 class="fw-bold text-primary mb-2">
                  <i class="bi bi-file-earmark-person me-1" />Complainant's Claim
                </h6>
                <div class="small text-muted mb-2">
                  Party: <strong>{{ data.dossier.case.complainant.name }}</strong>
                </div>
                <div class="p-3 bg-white rounded-3 small text-secondary lh-base mb-2">
                  {{ data.dossier.case.description }}
                </div>
                <div v-if="data.dossier.case.requested_resolution" class="p-2 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-2 small text-primary-emphasis">
                  <strong>Requested Resolution:</strong> {{ data.dossier.case.requested_resolution }}
                </div>
              </div>
            </div>

            <div class="col-12 col-md-6">
              <div class="card border rounded-4 p-3 h-100 bg-light">
                <h6 class="fw-bold text-warning-emphasis mb-2">
                  <i class="bi bi-reply-fill me-1" />Respondent's Defense / Response
                </h6>
                <div class="small text-muted mb-2">
                  Party: <strong>{{ data.dossier.case.respondent.name }}</strong>
                </div>
                <div v-if="data.dossier.response" class="p-3 bg-white rounded-3 small text-secondary lh-base mb-2">
                  <div class="badge bg-secondary mb-2 text-uppercase">{{ data.dossier.response.position }}</div>
                  <div>{{ data.dossier.response.response_text }}</div>
                </div>
                <div v-else class="text-muted small p-3 bg-white rounded-3">
                  No formal written response filed by respondent.
                </div>
              </div>
            </div>
          </div>

          <!-- Evidence Summary -->
          <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2">
              <i class="bi bi-paperclip me-1" />Admitted Case Evidence ({{ data.dossier.evidence.length }})
            </h6>
            <div v-if="!data.dossier.evidence.length" class="text-muted small p-3 bg-light rounded-3">
              No evidence submitted for this dispute.
            </div>
            <div v-else class="row g-2">
              <div v-for="ev in data.dossier.evidence" :key="ev.id" class="col-12 col-md-6">
                <div class="p-3 border rounded-3 bg-white d-flex justify-content-between align-items-center">
                  <div>
                    <span class="badge bg-dark font-monospace me-2">{{ ev.evidence_number }}</span>
                    <strong class="text-dark small">{{ ev.title }}</strong>
                    <div class="text-muted small mt-1">Type: {{ ev.type }} &bull; Status: {{ ev.status }}</div>
                  </div>
                  <span class="badge" :class="ev.challenge_status === 'challenged' ? 'bg-danger' : 'bg-success'">
                    {{ ev.challenge_status || 'unchallenged' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Witnesses & Testimony -->
          <div class="mb-4">
            <h6 class="fw-bold text-dark mb-2">
              <i class="bi bi-people me-1" />Witness Testimonies ({{ data.dossier.witnesses.length }})
            </h6>
            <div v-if="!data.dossier.witnesses.length" class="text-muted small p-3 bg-light rounded-3">
              No witnesses examined during hearing.
            </div>
            <div v-else class="d-flex flex-column gap-2">
              <div v-for="w in data.dossier.witnesses" :key="w.id" class="p-3 border rounded-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <strong class="text-dark">{{ w.witness_name }} ({{ w.side }} witness)</strong>
                  <span class="badge bg-secondary text-uppercase">{{ w.status }}</span>
                </div>
                <div class="small text-muted">{{ w.statement_summary || 'No written summary provided.' }}</div>
              </div>
            </div>
          </div>

          <!-- Hearing Transcript -->
          <div>
            <h6 class="fw-bold text-dark mb-2">
              <i class="bi bi-mic me-1" />Hearing Transcript Log ({{ data.dossier.hearing_entries.length }} entries)
            </h6>
            <div v-if="!data.dossier.hearing_entries.length" class="text-muted small p-3 bg-light rounded-3">
              No formal hearing transcript entries recorded.
            </div>
            <div v-else class="d-flex flex-column gap-2" style="max-height: 400px; overflow-y: auto;">
              <div
                v-for="entry in data.dossier.hearing_entries"
                :key="entry.id"
                class="p-2 border rounded-3 bg-light small"
              >
                <div class="d-flex justify-content-between text-muted mb-1">
                  <span>#{{ entry.sequence_number }} &bull; <strong>{{ entry.sender_name }}</strong> ({{ entry.entry_type }})</span>
                  <span>{{ entry.created_at }}</span>
                </div>
                <div class="text-dark">{{ entry.body }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 6: FINAL REVIEW & PUBLICATION -->
      <div v-if="activeSection === 'publish'">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
          <h5 class="fw-bold text-dark mb-3">
            <i class="bi bi-send-check me-2 text-primary" />Final Decision Review &amp; Publication
          </h5>

          <!-- Published State -->
          <div v-if="isFinal" class="alert alert-success rounded-4 p-4 mb-4">
            <div class="d-flex align-items-center gap-3 mb-2">
              <i class="bi bi-check-circle-fill fs-2 text-success" />
              <div>
                <h5 class="fw-bold text-success mb-0">Tribunal Final Decision Published</h5>
                <div class="text-dark small">
                  Decision Number: <strong>{{ data.decision?.decision_number }}</strong> &bull;
                  Published At: <strong>{{ data.decision?.published_at }}</strong>
                </div>
              </div>
            </div>
            <div class="border-top border-success-subtle pt-3 mt-3">
              <div class="row g-2">
                <div class="col-12 col-md-6">
                  <strong>Outcome:</strong> {{ formatOutcome(data.decision?.outcome || '') }}
                </div>
                <div class="col-12 col-md-6">
                  <strong>Appeal Deadline:</strong> {{ data.decision?.appeal_deadline || 'None' }}
                </div>
              </div>
              <p class="text-muted small mt-2 mb-0">
                The decision is sealed and published to the complainant, respondent, and legal representatives. Case status is set to <code>appeal_window</code>.
              </p>
            </div>
          </div>

          <!-- Publication Validation Checklist -->
          <div v-else>
            <div class="card border rounded-4 p-3 bg-light mb-4">
              <h6 class="fw-bold text-dark mb-3">Publication Requirements Checklist</h6>
              <ul class="list-group list-group-flush rounded-3">
                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                  <span>Formal hearing completed</span>
                  <span v-if="data.dossier.hearing?.status === 'completed'" class="badge bg-success">
                    <i class="bi bi-check-lg me-1" />Completed
                  </span>
                  <span v-else class="badge bg-danger">
                    <i class="bi bi-x-lg me-1" />Hearing Incomplete
                  </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                  <span>Adjudication outcome selected</span>
                  <span v-if="decisionDraft.outcome" class="badge bg-success">
                    <i class="bi bi-check-lg me-1" />{{ formatOutcome(decisionDraft.outcome) }}
                  </span>
                  <span v-else class="badge bg-danger">
                    <i class="bi bi-x-lg me-1" />Missing
                  </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                  <span>Decision executive summary supplied</span>
                  <span v-if="decisionDraft.summary?.trim()" class="badge bg-success">
                    <i class="bi bi-check-lg me-1" />Supplied
                  </span>
                  <span v-else class="badge bg-danger">
                    <i class="bi bi-x-lg me-1" />Missing
                  </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                  <span>Tribunal reasoning supplied</span>
                  <span v-if="decisionDraft.reasoning?.trim()" class="badge bg-success">
                    <i class="bi bi-check-lg me-1" />Supplied
                  </span>
                  <span v-else class="badge bg-danger">
                    <i class="bi bi-x-lg me-1" />Missing
                  </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                  <span>At least one public finding of fact</span>
                  <span v-if="publicFindingsCount > 0" class="badge bg-success">
                    <i class="bi bi-check-lg me-1" />{{ publicFindingsCount }} Recorded
                  </span>
                  <span v-else class="badge bg-danger">
                    <i class="bi bi-x-lg me-1" />None Recorded
                  </span>
                </li>
              </ul>
            </div>

            <!-- Warning Notice -->
            <div class="alert alert-warning rounded-4 p-3 d-flex align-items-center gap-3 mb-4">
              <i class="bi bi-exclamation-triangle-fill fs-3 text-warning" />
              <div class="small">
                <strong>Irreversible Action Warning:</strong>
                Publishing the final decision locks the Tribunal record permanently, completes the panel's deliberation, makes the decision and findings visible to the parties, and opens the statutory appeal window.
              </div>
            </div>

            <div class="d-flex justify-content-end">
              <button
                type="button"
                class="btn btn-warning btn-lg fw-bold rounded-pill px-5 text-dark shadow"
                :disabled="!canPublish || actionInProgress"
                @click="showPublishModal = true"
              >
                <i class="bi bi-file-earmark-check-fill me-2" />
                Publish Final Tribunal Decision
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD / EDIT NOTE -->
    <div v-if="showNoteModal" class="modal-backdrop fade show" />
    <div v-if="showNoteModal" class="modal fade show d-block" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header bg-dark text-white rounded-top-4">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-lock-fill me-2 text-danger" />
              {{ editingNoteId ? 'Edit Private Working Note' : 'Add Private Deliberation Note' }}
            </h5>
            <button type="button" class="btn-close btn-close-white" @click="showNoteModal = false" />
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label fw-bold small">Note Category</label>
              <select v-model="noteForm.note_type" class="form-select rounded-3">
                <option value="general">General</option>
                <option value="evidence_analysis">Evidence Analysis</option>
                <option value="witness_analysis">Witness Analysis</option>
                <option value="credibility">Credibility Assessment</option>
                <option value="issue_analysis">Issue Analysis</option>
                <option value="remedy_consideration">Remedy Consideration</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Note Body</label>
              <textarea
                v-model="noteForm.body"
                class="form-control rounded-3"
                rows="5"
                placeholder="Enter internal panel observations, analysis, or questions..."
              />
            </div>
            <div class="alert alert-light border small text-muted mb-0">
              <i class="bi bi-info-circle me-1" />
              This note is private to this Jury Panel. It will never be included in the public decision or sent to parties.
            </div>
          </div>
          <div class="modal-footer border-0 p-3">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" @click="showNoteModal = false">
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              :disabled="actionInProgress || !noteForm.body.trim()"
              @click="saveNote"
            >
              {{ actionInProgress ? 'Saving...' : 'Save Note' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD / EDIT FINDING -->
    <div v-if="showFindingModal" class="modal-backdrop fade show" />
    <div v-if="showFindingModal" class="modal fade show d-block" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header bg-primary text-white rounded-top-4">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-check2-circle me-2" />
              {{ editingFindingId ? 'Edit Finding' : 'Add Structured Finding' }}
            </h5>
            <button type="button" class="btn-close btn-close-white" @click="showFindingModal = false" />
          </div>
          <div class="modal-body p-4">
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-4">
                <label class="form-label fw-bold small">Finding Type</label>
                <select v-model="findingForm.finding_type" class="form-select rounded-3">
                  <option value="fact">Fact</option>
                  <option value="issue">Issue</option>
                  <option value="credibility">Credibility</option>
                  <option value="evidence">Evidence</option>
                  <option value="procedural">Procedural</option>
                </select>
              </div>
              <div class="col-12 col-md-5">
                <label class="form-label fw-bold small">Conclusion</label>
                <select v-model="findingForm.conclusion" class="form-select rounded-3">
                  <option value="established">Established</option>
                  <option value="not_established">Not Established</option>
                  <option value="partially_established">Partially Established</option>
                  <option value="not_applicable">Not Applicable</option>
                </select>
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label fw-bold small">Display Order</label>
                <input
                  v-model.number="findingForm.display_order"
                  type="number"
                  min="1"
                  class="form-control rounded-3"
                >
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Title (Optional)</label>
              <input
                v-model="findingForm.title"
                type="text"
                class="form-control rounded-3"
                placeholder="e.g. Disputed incident on 20 September 2026"
              >
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">Finding Text <span class="text-danger">*</span></label>
              <textarea
                v-model="findingForm.finding_text"
                class="form-control rounded-3"
                rows="4"
                placeholder="State the tribunal's factual determination and reasoning..."
              />
            </div>

            <div class="mb-3">
              <div class="form-check form-switch">
                <input
                  id="findingIsPublic"
                  v-model="findingForm.is_public"
                  class="form-check-input"
                  type="checkbox"
                >
                <label class="form-check-label fw-bold small" for="findingIsPublic">
                  Include in published decision (Public Finding)
                </label>
              </div>
            </div>

            <!-- Evidence References -->
            <div v-if="data?.dossier.evidence.length" class="mb-3">
              <label class="form-label fw-bold small">Referenced Case Evidence</label>
              <div class="d-flex flex-wrap gap-2">
                <div
                  v-for="ev in data.dossier.evidence"
                  :key="ev.id"
                  class="form-check form-check-inline"
                >
                  <input
                    :id="'evCheck' + ev.id"
                    v-model="findingForm.evidence_ids"
                    class="form-check-input"
                    type="checkbox"
                    :value="ev.id"
                  >
                  <label class="form-check-label small" :for="'evCheck' + ev.id">
                    {{ ev.evidence_number }} ({{ ev.title }})
                  </label>
                </div>
              </div>
            </div>

            <!-- Witness References -->
            <div v-if="data?.dossier.witnesses.length" class="mb-3">
              <label class="form-label fw-bold small">Referenced Witnesses</label>
              <div class="d-flex flex-wrap gap-2">
                <div
                  v-for="w in data.dossier.witnesses"
                  :key="w.id"
                  class="form-check form-check-inline"
                >
                  <input
                    :id="'witCheck' + w.id"
                    v-model="findingForm.witness_ids"
                    class="form-check-input"
                    type="checkbox"
                    :value="w.id"
                  >
                  <label class="form-check-label small" :for="'witCheck' + w.id">
                    {{ w.witness_name }} ({{ w.side }})
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 p-3">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" @click="showFindingModal = false">
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              :disabled="actionInProgress || !findingForm.finding_text.trim()"
              @click="saveFinding"
            >
              {{ actionInProgress ? 'Saving...' : 'Save Finding' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD / EDIT ORDER -->
    <div v-if="showOrderModal" class="modal-backdrop fade show" />
    <div v-if="showOrderModal" class="modal fade show d-block" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header bg-dark text-white rounded-top-4">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-hammer me-2 text-warning" />
              {{ editingOrderId ? 'Edit Remedy Order' : 'Add Tribunal Remedy Order' }}
            </h5>
            <button type="button" class="btn-close btn-close-white" @click="showOrderModal = false" />
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label fw-bold small">Order Type</label>
              <select v-model="orderForm.order_type" class="form-select rounded-3">
                <option value="warning">Warning</option>
                <option value="corrective_action">Corrective Action</option>
                <option value="compliance_requirement">Compliance Requirement</option>
                <option value="content_action">Content Action</option>
                <option value="account_action">Account Action</option>
                <option value="compensation_recommendation">Compensation Recommendation</option>
                <option value="no_action">No Action</option>
                <option value="other">Other Directive</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Target Side (Optional)</label>
              <select v-model="orderForm.target_side" class="form-select rounded-3">
                <option value="">None / Both</option>
                <option value="complainant">Complainant</option>
                <option value="respondent">Respondent</option>
                <option value="both">Both Parties</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Title <span class="text-danger">*</span></label>
              <input
                v-model="orderForm.title"
                type="text"
                class="form-control rounded-3"
                placeholder="e.g. Formal Warning regarding Conduct"
              >
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Description / Directive <span class="text-danger">*</span></label>
              <textarea
                v-model="orderForm.description"
                class="form-control rounded-3"
                rows="4"
                placeholder="Detail the specific action, compliance requirement, or remedy ordered..."
              />
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">Compliance Deadline (Optional)</label>
              <input
                v-model="orderForm.deadline_at"
                type="date"
                class="form-control rounded-3"
              >
            </div>
          </div>
          <div class="modal-footer border-0 p-3">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" @click="showOrderModal = false">
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              :disabled="actionInProgress || !orderForm.title.trim() || !orderForm.description.trim()"
              @click="saveOrder"
            >
              {{ actionInProgress ? 'Saving...' : 'Save Order' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: CONFIRM PUBLISH DECISION -->
    <div v-if="showPublishModal" class="modal-backdrop fade show" />
    <div v-if="showPublishModal" class="modal fade show d-block" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header bg-warning text-dark rounded-top-4">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-shield-check me-2" />Publish Final Tribunal Decision?
            </h5>
            <button type="button" class="btn-close" @click="showPublishModal = false" />
          </div>
          <div class="modal-body p-4">
            <p class="text-dark mb-3">
              This action will make the decision visible to the parties and lock the decision, findings, and orders against further editing.
            </p>
            <ul class="list-unstyled small text-muted mb-3">
              <li class="mb-1">&bull; Decision status will become <strong>final</strong></li>
              <li class="mb-1">&bull; Deliberation will become <strong>completed</strong></li>
              <li class="mb-1">&bull; Case status will transition to <strong>appeal_window</strong></li>
              <li class="mb-1">&bull; Parties and legal counsel will receive formal decision notifications</li>
            </ul>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-0">
              <i class="bi bi-exclamation-triangle-fill me-1" />
              This operation is final and cannot be undone.
            </div>
          </div>
          <div class="modal-footer border-0 p-3">
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-4"
              :disabled="publishing"
              @click="showPublishModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-warning fw-bold text-dark rounded-pill px-4 shadow-sm"
              :disabled="publishing"
              @click="handlePublishDecision"
            >
              {{ publishing ? 'Publishing Decision...' : 'Publish Final Decision' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hover-shadow:hover {
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
.transition {
  transition: all 0.2s ease-in-out;
}
</style>
