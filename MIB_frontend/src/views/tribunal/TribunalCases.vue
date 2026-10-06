<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import api from '@/assets/axios';
import { useTribunalStore } from '@/stores/tribunal';
import type { TribunalCase } from '@/types/tribunal';

const tribunalStore = useTribunalStore();
const router = useRouter();

const currentUserId = ref<number | null>(null);

const resolveCurrentUserId = async () => {
  const stored = localStorage.getItem('userData');
  if (stored) {
    try {
      const parsed = JSON.parse(stored);
      if (parsed.id) {
        currentUserId.value = parsed.id;
        return;
      }
    } catch {
      // Ignore JSON parse error and fallback to API
    }
  }

  try {
    const res = await api.get('/user');
    if (res.data?.data?.id) {
      currentUserId.value = res.data.data.id;
    }
  } catch (err) {
    console.error('Failed to resolve current authenticated user ID', err);
  }
};

const submittedCases = computed(() => {
  if (!currentUserId.value) return [];
  return tribunalStore.cases.filter((tribunalCase) =>
    tribunalCase.parties.some(
      (party) =>
        party.role === 'complainant' &&
        party.user.id === currentUserId.value
    )
  );
});

const receivedCases = computed(() => {
  if (!currentUserId.value) return [];
  return tribunalStore.cases.filter((tribunalCase) =>
    tribunalCase.parties.some(
      (party) =>
        party.role === 'respondent' &&
        party.user.id === currentUserId.value
    )
  );
});

const getPartyName = (
  c: TribunalCase,
  targetRole: 'complainant' | 'respondent'
): string => {
  const party = c.parties.find((p) => p.role === targetRole);
  return party?.user?.name || (party?.user?.id ? `User #${party.user.id}` : 'Unknown');
};

const formatStatus = (status: string): string => {
  if (!status) return '-';
  if (status === 'jury_selection') return 'Awaiting Jury Panel Assignment';
  return status
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char: string) => char.toUpperCase());
};

const getStatusBadgeClass = (status: string): string => {
  switch (status) {
    case 'submitted':
      return 'bg-secondary text-white';
    case 'awaiting_respondent':
      return 'bg-warning text-dark';
    case 'response_received':
      return 'bg-info text-dark';
    case 'decided':
    case 'settled':
    case 'closed':
      return 'bg-success text-white';
    case 'dismissed':
    case 'withdrawn':
      return 'bg-danger text-white';
    default:
      return 'bg-primary text-white';
  }
};

const getSeverityBadgeClass = (severity: string): string => {
  switch (severity?.toLowerCase()) {
    case 'high':
    case 'critical':
      return 'text-danger fw-bold';
    case 'medium':
      return 'text-warning fw-bold';
    default:
      return 'text-muted';
  }
};

const formatDate = (date: string | null): string => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const openCase = (id: number) => {
  router.push(`/tribunal/cases/${id}`);
};

const createCase = () => {
  router.push('/tribunal/create');
};

onMounted(async () => {
  await resolveCurrentUserId();
  await Promise.all([
    tribunalStore.fetchCases(),
    tribunalStore.fetchCapabilities(),
  ]);
});
</script>

