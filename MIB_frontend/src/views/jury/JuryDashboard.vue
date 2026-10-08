<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useJuryPanelStore } from '@/stores/juryPanel';

const router = useRouter();
const juryStore = useJuryPanelStore();

onMounted(async () => {
  try {
    await Promise.all([
      juryStore.fetchMe(),
      juryStore.fetchAssignedCases(),
    ]);
  } catch {
    // handled by store / error boundary
  }
});

const formatStatus = (status?: string): string => {
  if (!status) return '-';
  if (status === 'jury_selection') return 'Awaiting Jury Panel Assignment';
  return status
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatDate = (dateStr?: string | null): string => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};
</script>

<template>
  <div class="jury-dashboard">
    <!-- Welcome Header -->
    <div class="mb-4">
      <h3 class="fw-bold text-dark mb-1">
        Jury Panel Dashboard
      </h3>
      <p class="text-muted mb-0">
        Workload overview and assigned tribunal proceedings for {{ juryStore.panelName || 'this panel' }}.
      </p>
    </div>

    <!-- Panel Status Cards -->
    <div class="row g-3 mb-4">
      <!-- Panel Code -->
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary me-3">
              <i class="bi bi-tag fs-3" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Panel Code</div>
              <div class="fs-5 fw-bold font-monospace text-dark">{{ juryStore.panelCode || '-' }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel Name -->
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="p-3 rounded-3 bg-info bg-opacity-10 text-info me-3">
              <i class="bi bi-person-badge fs-3" />
            </div>
            <div class="text-truncate">
              <div class="text-muted small text-uppercase fw-semibold">Panel Name</div>
              <div class="fs-6 fw-bold text-dark text-truncate" :title="juryStore.panelName">
                {{ juryStore.panelName || '-' }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel Status -->
      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="p-3 rounded-3 bg-success bg-opacity-10 text-success me-3">
              <i class="bi bi-shield-check fs-3" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Status</div>
              <span
                class="badge rounded-pill px-3 py-1 text-capitalize mt-1"
                :class="{
                  'bg-success text-white': juryStore.panelStatus === 'active',
                  'bg-secondary text-white': juryStore.panelStatus === 'inactive',
                  'bg-danger text-white': juryStore.panelStatus === 'suspended'
                }"
              >
                {{ juryStore.panelStatus || 'Active' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Assigned Cases -->
      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="p-3 rounded-3 bg-warning bg-opacity-10 text-warning me-3">
              <i class="bi bi-journal-text fs-3" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Assigned Cases</div>
              <div class="fs-4 fw-bold text-dark">{{ juryStore.assignedCasesCount }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Active Cases -->
      <div class="col-12 col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="p-3 rounded-3 bg-purple bg-opacity-10 text-primary me-3">
              <i class="bi bi-folder-check fs-3" />
            </div>
            <div>
              <div class="text-muted small text-uppercase fw-semibold">Active Cases</div>
              <div class="fs-4 fw-bold text-dark">{{ juryStore.activeCasesCount }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assigned Cases Section -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold mb-0 text-dark">
          <i class="bi bi-journal-text me-2 text-primary" />Assigned Cases
        </h5>
        <button
          v-if="juryStore.assignedCases.length > 0"
          type="button"
          class="btn btn-outline-primary btn-sm rounded-pill px-3"
          @click="router.push('/jury/cases')"
        >
          View All Cases
        </button>
      </div>

      <div class="card-body p-0">
        <!-- Empty State -->
        <div v-if="juryStore.assignedCases.length === 0" class="text-center py-5">
          <i class="bi bi-inbox text-muted display-4 d-block mb-3" />
          <h5 class="fw-semibold text-dark">No Tribunal cases are currently assigned to this Jury Panel.</h5>
          <p class="text-muted small mb-0">
            Cases assigned automatically by the system will appear here.
          </p>
        </div>

        <!-- Cases Table Preview -->
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Case Number</th>
                <th>Title</th>
                <th>Category</th>
                <th>Status</th>
                <th>Assigned Date</th>
                <th class="text-end pe-4">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in juryStore.assignedCases.slice(0, 5)" :key="c.id">
                <td class="ps-4">
                  <span class="fw-bold font-monospace text-primary">{{ c.case_number }}</span>
                </td>
                <td>
                  <div class="fw-semibold text-dark">{{ c.title }}</div>
                  <div class="small text-muted">
                    {{ c.complainant_summary.name }} vs {{ c.respondent_summary.name }}
                  </div>
                </td>
                <td>
                  <span class="badge bg-light text-dark border">{{ c.category }}</span>
                </td>
                <td>
                  <span class="badge bg-primary text-white rounded-pill px-3 py-1">
                    {{ formatStatus(c.status) }}
                  </span>
                </td>
                <td class="text-muted small">
                  {{ formatDate(c.assigned_at) }}
                </td>
                <td class="text-end pe-4">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary rounded-pill px-3"
                    @click="router.push(`/jury/cases/${c.id}`)"
                  >
                    Open Case
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-monospace {
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
}
</style>
