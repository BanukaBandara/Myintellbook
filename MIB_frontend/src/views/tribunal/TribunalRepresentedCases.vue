<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { useTribunalStore } from '@/stores/tribunal';
import TribunalClientChatModal from '@/components/tribunal/TribunalClientChatModal.vue';
import type { RepresentationAssignment } from '@/types/tribunal';

const router = useRouter();
const tribunalStore = useTribunalStore();

const currentPage = ref(1);

// Chat Modal
const showChatModal = ref(false);
const activeChatCase = ref<{ id: number; case_number: string; title: string } | null>(null);

const assignmentsData = computed(() => tribunalStore.representedCases);
const assignments = computed<RepresentationAssignment[]>(() => assignmentsData.value?.data || []);

const loadCases = async () => {
  await tribunalStore.fetchRepresentedCases(currentPage.value);
};

onMounted(async () => {
  await tribunalStore.fetchCapabilities();
  if (!tribunalStore.capabilities?.representative?.eligible) {
    await Swal.fire({
      icon: 'error',
      title: 'Restricted Access',
      text: 'Only verified Attorneys-at-Law can access the Represented Cases Dashboard.',
    });
    router.push('/tribunal/cases');
    return;
  }
  await loadCases();
});

const openChatForCase = (assignment: RepresentationAssignment) => {
  if (!assignment.case) return;
  activeChatCase.value = {
    id: assignment.tribunal_case_id,
    case_number: assignment.case.case_number,
    title: assignment.case.title,
  };
  showChatModal.value = true;
};

const handleEndRepresentation = async (assignment: RepresentationAssignment) => {
  const result = await Swal.fire({
    title: 'End Representation?',
    text: `Are you sure you want to withdraw or conclude representation for ${assignment.client?.name}? This will close privileged communications.`,
    icon: 'warning',
    input: 'textarea',
    inputPlaceholder: 'Reason for concluding representation (optional)...',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Conclude Representation',
  });

  if (result.isConfirmed) {
    try {
      await tribunalStore.endRepresentation(assignment.tribunal_case_id, result.value || undefined);
      await Swal.fire({
        icon: 'success',
        title: 'Representation Concluded',
        text: 'You are no longer the legal representative for this case.',
      });
      await loadCases();
    } catch (err: any) {
      await Swal.fire({
        icon: 'error',
        title: 'Action Failed',
        text: err?.response?.data?.message || 'Failed to end representation.',
      });
    }
  }
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
            <i class="bi bi-briefcase-fill me-1"></i>
            Legal Counsel Practice
          </span>
        </div>
        <h2 class="fw-bold text-dark mb-0">My Represented Cases</h2>
        <p class="text-muted small mb-0">
          Tribunal cases where you are retained as authorized legal counsel.
        </p>
      </div>

      <div class="d-flex gap-2">
        <button
          type="button"
          class="btn btn-outline-primary rounded-pill px-3"
          @click="router.push('/tribunal/representation-requests')"
        >
          <i class="bi bi-inbox me-1"></i>
          Representation Requests Inbox
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="tribunalStore.loading" class="text-center py-5 bg-white rounded-4 shadow-sm">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading represented cases...</span>
      </div>
      <div class="text-muted mt-2 small">Loading active representations...</div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="assignments.length === 0"
      class="text-center py-5 bg-white rounded-4 shadow-sm border"
    >
      <i class="bi bi-folder-x fs-1 text-muted opacity-50 mb-3 d-block"></i>
      <h5 class="fw-bold text-dark">No Represented Cases Found</h5>
      <p class="text-muted small mb-3">
        You do not currently have any active or past client representations in External Tribunal cases.
      </p>
      <button
        type="button"
        class="btn btn-primary rounded-pill px-4"
        @click="router.push('/tribunal/representation-requests')"
      >
        Check Request Inbox
      </button>
    </div>

    <!-- Assignments Stream -->
    <div v-else class="d-flex flex-column gap-3">
      <div
        v-for="item in assignments"
        :key="item.id"
        class="card border-0 rounded-4 shadow-sm p-4 bg-white hover-shadow transition"
      >
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge bg-secondary font-monospace px-2 py-1">
                {{ item.case?.case_number || ('Case #' + item.tribunal_case_id) }}
              </span>
              <span
                class="badge rounded-pill text-capitalize"
                :class="item.status === 'active' 
                  ? 'bg-success-subtle text-success border border-success-subtle' 
                  : 'bg-secondary-subtle text-secondary'"
              >
                {{ item.status === 'active' ? 'Active Retainer' : 'Concluded' }}
              </span>
              <span class="badge bg-light text-dark border rounded-pill">
                Client Side: {{ item.side }}
              </span>
            </div>
            <h5 class="fw-bold text-dark mb-1">
              {{ item.case?.title || 'Case Title Unavailable' }}
            </h5>
            <div class="text-muted small">
              Category: {{ item.case?.category || '-' }} &bull; Severity: <span class="text-capitalize">{{ item.case?.severity || '-' }}</span>
            </div>
          </div>

          <div class="text-end">
            <small class="text-muted d-block">Retained Since</small>
            <strong class="text-dark small">{{ formatDate(item.accepted_at) }}</strong>
          </div>
        </div>

        <!-- Client Box -->
        <div class="p-3 bg-light rounded-3 mb-3 border">
          <div class="row g-2 align-items-center">
            <div class="col-md-5">
              <small class="text-muted d-block">Client Represented</small>
              <strong class="text-dark">{{ item.client?.name || ('User #' + item.client_user_id) }}</strong>
              <span class="badge bg-primary-subtle text-primary rounded-pill ms-2 text-capitalize">
                {{ item.side }}
              </span>
            </div>
            <div class="col-md-7 text-md-end">
              <small class="text-muted d-block">Tribunal Status</small>
              <span class="badge bg-dark-subtle text-dark border rounded-pill text-capitalize">
                {{ item.case?.status ? String(item.case.status).replace(/_/g, ' ') : 'Under Proceedings' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Ended Details -->
        <div v-if="item.status === 'ended'" class="alert alert-secondary py-2 px-3 small mb-3">
          <strong>Representation Concluded on {{ formatDate(item.ended_at) }}</strong>
          <span v-if="item.end_reason"> &bull; Reason: <em>"{{ item.end_reason }}"</em></span>
        </div>

        <!-- Action Footer -->
        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 pt-2 border-top">
          <template v-if="item.status === 'active'">
            <button
              type="button"
              class="btn btn-outline-danger btn-sm rounded-pill px-3"
              @click="handleEndRepresentation(item)"
            >
              <i class="bi bi-x-circle me-1"></i>
              End Representation
            </button>
            <button
              type="button"
              class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm"
              @click="openChatForCase(item)"
            >
              <i class="bi bi-chat-dots-fill me-1"></i>
              Confidential Client Consultation
            </button>
          </template>
          <button
            type="button"
            class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm"
            @click="router.push(`/tribunal/cases/${item.tribunal_case_id}`)"
          >
            <i class="bi bi-folder2-open me-1"></i>
            Open Case Dossier
          </button>
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
.hover-shadow:hover {
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
.transition {
  transition: all 0.2s ease;
}
</style>
