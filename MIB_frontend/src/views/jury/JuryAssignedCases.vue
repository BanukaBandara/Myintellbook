<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useJuryPanelStore } from '@/stores/juryPanel';

const router = useRouter();
const juryStore = useJuryPanelStore();

onMounted(async () => {
  try {
    await juryStore.fetchAssignedCases();
  } catch {
    // handled by store
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

const getStatusBadgeClass = (status?: string): string => {
  switch (status) {
    case 'evidence_collection':
      return 'bg-primary text-white';
    case 'jury_selection':
      return 'bg-info text-dark';
    case 'hearing':
    case 'deliberation':
      return 'bg-warning text-dark';
    case 'decided':
    case 'closed':
    case 'settled':
      return 'bg-success text-white';
    default:
      return 'bg-secondary text-white';
  }
};
</script>

<template>
  <div class="jury-assigned-cases">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold text-dark mb-1">
          <i class="bi bi-journal-text me-2 text-primary" />Assigned Cases
        </h3>
        <p class="text-muted mb-0">
          Cases allocated to this Jury Panel for review, proceedings, and adjudication.
        </p>
      </div>
      <div>
        <button
          type="button"
          class="btn btn-outline-secondary btn-sm rounded-pill px-3"
          :disabled="juryStore.loading"
          @click="juryStore.fetchAssignedCases"
        >
          <i class="bi bi-arrow-clockwise me-1" :class="{ 'spin': juryStore.loading }" />
          Refresh
        </button>
      </div>
    </div>

    <!-- Cases Card -->
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">
        <!-- Loading State -->
        <div v-if="juryStore.loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading cases...</span>
          </div>
          <p class="text-muted small mt-2 mb-0">Retrieving assigned cases...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="juryStore.assignedCases.length === 0" class="text-center py-5 px-3">
          <div class="p-3 bg-light rounded-circle d-inline-block mb-3">
            <i class="bi bi-folder2-open text-muted display-4" />
          </div>
          <h5 class="fw-semibold text-dark">No Tribunal cases are currently assigned to this Jury Panel.</h5>
          <p class="text-muted small max-w-500 mx-auto mb-4">
            New tribunal cases awaiting adjudicative panel assignment will automatically be allocated here.
          </p>
          <button
            type="button"
            class="btn btn-primary rounded-pill px-4 shadow-sm"
            @click="router.push('/jury')"
          >
            Back to Dashboard
          </button>
        </div>

        <!-- Cases Table -->
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small text-muted">
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
              <tr v-for="c in juryStore.assignedCases" :key="c.id">
                <td class="ps-4 font-monospace">
                  <span class="fw-bold text-primary">{{ c.case_number }}</span>
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
                  <span class="badge rounded-pill px-3 py-1" :class="getStatusBadgeClass(c.status)">
                    {{ formatStatus(c.status) }}
                  </span>
                </td>
                <td class="text-muted small">
                  {{ formatDate(c.assigned_at) }}
                </td>
                <td class="text-end pe-4">
                  <button
                    type="button"
                    class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm"
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
.max-w-500 {
  max-width: 500px;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.spin {
  display: inline-block;
  animation: spin 1s linear infinite;
}
</style>
