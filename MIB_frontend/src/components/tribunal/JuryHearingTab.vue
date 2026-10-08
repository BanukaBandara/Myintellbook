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
}>();

const emit = defineEmits<{
  (e: 'caseUpdated'): void;
}>();

const loading = ref(false);
const error = ref<string | null>(null);

const hearings = ref<TribunalHearing[]>([]);
const activeHearing = ref<TribunalHearing | null>(null);

// Modals
const showScheduleModal = ref(false);
const scheduling = ref(false);
const scheduleForm = ref({
  hearing_type: 'formal',
  scheduled_at: '',
  location_type: 'online',
  meeting_link: '',
  notes: '',
  });

const showDirectionModal = ref(false);
const postingDirection = ref(false);
const directionBody = ref('');

const showQuestionModal = ref(false);
const askingQuestion = ref(false);
const questionBody = ref('');
const questionTarget = ref<'complainant' | 'respondent' | 'both'>('both');

const actionInProgress = ref(false);

const isDeliberationReady = computed(() => {
  return props.caseStatus === 'deliberation' || (activeHearing.value && activeHearing.value.status === 'completed');
});

const canSchedule = computed(() => {
  if (isDeliberationReady.value) return false;
  if (!activeHearing.value) return true;
  return activeHearing.value.status === 'completed' || activeHearing.value.status === 'cancelled';
});

