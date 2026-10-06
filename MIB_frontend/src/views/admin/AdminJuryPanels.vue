<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { tribunalService } from '@/services/tribunalService';
import { useTribunalStore } from '@/stores/tribunal';
import type {
  CreateTribunalJuryPanelPayload,
  TribunalJuryPanel,
  TribunalJuryPanelStatus,
} from '@/types/tribunal';

const router = useRouter();
const tribunalStore = useTribunalStore();

const panels = ref<TribunalJuryPanel[]>([]);
const selectedPanel = ref<TribunalJuryPanel | null>(null);
const loading = ref(true);
const actionLoading = ref(false);

const filterStatus = ref('');
const searchQuery = ref('');

const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});

// Modals
const isCreateModalOpen = ref(false);
const isDetailModalOpen = ref(false);

// Create Form
const createForm = ref<CreateTribunalJuryPanelPayload>({
  panel_name: '',
  email: '',
  password: '',
  password_confirmation: '',
});
const createErrors = ref<Record<string, string[]>>({});
const showPassword = ref(false);

// Stats
const totalCount = computed(() => pagination.value.total);
const activeCount = computed(() => panels.value.filter((p) => p.status === 'active').length);
const inactiveCount = computed(() => panels.value.filter((p) => p.status !== 'active').length);

const loadPanels = async (page = 1) => {
  loading.value = true;
  try {
    const params: Record<string, any> = { page };
    if (filterStatus.value) params.status = filterStatus.value;
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();

    const response = await tribunalService.adminGetJuryPanels(params);
    panels.value = response.data;
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
        text: 'Administrator privileges are required to manage Jury Panels.',
      });
      router.push('/tribunal/cases');
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: err?.response?.data?.message || 'Failed to load Jury Panels.',
      });
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
      text: 'Only Super Administrators have access to Jury Panel Management.',
    });
    router.push('/tribunal/cases');
    return;
  }
  await loadPanels();
});

const openCreateModal = () => {
  createForm.value = {
    panel_name: '',
    email: '',
    password: '',
    password_confirmation: '',
  };
  createErrors.value = {};
  showPassword.value = false;
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
};

const handleCreatePanel = async () => {
  createErrors.value = {};

  if (!createForm.value.panel_name.trim()) {
    createErrors.value.panel_name = ['Panel name is required.'];
    return;
  }
  if (!createForm.value.email.trim()) {
    createErrors.value.email = ['Login email is required.'];
    return;
  }
  if (!createForm.value.password) {
    createErrors.value.password = ['Password is required (min 8 characters).'];
    return;
  }
  if (createForm.value.password !== createForm.value.password_confirmation) {
    createErrors.value.password_confirmation = ['Password confirmation does not match.'];
    return;
  }

  actionLoading.value = true;
  try {
    const res = await tribunalService.adminCreateJuryPanel({
      panel_name: createForm.value.panel_name.trim(),
      email: createForm.value.email.trim(),
      password: createForm.value.password,
      password_confirmation: createForm.value.password_confirmation,
    });

    closeCreateModal();
    await Swal.fire({
      icon: 'success',
      title: 'Jury Panel Created',
      text: `Jury Panel "${res.data.panel_name}" with code ${res.data.panel_code} has been created successfully.`,
    });
    await loadPanels(1);
  } catch (err: any) {
    if (err?.response?.status === 422 && err?.response?.data?.errors) {
      createErrors.value = err.response.data.errors;
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Creation Failed',
        text: err?.response?.data?.message || 'Failed to create jury panel.',
      });
    }
  } finally {
    actionLoading.value = false;
  }
};

const openDetailModal = async (panel: TribunalJuryPanel) => {
  try {
    actionLoading.value = true;
    const res = await tribunalService.adminGetJuryPanel(panel.id);
    selectedPanel.value = res.data;
    isDetailModalOpen.value = true;
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: err?.response?.data?.message || 'Could not load panel details.',
    });
  } finally {
    actionLoading.value = false;
  }
};

const closeDetailModal = () => {
  isDetailModalOpen.value = false;
  selectedPanel.value = null;
};

