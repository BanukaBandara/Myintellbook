<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { adminInternalReportService } from '@/services/adminInternalReportService';
import type { AdminInternalReportItem } from '@/types/internalReport';

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
  } catch (err: any) {
    errorMessage.value = 'Failed to load misconduct reports.';
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
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Internal Misconduct Reports</h1>
        <p class="text-sm text-gray-500 mt-0.5">
          Review, adjudicate, and audit member misconduct and policy violation reports.
        </p>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-[200px]">
        <input
          v-model="searchQuery"
          @keyup.enter="applyFilters"
          type="text"
          placeholder="Search report #, subject, or member..."
          class="w-full px-3.5 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      <div class="w-44">
        <select
          v-model="selectedStatus"
          @change="applyFilters"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Statuses</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>

      <div class="w-56">
        <select
          v-model="selectedCategory"
          @change="applyFilters"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Categories</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>

      <button
        @click="applyFilters"
        type="button"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors"
      >
        Filter
      </button>

      <button
        @click="resetFilters"
        type="button"
        class="px-3 py-2 text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
      >
        Reset
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-16 bg-white rounded-xl border border-gray-200">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <p class="mt-2 text-xs text-gray-500">Loading reports...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="text-center py-12 bg-white rounded-xl border border-red-200 p-6">
      <p class="text-xs text-red-600 font-medium">{{ errorMessage }}</p>
      <button
        @click="fetchReports(1)"
        class="mt-2 px-3 py-1 text-xs bg-red-50 text-red-700 rounded border border-red-200 hover:bg-red-100"
      >
        Retry
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="reports.length === 0" class="text-center py-16 bg-white rounded-xl border border-gray-200 p-8">
      <h3 class="text-sm font-semibold text-gray-700">No Misconduct Reports Found</h3>
      <p class="text-xs text-gray-400 mt-1">There are currently no internal reports matching your search criteria.</p>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
          <thead class="bg-gray-50 text-gray-600 font-semibold uppercase tracking-wider">
            <tr>
              <th scope="col" class="px-5 py-3.5 text-left">Report #</th>
              <th scope="col" class="px-5 py-3.5 text-left">Reporter</th>
              <th scope="col" class="px-5 py-3.5 text-left">Reported Member</th>
              <th scope="col" class="px-5 py-3.5 text-left">Category</th>
              <th scope="col" class="px-5 py-3.5 text-left">Subject</th>
              <th scope="col" class="px-5 py-3.5 text-center">Status</th>
              <th scope="col" class="px-5 py-3.5 text-left">Date</th>
              <th scope="col" class="px-5 py-3.5 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="rep in reports" :key="rep.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-5 py-3.5 font-mono font-semibold text-blue-600 whitespace-nowrap">
                {{ rep.report_number }}
              </td>
              <td class="px-5 py-3.5 whitespace-nowrap">
                <div class="font-medium text-gray-900">{{ rep.reporter?.name }}</div>
                <div class="text-[11px] text-gray-400">{{ rep.reporter?.email }}</div>
              </td>
              <td class="px-5 py-3.5 whitespace-nowrap">
                <div class="font-medium text-gray-900 flex items-center gap-1.5">
                  {{ rep.reported_user?.name }}
                  <span v-if="rep.reported_user?.is_jury_panel" class="text-[10px] bg-purple-100 text-purple-700 px-1 rounded font-bold">
                    Jury
                  </span>
                </div>
                <div class="text-[11px] text-gray-400">{{ rep.reported_user?.email }}</div>
              </td>
              <td class="px-5 py-3.5 text-gray-700 whitespace-nowrap">
                {{ rep.category }}
              </td>
              <td class="px-5 py-3.5 text-gray-800 max-w-xs truncate font-medium">
                {{ rep.subject }}
              </td>
              <td class="px-5 py-3.5 text-center whitespace-nowrap">
                <span
                  :class="[
                    'inline-block px-2.5 py-0.5 text-[11px] font-semibold rounded-full border',
                    getStatusBadgeClass(rep.status)
                  ]"
                >
                  {{ rep.status }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">
                {{ new Date(rep.created_at).toLocaleDateString() }}
              </td>
              <td class="px-5 py-3.5 text-right whitespace-nowrap">
                <router-link
                  :to="`/admin/internal-reports/${rep.id}`"
                  class="text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 px-2.5 py-1 rounded hover:bg-blue-100 transition-colors"
                >
                  Review Dossier &rarr;
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500"
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
