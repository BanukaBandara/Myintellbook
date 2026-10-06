<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { tribunalService } from '@/services/tribunalService';
import type { TribunalDecision } from '@/types/tribunal';

const props = defineProps<{
  caseId: number;
  caseStatus: string;
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const decision = ref<TribunalDecision | null>(null);
const isPending = ref(true);

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
    }
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load tribunal decision.';
  } finally {
    loading.value = false;
  }
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
              <div v-if="finding.evidence?.length || finding.witnesses?.length || finding.hearing_entries?.length" class="small text-muted border-top pt-2 mt-1">
                <strong>Cited In Support:</strong>
                <span v-for="ev in finding.evidence" :key="ev.id" class="badge bg-white text-dark border me-1">
                  <i class="bi bi-paperclip me-1" />{{ ev.evidence_number }}
                </span>
                <span v-for="w in finding.witnesses" :key="w.id" class="badge bg-white text-dark border me-1">
                  <i class="bi bi-person me-1" />{{ w.witness_name }}
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
