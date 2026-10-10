<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { tribunalService } from '@/services/tribunalService';
import type {
  TribunalHearing,
  TribunalHearingEntry,
} from '@/types/tribunal';

const props = defineProps<{
  caseId: number;
  caseStatus: string;
  caseNumber: string;
  userRole?: string;
  userSide?: string;
}>();

const emit = defineEmits<{
  (e: 'caseUpdated'): void;
}>();

const loading = ref(false);
const error = ref<string | null>(null);

const hearings = ref<TribunalHearing[]>([]);
const activeHearing = ref<TribunalHearing | null>(null);

const showEntryModal = ref(false);
const submittingEntry = ref(false);
const entryForm = ref({
  entry_type: 'opening_statement',
  body: '',
  related_evidence_id: null as number | null,
});

const showReplyModal = ref(false);
const replying = ref(false);
const replyQuestion = ref<TribunalHearingEntry | null>(null);
const replyBody = ref('');

const isDeliberationReady = computed(() => {
  return props.caseStatus === 'deliberation' || (activeHearing.value && activeHearing.value.status === 'completed');
});

const isHearingActive = computed(() => {
  return activeHearing.value?.status === 'active';
});

const fetchHearingData = async () => {
  loading.value = true;
  error.value = null;
  try {
    const hRes = await tribunalService.getTribunalHearings(props.caseId);

    hearings.value = hRes.hearings || [];
    activeHearing.value = hRes.active_hearing || null;

    if (activeHearing.value?.id) {
      const singleRes = await tribunalService.getTribunalHearing(activeHearing.value.id);
      if (singleRes.hearing) {
        activeHearing.value = singleRes.hearing;
      }
    }
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load hearing information.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchHearingData();
});

const openEntryModal = (type: string) => {
  entryForm.value = {
    entry_type: type,
    body: '',
    related_evidence_id: null,
  };
  showEntryModal.value = true;
};

const handleSubmitEntry = async () => {
  if (!activeHearing.value || !entryForm.value.body.trim() || submittingEntry.value) return;
  submittingEntry.value = true;
  try {
    await tribunalService.addTribunalHearingEntry(activeHearing.value.id, {
      entry_type: entryForm.value.entry_type,
      body: entryForm.value.body.trim(),
      related_evidence_id: entryForm.value.related_evidence_id || undefined,
    });
    showEntryModal.value = false;
    await fetchHearingData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to record entry.');
  } finally {
    submittingEntry.value = false;
  }
};

const openReplyModal = (question: TribunalHearingEntry) => {
  replyQuestion.value = question;
  replyBody.value = '';
  showReplyModal.value = true;
};

const handleReplyQuestion = async () => {
  if (!activeHearing.value || !replyQuestion.value || !replyBody.value.trim() || replying.value) return;
  replying.value = true;
  try {
    await tribunalService.respondToTribunalHearingQuestion(activeHearing.value.id, replyQuestion.value.id, {
      body: replyBody.value.trim(),
    });
    showReplyModal.value = false;
    await fetchHearingData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to submit response.');
  } finally {
    replying.value = false;
  }
};

const formatDate = (dateStr?: string | null) => {
  if (!dateStr) return 'TBD';
  const d = new Date(dateStr);
  return isNaN(d.getTime()) ? dateStr : d.toLocaleString();
};

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'scheduled': return 'bg-info bg-opacity-10 text-info border border-info border-opacity-25';
    case 'active': return 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
    case 'recessed': return 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
    case 'completed': return 'bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25';
    case 'cancelled': return 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
    default: return 'bg-light text-dark';
  }
};

const getEntryBadge = (entryType: string) => {
  switch (entryType) {
    case 'opening_statement': return 'bg-primary text-white';
    case 'closing_statement': return 'bg-dark text-white';
    case 'jury_question': return 'bg-warning text-dark';
    case 'party_answer': return 'bg-info text-dark';
    case 'witness_testimony': return 'bg-success text-white';
    case 'procedural_direction': return 'bg-danger text-white';
    case 'system_event': return 'bg-secondary text-white';
    default: return 'bg-light text-dark border';
  }
};
</script>