const fetchHearingData = async () => {
  loading.value = true;
  error.value = null;
  try {
    const hearingRes = await tribunalService.getJuryHearings(props.caseId);

    hearings.value = hearingRes.hearings || [];
    activeHearing.value = hearingRes.active_hearing || null;

    if (activeHearing.value?.id) {
      const singleRes = await tribunalService.getJuryHearing(activeHearing.value.id);
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

const handleScheduleHearing = async () => {
  if (scheduling.value) return;
  scheduling.value = true;
  try {
    await tribunalService.scheduleJuryHearing(props.caseId, {
      hearing_type: scheduleForm.value.hearing_type,
      scheduled_at: scheduleForm.value.scheduled_at || undefined,
      location_type: scheduleForm.value.location_type,
      meeting_link: scheduleForm.value.meeting_link || undefined,
      notes: scheduleForm.value.notes || undefined,
    });
    showScheduleModal.value = false;
    await fetchHearingData();
    emit('caseUpdated');
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to schedule hearing.');
  } finally {
    scheduling.value = false;
  }
};

const handleStartHearing = async () => {
  if (!activeHearing.value || actionInProgress.value) return;
  if (!confirm('Commence formal Tribunal hearing now? Case status will transition to Hearing.')) return;
  actionInProgress.value = true;
  try {
    await tribunalService.startJuryHearing(activeHearing.value.id);
    await fetchHearingData();
    emit('caseUpdated');
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to start hearing.');
  } finally {
    actionInProgress.value = false;
  }
};

const handleRecessHearing = async () => {
  if (!activeHearing.value || actionInProgress.value) return;
  if (!confirm('Order temporary recess in the hearing?')) return;
  actionInProgress.value = true;
  try {
    await tribunalService.recessJuryHearing(activeHearing.value.id);
    await fetchHearingData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to recess hearing.');
  } finally {
    actionInProgress.value = false;
  }
};

const handleResumeHearing = async () => {
  if (!activeHearing.value || actionInProgress.value) return;
  actionInProgress.value = true;
  try {
    await tribunalService.resumeJuryHearing(activeHearing.value.id);
    await fetchHearingData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to resume hearing.');
  } finally {
    actionInProgress.value = false;
  }
};

const handleCloseHearing = async () => {
  if (!activeHearing.value || actionInProgress.value) return;
  if (!confirm('Formally close the hearing? The case will immediately move to deliberation.')) return;
  actionInProgress.value = true;
  try {
    await tribunalService.closeJuryHearing(activeHearing.value.id);
    await fetchHearingData();
    emit('caseUpdated');
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to close hearing.');
  } finally {
    actionInProgress.value = false;
  }
};

const handlePostDirection = async () => {
  if (!activeHearing.value || !directionBody.value.trim() || postingDirection.value) return;
  postingDirection.value = true;
  try {
    await tribunalService.addJuryHearingEntry(activeHearing.value.id, {
      entry_type: 'procedural_direction',
      body: directionBody.value.trim(),
    });
    directionBody.value = '';
    showDirectionModal.value = false;
    await fetchHearingData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to post procedural direction.');
  } finally {
    postingDirection.value = false;
  }
};

const handleAskQuestion = async () => {
  if (!activeHearing.value || !questionBody.value.trim() || askingQuestion.value) return;
  askingQuestion.value = true;
  try {
    await tribunalService.askJuryHearingQuestion(activeHearing.value.id, {
      body: questionBody.value.trim(),
      target_side: questionTarget.value,
    });
    questionBody.value = '';
    showQuestionModal.value = false;
    await fetchHearingData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to post question.');
  } finally {
    askingQuestion.value = false;
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
  <div class="jury-hearing-tab">
    <!-- Ready for Deliberation Placeholder Banner -->
    <div v-if="isDeliberationReady" class="card border-0 shadow-sm rounded-4 mb-4 bg-purple bg-opacity-10 border border-purple border-opacity-25">
      <div class="card-body p-4 text-center">
        <div class="d-inline-flex p-3 bg-purple bg-opacity-25 text-purple rounded-circle mb-3">
          <i class="bi bi-bank2 fs-2" />
        </div>
        <h4 class="fw-bold text-dark mb-2">Hearing Completed — Ready for Jury Panel Deliberation</h4>
        <p class="text-muted max-w-600 mx-auto mb-0">
          The formal hearing session has concluded. Case evidence and hearing statements have been entered into the official record. Deliberation and verdict workflows will occur in the next judicial stage.
        </p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading hearing...</span>
      </div>
      <p class="text-muted small mt-2">Loading hearing dossiers and transcripts...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger rounded-4 shadow-sm mb-4">
      <i class="bi bi-exclamation-triangle-fill me-2" />{{ error }}
    </div>

    <div v-else>
      <!-- Top Action / Status Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-3 py-1" :class="activeHearing ? getStatusBadge(activeHearing.status) : 'bg-secondary'">
                  {{ activeHearing ? activeHearing.status.toUpperCase() : 'NO ACTIVE HEARING' }}
                </span>
                <span v-if="activeHearing" class="fw-bold font-monospace text-primary">
                  {{ activeHearing.hearing_number }}
                </span>
                <span v-if="activeHearing" class="badge bg-light text-dark border">
                  {{ activeHearing.hearing_type }}
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
                  <i class="bi bi-geo-alt me-1" />Location: <strong>{{ activeHearing.location_type }}</strong>
                </span>
                <span v-if="activeHearing?.meeting_link" class="ms-2">
                  <a :href="activeHearing.meeting_link" target="_blank" class="btn btn-sm btn-link p-0 text-decoration-none">
                    <i class="bi bi-link-45deg" />Meeting Link
                  </a>
                </span>
              </p>
            </div>

            <!-- Jury Action Controls -->
            <div class="d-flex flex-wrap gap-2">
              <button
                v-if="canSchedule"
                type="button"
                class="btn btn-primary rounded-pill px-3"
                @click="showScheduleModal = true"
              >
                <i class="bi bi-calendar-plus me-1" />Schedule Hearing
              </button>

              <button
                v-if="activeHearing?.status === 'scheduled'"
                type="button"
                class="btn btn-success rounded-pill px-3"
                :disabled="actionInProgress"
                @click="handleStartHearing"
              >
                <i class="bi bi-play-fill me-1" />Start Hearing
              </button>

              <button
                v-if="activeHearing?.status === 'active'"
                type="button"
                class="btn btn-warning rounded-pill px-3"
                :disabled="actionInProgress"
                @click="handleRecessHearing"
              >
                <i class="bi bi-pause-fill me-1" />Recess
              </button>

              <button
                v-if="activeHearing?.status === 'recessed'"
                type="button"
                class="btn btn-success rounded-pill px-3"
                :disabled="actionInProgress"
                @click="handleResumeHearing"
              >
                <i class="bi bi-play-circle me-1" />Resume
              </button>

              <button
                v-if="activeHearing?.status === 'active'"
                type="button"
                class="btn btn-outline-danger rounded-pill px-3"
                @click="showDirectionModal = true"
              >
                <i class="bi bi-megaphone me-1" />Procedural Direction
              </button>

              <button
                v-if="activeHearing?.status === 'active'"
                type="button"
                class="btn btn-outline-primary rounded-pill px-3"
                @click="showQuestionModal = true"
              >
                <i class="bi bi-question-circle me-1" />Ask Question
              </button>

              <button
                v-if="activeHearing?.status === 'active' || activeHearing?.status === 'recessed'"
                type="button"
                class="btn btn-danger rounded-pill px-3"
                :disabled="actionInProgress"
                @click="handleCloseHearing"
              >
                <i class="bi bi-x-circle me-1" />Close Hearing
              </button>
            </div>
          </div>

          <div v-if="activeHearing?.notes" class="mt-3 pt-3 border-top text-muted small">
            <strong>Notes / Order:</strong> {{ activeHearing.notes }}
          </div>
        </div>
      </div>

      <!-- Two-Column Layout: Participants | Hearing Record Transcript -->
      <div class="row g-4">
        <!-- Left: Participants -->
        <div class="col-lg-5">
          <!-- Participants Card -->
          <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
              <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-people me-2 text-primary" />Hearing Participants
              </h5>
            </div>
            <div class="card-body p-4 pt-2">
              <div v-if="!activeHearing?.participants?.length" class="text-muted small py-2">
                No participants registered yet.
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
                      {{ p.participant_type.replace('_', ' ') }} &bull; {{ p.side || 'Neutral' }}
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

        <!-- Right: Official Hearing Record Transcript -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold text-dark mb-0">
                  <i class="bi bi-journal-text me-2 text-primary" />Official Hearing Transcript
                </h5>
                <p class="text-muted small mb-0">Append-only immutable record of hearing sessions</p>
              </div>
              <span v-if="activeHearing?.entries?.length" class="badge bg-primary rounded-pill">
                {{ activeHearing.entries.length }} Entries
              </span>
            </div>

            <div class="card-body p-4">
              <div v-if="!activeHearing?.entries?.length" class="text-center py-5 text-muted">
                <i class="bi bi-chat-left-dots fs-1 d-block mb-2 text-secondary" />
                No hearing record entries yet. Start the hearing to begin recording statements.
              </div>

              <!-- Entries Timeline -->
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

                  <div v-if="entry.relatedEvidence" class="small text-muted mb-1">
                    <i class="bi bi-paperclip me-1" />Evidence: <strong>{{ entry.relatedEvidence.evidence_number }} — {{ entry.relatedEvidence.title }}</strong>
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

    <!-- Schedule Modal -->
    <div v-if="showScheduleModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="fw-bold mb-0">Schedule Formal Hearing</h5>
            <button type="button" class="btn-close" @click="showScheduleModal = false" />
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label small fw-semibold">Hearing Type</label>
              <select v-model="scheduleForm.hearing_type" class="form-select">
                <option value="formal">Formal Hearing</option>
                <option value="preliminary">Preliminary Session</option>
                <option value="continuation">Continuation</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Scheduled Date &amp; Time</label>
              <input v-model="scheduleForm.scheduled_at" type="datetime-local" class="form-control" />
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Location / Mode</label>
              <select v-model="scheduleForm.location_type" class="form-select">
                <option value="online">Online (Tribunal Hearing Record)</option>
                <option value="physical">Physical Session</option>
                <option value="hybrid">Hybrid</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Meeting Link (Optional)</label>
              <input v-model="scheduleForm.meeting_link" type="url" placeholder="https://..." class="form-control" />
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Notes / Orders to Parties (Optional)</label>
              <textarea v-model="scheduleForm.notes" rows="3" class="form-control" placeholder="Procedural directions prior to hearing..." />
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" @click="showScheduleModal = false">Cancel</button>
            <button type="button" class="btn btn-primary rounded-pill px-4" :disabled="scheduling" @click="handleScheduleHearing">
              {{ scheduling ? 'Scheduling...' : 'Confirm Schedule' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Direction Modal -->
    <div v-if="showDirectionModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="fw-bold mb-0 text-danger">Issue Procedural Direction</h5>
            <button type="button" class="btn-close" @click="showDirectionModal = false" />
          </div>
          <div class="modal-body p-4">
            <label class="form-label small fw-semibold">Direction / Order</label>
            <textarea v-model="directionBody" rows="4" class="form-control" placeholder="Enter formal order to parties..." />
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" @click="showDirectionModal = false">Cancel</button>
            <button type="button" class="btn btn-danger rounded-pill px-4" :disabled="postingDirection || !directionBody.trim()" @click="handlePostDirection">
              {{ postingDirection ? 'Posting...' : 'Issue Direction' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Question Modal -->
    <div v-if="showQuestionModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="fw-bold mb-0 text-primary">Direct Hearing Question</h5>
            <button type="button" class="btn-close" @click="showQuestionModal = false" />
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label small fw-semibold">Direct Question To</label>
              <select v-model="questionTarget" class="form-select">
                <option value="both">Both Parties</option>
                <option value="complainant">Complainant (and Counsel)</option>
                <option value="respondent">Respondent (and Counsel)</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Question</label>
              <textarea v-model="questionBody" rows="4" class="form-control" placeholder="Enter question for the party..." />
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" @click="showQuestionModal = false">Cancel</button>
            <button type="button" class="btn btn-primary rounded-pill px-4" :disabled="askingQuestion || !questionBody.trim()" @click="handleAskQuestion">
              {{ askingQuestion ? 'Posting...' : 'Send Question' }}
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
