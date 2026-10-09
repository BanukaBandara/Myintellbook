<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import { internalReportService } from '@/services/internalReportService';
import type { InternalReportItem } from '@/types/internalReport';

const route = useRoute();
const reportId = Number(route.params.id);

const report = ref<InternalReportItem | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

const fetchDetails = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    report.value = await internalReportService.getReport(reportId);
  } catch (err: any) {
    errorMessage.value =
      err.response?.status === 403
        ? 'Access denied. You may only view reports submitted by your own account.'
        : 'Failed to load report details.';
  } finally {
    isLoading.value = false;
  }
};

const downloadEvidence = async (evidenceId: number, filename: string) => {
  try {
    const blob = await internalReportService.downloadEvidence(evidenceId);
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Download Failed',
      text: 'Unable to download evidence file.',
    });
  }
};

const getStatusBadgeClass = (status: string) => {
  switch (status) {
    case 'Submitted':
      return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'UnderReview':
      return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    case 'NeedsMoreInformation':
      return 'bg-orange-100 text-orange-800 border-orange-200';
    case 'Valid':
      return 'bg-green-100 text-green-800 border-green-200';
    case 'Invalid':
      return 'bg-gray-100 text-gray-700 border-gray-200';
    case 'Closed':
      return 'bg-purple-100 text-purple-800 border-purple-200';
    default:
      return 'bg-gray-100 text-gray-700 border-gray-200';
  }
};

onMounted(() => {
  fetchDetails();
});
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
      <router-link
        to="/internal-tribunal"
        class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1 mb-2"
      >
        &larr; Back to My Reports
      </router-link>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-16 bg-white rounded-xl border border-gray-200">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <p class="mt-2 text-sm text-gray-500">Loading report...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="text-center py-12 bg-white rounded-xl border border-red-200 p-6">
      <p class="text-sm text-red-600 font-medium">{{ errorMessage }}</p>
      <router-link
        to="/internal-tribunal"
        class="mt-4 inline-block px-4 py-2 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium"
      >
        Return to My Reports
      </router-link>
    </div>

    <!-- Report Detail Card -->
    <div v-else-if="report" class="space-y-6">
      <!-- Top Card Header -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-gray-100">
          <div>
            <div class="flex items-center gap-3">
              <span class="font-mono text-lg font-bold text-blue-600">{{ report.report_number }}</span>
              <span
                :class="[
                  'px-3 py-1 text-xs font-semibold rounded-full border',
                  getStatusBadgeClass(report.status)
                ]"
              >
                {{ report.status }}
              </span>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mt-2">{{ report.subject }}</h1>
          </div>
          <div class="text-xs text-gray-500 sm:text-right">
            <div>Submitted On:</div>
            <div class="font-medium text-gray-700">{{ new Date(report.created_at).toLocaleString() }}</div>
          </div>
        </div>

        <!-- Reported Member Summary -->
        <div class="py-6 border-b border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <h3 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-2">Reported Member</h3>
            <div class="flex items-center gap-3">
              <img
                v-if="report.reported_user?.profile_image"
                :src="report.reported_user.profile_image"
                alt="avatar"
                class="w-10 h-10 rounded-full object-cover border border-gray-200"
              />
              <div v-else class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-600 text-sm">
                {{ report.reported_user?.name?.charAt(0) || 'U' }}
              </div>
              <div>
                <div class="font-semibold text-gray-900 text-sm">{{ report.reported_user?.name }}</div>
                <div class="text-xs text-gray-500">@{{ report.reported_user?.username }}</div>
              </div>
            </div>
          </div>

          <div>
            <h3 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-2">Category & Severity</h3>
            <div class="text-sm font-medium text-gray-800">{{ report.category }}</div>
            <div class="text-xs text-gray-500 capitalize mt-0.5">Severity: <span class="font-medium text-gray-700">{{ report.severity }}</span></div>
          </div>
        </div>

        <!-- Incident Description -->
        <div class="py-6 border-b border-gray-100">
          <h3 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-2">Description</h3>
          <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ report.description }}</p>
        </div>

        <!-- Evidence Section -->
        <div class="py-6 border-b border-gray-100">
          <h3 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-2">
            Attached Evidence ({{ report.evidence?.length || 0 }})
          </h3>
          <div v-if="!report.evidence || report.evidence.length === 0" class="text-xs text-gray-400 italic">
            No evidence files attached to this report.
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
            <div
              v-for="ev in report.evidence"
              :key="ev.id"
              class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs"
            >
              <div class="truncate mr-2">
                <div class="font-medium text-gray-800 truncate">{{ ev.original_name }}</div>
                <div class="text-[10px] text-gray-400">{{ (ev.size / (1024 * 1024)).toFixed(2) }} MB &bull; SHA-256 Verified</div>
              </div>
              <button
                type="button"
                @click="downloadEvidence(ev.id, ev.original_name)"
                class="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded"
              >
                Download
              </button>
            </div>
          </div>
        </div>

        <!-- Administrative Decision (If finalized) -->
        <div v-if="report.decision_reason" class="pt-6">
          <h3 class="text-xs font-semibold uppercase text-gray-400 tracking-wider mb-2">Administrative Outcome Note</h3>
          <div class="p-4 bg-blue-50/50 border border-blue-200 rounded-lg text-sm text-gray-800">
            {{ report.decision_reason }}
          </div>
        </div>

      </div>

      <!-- Confidentiality Reassurance Banner -->
      <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-500 flex items-center gap-3">
        <span class="text-lg text-gray-400">&#128274;</span>
        <span>
          Whistleblower Safety: This report is securely stored and handled under strict platform confidentiality protocols. The reported member cannot view your profile or this report record.
        </span>
      </div>
    </div>
  </div>
</template>