<template>
  <div class="container py-4">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <h2 class="fw-bold mb-1 text-primary">
          🏛️ External Tribunal Cases
        </h2>
        <p class="text-muted mb-0">
          Track cases you have submitted and cases filed involving your account.
        </p>
      </div>

      <div class="d-flex gap-2">
        <button
          v-if="tribunalStore.capabilities?.adjudicator?.eligible"
          type="button"
          class="btn btn-outline-primary shadow-sm px-3"
          @click="router.push('/tribunal/jury')"
        >
          <i class="bi bi-bank me-1" />
          Adjudicator Assignments
          <span
            v-if="(tribunalStore.capabilities?.adjudicator?.pending_assignments || 0) > 0"
            class="badge bg-danger ms-1 rounded-pill"
          >
            {{ tribunalStore.capabilities?.adjudicator?.pending_assignments }}
          </span>
        </button>
        <button
          v-if="tribunalStore.capabilities?.is_admin_reviewer"
          type="button"
          class="btn btn-outline-warning shadow-sm px-3"
          @click="router.push('/admin/professional-verifications')"
        >
          <i class="bi bi-shield-check me-1" />
          Review Verifications
        </button>
        <button
          v-if="tribunalStore.capabilities?.is_admin_reviewer"
          type="button"
          class="btn btn-outline-info shadow-sm px-3"
          @click="router.push('/admin/tribunal/jury-panels')"
        >
          <i class="bi bi-people me-1" />
          Manage Jury Panels
        </button>
        <button
          type="button"
          class="btn btn-primary shadow-sm px-4"
          @click="createCase"
        >
          <i class="bi bi-plus-circle me-1" />
          Submit New Case
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div
      v-if="tribunalStore.loading"
      class="text-center py-5 my-4 bg-white rounded-4 shadow-sm"
    >
      <div class="spinner-border text-primary" role="status" />
      <p class="mt-3 text-muted">
        Loading your tribunal cases...
      </p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="tribunalStore.error"
      class="alert alert-danger shadow-sm rounded-3 d-flex align-items-center gap-2"
    >
      <i class="bi bi-exclamation-triangle-fill fs-5" />
      <span>{{ tribunalStore.error }}</span>
    </div>

    <template v-else>
      <!-- Section 1: Cases I Submitted -->
      <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-send me-2 text-primary" />
            Cases I Submitted
            <span class="badge bg-primary rounded-pill ms-2">{{ submittedCases.length }}</span>
          </h5>
        </div>

        <div class="card-body p-0">
          <div
            v-if="submittedCases.length === 0"
            class="empty-state text-center py-5"
          >
            <i class="bi bi-inbox fs-1 text-muted" />
            <p class="mt-2 text-muted mb-3">
              You have not submitted any external tribunal cases yet.
            </p>
            <button
              type="button"
              class="btn btn-sm btn-outline-primary"
              @click="createCase"
            >
              Submit a Case
            </button>
          </div>

          <div
            v-else
            class="table-responsive"
          >
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Case #</th>
                  <th>Title</th>
                  <th>Respondent</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Severity</th>
                  <th>Submitted Date</th>
                  <th class="text-end pe-4">Action</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="c in submittedCases"
                  :key="c.id"
                >
                  <td class="ps-4">
                    <span class="fw-bold text-dark">{{ c.case_number }}</span>
                  </td>
                  <td>
                    <span class="fw-semibold text-truncate d-inline-block" style="max-width: 240px;">
                      {{ c.title }}
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border">
                      {{ getPartyName(c, 'respondent') }}
                    </span>
                  </td>
                  <td>
                    <span class="text-secondary small">{{ c.category }}</span>
                  </td>
                  <td>
                    <span class="badge" :class="getStatusBadgeClass(c.status)">
                      {{ formatStatus(c.status) }}
                    </span>
                  </td>
                  <td>
                    <span :class="getSeverityBadgeClass(c.severity)">
                      {{ c.severity.toUpperCase() }}
                    </span>
                  </td>
                  <td>
                    <small class="text-muted">{{ formatDate(c.submitted_at) }}</small>
                  </td>
                  <td class="text-end pe-4">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary px-3 rounded-pill"
                      @click="openCase(c.id)"
                    >
                      View
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Section 2: Cases Against Me -->
      <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-shield-exclamation me-2 text-warning" />
            Cases Against Me
            <span class="badge bg-warning text-dark rounded-pill ms-2">{{ receivedCases.length }}</span>
          </h5>
        </div>

        <div class="card-body p-0">
          <div
            v-if="receivedCases.length === 0"
            class="empty-state text-center py-5"
          >
            <i class="bi bi-shield-check fs-1 text-success" />
            <p class="mt-2 text-muted mb-0">
              There are currently no tribunal cases filed against you.
            </p>
          </div>

          <div
            v-else
            class="table-responsive"
          >
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Case #</th>
                  <th>Title</th>
                  <th>Complainant</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Severity</th>
                  <th>Submitted Date</th>
                  <th class="text-end pe-4">Action</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="c in receivedCases"
                  :key="c.id"
                >
                  <td class="ps-4">
                    <span class="fw-bold text-dark">{{ c.case_number }}</span>
                  </td>
                  <td>
                    <span class="fw-semibold text-truncate d-inline-block" style="max-width: 240px;">
                      {{ c.title }}
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border">
                      {{ getPartyName(c, 'complainant') }}
                    </span>
                  </td>
                  <td>
                    <span class="text-secondary small">{{ c.category }}</span>
                  </td>
                  <td>
                    <span class="badge" :class="getStatusBadgeClass(c.status)">
                      {{ formatStatus(c.status) }}
                    </span>
                  </td>
                  <td>
                    <span :class="getSeverityBadgeClass(c.severity)">
                      {{ c.severity.toUpperCase() }}
                    </span>
                  </td>
                  <td>
                    <small class="text-muted">{{ formatDate(c.submitted_at) }}</small>
                  </td>
                  <td class="text-end pe-4">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-warning px-3 rounded-pill"
                      @click="openCase(c.id)"
                    >
                      View / Respond
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.empty-state {
  padding: 3rem 1rem;
}

.table th {
  font-size: 0.82rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #6c757d;
  white-space: nowrap;
}

.table td {
  padding-top: 0.85rem;
  padding-bottom: 0.85rem;
}

.card {
  overflow: hidden;
}
</style>