const handleDeactivate = async (panel: TribunalJuryPanel) => {
  const result = await Swal.fire({
    title: 'Deactivate Jury Panel?',
    text: `Are you sure you want to deactivate ${panel.panel_name} (${panel.panel_code})?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Deactivate',
  });

  if (!result.isConfirmed) return;

  actionLoading.value = true;
  try {
    await tribunalService.adminDeactivateJuryPanel(panel.id);
    await Swal.fire({
      icon: 'success',
      title: 'Panel Deactivated',
      text: `${panel.panel_name} is now inactive.`,
      timer: 2000,
      showConfirmButton: false,
    });
    if (selectedPanel.value && selectedPanel.value.id === panel.id) {
      selectedPanel.value.status = 'inactive';
    }
    await loadPanels(pagination.value.current_page);
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err?.response?.data?.message || 'Failed to deactivate panel.',
    });
  } finally {
    actionLoading.value = false;
  }
};

const handleActivate = async (panel: TribunalJuryPanel) => {
  const result = await Swal.fire({
    title: 'Activate Jury Panel?',
    text: `Are you sure you want to activate ${panel.panel_name} (${panel.panel_code})?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#198754',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Activate',
  });

  if (!result.isConfirmed) return;

  actionLoading.value = true;
  try {
    await tribunalService.adminActivateJuryPanel(panel.id);
    await Swal.fire({
      icon: 'success',
      title: 'Panel Activated',
      text: `${panel.panel_name} is now active.`,
      timer: 2000,
      showConfirmButton: false,
    });
    if (selectedPanel.value && selectedPanel.value.id === panel.id) {
      selectedPanel.value.status = 'active';
    }
    await loadPanels(pagination.value.current_page);
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err?.response?.data?.message || 'Failed to activate panel.',
    });
  } finally {
    actionLoading.value = false;
  }
};

