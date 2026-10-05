<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { useTribunalStore } from '@/stores/tribunal';
import type { TribunalJuryAssignment } from '@/types/tribunal';

const router = useRouter();
const tribunalStore = useTribunalStore();

const activeTab = ref<'pending' | 'active'>('pending');
const processingId = ref<number | null>(null);

const pendingAssignments = computed<TribunalJuryAssignment[]>(
  () => tribunalStore.jurorCases.pending || []
);

const activeAssignments = computed<TribunalJuryAssignment[]>(
  () => tribunalStore.jurorCases.active || []
);

onMounted(async () => {
  await tribunalStore.fetchCapabilities();
  if (tribunalStore.capabilities?.adjudicator?.eligible) {
    await tribunalStore.fetchJurorCases();
    if (pendingAssignments.value.length === 0 && activeAssignments.value.length > 0) {
      activeTab.value = 'active';
    }
  }
});

const handleAcceptNoConflict = async (assignment: TribunalJuryAssignment) => {
  const result = await Swal.fire({
    title: 'Confirm No Conflict of Interest',
    text: `You confirm that you have no personal, professional, or financial connection with the complainant or respondent in case ${assignment.case?.case_number}.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Accept Appointment',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0d6efd',
  });

  if (!result.isConfirmed) return;

  processingId.value = assignment.id;
  const res = await tribunalStore.declareJuryConflict(assignment.tribunal_case_id, {
    has_conflict: false,
  });
  processingId.value = null;

  if (res) {
    await Swal.fire({
      icon: 'success',
      title: 'Appointment Accepted',
      text: 'You have accepted the adjudicator assignment for this case. You may now review full case details and evidence.',
    });
    activeTab.value = 'active';
  } else {
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: tribunalStore.error ?? 'Failed to accept assignment.',
    });
  }
};

const handleDeclareConflict = async (assignment: TribunalJuryAssignment) => {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Declare Conflict of Interest',
    text: `Please state your reason for recusal from case ${assignment.case?.case_number}. This will remain confidential.`,
    input: 'textarea',
    inputPlaceholder: 'e.g. I personally know the respondent / I have a business relationship with the complainant...',
    inputAttributes: {
      minlength: '10',
      maxlength: '5000',
    },
    inputValidator: (val) => {
      if (!val || val.trim().length < 10) {
        return 'Please enter a valid explanation of at least 10 characters.';
      }
      return null;
    },
    showCancelButton: true,
    confirmButtonText: 'Submit Recusal',
    confirmButtonColor: '#dc3545',
    cancelButtonText: 'Cancel',
  });

  if (!isConfirmed || !reason) return;

  processingId.value = assignment.id;
  const res = await tribunalStore.declareJuryConflict(assignment.tribunal_case_id, {
    has_conflict: true,
    conflict_reason: reason.trim(),
  });
  processingId.value = null;

  if (res) {
    await Swal.fire({
      icon: 'info',
      title: 'Conflict Declared',
      text: 'You have been recused from this case. A replacement juror will be selected by the system.',
    });
  } else {
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: tribunalStore.error ?? 'Failed to submit conflict declaration.',
    });
  }
};

const openCase = (caseId: number) => {
  router.push(`/tribunal/cases/${caseId}`);
};

const formatDate = (dateStr?: string | null): string => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};
</script>

<template>
  <div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
      <div>
        <h2 class="fw-bold mb-1 text-primary">
          <i class="bi bi-bank me-2" />Tribunal Adjudicator Portal
        </h2>
        <p class="text-muted mb-0">
          Review your adjudicator case appointments, declare conflicts of interest, and deliberate on assigned dispute cases.
        </p>
      </div>
      <div>
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3 me-2"
          @click="router.push('/tribunal/cases')"
        >
          <i class="bi bi-briefcase me-1" />
          My Disputes
        </button>
        <button
          type="button"
          class="btn btn-primary rounded-pill px-3"
          :disabled="tribunalStore.loading"
          @click="tribunalStore.fetchJurorCases()"
        >
          <i class="bi bi-arrow-clockwise me-1" :class="{ 'spin': tribunalStore.loading }" />
          Refresh
        </button>
      </div>
    </div>

    <!-- Access Restricted State for Ordinary Users -->
    <div
      v-if="!tribunalStore.loading && tribunalStore.capabilities && !tribunalStore.capabilities.adjudicator.eligible"
      class="card border-0 rounded-4 shadow-sm p-5 text-center my-4"
      style="max-width: 700px; margin: 0 auto;"
    >
      <div class="mb-3">
        <i class="bi bi-shield-lock-fill text-warning fs-1" />
      </div>
      <h4 class="fw-bold mb-2 text-dark">
        Tribunal Adjudicator Access Restricted
      </h4>
      <p class="text-muted mb-4">
        Adjudicator appointments and deliberations are strictly reserved for verified legal professionals who meet Tribunal qualification requirements. Ordinary user accounts cannot serve as Tribunal decision-makers.
      </p>

      <div class="d-flex justify-content-center gap-3">
        <button
          type="button"
          class="btn btn-primary rounded-pill px-4"
          @click="router.push('/professional-verification')"
        >
          <i class="bi bi-patch-check me-1" />
          Apply for Professional Verification
        </button>
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-4"
          @click="router.push('/tribunal/cases')"
        >
          <i class="bi bi-briefcase me-1" />
          My Disputes
        </button>
      </div>
    </div>

    <!-- Eligible Adjudicator Portal Content -->
    <div v-else>
      <!-- Navigation Tabs -->
      <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
          <button
            type="button"
            class="nav-link rounded-pill position-relative px-4"
            :class="{ active: activeTab === 'pending' }"
            @click="activeTab = 'pending'"
          >
            <i class="bi bi-exclamation-circle me-1" />
            Pending Assignments
            <span
              v-if="pendingAssignments.length > 0"
              class="badge bg-danger rounded-pill ms-2"
            >
              {{ pendingAssignments.length }}
            </span>
          </button>
        </li>
        <li class="nav-item">
          <button
            type="button"
            class="nav-link rounded-pill position-relative px-4"
            :class="{ active: activeTab === 'active' }"
            @click="activeTab = 'active'"
          >
            <i class="bi bi-check2-circle me-1" />
            Active Adjudications
            <span
              v-if="activeAssignments.length > 0"
              class="badge bg-success rounded-pill ms-2"
            >
              {{ activeAssignments.length }}
            </span>
          </button>
        </li>
      </ul>

      <!-- Loading State -->
      <div
        v-if="tribunalStore.loading && pendingAssignments.length === 0 && activeAssignments.length === 0"
        class="text-center py-5 bg-white rounded-4 shadow-sm"
      >
        <div class="spinner-border text-primary" role="status" />
        <p class="mt-3 text-muted">
          Loading adjudicator assignments...
        </p>
      </div>

      <!-- TAB 1: PENDING ASSIGNMENTS -->
      <div v-else-if="activeTab === 'pending'">
        <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4 d-flex align-items-start gap-3">
          <i class="bi bi-shield-exclamation fs-3 text-warning" />
          <div>
            <h6 class="fw-bold mb-1">
              Mandatory Conflict-of-Interest Declaration
            </h6>
            <p class="mb-0 small text-dark">
              Before reviewing evidence or adjudicating a case, you must examine the participants' names. If you have any personal, financial, or direct professional relationship with either party, you must declare a conflict and recuse yourself.
            </p>
          </div>
        </div>

        <div v-if="pendingAssignments.length === 0" class="card border-0 rounded-4 shadow-sm text-center py-5">
          <div class="card-body">
            <i class="bi bi-check-circle-fill text-success fs-1 mb-3" />
            <h5 class="fw-bold">
              No Pending Appointments
            </h5>
            <p class="text-muted mb-0">
              You have no pending adjudicator appointment invitations requiring conflict declaration at this time.
            </p>
          </div>
        </div>

        <div v-else class="row g-4">
          <div
            v-for="assignment in pendingAssignments"
            :key="assignment.id"
            class="col-12 col-md-6"
          >
            <div class="card border-0 rounded-4 shadow-sm h-100 p-3">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-bold">
                    {{ assignment.case?.case_number }}
                  </span>
                  <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                    Conflict Declaration Required
                  </span>
                </div>

                <h5 class="card-title fw-bold text-dark mb-2">
                  {{ assignment.case?.title }}
                </h5>

                <div class="mb-3 text-muted small">
                  <i class="bi bi-tag me-1" />
                  Category: <span class="fw-semibold text-dark">{{ assignment.case?.category }}</span>
                </div>

                <div class="bg-light p-3 rounded-3 mb-4">
                  <div class="row g-2 small">
                    <div class="col-6">
                      <div class="text-muted">
                        Complainant
                      </div>
                      <div class="fw-bold text-dark">
                        {{ assignment.case?.complainant_name }}
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted">
                        Respondent
                      </div>
                      <div class="fw-bold text-dark">
                        {{ assignment.case?.respondent_name }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-auto d-flex gap-2">
                  <button
                    type="button"
                    class="btn btn-success rounded-pill flex-grow-1"
                    :disabled="processingId === assignment.id"
                    @click="handleAcceptNoConflict(assignment)"
                  >
                    <i class="bi bi-check-lg me-1" />
                    No Conflict (Accept Appointment)
                  </button>
                  <button
                    type="button"
                    class="btn btn-outline-danger rounded-pill flex-grow-1"
                    :disabled="processingId === assignment.id"
                    @click="handleDeclareConflict(assignment)"
                  >
                    <i class="bi bi-shield-x me-1" />
                    Declare Conflict (Recuse)
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: ACTIVE ADJUDICATIONS -->
      <div v-else-if="activeTab === 'active'">
        <div v-if="activeAssignments.length === 0" class="card border-0 rounded-4 shadow-sm text-center py-5">
          <div class="card-body">
            <i class="bi bi-folder2-open text-muted fs-1 mb-3" />
            <h5 class="fw-bold">
              No Active Case Deliberations
            </h5>
            <p class="text-muted mb-0">
              You do not currently have any active cases assigned to you as an appointed Tribunal adjudicator.
            </p>
          </div>
        </div>

        <div v-else class="row g-4">
          <div
            v-for="assignment in activeAssignments"
            :key="assignment.id"
            class="col-12 col-md-6 col-lg-4"
          >
            <div class="card border-0 rounded-4 shadow-sm h-100 p-3 hover-shadow transition">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="badge bg-dark-subtle text-dark border px-3 py-2 rounded-pill fw-bold">
                    {{ assignment.case?.case_number }}
                  </span>
                  <span class="badge bg-success text-white px-3 py-2 rounded-pill">
                    Verified Tribunal Adjudicator
                  </span>
                </div>

                <h5 class="card-title fw-bold text-dark mb-2">
                  {{ assignment.case?.title }}
                </h5>

                <div class="mb-3 text-muted small">
                  <i class="bi bi-tag me-1" />
                  Category: <span class="fw-semibold text-dark">{{ assignment.case?.category }}</span>
                </div>

                <div class="bg-light p-3 rounded-3 mb-4 small">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Complainant:</span>
                    <span class="fw-bold text-dark">{{ assignment.case?.complainant_name }}</span>
                  </div>
                  <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Respondent:</span>
                    <span class="fw-bold text-dark">{{ assignment.case?.respondent_name }}</span>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span class="text-muted">Accepted On:</span>
                    <span class="text-dark">{{ formatDate(assignment.responded_at) }}</span>
                  </div>
                </div>

                <div class="mt-auto">
                  <button
                    type="button"
                    class="btn btn-primary rounded-pill w-100"
                    @click="openCase(assignment.tribunal_case_id)"
                  >
                    <i class="bi bi-eye me-1" />
                    View Case & Evidence
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.hover-shadow:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
}

.transition {
  transition: all 0.2s ease-in-out;
}
</style>
