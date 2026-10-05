<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { tribunalService } from '@/services/tribunalService';
import { useTribunalStore } from '@/stores/tribunal';
import type { ProfessionalVerification } from '@/types/tribunal';

const router = useRouter();
const tribunalStore = useTribunalStore();

const verifications = ref<ProfessionalVerification[]>([]);
const selectedVerification = ref<ProfessionalVerification | null>(null);
const loading = ref(true);
const actionLoading = ref(false);

const filterStatus = ref('');
const filterProfession = ref('');
const searchQuery = ref('');

const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});

const isReviewModalOpen = ref(false);

const loadVerifications = async (page = 1) => {
  loading.value = true;
  try {
    const params: Record<string, any> = { page };
    if (filterStatus.value) params.status = filterStatus.value;
    if (filterProfession.value) params.profession_type = filterProfession.value;
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();

    const response = await tribunalService.adminGetVerifications(params);
    verifications.value = response.data;
    if (response.meta) {
      pagination.value = {
        current_page: response.meta.current_page,
        last_page: response.meta.last_page,
        total: response.meta.total,
      };
    }
  } catch (err: any) {
    if (err?.response?.status === 403) {
      Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'Administrator privileges are required to view professional verifications.',
      });
      router.push('/tribunal/cases');
    }
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await tribunalStore.fetchCapabilities();
  if (tribunalStore.capabilities && !tribunalStore.capabilities.is_admin_reviewer) {
    Swal.fire({
      icon: 'error',
      title: 'Unauthorized',
      text: 'Only Tribunal Review Administrators have access to this page.',
    });
    router.push('/tribunal/cases');
    return;
  }
  await loadVerifications();
});

const openReview = async (item: ProfessionalVerification) => {
  try {
    actionLoading.value = true;
    const res = await tribunalService.adminGetVerification(item.id);
    selectedVerification.value = res.data;
    isReviewModalOpen.value = true;
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: err?.response?.data?.message || 'Could not load application details.',
    });
  } finally {
    actionLoading.value = false;
  }
};

const closeReview = () => {
  isReviewModalOpen.value = false;
  selectedVerification.value = null;
};