const getStatusBadgeClass = (status: TribunalJuryPanelStatus | string): string => {
  switch (status) {
    case 'active':
      return 'bg-success text-white';
    case 'inactive':
      return 'bg-secondary text-white';
    case 'suspended':
      return 'bg-danger text-white';
    default:
      return 'bg-dark text-white';
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
          <i class="bi bi-people-fill me-2" />Jury Panel Management
        </h2>
        <p class="text-muted mb-0">
          Create, view, and administer dedicated Jury Panel accounts for Tribunal arbitration.
        </p>
      </div>
      <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3"
          @click="router.push('/tribunal/cases')"
        >
          <i class="bi bi-briefcase me-1" />
          Tribunal Cases
        </button>
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3"
          @click="router.push('/admin/professional-verifications')"
        >
          <i class="bi bi-shield-check me-1" />
          Verifications
        </button>
        <button
          type="button"
          class="btn btn-primary rounded-pill px-4 shadow-sm"
          @click="openCreateModal"
        >
          <i class="bi bi-plus-circle me-1" />
          Create Jury Panel
        </button>
      </div>
    </div>

    <!-- Information Note for Super Admin -->
    <div class="alert alert-light border shadow-xs rounded-4 p-3 mb-4 d-flex align-items-center gap-2">
      <i class="bi bi-info-circle text-primary fs-5" />
      <span class="text-muted small">
        <strong>Note:</strong> Jury Panel accounts use the standard Myintellibook login page and are automatically redirected to the dedicated Jury Panel Portal.
      </span>
    </div>

    <!-- Summary Stats Cards -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary me-3">
              <i class="bi bi-people fs-3" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Total Panels</div>
              <div class="fs-4 fw-bold text-dark">{{ totalCount }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-success bg-opacity-10 text-success me-3">
              <i class="bi bi-check-circle fs-3" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Active on Current Page</div>
              <div class="fs-4 fw-bold text-success">{{ activeCount }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-secondary bg-opacity-10 text-secondary me-3">
              <i class="bi bi-dash-circle fs-3" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Inactive on Current Page</div>
              <div class="fs-4 fw-bold text-secondary">{{ inactiveCount }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters & Search -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-3">
        <div class="row g-2 align-items-center">
          <div class="col-12 col-md-5">
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-search text-muted" />
              </span>
              <input
                v-model="searchQuery"
                type="text"
                class="form-control border-start-0 bg-light"
                placeholder="Search by code, panel name, or email..."
                @keyup.enter="loadPanels(1)"
              />
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-4">
            <select
              v-model="filterStatus"
              class="form-select bg-light"
              @change="loadPanels(1)"
            >
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
            <button
              type="button"
              class="btn btn-outline-primary w-100 rounded-3"
              @click="loadPanels(1)"
            >
              Filter
            </button>
            <button
              type="button"
              class="btn btn-light border w-100 rounded-3"
              @click="searchQuery = ''; filterStatus = ''; loadPanels(1)"
            >
              Reset
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Jury Panels Table -->
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-muted mt-2 mb-0">Loading Jury Panels...</p>
        </div>

        <div v-else-if="panels.length === 0" class="text-center py-5">
          <i class="bi bi-people text-muted display-4 d-block mb-3" />
          <h5 class="fw-semibold">No Jury Panels Found</h5>
          <p class="text-muted mb-3">
            {{ searchQuery || filterStatus ? 'Try adjusting your search or filters.' : 'Click "Create Jury Panel" to add the first panel.' }}
          </p>
          <button
            v-if="!searchQuery && !filterStatus"
            type="button"
            class="btn btn-primary rounded-pill px-4"
            @click="openCreateModal"
          >
            Create Jury Panel
          </button>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small text-muted">
              <tr>
                <th class="ps-4">Panel Code</th>
                <th>Panel Name</th>
                <th>Login Email</th>
                <th>Status</th>
                <th>Created Date</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="panel in panels" :key="panel.id">
                <td class="ps-4">
                  <span class="badge bg-light text-dark border px-2 py-1 font-monospace">
                    {{ panel.panel_code }}
                  </span>
                </td>
                <td>
                  <div class="fw-bold text-dark">{{ panel.panel_name }}</div>
                </td>
                <td>
                  <span class="text-muted font-monospace small">
                    {{ panel.login_email || '-' }}
                  </span>
                </td>
                <td>
                  <span class="badge rounded-pill px-3 py-1" :class="getStatusBadgeClass(panel.status)">
                    {{ panel.status }}
                  </span>
                </td>
                <td>
                  <span class="text-muted small">{{ formatDate(panel.created_at) }}</span>
                </td>
                <td class="text-end pe-4">
                  <div class="btn-group btn-group-sm">
                    <button
                      type="button"
                      class="btn btn-outline-primary"
                      title="View Details"
                      @click="openDetailModal(panel)"
                    >
                      <i class="bi bi-eye me-1" />View
                    </button>
                    <button
                      v-if="panel.status === 'active'"
                      type="button"
                      class="btn btn-outline-danger"
                      title="Deactivate Panel"
                      :disabled="actionLoading"
                      @click="handleDeactivate(panel)"
                    >
                      <i class="bi bi-slash-circle me-1" />Deactivate
                    </button>
                    <button
                      v-else
                      type="button"
                      class="btn btn-outline-success"
                      title="Activate Panel"
                      :disabled="actionLoading"
                      @click="handleActivate(panel)"
                    >
                      <i class="bi bi-check2-circle me-1" />Activate
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="pagination.last_page > 1"
          class="d-flex justify-content-between align-items-center p-3 border-top"
        >
          <div class="text-muted small">
            Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total)
          </div>
          <div class="btn-group btn-group-sm">
            <button
              type="button"
              class="btn btn-outline-secondary"
              :disabled="pagination.current_page <= 1"
              @click="loadPanels(pagination.current_page - 1)"
            >
              Previous
            </button>
            <button
              type="button"
              class="btn btn-outline-secondary"
              :disabled="pagination.current_page >= pagination.last_page"
              @click="loadPanels(pagination.current_page + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Jury Panel Modal -->
    <div
      v-if="isCreateModalOpen"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0,0,0,0.5);"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header border-bottom pb-3">
            <h5 class="modal-title fw-bold text-primary">
              <i class="bi bi-plus-circle me-2" />Create Jury Panel
            </h5>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="closeCreateModal"
            />
          </div>
          <div class="modal-body p-4">
            <div class="alert alert-info border-0 rounded-3 small mb-4">
              <i class="bi bi-info-circle me-1" />
              A dedicated User account will be created for this panel with
              <strong>is_admin = false</strong>. The system will automatically generate a sequential Panel Code (e.g. JP-0001).
            </div>

            <form @submit.prevent="handleCreatePanel">
              <div class="mb-3">
                <label class="form-label fw-semibold">Panel Name <span class="text-danger">*</span></label>
                <input
                  v-model="createForm.panel_name"
                  type="text"
                  class="form-control"
                  placeholder="e.g. Jury Panel 01"
                  :class="{ 'is-invalid': createErrors.panel_name }"
                  required
                />
                <div v-if="createErrors.panel_name" class="invalid-feedback">
                  {{ createErrors.panel_name[0] }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Login Email <span class="text-danger">*</span></label>
                <input
                  v-model="createForm.email"
                  type="email"
                  class="form-control"
                  placeholder="e.g. jury01@myintellbook.test"
                  :class="{ 'is-invalid': createErrors.email }"
                  required
                />
                <div v-if="createErrors.email" class="invalid-feedback">
                  {{ createErrors.email[0] }}
                </div>
                <div class="form-text small">
                  This email will be used exclusively for the dedicated Jury Panel account login.
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input
                    v-model="createForm.password"
                    :type="showPassword ? 'text' : 'password'"
                    class="form-control"
                    placeholder="Enter secure password"
                    :class="{ 'is-invalid': createErrors.password }"
                    required
                  />
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="showPassword = !showPassword"
                  >
                    <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'" />
                  </button>
                </div>
                <div v-if="createErrors.password" class="text-danger small mt-1">
                  {{ createErrors.password[0] }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                <input
                  v-model="createForm.password_confirmation"
                  :type="showPassword ? 'text' : 'password'"
                  class="form-control"
                  placeholder="Re-type password"
                  :class="{ 'is-invalid': createErrors.password_confirmation }"
                  required
                />
                <div v-if="createErrors.password_confirmation" class="invalid-feedback">
                  {{ createErrors.password_confirmation[0] }}
                </div>
              </div>

              <div class="alert alert-warning border-0 rounded-3 small mt-3 mb-0">
                <i class="bi bi-shield-lock me-1" />
                Passwords are not visible after creation for security purposes.
              </div>
            </form>
          </div>
          <div class="modal-footer border-top pt-3">
            <button
              type="button"
              class="btn btn-light rounded-pill px-4"
              :disabled="actionLoading"
              @click="closeCreateModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              :disabled="actionLoading"
              @click="handleCreatePanel"
            >
              <span v-if="actionLoading" class="spinner-border spinner-border-sm me-1" />
              Create Panel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Details Modal -->
    <div
      v-if="isDetailModalOpen && selectedPanel"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0,0,0,0.5);"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header border-bottom pb-3">
            <div>
              <h5 class="modal-title fw-bold text-primary mb-0">
                <i class="bi bi-info-circle me-2" />{{ selectedPanel.panel_name }}
              </h5>
              <span class="badge bg-light text-dark border font-monospace mt-1">
                {{ selectedPanel.panel_code }}
              </span>
            </div>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="closeDetailModal"
            />
          </div>
          <div class="modal-body p-4">
            <!-- Details Grid -->
            <div class="row g-3 mb-4">
              <div class="col-sm-6">
                <div class="p-3 bg-light rounded-3">
                  <div class="text-muted small">Panel Code</div>
                  <div class="fw-bold font-monospace fs-6">{{ selectedPanel.panel_code }}</div>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="p-3 bg-light rounded-3">
                  <div class="text-muted small">Status</div>
                  <span class="badge rounded-pill px-3 py-1 mt-1" :class="getStatusBadgeClass(selectedPanel.status)">
                    {{ selectedPanel.status }}
                  </span>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="p-3 bg-light rounded-3">
                  <div class="text-muted small">Dedicated Login Email</div>
                  <div class="fw-bold font-monospace small text-primary">
                    {{ selectedPanel.login_email || '-' }}
                  </div>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="p-3 bg-light rounded-3">
                  <div class="text-muted small">Created Date</div>
                  <div class="fw-semibold">{{ formatDate(selectedPanel.created_at) }}</div>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="p-3 bg-light rounded-3">
                  <div class="text-muted small">Assigned Cases (Total)</div>
                  <div class="fw-bold fs-6 text-dark">{{ selectedPanel.assigned_cases_count ?? 0 }}</div>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="p-3 bg-light rounded-3">
                  <div class="text-muted small">Active Cases (Workload)</div>
                  <div class="fw-bold fs-6 text-primary">{{ selectedPanel.active_cases_count ?? 0 }}</div>
                </div>
              </div>
            </div>

            <!-- Role Isolation Notice -->
            <div class="alert alert-secondary border-0 rounded-3 small mb-4">
              <i class="bi bi-shield-check text-primary me-2" />
              <strong>Role Separation:</strong> This account is strictly identified as a Jury Panel entity and cannot act as a legal representative or normal party.
            </div>

            <!-- Audit Trail Events -->
            <h6 class="fw-bold mb-3">
              <i class="bi bi-clock-history me-1" />Audit Trail
            </h6>
            <div v-if="selectedPanel.events && selectedPanel.events.length > 0" class="list-group list-group-flush border rounded-3">
              <div
                v-for="event in selectedPanel.events"
                :key="event.id"
                class="list-group-item d-flex justify-content-between align-items-center py-2"
              >
                <div>
                  <span class="badge bg-secondary me-2">{{ event.event_type }}</span>
                  <span v-if="event.actor_id" class="text-muted small">by Admin #{{ event.actor_id }}</span>
                </div>
                <div class="text-muted small">{{ formatDate(event.created_at) }}</div>
              </div>
            </div>
            <div v-else class="text-muted small py-2">
              No audit events recorded yet.
            </div>
          </div>
          <div class="modal-footer border-top pt-3 d-flex justify-content-between">
            <div>
              <button
                v-if="selectedPanel.status === 'active'"
                type="button"
                class="btn btn-outline-danger rounded-pill px-3"
                :disabled="actionLoading"
                @click="handleDeactivate(selectedPanel)"
              >
                <i class="bi bi-slash-circle me-1" />Deactivate Panel
              </button>
              <button
                v-else
                type="button"
                class="btn btn-outline-success rounded-pill px-3"
                :disabled="actionLoading"
                @click="handleActivate(selectedPanel)"
              >
                <i class="bi bi-check2-circle me-1" />Activate Panel
              </button>
            </div>
            <button
              type="button"
              class="btn btn-light rounded-pill px-4"
              @click="closeDetailModal"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-monospace {
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
</style>
