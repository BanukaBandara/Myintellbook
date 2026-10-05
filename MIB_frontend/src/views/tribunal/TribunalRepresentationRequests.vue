<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { useTribunalStore } from '@/stores/tribunal';
import TribunalClientChatModal from '@/components/tribunal/TribunalClientChatModal.vue';
import type { RepresentationRequest } from '@/types/tribunal';

const router = useRouter();
const tribunalStore = useTribunalStore();

const activeTab = ref<'pending' | 'accepted' | 'declined' | 'all'>('pending');
const currentPage = ref(1);

// Modals
const showChatModal = ref(false);
const activeChatCase = ref<{ id: number; case_number: string; title: string } | null>(null);

const showDeclineModal = ref(false);
const requestToDecline = ref<RepresentationRequest | null>(null);
const declineReason = ref('');
const isDeclining = ref(false);

const showAcceptModal = ref(false);
const requestToAccept = ref<RepresentationRequest | null>(null);
const acceptNote = ref('');
const isAccepting = ref(false);

const requestsData = computed(() => tribunalStore.lawyerRequests);
const requests = computed<RepresentationRequest[]>(() => requestsData.value?.data || []);
const meta = computed(() => requestsData.value?.meta || {});

const loadRequests = async () => {
  await tribunalStore.fetchLawyerRequests(activeTab.value, currentPage.value);
};

watch(activeTab, () => {
  currentPage.value = 1;
  loadRequests();
});

onMounted(async () => {
  await tribunalStore.fetchCapabilities();
  if (!tribunalStore.capabilities?.representative?.eligible) {
    // Not an eligible attorney
    await Swal.fire({
      icon: 'error',
      title: 'Restricted Access',
      text: 'Only verified Attorneys-at-Law can access the Representation Request Inbox.',
    });
    router.push('/tribunal/cases');
    return;
  }
  await loadRequests();
});

const openAcceptModal = (req: RepresentationRequest) => {
  requestToAccept.value = req;
  acceptNote.value = '';
  showAcceptModal.value = true;
};

const handleAccept = async () => {
  if (!requestToAccept.value) return;
  isAccepting.value = true;
  try {
    await tribunalStore.acceptRepresentationRequest(
      requestToAccept.value.id,
      acceptNote.value.trim() || undefined
    );
    showAcceptModal.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Representation Accepted',
      text: 'You have been assigned as legal representative. The client has been notified and private consultation is now open.',
    });
    await loadRequests();
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err?.response?.data?.message || 'Failed to accept request.',
    });
  } finally {
    isAccepting.value = false;
  }
};

const openDeclineModal = (req: RepresentationRequest) => {
  requestToDecline.value = req;
  declineReason.value = '';
  showDeclineModal.value = true;
};

const handleDecline = async () => {
  if (!requestToDecline.value || !declineReason.value.trim()) return;
  isDeclining.value = true;
  try {
    await tribunalStore.declineRepresentationRequest(
      requestToDecline.value.id,
      declineReason.value.trim()
    );
    showDeclineModal.value = false;
    await Swal.fire({
      icon: 'info',
      title: 'Request Declined',
      text: 'The client has been notified of your decision.',
    });
    await loadRequests();
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err?.response?.data?.message || 'Failed to decline request.',
    });
  } finally {
    isDeclining.value = false;
  }
};

const openChatForCase = (req: RepresentationRequest) => {
  if (!req.case) return;
  activeChatCase.value = {
    id: req.tribunal_case_id,
    case_number: req.case.case_number,
    title: req.case.title,
  };
  showChatModal.value = true;
};

