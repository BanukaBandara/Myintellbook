<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { internalReportService } from '@/services/internalReportService';
import type { InternalReportItem } from '@/types/internalReport';

const reports = ref<InternalReportItem[]>([]);
const meta = ref<any>(null);
const isLoading = ref(true);
const errorMessage = ref('');

const fetchReports = async (page = 1) => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const res = await internalReportService.getMyReports(page);
    reports.value = res.data;
    meta.value = res.meta;
  } catch (err: any) {
    errorMessage.value = 'Failed to load your submitted misconduct reports.';
  } finally {
    isLoading.value = false;
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
  fetchReports();
});
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Internal Misconduct Reports</h1>
        <p class="text-sm text-gray-600 mt-1">
          Track the status and outcome of policy violations and misconduct you have reported.
        </p>
      </div>
      <router-link
        to="/internal-tribunal/create"
        class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors"
      >
        + Report Misconduct
      </router-link>
    </div>

    <!-- Confidentiality Notice Banner -->
    <div class="mb-6 bg-slate-50 border border-slate-200 rounded-lg p-4 text-xs text-slate-600 flex items-start gap-3">
      <span class="text-base text-blue-600 font-bold">&#9432;</span>
      <div>
        <span class="font-semibold text-slate-800">Confidentiality Guarantee:</span>
        Your submitted reports are exclusively accessible to platform Super Administrators. The reported party receives no identification details, nor do other platform members have access to your submissions.
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-16 bg-white rounded-xl border border-gray-200">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <p class="mt-2 text-sm text-gray-500">Loading your reports...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="text-center py-12 bg-white rounded-xl border border-red-200 p-6">
      <p class="text-sm text-red-600 font-medium">{{ errorMessage }}</p>
      <button
        @click="fetchReports(1)"
        class="mt-3 px-4 py-1.5 text-xs bg-red-50 text-red-700 rounded-lg border border-red-200 hover:bg-red-100"
      >
        Retry
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="reports.length === 0" class="text-center py-16 bg-white rounded-xl border border-gray-200 p-8">
      <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 text-xl font-bold">
        &check;
      </div>
      <h3 class="text-base font-semibold text-gray-900">No Submitted Reports</h3>
      <p class="text-sm text-gray-500 max-w-md mx-auto mt-1 mb-6">
        You have not submitted any internal misconduct reports. If you encounter harassment, fraud, or violations, you can file a confidential report at any time.
      </p>
      <router-link
        to="/internal-tribunal/create"
        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg"
      >
        Submit a Report
      </router-link>
    </div>

    <!-- Report Table / Cards -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase tracking-wider">
            <tr>
              <th scope="col" class="px-6 py-3.5 text-left">Report #</th>
              <th scope="col" class="px-6 py-3.5 text-left">Reported Member</th>
              <th scope="col" class="px-6 py-3.5 text-left">Category</th>
              <th scope="col" class="px-6 py-3.5 text-left">Subject</th>
              <th scope="col" class="px-6 py-3.5 text-center">Status</th>
              <th scope="col" class="px-6 py-3.5 text-left">Submitted Date</th>
              <th scope="col" class="px-6 py-3.5 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            <tr v-for="rep in reports" :key="rep.id" class="hover:bg-gray-50/80 transition-colors">
              <td class="px-6 py-4 font-mono font-medium text-blue-600 whitespace-nowrap">
                {{ rep.report_number }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <img
                    v-if="rep.reported_user?.profile_image"
                    :src="rep.reported_user.profile_image"
                    alt="avatar"
                    class="w-7 h-7 rounded-full object-cover"
                  />
                  <div v-else class="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                    {{ rep.reported_user?.name?.charAt(0) || 'U' }}
                  </div>
                  <div>
                    <div class="font-medium text-gray-900 text-xs">{{ rep.reported_user?.name }}</div>
                    <div class="text-[11px] text-gray-400">@{{ rep.reported_user?.username }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-xs text-gray-700 whitespace-nowrap">
                {{ rep.category }}
              </td>
              <td class="px-6 py-4 text-xs text-gray-900 max-w-xs truncate font-medium">
                {{ rep.subject }}
              </td>
              <td class="px-6 py-4 text-center whitespace-nowrap">
                <span
                  :class="[
                    'inline-block px-2.5 py-1 text-xs font-semibold rounded-full border',
                    getStatusBadgeClass(rep.status)
                  ]"
                >
                  {{ rep.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap">
                {{ new Date(rep.created_at).toLocaleDateString() }}
              </td>
              <td class="px-6 py-4 text-right whitespace-nowrap">
                <router-link
                  :to="`/internal-tribunal/${rep.id}`"
                  class="text-xs font-semibold text-blue-600 hover:text-blue-800"
                >
                  View Details &rarr;
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="px-6 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500"
      >
        <div>
          Showing page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)
        </div>
        <div class="flex gap-2">
          <button
            :disabled="meta.current_page <= 1"
            @click="fetchReports(meta.current_page - 1)"
            class="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-50"
          >
            Previous
          </button>
          <button
            :disabled="meta.current_page >= meta.last_page"
            @click="fetchReports(meta.current_page + 1)"
            class="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-50"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
