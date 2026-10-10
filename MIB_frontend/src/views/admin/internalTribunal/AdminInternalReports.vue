<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AdminInternalReportStatusBadge from '@/components/internalTribunal/AdminInternalReportStatusBadge.vue';
import { adminInternalReportService } from '@/services/adminInternalReportService';
import type { AdminInternalReportItem } from '@/types/internalReport';

const router = useRouter();

const reports = ref<AdminInternalReportItem[]>([]);
const meta = ref<any>(null);
const isLoading = ref(true);
const errorMessage = ref('');

// Filters
const selectedStatus = ref('');
const selectedCategory = ref('');
const searchQuery = ref('');

const categories = [
  'Identity & Profile Fraud',
  'Qualification / Professional Fraud',
  'Harassment & Inappropriate Behaviour',
  'Scam / Security / Privacy Violation',
  'Academic & Score Manipulation',
  'Tribunal / Legal Process Misconduct',
  'Content & Community Abuse',
  'Other Platform Misconduct',
];

const statuses = [
  'Submitted',
  'UnderReview',
  'NeedsMoreInformation',
  'Valid',
  'Invalid',
  'Closed',
];

const fetchReports = async (page = 1) => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const res = await adminInternalReportService.getReports({
      status: selectedStatus.value || undefined,
      category: selectedCategory.value || undefined,
      search: searchQuery.value.trim() || undefined,
      page,
    });
    reports.value = res.data;
    meta.value = res.meta;
  } catch {
    errorMessage.value = 'Failed to load misconduct reports. Please check your permissions or network connection.';
  } finally {
    isLoading.value = false;
  }
};

const applyFilters = () => {
  fetchReports(1);
};

const resetFilters = () => {
  selectedStatus.value = '';
  selectedCategory.value = '';
  searchQuery.value = '';
  fetchReports(1);
};

// Summary metrics computed from current batch / meta
const totalReportsCount = computed(() => meta.value?.total ?? reports.value.length);
const countByStatus = (status: string) => reports.value.filter((r) => r.status === status).length;

const formatDate = (dateStr?: string | null): string => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

onMounted(() => {
  fetchReports(1);
});
</script>

