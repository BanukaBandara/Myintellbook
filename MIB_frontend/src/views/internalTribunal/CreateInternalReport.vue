<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { internalReportService } from '@/services/internalReportService';
import type { ReportUserSummary, InternalReportCategory } from '@/types/internalReport';

const router = useRouter();

// Form State
const selectedUser = ref<ReportUserSummary | null>(null);
const searchQuery = ref('');
const searchResults = ref<ReportUserSummary[]>([]);
const isSearching = ref(false);
const searchError = ref('');

const category = ref<InternalReportCategory>('Identity & Profile Fraud');
const subject = ref('');
const description = ref('');
const severity = ref('medium');
const evidenceFiles = ref<File[]>([]);
const isSubmitting = ref(false);

const categories: InternalReportCategory[] = [
  'Identity & Profile Fraud',
  'Qualification / Professional Fraud',
  'Harassment & Inappropriate Behaviour',
  'Scam / Security / Privacy Violation',
  'Academic & Score Manipulation',
  'Tribunal / Legal Process Misconduct',
  'Content & Community Abuse',
  'Other Platform Misconduct',
];

// Debounced search
let searchTimer: any = null;
const onSearchInput = () => {
  clearTimeout(searchTimer);
  searchError.value = '';
  if (searchQuery.value.trim().length < 3) {
    searchResults.value = [];
    return;
  }
  searchTimer = setTimeout(async () => {
    isSearching.value = true;
    try {
      searchResults.value = await internalReportService.searchUsers(searchQuery.value.trim());
    } catch (err: any) {
      searchError.value = 'Failed to search members. Please try again.';
    } finally {
      isSearching.value = false;
    }
  }, 300);
};

const selectUser = (user: ReportUserSummary) => {
  selectedUser.value = user;
  searchResults.value = [];
  searchQuery.value = '';
  // If Jury Panel member, restrict category
  if (user.is_jury_panel) {
    category.value = 'Tribunal / Legal Process Misconduct';
  }
};

const clearSelectedUser = () => {
  selectedUser.value = null;
};

// File handling
const onFileSelected = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (!target.files) return;

  const files = Array.from(target.files);
  for (const file of files) {
    if (evidenceFiles.value.length >= 5) {
      Swal.fire({
        icon: 'warning',
        title: 'Limit Reached',
        text: 'You may upload a maximum of 5 evidence files.',
      });
      break;
    }
    if (file.size > 10 * 1024 * 1024) {
      Swal.fire({
        icon: 'error',
        title: 'File Too Large',
        text: `File "${file.name}" exceeds the 10 MB limit.`,
      });
      continue;
    }
    evidenceFiles.value.push(file);
  }
  target.value = '';
};

const removeFile = (index: number) => {
  evidenceFiles.value.splice(index, 1);
};

const canSubmit = computed(() => {
  return (
    selectedUser.value !== null &&
    subject.value.trim().length >= 3 &&
    description.value.trim().length >= 10 &&
    !isSubmitting.value
  );
});