const formatDate = (isoString?: string | null) => {
  if (!isoString) return '-';
  const d = new Date(isoString);
  return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">
            <i class="bi bi-shield-check me-1"></i>
            Verified Attorney Portal
          </span>
        </div>
        <h2 class="fw-bold text-dark mb-0">Representation Requests</h2>
        <p class="text-muted small mb-0">
          Review and respond to client requests for legal representation in External Tribunal cases.
        </p>
      </div>

      <div class="d-flex gap-2">
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3"
          @click="router.push('/tribunal/represented-cases')"
        >
          <i class="bi bi-briefcase me-1"></i>
          My Represented Cases
        </button>
      </div>
    </div>

    <!-- Filter Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-4 shadow-sm border">
      <li class="nav-item">
        <button
          type="button"
          class="nav-link rounded-pill px-4"
          :class="{ active: activeTab === 'pending' }"
          @click="activeTab = 'pending'"
        >
          <i class="bi bi-hourglass-split me-1"></i>
          Pending
        </button>
      </li>
      <li class="nav-item">
        <button
          type="button"
          class="nav-link rounded-pill px-4"
          :class="{ active: activeTab === 'accepted' }"
          @click="activeTab = 'accepted'"
        >
          <i class="bi bi-check-circle me-1"></i>
          Accepted
        </button>
      </li>
      <li class="nav-item">
        <button
          type="button"
          class="nav-link rounded-pill px-4"
          :class="{ active: activeTab === 'declined' }"
          @click="activeTab = 'declined'"
        >
          <i class="bi bi-x-circle me-1"></i>
          Declined
        </button>
      </li>
      <li class="nav-item">
        <button
          type="button"
          class="nav-link rounded-pill px-4"
          :class="{ active: activeTab === 'all' }"
          @click="activeTab = 'all'"
        >
          All Requests
        </button>
      </li>
    </ul>

    <!-- Loading State -->
    <div v-if="tribunalStore.loading" class="text-center py-5 bg-white rounded-4 shadow-sm">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading requests...</span>
      </div>
      <div class="text-muted mt-2 small">Loading representation requests...</div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="requests.length === 0"
      class="text-center py-5 bg-white rounded-4 shadow-sm border"
    >
      <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
      <h5 class="fw-bold text-dark">No Representation Requests</h5>
      <p class="text-muted small mb-0">
        You currently have no {{ activeTab !== 'all' ? activeTab : '' }} representation requests.
      </p>
    </div>

    <!-- Requests Stream -->
    <div v-else class="d-flex flex-column gap-3">
      <div
        v-for="req in requests"
        :key="req.id"
        class="card border-0 rounded-4 shadow-sm p-4 bg-white"
      >
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="badge bg-secondary font-monospace px-2 py-1">
                {{ req.case?.case_number || ('Case #' + req.tribunal_case_id) }}
              </span>
              <span
                class="badge rounded-pill text-capitalize"
                :class="{
                  'bg-warning-subtle text-warning-emphasis border border-warning-subtle': req.status === 'pending',
                  'bg-success-subtle text-success border border-success-subtle': req.status === 'accepted',
                  'bg-danger-subtle text-danger border border-danger-subtle': req.status === 'declined',
                  'bg-secondary-subtle text-secondary': req.status === 'cancelled'
                }"
              >
                {{ req.status }}
              </span>
              <span class="badge bg-light text-dark border rounded-pill">
                Client Side: {{ req.side }}
              </span>
            </div>
            <h5 class="fw-bold text-dark mb-1">
              {{ req.case?.title || 'Case Title Unavailable' }}
            </h5>
            <div class="text-muted small">
              Category: {{ req.case?.category || '-' }} &bull; Severity: <span class="text-capitalize">{{ req.case?.severity || '-' }}</span>
            </div>
          </div>

          <div class="text-end">
            <small class="text-muted d-block">Requested At</small>
            <strong class="text-dark small">{{ formatDate(req.requested_at) }}</strong>
          </div>
        </div>

        <!-- Client Information Box -->
        <div class="p-3 bg-light rounded-3 mb-3 border">
          <div class="row g-2 align-items-center">
            <div class="col-md-4">
              <small class="text-muted d-block">Prospective Client</small>
              <strong class="text-dark">{{ req.client?.name || ('User #' + req.client_user_id) }}</strong>
            </div>
            <div class="col-md-8">
              <small class="text-muted d-block">Client's Initial Message / Notes</small>
              <span class="text-dark fst-italic">
                "{{ req.message || 'No additional note provided by client.' }}"
              </span>
            </div>
          </div>
        </div>

        <!-- Status Responses -->
        <div v-if="req.status === 'declined' && req.decline_reason" class="alert alert-danger-subtle py-2 px-3 small mb-3">
          <strong>Decline Reason Stated:</strong> {{ req.decline_reason }}
        </div>

        <!-- Action Footer -->
        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 pt-2 border-top">
          <!-- Pending Actions -->
          <template v-if="req.status === 'pending'">
            <button
              type="button"
              class="btn btn-outline-danger btn-sm rounded-pill px-3"
              @click="openDeclineModal(req)"
            >
              <i class="bi bi-x me-1"></i>
              Decline Request
            </button>
            <button
              type="button"
              class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm"
              @click="openAcceptModal(req)"
            >
              <i class="bi bi-check-lg me-1"></i>
              Accept Representation
            </button>
          </template>

          <!-- Accepted Actions -->
          <template v-else-if="req.status === 'accepted'">
            <button
              type="button"
              class="btn btn-outline-primary btn-sm rounded-pill px-3"
              @click="openChatForCase(req)"
            >
              <i class="bi bi-chat-dots-fill me-1"></i>
              Confidential Consultation
            </button>
            <button
              type="button"
              class="btn btn-primary btn-sm rounded-pill px-3"
              @click="router.push(`/tribunal/cases/${req.tribunal_case_id}`)"
            >
              <i class="bi bi-folder2-open me-1"></i>
              View Case Dossier
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- ACCEPT MODAL -->
    <div v-if="showAcceptModal" class="modal-backdrop fade show"></div>
    <div
      v-if="showAcceptModal"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-primary text-white border-0 py-3">
            <h6 class="modal-title fw-bold">Accept Legal Representation</h6>
            <button
              type="button"
              class="btn-close btn-close-white"
              @click="showAcceptModal = false"
            ></button>
          </div>
          <div class="modal-body p-4">
            <p class="text-dark mb-3">
              You are agreeing to represent <strong>{{ requestToAccept?.client?.name }}</strong> in Case <strong>{{ requestToAccept?.case?.case_number }}</strong>.
            </p>
            <div class="mb-3">
              <label class="form-label fw-bold small text-muted">Response Note to Client (Optional)</label>
              <textarea
                v-model="acceptNote"
                class="form-control rounded-3"
                rows="3"
                placeholder="e.g. I am pleased to accept representation and look forward to reviewing your case."
              ></textarea>
            </div>
            <div class="alert alert-info py-2 px-3 small mb-0">
              <i class="bi bi-info-circle me-1"></i>
              Accepting will grant you full authorized access to the case dossier, evidence registry, and open a confidential privileged chat.
            </div>
          </div>
          <div class="modal-footer border-0 p-3 pt-0">
            <button
              type="button"
              class="btn btn-light rounded-pill px-3"
              :disabled="isAccepting"
              @click="showAcceptModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4 shadow-sm"
              :disabled="isAccepting"
              @click="handleAccept"
            >
              <span v-if="isAccepting" class="spinner-border spinner-border-sm me-1"></span>
              Confirm & Accept
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- DECLINE MODAL -->
    <div v-if="showDeclineModal" class="modal-backdrop fade show"></div>
    <div
      v-if="showDeclineModal"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-danger text-white border-0 py-3">
            <h6 class="modal-title fw-bold">Decline Legal Representation</h6>
            <button
              type="button"
              class="btn-close btn-close-white"
              @click="showDeclineModal = false"
            ></button>
          </div>
          <div class="modal-body p-4">
            <p class="text-dark mb-3">
              Please specify the reason for declining representation for <strong>{{ requestToDecline?.client?.name }}</strong>.
            </p>
            <div class="mb-3">
              <label class="form-label fw-bold small text-muted">Reason for Declining <span class="text-danger">*</span></label>
              <textarea
                v-model="declineReason"
                class="form-control rounded-3"
                rows="3"
                placeholder="e.g. Schedule conflicts, lack of jurisdiction, or current trial obligations..."
                required
              ></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 p-3 pt-0">
            <button
              type="button"
              class="btn btn-light rounded-pill px-3"
              :disabled="isDeclining"
              @click="showDeclineModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-danger rounded-pill px-4 shadow-sm"
              :disabled="!declineReason.trim() || isDeclining"
              @click="handleDecline"
            >
              <span v-if="isDeclining" class="spinner-border spinner-border-sm me-1"></span>
              Decline Request
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confidential Chat Modal -->
    <TribunalClientChatModal
      v-if="activeChatCase"
      :show="showChatModal"
      :case-id="activeChatCase.id"
      :case-number="activeChatCase.case_number"
      :case-title="activeChatCase.title"
      @close="showChatModal = false"
    />
  </div>
</template>

<style scoped>
.modal-backdrop {
  z-index: 1050;
  background-color: rgba(0, 0, 0, 0.5);
}
.modal {
  z-index: 1055;
}
</style>