<template>
  <div class="container-fluid py-4 px-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
      <div>
        <h2 class="fw-bold mb-1 text-primary">
          <i class="bi bi-shield-lock-fill me-2" />Internal Tribunal Reports
        </h2>
        <p class="text-muted mb-0">
          Review, adjudicate, and audit confidential member misconduct reports and platform policy violations.
        </p>
      </div>
      <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3"
          @click="router.push('/admin/tribunal/jury-panels')"
        >
          <i class="bi bi-people-fill me-1" />
          Jury Panels
        </button>
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3"
          @click="router.push('/admin/professional-verifications')"
        >
          <i class="bi bi-shield-check me-1" />
          Verifications
        </button>
      </div>
    </div>

    <!-- Summary Stats Cards -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary me-3">
              <i class="bi bi-folder2-open fs-4" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Total Reports</div>
              <div class="fs-4 fw-bold text-dark">{{ totalReportsCount }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-info bg-opacity-10 text-info me-3">
              <i class="bi bi-inbox fs-4" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Submitted</div>
              <div class="fs-4 fw-bold text-info">{{ countByStatus('Submitted') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-warning bg-opacity-10 text-warning-emphasis me-3">
              <i class="bi bi-hourglass-split fs-4" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Under Review</div>
              <div class="fs-4 fw-bold text-warning-emphasis">{{ countByStatus('UnderReview') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-danger bg-opacity-10 text-danger me-3">
              <i class="bi bi-question-circle-fill fs-4" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Needs Info</div>
              <div class="fs-4 fw-bold text-danger">{{ countByStatus('NeedsMoreInformation') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-success bg-opacity-10 text-success me-3">
              <i class="bi bi-shield-check fs-4" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Valid</div>
              <div class="fs-4 fw-bold text-success">{{ countByStatus('Valid') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body d-flex align-items-center">
            <div class="p-3 rounded-3 bg-secondary bg-opacity-10 text-secondary me-3">
              <i class="bi bi-archive-fill fs-4" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Closed / Invalid</div>
              <div class="fs-4 fw-bold text-secondary">{{ countByStatus('Closed') + countByStatus('Invalid') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-3">
        <div class="row g-2 align-items-center">
          <div class="col-12 col-md-4">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search" />
              </span>
              <input
                v-model="searchQuery"
                type="text"
                class="form-control border-start-0"
                placeholder="Search report #, subject, or member..."
                @keyup.enter="applyFilters"
              />
            </div>
          </div>

          <div class="col-12 col-sm-6 col-md-3">
            <select v-model="selectedStatus" class="form-select" @change="applyFilters">
              <option value="">All Statuses</option>
              <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>

          <div class="col-12 col-sm-6 col-md-3">
            <select v-model="selectedCategory" class="form-select" @change="applyFilters">
              <option value="">All Categories</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <div class="col-12 col-md-2 d-flex gap-2">
            <button
              type="button"
              class="btn btn-primary rounded-pill px-3 flex-grow-1 shadow-sm"
              @click="applyFilters"
            >
              Filter
            </button>
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-3"
              title="Reset Filters"
              @click="resetFilters"
            >
              <i class="bi bi-arrow-counterclockwise" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="card border-0 shadow-sm rounded-4 text-center py-5">
      <div class="spinner-border text-primary mx-auto mb-2" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted small mb-0">Loading confidential internal reports...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="card border-0 shadow-sm rounded-4 text-center py-4 p-3 border-start border-danger border-4">
      <i class="bi bi-exclamation-triangle text-danger fs-3 d-block mb-2" />
      <p class="text-danger fw-semibold mb-2">{{ errorMessage }}</p>
      <div>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" @click="fetchReports(1)">
          <i class="bi bi-arrow-clockwise me-1" /> Retry
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="reports.length === 0" class="card border-0 shadow-sm rounded-4 text-center py-5 p-4">
      <div class="p-3 rounded-circle bg-light d-inline-block mx-auto mb-3 text-muted">
        <i class="bi bi-shield-check fs-2" />
      </div>
      <h5 class="fw-bold text-dark mb-1">No Misconduct Reports Found</h5>
      <p class="text-muted small mb-3">
        There are currently no internal misconduct reports matching your selected criteria.
      </p>
      <div>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" @click="resetFilters">
          Reset Filter Criteria
        </button>
      </div>
    </div>

    <!-- Table Card -->
    <div v-else class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light text-muted small text-uppercase">
            <tr>
              <th scope="col" class="ps-4">Report Reference</th>
              <th scope="col">Reporter</th>
              <th scope="col">Reported Member</th>
              <th scope="col">Category</th>
              <th scope="col">Subject</th>
              <th scope="col" class="text-center">Status</th>
              <th scope="col">Submitted</th>
              <th scope="col" class="text-end pe-4">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rep in reports" :key="rep.id">
              <td class="ps-4 font-mono fw-bold text-primary">
                {{ rep.report_number }}
              </td>
              <td>
                <div class="fw-semibold text-dark">{{ rep.reporter?.name }}</div>
                <div class="small text-muted d-flex align-items-center gap-1">
                  <span class="badge bg-light text-secondary border px-1.5 py-0.5 rounded text-2xs">Whistleblower</span>
                  <span>#{{ rep.reporter?.id }}</span>
                </div>
              </td>
              <td>
                <div class="fw-semibold text-dark d-flex align-items-center gap-1.5">
                  {{ rep.reported_user?.name }}
                  <span v-if="rep.reported_user?.is_jury_panel" class="badge bg-purple-subtle text-purple border border-purple-subtle px-1.5 py-0.5 rounded text-2xs">
                    Jury Panel
                  </span>
                </div>
                <div class="small text-muted">@{{ rep.reported_user?.username }}</div>
              </td>
              <td>
                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill">
                  {{ rep.category }}
                </span>
              </td>
              <td class="text-truncate" style="max-width: 220px;" :title="rep.subject">
                <span class="fw-medium text-dark">{{ rep.subject }}</span>
              </td>
              <td class="text-center">
                <AdminInternalReportStatusBadge :status="rep.status" size="sm" />
              </td>
              <td class="small text-muted">
                {{ formatDate(rep.created_at) }}
              </td>
              <td class="text-end pe-4">
                <router-link
                  :to="`/admin/internal-reports/${rep.id}`"
                  class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-2xs"
                >
                  Review Dossier <i class="bi bi-arrow-right ms-1" />
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="d-flex justify-content-between align-items-center p-3 border-top bg-light bg-opacity-25"
      >
        <span class="text-muted small">
          Showing page <strong>{{ meta.current_page }}</strong> of <strong>{{ meta.last_page }}</strong> ({{ meta.total }} total records)
        </span>
        <div class="d-flex gap-2">
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm rounded-pill px-3"
            :disabled="meta.current_page <= 1"
            @click="fetchReports(meta.current_page - 1)"
          >
            <i class="bi bi-chevron-left me-1" /> Previous
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm rounded-pill px-3"
            :disabled="meta.current_page >= meta.last_page"
            @click="fetchReports(meta.current_page + 1)"
          >
            Next <i class="bi bi-chevron-right ms-1" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-mono {
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
.text-2xs {
  font-size: 10px;
}
.shadow-2xs {
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.bg-purple-subtle {
  background-color: #f3e8ff !important;
}
.text-purple {
  color: #7e22ce !important;
}
.border-purple-subtle {
  border-color: #e9d5ff !important;
}
</style>