const submitReport = async () => {
  if (!canSubmit.value || !selectedUser.value) return;

  const result = await Swal.fire({
    title: 'Submit Misconduct Report?',
    text: 'Your report will be reviewed confidentially by Super Administrators. The reported user will not see your identity or private evidence.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, Submit Report',
  });

  if (!result.isConfirmed) return;

  isSubmitting.value = true;

  try {
    const formData = new FormData();
    formData.append('reported_user_id', String(selectedUser.value.id));
    formData.append('category', category.value);
    formData.append('subject', subject.value.trim());
    formData.append('description', description.value.trim());
    formData.append('severity', severity.value);

    evidenceFiles.value.forEach((file) => {
      formData.append('evidence[]', file);
    });

    const report = await internalReportService.submitReport(formData);

    await Swal.fire({
      icon: 'success',
      title: 'Report Submitted',
      text: `Your report (${report.report_number}) has been securely recorded and queued for administrative review.`,
    });

    router.push(`/internal-tribunal/${report.id}`);
  } catch (err: any) {
    const message =
      err.response?.data?.message ||
      err.response?.data?.errors?.reported_user_id?.[0] ||
      'Failed to submit report. Please check your inputs.';
    Swal.fire({
      icon: 'error',
      title: 'Submission Failed',
      text: message,
    });
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Breadcrumb / Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <router-link
          to="/internal-tribunal"
          class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1 mb-2"
        >
          &larr; Back to My Reports
        </router-link>
        <h1 class="text-2xl font-bold text-gray-900">Report Platform Misconduct</h1>
        <p class="text-sm text-gray-600 mt-1">
          Confidential internal reporting for policy violations, fraud, harassment, or abuse.
        </p>
      </div>
      <div class="hidden sm:flex items-center gap-2 bg-blue-50 border border-blue-200 text-blue-700 px-3 py-2 rounded-lg text-xs">
        <span class="font-semibold">Confidentiality Protected:</span>
        <span>Your identity and evidence are never shared with reported parties.</span>
      </div>
    </div>

    <!-- Main Card Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-6 sm:p-8 space-y-6">

        <!-- 1. Select Reported Member -->
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">
            1. Reported Member <span class="text-red-500">*</span>
          </label>

          <div v-if="!selectedUser" class="relative">
            <div class="relative">
              <input
                v-model="searchQuery"
                @input="onSearchInput"
                type="text"
                placeholder="Type member name, email or username (min 3 chars)..."
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
              />
              <span v-if="isSearching" class="absolute right-3 top-3 text-xs text-gray-400">Searching...</span>
            </div>

            <!-- Search Dropdown Results -->
            <div
              v-if="searchResults.length > 0"
              class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto divide-y divide-gray-100"
            >
              <div
                v-for="user in searchResults"
                :key="user.id"
                @click="selectUser(user)"
                class="p-3 hover:bg-blue-50 cursor-pointer flex items-center justify-between transition-colors"
              >
                <div class="flex items-center gap-3">
                  <img
                    v-if="user.profile_image"
                    :src="user.profile_image"
                    alt="avatar"
                    class="w-8 h-8 rounded-full object-cover"
                  />
                  <div v-else class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                    {{ user.name.charAt(0) }}
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                    <div class="text-xs text-gray-500">@{{ user.username }}</div>
                  </div>
                </div>
                <div v-if="user.is_jury_panel" class="text-xs bg-purple-100 text-purple-700 font-semibold px-2 py-0.5 rounded">
                  Jury Panel
                </div>
              </div>
            </div>

            <p v-if="searchError" class="text-xs text-red-600 mt-1">{{ searchError }}</p>
          </div>

          <!-- Selected User Card -->
          <div
            v-else
            class="flex items-center justify-between p-3.5 bg-blue-50/60 border border-blue-200 rounded-lg"
          >
            <div class="flex items-center gap-3">
              <img
                v-if="selectedUser.profile_image"
                :src="selectedUser.profile_image"
                alt="avatar"
                class="w-10 h-10 rounded-full object-cover border border-blue-300"
              />
              <div v-else class="w-10 h-10 rounded-full bg-blue-200 flex items-center justify-center font-bold text-blue-700 text-sm">
                {{ selectedUser.name.charAt(0) }}
              </div>
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ selectedUser.name }}</div>
                <div class="text-xs text-gray-500">@{{ selectedUser.username }}</div>
              </div>
              <span v-if="selectedUser.is_jury_panel" class="text-xs bg-purple-100 text-purple-700 font-semibold px-2 py-0.5 rounded ml-2">
                Jury Panel Member
              </span>
            </div>
            <button
              @click="clearSelectedUser"
              type="button"
              class="text-xs text-red-600 hover:text-red-800 font-medium px-2 py-1 rounded hover:bg-red-50 transition-colors"
            >
              Change
            </button>
          </div>
        </div>

        <!-- 2. Category Selection -->
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">
            2. Misconduct Category <span class="text-red-500">*</span>
          </label>
          <select
            v-model="category"
            :disabled="selectedUser?.is_jury_panel === true"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white"
          >
            <option v-for="cat in categories" :key="cat" :value="cat">
              {{ cat }}
            </option>
          </select>
          <p v-if="selectedUser?.is_jury_panel" class="text-xs text-purple-600 mt-1">
            Note: Jury Panel members may only be reported under "Tribunal / Legal Process Misconduct".
          </p>
        </div>

        <!-- 3. Severity Level -->
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">
            3. Severity Assessment
          </label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <label
              v-for="sev in ['low', 'medium', 'high', 'critical']"
              :key="sev"
              :class="[
                'border rounded-lg p-3 text-center cursor-pointer transition-all text-xs font-semibold uppercase tracking-wider',
                severity === sev
                  ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm'
                  : 'border-gray-200 text-gray-600 hover:border-gray-300 bg-white'
              ]"
            >
              <input type="radio" v-model="severity" :value="sev" class="sr-only" />
              {{ sev }}
            </label>
          </div>
        </div>

        <!-- 4. Subject Line -->
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">
            4. Subject / Summary <span class="text-red-500">*</span>
          </label>
          <input
            v-model="subject"
            type="text"
            maxlength="200"
            placeholder="Brief summary of the issue (e.g. Forged diploma credentials on profile)"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
          />
          <div class="text-right text-xs text-gray-400 mt-1">{{ subject.length }}/200</div>
        </div>

        <!-- 5. Detailed Description -->
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">
            5. Incident Description & Details <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="description"
            rows="5"
            maxlength="10000"
            placeholder="Please detail what occurred, dates, context, and why this represents misconduct..."
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
          ></textarea>
          <div class="flex justify-between text-xs text-gray-400 mt-1">
            <span>Minimum 10 characters</span>
            <span>{{ description.length }}/10,000</span>
          </div>
        </div>

        <!-- 6. Private Evidence Upload -->
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">
            6. Evidence Files (Optional)
          </label>
          <p class="text-xs text-gray-500 mb-2">
            Upload screenshots, PDFs, documents, or logs (Max 5 files, 10 MB each). Stored in encrypted private storage.
          </p>

          <div
            v-if="evidenceFiles.length < 5"
            class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors bg-gray-50/50"
          >
            <input
              type="file"
              id="evidenceUpload"
              multiple
              accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.webp"
              @change="onFileSelected"
              class="sr-only"
            />
            <label for="evidenceUpload" class="cursor-pointer">
              <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <span class="text-sm font-medium text-blue-600 hover:text-blue-800">Click to browse files</span>
              <span class="text-xs text-gray-500 block mt-1">PDF, DOC, DOCX, TXT, PNG, JPG, WEBP</span>
            </label>
          </div>

          <!-- Uploaded files list -->
          <div v-if="evidenceFiles.length > 0" class="mt-3 space-y-2">
            <div
              v-for="(f, idx) in evidenceFiles"
              :key="idx"
              class="flex items-center justify-between p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-xs"
            >
              <div class="flex items-center gap-2 truncate">
                <span class="font-medium text-gray-700 truncate">{{ f.name }}</span>
                <span class="text-gray-400 text-[10px]">({{ (f.size / (1024 * 1024)).toFixed(2) }} MB)</span>
              </div>
              <button
                type="button"
                @click="removeFile(idx)"
                class="text-red-500 hover:text-red-700 font-semibold px-2 py-0.5"
              >
                Remove
              </button>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
          <router-link
            to="/internal-tribunal"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800"
          >
            Cancel
          </router-link>
          <button
            type="button"
            @click="submitReport"
            :disabled="!canSubmit"
            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium rounded-lg text-sm transition-colors shadow-sm"
          >
            {{ isSubmitting ? 'Submitting Report...' : 'Submit Confidential Report' }}
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