<template>
  <div class="party-hearing-tab">
    <!-- Deliberation Banner -->
    <div v-if="isDeliberationReady" class="card border-0 shadow-sm rounded-4 mb-4 bg-purple bg-opacity-10 border border-purple border-opacity-25">
      <div class="card-body p-4 text-center">
        <div class="d-inline-flex p-3 bg-purple bg-opacity-25 text-purple rounded-circle mb-3">
          <i class="bi bi-bank2 fs-2" />
        </div>
        <h4 class="fw-bold text-dark mb-2">Hearing Completed — Ready for Jury Panel Deliberation</h4>
        <p class="text-muted max-w-600 mx-auto mb-0">
          The formal hearing session has concluded. Case evidence and submissions have entered the immutable judicial record. The Jury Panel will deliberate and formulate findings in the next phase.
        </p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading hearing...</span>
      </div>
      <p class="text-muted small mt-2">Loading hearing records...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger rounded-4 shadow-sm mb-4">
      <i class="bi bi-exclamation-triangle-fill me-2" />{{ error }}
    </div>

    <div v-else>
      <!-- Hearing Status Header Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-3 py-1" :class="activeHearing ? getStatusBadge(activeHearing.status) : 'bg-secondary'">
                  {{ activeHearing ? activeHearing.status.toUpperCase() : 'NO SCHEDULED HEARING' }}
                </span>
                <span v-if="activeHearing" class="fw-bold font-monospace text-primary">
                  {{ activeHearing.hearing_number }}
                </span>
              </div>
              <h4 class="fw-bold text-dark mb-1">
                Formal Tribunal Hearing Session
              </h4>
              <p class="text-muted small mb-0">
                <span v-if="activeHearing?.scheduled_at">
                  <i class="bi bi-calendar3 me-1" />Scheduled: <strong>{{ formatDate(activeHearing.scheduled_at) }}</strong> &bull;
                </span>
                <span v-if="activeHearing?.location_type">
                  <i class="bi bi-geo-alt me-1" />Mode: <strong>{{ activeHearing.location_type }}</strong>
                </span>
                <span v-if="activeHearing?.meeting_link" class="ms-2">
                  <a :href="activeHearing.meeting_link" target="_blank" class="btn btn-sm btn-link p-0 text-decoration-none">
                    <i class="bi bi-link-45deg" />Join Link
                  </a>
                </span>
              </p>
            </div>

            <!-- Party Participation Buttons (During Active Hearing) -->
            <div class="d-flex flex-wrap gap-2">
              <button
                v-if="isHearingActive"
                type="button"
                class="btn btn-primary rounded-pill px-3"
                @click="openEntryModal('opening_statement')"
              >
                <i class="bi bi-pencil-square me-1" />Opening Statement
              </button>

              <button
                v-if="isHearingActive"
                type="button"
                class="btn btn-outline-secondary rounded-pill px-3"
                @click="openEntryModal('closing_statement')"
              >
                <i class="bi bi-check-all me-1" />Closing Statement
              </button>
            </div>
          </div>

          <div v-if="activeHearing?.notes" class="mt-3 pt-3 border-top text-muted small">
            <strong>Panel Instructions / Orders:</strong> {{ activeHearing.notes }}
          </div>
        </div>
      </div>

      <!-- Two-Column Layout -->
      <div class="row g-4">
        <!-- Left: Participants -->
        <div class="col-lg-5">
          <!-- Participants Card -->
          <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
              <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-people me-2 text-primary" />Participants
              </h5>
            </div>
            <div class="card-body p-4 pt-2">
              <div v-if="!activeHearing?.participants?.length" class="text-muted small py-2">
                Participants will be populated upon hearing scheduling.
              </div>
              <ul v-else class="list-group list-group-flush">
                <li
                  v-for="p in activeHearing.participants"
                  :key="p.id"
                  class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center"
                >
                  <div>
                    <div class="fw-semibold text-dark">{{ p.display_name }}</div>
                    <div class="small text-muted text-capitalize">
                      {{ p.participant_type.replace('_', ' ') }}
                    </div>
                  </div>
                  <span class="badge bg-light text-dark border">
                    {{ p.attendance_status }}
                  </span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Right: Hearing Transcript -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold text-dark mb-0">
                  <i class="bi bi-journal-text me-2 text-primary" />Hearing Session Record
                </h5>
                <p class="text-muted small mb-0">Live transcript &amp; formal responses</p>
              </div>
            </div>

            <div class="card-body p-4">
              <div v-if="!activeHearing?.entries?.length" class="text-center py-5 text-muted">
                <i class="bi bi-chat-left-dots fs-1 d-block mb-2 text-secondary" />
                No hearing record entries yet. Statements and directions will appear here once the session commences.
              </div>

              <div v-else class="d-flex flex-column gap-3">
                <div
                  v-for="entry in activeHearing.entries"
                  :key="entry.id"
                  class="p-3 rounded-4 border"
                  :class="{
                    'bg-danger bg-opacity-10 border-danger border-opacity-25': entry.entry_type === 'procedural_direction',
                    'bg-warning bg-opacity-10 border-warning border-opacity-25': entry.entry_type === 'jury_question',
                    'bg-success bg-opacity-10 border-success border-opacity-25': entry.entry_type === 'witness_testimony',
                    'bg-light': entry.entry_type !== 'procedural_direction' && entry.entry_type !== 'jury_question' && entry.entry_type !== 'witness_testimony',
                  }"
                >
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge font-monospace bg-secondary">#{{ entry.sequence_number }}</span>
                      <span class="badge" :class="getEntryBadge(entry.entry_type)">
                        {{ entry.entry_type.replace('_', ' ').toUpperCase() }}
                      </span>
                      <strong class="text-dark">
                        {{ entry.sender?.name || (entry.participant_type === 'jury_panel' ? 'Jury Panel' : entry.participant_type) }}
                      </strong>
                      <span v-if="entry.side" class="text-muted small text-capitalize">
                        ({{ entry.participant_type.replace('_', ' ') }} &bull; {{ entry.side }})
                      </span>
                    </div>
                    <span class="text-muted small font-monospace">
                      {{ formatDate(entry.created_at) }}
                    </span>
                  </div>

                  <div class="text-dark mb-2" style="white-space: pre-wrap;">
                    {{ entry.body }}
                  </div>

                  <!-- Question Answer Action -->
                  <div
                    v-if="isHearingActive && entry.entry_type === 'jury_question' && (entry.target_side === props.userSide || entry.target_side === 'both')"
                    class="mt-2 pt-2 border-top d-flex justify-content-end"
                  >
                    <button
                      type="button"
                      class="btn btn-sm btn-primary rounded-pill px-3"
                      @click="openReplyModal(entry)"
                    >
                      <i class="bi bi-reply-fill me-1" />Answer Question
                    </button>
                  </div>

                  <!-- Threaded Responses -->
                  <div v-if="entry.responses?.length" class="mt-3 ps-3 border-start border-3 border-primary">
                    <div class="small fw-bold text-muted mb-1">Responses:</div>
                    <div
                      v-for="resp in entry.responses"
                      :key="resp.id"
                      class="p-2 mb-2 rounded bg-white border"
                    >
                      <div class="d-flex justify-content-between small text-muted mb-1">
                        <strong>{{ resp.sender?.name || 'Party' }} ({{ resp.participant_type }})</strong>
                        <span>{{ formatDate(resp.created_at) }}</span>
                      </div>
                      <div class="text-dark small">{{ resp.body }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Submit Statement Entry Modal -->
    <div v-if="showEntryModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="fw-bold mb-0 text-capitalize">
              {{ entryForm.entry_type.replace('_', ' ') }}
            </h5>
            <button type="button" class="btn-close" @click="showEntryModal = false" />
          </div>
          <div class="modal-body p-4">
            <label class="form-label small fw-semibold">Statement Content</label>
            <textarea
              v-model="entryForm.body"
              rows="6"
              class="form-control"
              placeholder="Enter your formal statement for the Tribunal record..."
            />
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" @click="showEntryModal = false">Cancel</button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              :disabled="submittingEntry || !entryForm.body.trim()"
              @click="handleSubmitEntry"
            >
              {{ submittingEntry ? 'Submitting...' : 'Submit to Record' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reply to Question Modal -->
    <div v-if="showReplyModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="fw-bold mb-0 text-primary">Answer Jury Question</h5>
            <button type="button" class="btn-close" @click="showReplyModal = false" />
          </div>
          <div class="modal-body p-4">
            <div class="alert alert-warning py-2 small mb-3">
              <strong>Question from Jury Panel:</strong> "{{ replyQuestion?.body }}"
            </div>
            <label class="form-label small fw-semibold">Your Formal Response</label>
            <textarea
              v-model="replyBody"
              rows="5"
              class="form-control"
              placeholder="Enter your detailed answer..."
            />
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" @click="showReplyModal = false">Cancel</button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              :disabled="replying || !replyBody.trim()"
              @click="handleReplyQuestion"
            >
              {{ replying ? 'Sending...' : 'Send Response' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-purple {
  color: #6f42c1 !important;
}
.bg-purple {
  background-color: #6f42c1 !important;
}
.border-purple {
  border-color: #6f42c1 !important;
}
.max-w-600 {
  max-width: 600px;
}
</style>