const handleApprove = async () => {
  if (!selectedVerification.value) return;

  const result = await Swal.fire({
    title: 'Approve Professional Verification?',
    text: `Confirm approving ${selectedVerification.value.user?.name || selectedVerification.value.user?.email} as a Verified ${selectedVerification.value.profession_label}. An adjudicator candidate profile will be automatically provisioned.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Approve',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#198754',
  });

  if (!result.isConfirmed) return;

  actionLoading.value = true;
  try {
    await tribunalService.adminApproveVerification(selectedVerification.value.id);
    await Swal.fire({
      icon: 'success',
      title: 'Approved',
      text: 'Professional verification approved successfully.',
    });
    closeReview();
    await loadVerifications(pagination.value.current_page);
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Approval Failed',
      text: err?.response?.data?.message || 'Failed to approve application.',
    });
  } finally {
    actionLoading.value = false;
  }
};

const handleReject = async () => {
  if (!selectedVerification.value) return;

  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Reject Application',
    text: 'Please provide the specific reason for rejecting this verification. This reason will be securely communicated to the applicant.',
    input: 'textarea',
    inputPlaceholder: 'e.g. Credential documents could not be verified with the issuing authority...',
    inputValidator: (val) => {
      if (!val || val.trim().length < 10) {
        return 'Please enter a detailed rejection reason (at least 10 characters).';
      }
      return null;
    },
    showCancelButton: true,
    confirmButtonText: 'Confirm Rejection',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#dc3545',
  });

  if (!isConfirmed || !reason) return;

  actionLoading.value = true;
  try {
    await tribunalService.adminRejectVerification(selectedVerification.value.id, reason.trim());
    await Swal.fire({
      icon: 'info',
      title: 'Application Rejected',
      text: 'The applicant has been notified of the rejection.',
    });
    closeReview();
    await loadVerifications(pagination.value.current_page);
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Rejection Failed',
      text: err?.response?.data?.message || 'Failed to reject application.',
    });
  } finally {
    actionLoading.value = false;
  }
};

const handleSuspend = async () => {
  if (!selectedVerification.value) return;

  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Suspend Verified Status',
    text: 'Please provide the reason for suspending this professional status.',
    input: 'textarea',
    inputPlaceholder: 'e.g. Bar association disciplinary proceedings pending...',
    inputValidator: (val) => {
      if (!val || val.trim().length < 10) {
        return 'Please enter a detailed suspension reason (at least 10 characters).';
      }
      return null;
    },
    showCancelButton: true,
    confirmButtonText: 'Confirm Suspension',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#dc3545',
  });

  if (!isConfirmed || !reason) return;

  actionLoading.value = true;
  try {
    await tribunalService.adminSuspendVerification(selectedVerification.value.id, reason.trim());
    await Swal.fire({
      icon: 'warning',
      title: 'Status Suspended',
      text: 'The professional status has been suspended and adjudicator eligibility revoked.',
    });
    closeReview();
    await loadVerifications(pagination.value.current_page);
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Suspension Failed',
      text: err?.response?.data?.message || 'Failed to suspend application.',
    });
  } finally {
    actionLoading.value = false;
  }
};

const downloadDoc = async (type: string) => {
  if (!selectedVerification.value) return;
  try {
    await tribunalService.adminDownloadDocument(
      selectedVerification.value.id,
      type,
      `${selectedVerification.value.user_id}_${type}_document.pdf`
    );
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Download Failed',
      text: 'Could not download the requested document.',
    });
  }
};

const getStatusBadgeClass = (status: string): string => {
  switch (status) {
    case 'verified':
      return 'bg-success text-white';
    case 'pending':
    case 'under_review':
      return 'bg-warning text-dark';
    case 'rejected':
      return 'bg-danger text-white';
    case 'suspended':
      return 'bg-dark text-white';
    default:
      return 'bg-secondary text-white';
  }
};

const formatDate = (dateStr?: string | null): string => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};
</script>

<template>
  <div class="container-fluid py-4 px-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
      <div>
        <h2 class="fw-bold mb-1 text-primary">
          <i class="bi bi-shield-check me-2" />Tribunal Professional Verifications
        </h2>
        <p class="text-muted mb-0">
          Review, verify, and manage legal professional credentials for Tribunal Adjudicator and Representative eligibility.
        </p>
      </div>
      <div>
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3 me-2"
          @click="router.push('/tribunal/cases')"
        >
          <i class="bi bi-briefcase me-1" />
          Tribunal Cases
        </button>
        <button
          type="button"
          class="btn btn-primary rounded-pill px-3"
          :disabled="loading"
          @click="loadVerifications(pagination.current_page)"
        >
          <i class="bi bi-arrow-clockwise me-1" :class="{ 'spin': loading }" />
          Refresh
        </button>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="card border-0 rounded-4 shadow-sm p-3 mb-4 bg-white">
      <div class="row g-3 align-items-center">
        <div class="col-12 col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-light border-0"><i class="bi bi-search" /></span>
            <input
              v-model="searchQuery"
              type="text"
              class="form-control bg-light border-0"
              placeholder="Search applicant, authority, license..."
              @keyup.enter="loadVerifications(1)"
            >
          </div>
        </div>
        <div class="col-6 col-md-3">
          <select v-model="filterStatus" class="form-select bg-light border-0" @change="loadVerifications(1)">
            <option value="">
              All Statuses
            </option>
            <option value="pending">
              Pending
            </option>
            <option value="under_review">
              Under Review
            </option>
            <option value="verified">
              Verified
            </option>
            <option value="rejected">
              Rejected
            </option>
            <option value="suspended">
              Suspended
            </option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select v-model="filterProfession" class="form-select bg-light border-0" @change="loadVerifications(1)">
            <option value="">
              All Professions
            </option>
            <option value="attorney_at_law">
              Attorney-at-Law
            </option>
            <option value="judge">
              Judge / Judicial Officer
            </option>
            <option value="legal_officer">
              Legal Officer
            </option>
            <option value="mediator">
              Mediator
            </option>
            <option value="other_legal_professional">
              Other
            </option>
          </select>
        </div>
        <div class="col-12 col-md-2 text-end">
          <button type="button" class="btn btn-outline-primary rounded-pill w-100" @click="loadVerifications(1)">
            Filter
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && verifications.length === 0" class="text-center py-5 bg-white rounded-4 shadow-sm">
      <div class="spinner-border text-primary" role="status" />
      <p class="mt-3 text-muted">
        Loading verification requests...
      </p>
    </div>

    <!-- Empty State -->
    <div v-else-if="verifications.length === 0" class="card border-0 rounded-4 shadow-sm text-center py-5 bg-white">
      <div class="card-body">
        <i class="bi bi-inbox text-muted fs-1 mb-3" />
        <h5 class="fw-bold">
          No Verification Requests
        </h5>
        <p class="text-muted mb-0">
          There are no professional verification applications matching your criteria.
        </p>
      </div>
    </div>

    <!-- Table of Verifications -->
    <div v-else class="card border-0 rounded-4 shadow-sm overflow-hidden bg-white">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light text-muted small text-uppercase">
            <tr>
              <th scope="col" class="ps-4">
                Applicant
              </th>
              <th scope="col">
                Profession
              </th>
              <th scope="col">
                Issuing Authority
              </th>
              <th scope="col">
                Credentials
              </th>
              <th scope="col">
                Submitted Date
              </th>
              <th scope="col">
                Status
              </th>
              <th scope="col" class="text-end pe-4">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in verifications" :key="item.id">
              <td class="ps-4">
                <div class="fw-bold text-dark">
                  {{ item.user?.name || item.user?.email }}
                </div>
                <div class="text-muted small">
                  {{ item.user?.email }} (ID: {{ item.user_id }})
                </div>
              </td>
              <td>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">
                  {{ item.profession_label }}
                </span>
              </td>
              <td>
                <div class="fw-semibold text-dark">
                  {{ item.issuing_authority }}
                </div>
                <div class="text-muted small">
                  {{ item.years_of_experience }} years exp.
                </div>
              </td>
              <td class="font-monospace small">
                <div v-if="item.masked_registration_number">
                  Reg: {{ item.masked_registration_number }}
                </div>
                <div v-if="item.masked_enrollment_number">
                  Enr: {{ item.masked_enrollment_number }}
                </div>
                <div v-if="!item.masked_registration_number && !item.masked_enrollment_number" class="text-muted">
                  None provided
                </div>
              </td>
              <td class="small text-muted">
                {{ formatDate(item.submitted_at) }}
              </td>
              <td>
                <span class="badge rounded-pill px-3 py-2 text-uppercase" :class="getStatusBadgeClass(item.verification_status)">
                  {{ item.verification_status }}
                </span>
              </td>
              <td class="text-end pe-4">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary rounded-pill px-3"
                  @click="openReview(item)"
                >
                  <i class="bi bi-eye me-1" />Review
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="d-flex justify-content-between align-items-center p-3 border-top">
        <div class="text-muted small">
          Showing page {{ pagination.current_page }} of {{ pagination.last_page }} (Total: {{ pagination.total }})
        </div>
        <div class="btn-group">
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            :disabled="pagination.current_page <= 1"
            @click="loadVerifications(pagination.current_page - 1)"
          >
            Previous
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="loadVerifications(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Review Modal / Detail Dialog -->
    <div
      v-if="isReviewModalOpen && selectedVerification"
      class="modal show d-block"
      tabindex="-1"
      style="background: rgba(0, 0, 0, 0.5);"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-bottom px-4 py-3">
            <h5 class="modal-title fw-bold text-primary">
              <i class="bi bi-file-earmark-person me-2" />Review Professional Verification #{{ selectedVerification.id }}
            </h5>
            <button type="button" class="btn-close" @click="closeReview" />
          </div>

          <div class="modal-body px-4 py-3">
            <!-- Applicant Summary -->
            <div class="bg-light p-3 rounded-3 mb-4">
              <div class="row g-3">
                <div class="col-sm-6">
                  <div class="text-muted small">
                    Applicant
                  </div>
                  <div class="fw-bold text-dark">
                    {{ selectedVerification.user?.name || selectedVerification.user?.email }}
                  </div>
                  <div class="text-muted small">
                    {{ selectedVerification.user?.email }}
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small">
                    Profession
                  </div>
                  <div class="fw-bold text-dark">
                    {{ selectedVerification.profession_label }}
                  </div>
                  <span class="badge rounded-pill mt-1" :class="getStatusBadgeClass(selectedVerification.verification_status)">
                    {{ selectedVerification.verification_status }}
                  </span>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small">
                    Issuing Authority
                  </div>
                  <div class="fw-bold text-dark">
                    {{ selectedVerification.issuing_authority }}
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small">
                    Years of Practice
                  </div>
                  <div class="fw-bold text-dark">
                    {{ selectedVerification.years_of_experience }} Years
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small">
                    Registration Number
                  </div>
                  <div class="font-monospace fw-bold text-dark">
                    {{ selectedVerification.registration_number || selectedVerification.masked_registration_number || 'None' }}
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small">
                    Enrollment Number
                  </div>
                  <div class="font-monospace fw-bold text-dark">
                    {{ selectedVerification.enrollment_number || selectedVerification.masked_enrollment_number || 'None' }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Private Credential Documents -->
            <h6 class="fw-bold text-dark mb-3">
              <i class="bi bi-lock-fill me-1 text-primary" />Secure Credential Documents
            </h6>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <button
                v-if="selectedVerification.has_qualification_document"
                type="button"
                class="btn btn-outline-primary rounded-pill btn-sm px-3"
                @click="downloadDoc('qualification')"
              >
                <i class="bi bi-file-earmark-pdf me-1" />
                Download Qualification Document
              </button>
              <button
                v-if="selectedVerification.has_identity_document"
                type="button"
                class="btn btn-outline-primary rounded-pill btn-sm px-3"
                @click="downloadDoc('identity')"
              >
                <i class="bi bi-person-badge me-1" />
                Download Identity Document
              </button>
              <button
                v-if="selectedVerification.has_additional_document"
                type="button"
                class="btn btn-outline-secondary rounded-pill btn-sm px-3"
                @click="downloadDoc('additional')"
              >
                <i class="bi bi-paperclip me-1" />
                Download Additional Document
              </button>
            </div>

            <!-- Adjudicator Profile Status if present -->
            <div v-if="selectedVerification.adjudicator_profile" class="alert alert-secondary border-0 rounded-3 mb-4">
              <h6 class="fw-bold mb-2">
                Linked Adjudicator Profile:
              </h6>
              <div class="row g-2 small">
                <div class="col-4">
                  Status: <strong>{{ selectedVerification.adjudicator_profile.status }}</strong>
                </div>
                <div class="col-4">
                  Qualification: <strong>{{ selectedVerification.adjudicator_profile.qualification_status }}</strong>
                </div>
                <div class="col-4">
                  Available: <strong>{{ selectedVerification.adjudicator_profile.available ? 'Yes' : 'No' }}</strong>
                </div>
              </div>
            </div>

            <!-- Audit Trail -->
            <div v-if="selectedVerification.events && selectedVerification.events.length > 0" class="mb-3">
              <h6 class="fw-bold text-dark mb-2">
                <i class="bi bi-clock-history me-1" />Audit Trail
              </h6>
              <ul class="list-group list-group-flush small">
                <li
                  v-for="ev in selectedVerification.events"
                  :key="ev.id"
                  class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center"
                >
                  <div>
                    <span class="badge bg-light text-dark border me-2">{{ ev.event_type }}</span>
                    <span v-if="ev.metadata?.reason" class="text-muted">({{ ev.metadata.reason }})</span>
                  </div>
                  <span class="text-muted">{{ formatDate(ev.created_at) }}</span>
                </li>
              </ul>
            </div>
          </div>

          <div class="modal-footer border-top px-4 py-3 d-flex justify-content-between">
            <div>
              <button
                v-if="selectedVerification.verification_status === 'verified'"
                type="button"
                class="btn btn-outline-danger rounded-pill px-3"
                :disabled="actionLoading"
                @click="handleSuspend"
              >
                <i class="bi bi-slash-circle me-1" />Suspend Status
              </button>
            </div>

            <div class="d-flex gap-2">
              <button
                v-if="selectedVerification.verification_status !== 'rejected'"
                type="button"
                class="btn btn-outline-danger rounded-pill px-3"
                :disabled="actionLoading"
                @click="handleReject"
              >
                <i class="bi bi-x-circle me-1" />Reject Application
              </button>

              <button
                v-if="selectedVerification.verification_status !== 'verified'"
                type="button"
                class="btn btn-success rounded-pill px-4"
                :disabled="actionLoading"
                @click="handleApprove"
              >
                <i class="bi bi-check-circle me-1" />Approve Application
              </button>

              <button
                type="button"
                class="btn btn-secondary rounded-pill px-3"
                @click="closeReview"
              >
                Close
              </button>
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
</style>
