<template>
  <div class="internal-report-wizard">
    <!-- Success Confirmation View -->
    <section v-if="submittedReport" class="panel success-panel" aria-labelledby="success-title">
      <div class="success-icon" aria-hidden="true">
        <i class="bi bi-shield-fill-check"></i>
      </div>
      <h2 id="success-title" class="panel-title text-center">Misconduct Report Submitted</h2>
      <p class="success-report-num">
        Report Reference: <strong>{{ submittedReport.report_number }}</strong>
      </p>
      <p class="success-text">
        Your report regarding <strong>{{ submittedReport.reported_user?.name || 'the member' }}</strong> has been securely recorded and queued for confidential review by Super Administrators.
      </p>
      <div class="confidentiality-notice">
        <i class="bi bi-lock-fill" aria-hidden="true"></i>
        <span><strong>Confidentiality Guarantee:</strong> The reported member will not be informed of your identity or provided access to your evidence.</span>
      </div>

      <div class="success-actions">
        <button
          type="button"
          class="btn primary"
          @click="router.push(`/internal-tribunal/${submittedReport.id}`)"
        >
          <i class="bi bi-eye" aria-hidden="true"></i> View Report Details
        </button>
        <button
          type="button"
          class="btn ghost"
          @click="router.push('/internal-tribunal')"
        >
          <i class="bi bi-folder2-open" aria-hidden="true"></i> My Misconduct Reports
        </button>
        <button
          type="button"
          class="btn ghost"
          @click="resetForm"
        >
          <i class="bi bi-plus-circle" aria-hidden="true"></i> Submit Another Report
        </button>
      </div>
    </section>

    <!-- Multi-step Form Wizard -->
    <section v-else class="panel" aria-labelledby="wizard-title">
      <div class="panel-head">
        <h2 id="wizard-title" class="panel-title">Report Platform Misconduct</h2>
        <span class="step-count">Step {{ step + 1 }} of {{ STEPS.length }}</span>
      </div>

      <div
        class="progress-track"
        role="progressbar"
        :aria-valuenow="step + 1"
        aria-valuemin="1"
        :aria-valuemax="STEPS.length"
        :aria-label="`Step ${step + 1} of ${STEPS.length}`"
      >
        <span :style="{ width: `${((step + 1) / STEPS.length) * 100}%` }"></span>
      </div>

      <ol class="step-list">
        <li
          v-for="(item, index) in STEPS"
          :key="item.key"
          :class="{ current: index === step, done: index < step }"
        >
          <span class="step-dot" aria-hidden="true">
            <i :class="['bi', index < step ? 'bi-check-lg' : item.icon]"></i>
          </span>
          <span class="step-name">{{ item.label }}</span>
        </li>
      </ol>

      <div class="step-body">
        <!-- STEP 1: Select Reported Member -->
        <div v-if="step === 0" class="step-content">
          <div class="field">
            <label for="member-search-input" class="field-label">
              Which member are you reporting? <span class="required-star">*</span>
            </label>

            <!-- Search Input when no user selected -->
            <div v-if="!selectedUser" class="search-box-wrapper">
              <div class="search-input-container">
                <i class="bi bi-search search-icon" aria-hidden="true"></i>
                <input
                  id="member-search-input"
                  v-model="searchQuery"
                  @input="onSearchInput"
                  type="text"
                  placeholder="Type member name, email or username (min 3 chars)..."
                  class="search-input"
                  autocomplete="off"
                />
                <span v-if="isSearching" class="search-spinner" aria-hidden="true"></span>
              </div>

              <!-- Search Results Dropdown -->
              <div v-if="searchResults.length > 0" class="search-dropdown" role="listbox">
                <div
                  v-for="user in searchResults"
                  :key="user.id"
                  class="search-result-item"
                  role="option"
                  @click="selectUser(user)"
                >
                  <div class="user-avatar-wrap">
                    <img
                      v-if="user.profile_image"
                      :src="user.profile_image"
                      alt="avatar"
                      class="user-avatar"
                      @error="onAvatarError"
                    />
                    <div v-else class="user-avatar-initials">
                      {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                  </div>
                  <div class="user-info">
                    <span class="user-name">{{ user.name }}</span>
                    <span class="user-username">@{{ user.username }}</span>
                  </div>
                  <span v-if="user.is_jury_panel" class="badge-jury-panel">
                    Jury Panel
                  </span>
                </div>
              </div>

              <p v-if="searchError" class="field-error-text">{{ searchError }}</p>
              <p class="field-hint">Search for members across MyIntellibook to report policy violations or misconduct.</p>
            </div>

            <!-- Selected Member Card -->
            <div v-else class="selected-member-card">
              <div class="selected-member-info">
                <div class="user-avatar-wrap">
                  <img
                    v-if="selectedUser.profile_image"
                    :src="selectedUser.profile_image"
                    alt="avatar"
                    class="user-avatar"
                    @error="onAvatarError"
                  />
                  <div v-else class="user-avatar-initials">
                    {{ selectedUser.name.charAt(0).toUpperCase() }}
                  </div>
                </div>
                <div class="user-details">
                  <span class="selected-user-name">{{ selectedUser.name }}</span>
                  <span class="selected-user-handle">@{{ selectedUser.username }}</span>
                  <span v-if="selectedUser.is_jury_panel" class="badge-jury-panel inline">
                    Jury Panel Institutional Account
                  </span>
                </div>
              </div>
              <button
                type="button"
                class="btn-change-user"
                @click="clearSelectedUser"
              >
                <i class="bi bi-x-circle" aria-hidden="true"></i> Change
              </button>
            </div>

            <!-- Jury Panel Category Notice -->
            <div v-if="selectedUser?.is_jury_panel" class="info-notice-box">
              <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
              <span><strong>Jury Panel Target:</strong> Institutional Jury Panel accounts may only be reported under <em>"Tribunal / Legal Process Misconduct"</em>. This category will be locked automatically.</span>
            </div>
          </div>
        </div>

        <!-- STEP 2: Misconduct Details -->
        <div v-else-if="step === 1" class="step-content">
          <!-- Category -->
          <div class="field">
            <label for="category-select" class="field-label">
              Misconduct Category <span class="required-star">*</span>
            </label>
            <select
              id="category-select"
              v-model="category"
              :disabled="selectedUser?.is_jury_panel === true"
              class="form-select"
            >
              <option v-for="cat in categories" :key="cat" :value="cat">
                {{ cat }}
              </option>
            </select>
            <p v-if="selectedUser?.is_jury_panel" class="field-hint text-purple">
              Category locked to "Tribunal / Legal Process Misconduct" for Jury Panel accounts.
            </p>
            <p v-else class="field-hint">Select the category that best describes the policy breach or incident.</p>
          </div>

          <!-- Severity Level -->
          <div class="field mt-3">
            <label class="field-label">Severity Level</label>
            <div class="severity-grid">
              <label
                v-for="sev in severityOptions"
                :key="sev.value"
                class="severity-card"
                :class="{ active: severity === sev.value, [sev.value]: true }"
              >
                <input
                  type="radio"
                  v-model="severity"
                  :value="sev.value"
                  class="sr-only"
                />
                <i :class="['bi', sev.icon]" aria-hidden="true"></i>
                <span class="severity-title">{{ sev.label }}</span>
              </label>
            </div>
          </div>

          <!-- Subject -->
          <div class="field mt-3">
            <label for="subject-input" class="field-label">
              Subject / Summary <span class="required-star">*</span>
            </label>
            <input
              id="subject-input"
              v-model="subject"
              type="text"
              maxlength="200"
              placeholder="Brief summary of the issue (e.g. Forged degree certificate on profile)"
              class="form-input"
            />
            <div class="field-footer">
              <span class="field-hint">Minimum 3 characters</span>
              <span class="char-count" :class="{ limit: subject.length >= 200 }">{{ subject.length }}/200</span>
            </div>
          </div>

          <!-- Detailed Description -->
          <div class="field mt-3">
            <label for="description-input" class="field-label">
              Detailed Description <span class="required-star">*</span>
            </label>
            <textarea
              id="description-input"
              v-model="description"
              rows="5"
              maxlength="10000"
              placeholder="Describe the incident, dates, what was observed, and any context relevant to the Super Admin review team..."
              class="form-textarea"
            ></textarea>
            <div class="field-footer">
              <span class="field-hint">Minimum 10 characters</span>
              <span class="char-count" :class="{ limit: description.length >= 10000 }">{{ description.length }}/10,000</span>
            </div>
          </div>
        </div>

        <!-- STEP 3: Evidence Files -->
        <div v-else-if="step === 2" class="step-content">
          <div class="field">
            <div class="field-header-row">
              <label class="field-label">Supporting Evidence (Optional)</label>
              <span class="file-count-badge">{{ evidenceFiles.length }} / 5 files</span>
            </div>
            <p class="field-hint mb-2">
              Upload documents, screenshots, or logs to substantiate the report. Max 5 files, 10 MB per file.
            </p>

            <!-- Dropzone -->
            <div
              v-if="evidenceFiles.length < 5"
              class="dropzone-area"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="onDropFiles"
              :class="{ dragging: isDragging }"
            >
              <input
                type="file"
                id="evidence-file-input"
                multiple
                accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.webp"
                class="sr-only"
                @change="onFileSelected"
              />
              <label for="evidence-file-input" class="dropzone-label">
                <i class="bi bi-cloud-arrow-up dropzone-icon" aria-hidden="true"></i>
                <span class="dropzone-text">Click or drag files here to upload</span>
                <span class="dropzone-formats">Accepted formats: PDF, DOC, DOCX, TXT, PNG, JPG, WEBP</span>
              </label>
            </div>

            <!-- Uploaded Files List -->
            <ul v-if="evidenceFiles.length > 0" class="uploaded-files-list">
              <li
                v-for="(file, idx) in evidenceFiles"
                :key="idx"
                class="uploaded-file-row"
              >
                <div class="file-info-group">
                  <i :class="['bi', getFileIcon(file.name)]" aria-hidden="true"></i>
                  <span class="file-name" :title="file.name">{{ file.name }}</span>
                  <span class="file-size">({{ (file.size / (1024 * 1024)).toFixed(2) }} MB)</span>
                </div>
                <button
                  type="button"
                  class="btn-remove-file"
                  @click="removeFile(idx)"
                  :aria-label="`Remove file ${file.name}`"
                >
                  <i class="bi bi-trash3" aria-hidden="true"></i> Remove
                </button>
              </li>
            </ul>

            <!-- Evidence Security Notice -->
            <div class="confidentiality-notice mt-3">
              <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
              <span><strong>Private Encrypted Storage:</strong> Uploaded evidence is stored in confidential storage and accessed exclusively by Super Administrators. The reported user never receives evidence files.</span>
            </div>
          </div>
        </div>

        <!-- STEP 4: Review & Submit -->
        <div v-else class="step-content">
          <dl class="review-list">
            <div>
              <dt>Reported Member</dt>
              <dd class="review-member-dd">
                <div class="user-avatar-wrap sm">
                  <img
                    v-if="selectedUser?.profile_image"
                    :src="selectedUser.profile_image"
                    alt="avatar"
                    class="user-avatar"
                    @error="onAvatarError"
                  />
                  <div v-else class="user-avatar-initials">
                    {{ selectedUser?.name.charAt(0).toUpperCase() }}
                  </div>
                </div>
                <span><strong>{{ selectedUser?.name }}</strong> (@{{ selectedUser?.username }})</span>
                <span v-if="selectedUser?.is_jury_panel" class="badge-jury-panel ml-2">
                  Jury Panel
                </span>
              </dd>
            </div>

            <div>
              <dt>Category</dt>
              <dd>
                <span>{{ category }}</span>
              </dd>
            </div>

            <div>
              <dt>Severity</dt>
              <dd>
                <span class="severity-badge" :class="severity">
                  {{ severity.toUpperCase() }}
                </span>
              </dd>
            </div>

            <div>
              <dt>Subject</dt>
              <dd><strong>{{ subject }}</strong></dd>
            </div>

            <div>
              <dt>Description</dt>
              <dd class="description-text">{{ description }}</dd>
            </div>

            <div>
              <dt>Evidence</dt>
              <dd>
                <span v-if="evidenceFiles.length === 0" class="text-muted">None attached</span>
                <ul v-else class="review-evidence-list">
                  <li v-for="(f, i) in evidenceFiles" :key="i">
                    <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                    {{ f.name }} ({{ (f.size / (1024 * 1024)).toFixed(2) }} MB)
                  </li>
                </ul>
              </dd>
            </div>
          </dl>

          <!-- Whistleblower Protection Assurance Banner -->
          <div class="confidentiality-notice mt-3">
            <i class="bi bi-shield-fill-check" aria-hidden="true"></i>
            <div>
              <strong>Confidential Whistleblower Protection:</strong>
              <p class="mb-0 mt-1 text-xs">
                Your identity, account details, and private evidence will remain confidential with Super Administrators. The reported member receives zero information about who submitted this report.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <p v-if="formError" class="alert error" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ formError }}
      </p>

      <!-- Wizard Navigation Footer -->
      <footer class="form-nav">
        <button
          type="button"
          class="btn ghost"
          :disabled="step === 0 || isSubmitting"
          @click="prevStep"
        >
          <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
        </button>

        <button
          v-if="step < STEPS.length - 1"
          type="button"
          class="btn primary"
          :disabled="!canContinue"
          @click="nextStep"
        >
          Continue <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>

        <button
          v-else
          type="button"
          class="btn primary"
          :disabled="!canSubmit || isSubmitting"
          @click="submitReport"
        >
          <span v-if="isSubmitting" class="spinner" aria-hidden="true"></span>
          {{ isSubmitting ? 'Submitting Report...' : 'Submit Confidential Report' }}
        </button>
      </footer>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import userPng from '@/assets/user.png';
import { internalReportService } from '@/services/internalReportService';
import type {
  ReportUserSummary,
  InternalReportCategory,
  InternalReportItem,
} from '@/types/internalReport';

const router = useRouter();

const STEPS = [
  { key: 'member', label: 'Member', icon: 'bi-person' },
  { key: 'details', label: 'Details', icon: 'bi-card-checklist' },
  { key: 'evidence', label: 'Evidence', icon: 'bi-paperclip' },
  { key: 'review', label: 'Review', icon: 'bi-eye' },
];

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

const severityOptions = [
  { value: 'low', label: 'Low', icon: 'bi-info-circle' },
  { value: 'medium', label: 'Medium', icon: 'bi-exclamation-circle' },
  { value: 'high', label: 'High', icon: 'bi-exclamation-triangle' },
  { value: 'critical', label: 'Critical', icon: 'bi-shield-slash' },
];

// Form Wizard State
const step = ref(0);
const selectedUser = ref<ReportUserSummary | null>(null);
const searchQuery = ref('');
const searchResults = ref<ReportUserSummary[]>([]);
const isSearching = ref(false);
const searchError = ref('');

const category = ref<InternalReportCategory>('Identity & Profile Fraud');
const severity = ref('medium');
const subject = ref('');
const description = ref('');
const evidenceFiles = ref<File[]>([]);
const isDragging = ref(false);

const isSubmitting = ref(false);
const formError = ref('');
const submittedReport = ref<InternalReportItem | null>(null);

// Debounced member search
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const onSearchInput = () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchError.value = '';
  formError.value = '';

  const q = searchQuery.value.trim();
  if (q.length < 3) {
    searchResults.value = [];
    isSearching.value = false;
    return;
  }

  isSearching.value = true;
  searchTimer = setTimeout(async () => {
    try {
      searchResults.value = await internalReportService.searchUsers(q);
      if (searchResults.value.length === 0) {
        searchError.value = 'No members found matching that search term.';
      }
    } catch {
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
  searchError.value = '';

  // If Jury Panel member, restrict category
  if (user.is_jury_panel) {
    category.value = 'Tribunal / Legal Process Misconduct';
  }
};

const clearSelectedUser = () => {
  selectedUser.value = null;
  searchQuery.value = '';
  searchResults.value = [];
  searchError.value = '';
};

const onAvatarError = (event: Event) => {
  const target = event.target as HTMLImageElement;
  if (target && target.src !== userPng) {
    target.src = userPng;
  }
};

// Evidence file handling
const addFiles = (files: File[]) => {
  formError.value = '';
  for (const file of files) {
    if (evidenceFiles.value.length >= 5) {
      formError.value = 'You may upload a maximum of 5 evidence files.';
      break;
    }
    if (file.size > 10 * 1024 * 1024) {
      formError.value = `File "${file.name}" exceeds the 10 MB size limit.`;
      continue;
    }
    // Prevent duplicate files by name and size
    const exists = evidenceFiles.value.some(
      (f) => f.name === file.name && f.size === file.size
    );
    if (!exists) {
      evidenceFiles.value.push(file);
    }
  }
};

const onFileSelected = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files) {
    addFiles(Array.from(target.files));
  }
  target.value = '';
};

const onDropFiles = (event: DragEvent) => {
  isDragging.value = false;
  if (event.dataTransfer?.files) {
    addFiles(Array.from(event.dataTransfer.files));
  }
};

const removeFile = (index: number) => {
  evidenceFiles.value.splice(index, 1);
};

const getFileIcon = (filename: string): string => {
  const ext = filename.split('.').pop()?.toLowerCase() || '';
  if (['png', 'jpg', 'jpeg', 'webp'].includes(ext)) return 'bi-file-earmark-image';
  if (['pdf'].includes(ext)) return 'bi-file-earmark-pdf';
  if (['doc', 'docx'].includes(ext)) return 'bi-file-earmark-word';
  return 'bi-file-earmark-text';
};

// Wizard navigation and validation
const canContinue = computed(() => {
  if (step.value === 0) {
    return selectedUser.value !== null;
  }
  if (step.value === 1) {
    return (
      category.value.length > 0 &&
      subject.value.trim().length >= 3 &&
      description.value.trim().length >= 10
    );
  }
  return true;
});

const canSubmit = computed(() => {
  return (
    selectedUser.value !== null &&
    subject.value.trim().length >= 3 &&
    description.value.trim().length >= 10 &&
    !isSubmitting.value
  );
});

const nextStep = () => {
  formError.value = '';
  if (canContinue.value && step.value < STEPS.length - 1) {
    step.value++;
  }
};

const prevStep = () => {
  formError.value = '';
  if (step.value > 0) {
    step.value--;
  }
};

const submitReport = async () => {
  if (!canSubmit.value || !selectedUser.value) return;

  const confirm = await Swal.fire({
    title: 'Submit Misconduct Report?',
    text: 'Your report will be reviewed confidentially by Super Administrators. The reported member receives zero identification details.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Yes, Submit Report',
  });

  if (!confirm.isConfirmed) return;

  isSubmitting.value = true;
  formError.value = '';

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

    const res = await internalReportService.submitReport(formData);
    submittedReport.value = res;
  } catch (err: any) {
    const message =
      err.response?.data?.message ||
      err.response?.data?.errors?.reported_user_id?.[0] ||
      err.response?.data?.errors?.category?.[0] ||
      err.response?.data?.errors?.subject?.[0] ||
      err.response?.data?.errors?.description?.[0] ||
      'Failed to submit report. Please review your entries and try again.';
    formError.value = message;
    Swal.fire({
      icon: 'error',
      title: 'Submission Error',
      text: message,
    });
  } finally {
    isSubmitting.value = false;
  }
};

const resetForm = () => {
  submittedReport.value = null;
  selectedUser.value = null;
  searchQuery.value = '';
  searchResults.value = [];
  category.value = 'Identity & Profile Fraud';
  severity.value = 'medium';
  subject.value = '';
  description.value = '';
  evidenceFiles.value = [];
  formError.value = '';
  step.value = 0;
};
</script>

<style scoped>
.internal-report-wizard {
  margin-bottom: 14px;
}

.panel {
  margin-bottom: 14px;
  padding: 18px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

.panel-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 6px;
  margin-bottom: 10px;
}

.panel-title {
  margin: 0 0 4px;
  color: var(--ds-text);
  font-size: 16px;
  font-weight: 800;
}

.step-count {
  color: var(--ds-primary);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.progress-track {
  height: 6px;
  overflow: hidden;
  background: var(--ds-surface-muted);
  border-radius: 999px;
}

.progress-track span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--ds-primary-300), var(--ds-primary));
  border-radius: 999px;
  transition: width 0.3s ease;
}

.step-list {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  margin: 12px 0 18px;
  padding: 0;
  list-style: none;
}

.step-list li {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  color: var(--ds-text-subtle);
  font-size: 12px;
  font-weight: 600;
  text-align: center;
}

.step-dot {
  display: grid;
  width: 30px;
  height: 30px;
  font-size: 13px;
  place-items: center;
  background: var(--ds-surface-muted);
  border-radius: 50%;
}

.step-list li.current {
  color: var(--ds-primary-hover);
}

.step-list li.current .step-dot {
  color: #fff;
  background: var(--ds-primary);
  box-shadow: 0 0 0 4px var(--ds-primary-100);
}

.step-list li.done {
  color: var(--ds-success-text);
}

.step-list li.done .step-dot {
  color: #fff;
  background: var(--ds-success);
}

.step-body {
  min-height: 140px;
}

.field {
  display: grid;
  gap: 8px;
}

.field-label {
  color: var(--ds-text);
  font-size: 14px;
  font-weight: 700;
}

.required-star {
  color: var(--ds-danger-text);
}

.field-hint {
  margin: 0;
  color: var(--ds-text-muted);
  font-size: 12.5px;
  line-height: 1.4;
}

.field-error-text {
  margin: 4px 0 0;
  color: var(--ds-danger-text);
  font-size: 12px;
  font-weight: 600;
}

.field-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 2px;
}

.char-count {
  color: var(--ds-text-muted);
  font-size: 11.5px;
  font-weight: 600;
}

.char-count.limit {
  color: var(--ds-danger-text);
}

.form-select,
.form-input,
.form-textarea {
  width: 100%;
  padding: 9px 12px;
  font-size: 13.5px;
  color: var(--ds-text);
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 12px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-select:focus,
.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--ds-primary);
  box-shadow: 0 0 0 3px var(--ds-primary-100);
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
}

/* Search Box & Dropdown */
.search-box-wrapper {
  position: relative;
}

.search-input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: var(--ds-text-muted);
  font-size: 14px;
}

.search-input {
  width: 100%;
  padding: 10px 36px 10px 34px;
  font-size: 13.5px;
  color: var(--ds-text);
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 12px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: var(--ds-primary);
  box-shadow: 0 0 0 3px var(--ds-primary-100);
}

.search-spinner {
  position: absolute;
  right: 12px;
  width: 14px;
  height: 14px;
  border: 2px solid var(--ds-border);
  border-top-color: var(--ds-primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

.search-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 25;
  margin-top: 4px;
  max-height: 240px;
  overflow-y: auto;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.1);
}

.search-result-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid var(--ds-surface-muted);
  transition: background-color 0.15s ease;
}

.search-result-item:last-child {
  border-bottom: 0;
}

.search-result-item:hover {
  background: var(--ds-primary-soft);
}

.user-avatar-wrap {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
}

.user-avatar-wrap.sm {
  width: 26px;
  height: 26px;
}

.user-avatar {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-avatar-initials {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--ds-primary-soft);
  color: var(--ds-primary);
  font-weight: 700;
  font-size: 13px;
}

.user-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.user-name {
  color: var(--ds-text);
  font-size: 13.5px;
  font-weight: 650;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-username {
  color: var(--ds-text-muted);
  font-size: 12px;
}

.badge-jury-panel {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  font-size: 10.5px;
  font-weight: 700;
  color: #7c3aed;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  border-radius: 999px;
  flex-shrink: 0;
}

.badge-jury-panel.inline {
  margin-top: 3px;
  align-self: flex-start;
}

/* Selected User Card */
.selected-member-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  background: var(--ds-primary-soft);
  border: 1px solid var(--ds-primary-200);
  border-radius: 14px;
}

.selected-member-info {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.user-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.selected-user-name {
  color: var(--ds-text);
  font-size: 14px;
  font-weight: 750;
}

.selected-user-handle {
  color: var(--ds-text-muted);
  font-size: 12px;
}

.btn-change-user {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 650;
  color: var(--ds-danger-text);
  background: #fff;
  border: 1px solid var(--ds-danger-border);
  border-radius: 8px;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-change-user:hover {
  background: var(--ds-danger-soft);
}

.info-notice-box {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  font-size: 12.5px;
  color: #6b21a8;
  background: #faf5ff;
  border: 1px solid #e9d5ff;
  border-radius: 10px;
}

/* Severity Grid */
.severity-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.severity-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px 6px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.severity-card i {
  font-size: 15px;
}

.severity-title {
  font-size: 12px;
  font-weight: 700;
}

.severity-card.active.low {
  color: #0369a1;
  background: #f0f9ff;
  border-color: #7dd3fc;
  box-shadow: 0 0 0 2px #e0f2fe;
}

.severity-card.active.medium {
  color: #2563eb;
  background: #eff6ff;
  border-color: #93c5fd;
  box-shadow: 0 0 0 2px #dbeafe;
}

.severity-card.active.high {
  color: #c2410c;
  background: #fff7ed;
  border-color: #fdba74;
  box-shadow: 0 0 0 2px #ffedd5;
}

.severity-card.active.critical {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fca5a5;
  box-shadow: 0 0 0 2px #fee2e2;
}

/* Evidence Dropzone & Files */
.field-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.file-count-badge {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--ds-text-muted);
}

.dropzone-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  background: #fcfcfd;
  border: 2px dashed var(--ds-border);
  border-radius: 14px;
  cursor: pointer;
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.dropzone-area:hover,
.dropzone-area.dragging {
  background: var(--ds-primary-soft);
  border-color: var(--ds-primary);
}

.dropzone-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  text-align: center;
}

.dropzone-icon {
  font-size: 28px;
  color: var(--ds-primary);
  margin-bottom: 6px;
}

.dropzone-text {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ds-primary);
}

.dropzone-formats {
  font-size: 11.5px;
  color: var(--ds-text-muted);
  margin-top: 3px;
}

.uploaded-files-list {
  display: grid;
  gap: 6px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
}

.uploaded-file-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 12px;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 10px;
}

.file-info-group {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.file-info-group i {
  color: var(--ds-primary);
  font-size: 15px;
  flex-shrink: 0;
}

.file-name {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ds-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.file-size {
  font-size: 11.5px;
  color: var(--ds-text-muted);
  flex-shrink: 0;
}

.btn-remove-file {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--ds-danger-text);
  background: none;
  border: 0;
  cursor: pointer;
  transition: color 0.15s ease;
}

.btn-remove-file:hover {
  text-decoration: underline;
}

/* Review List */
.review-list {
  display: grid;
  gap: 8px;
  margin: 0;
}

.review-list > div {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 10px;
  padding: 10px 12px;
  background: var(--ds-surface-subtle);
  border-radius: 12px;
  align-items: baseline;
}

.review-list dt {
  color: var(--ds-text-muted);
  font-size: 12.5px;
  font-weight: 700;
}

.review-list dd {
  margin: 0;
  color: var(--ds-text);
  font-size: 13.5px;
  font-weight: 500;
}

.review-member-dd {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.severity-badge {
  display: inline-block;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 800;
  border-radius: 999px;
}

.severity-badge.low {
  color: #0369a1;
  background: #e0f2fe;
}

.severity-badge.medium {
  color: #1d4ed8;
  background: #dbeafe;
}

.severity-badge.high {
  color: #c2410c;
  background: #ffedd5;
}

.severity-badge.critical {
  color: #b91c1c;
  background: #fee2e2;
}

.description-text {
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.5;
  font-size: 13px !important;
}

.review-evidence-list {
  margin: 0;
  padding-left: 18px;
  font-size: 12.5px;
}

/* Confidentiality Notice Banner */
.confidentiality-notice {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 14px;
  font-size: 12.5px;
  line-height: 1.45;
  color: #1e3a8a;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
}

.confidentiality-notice i {
  color: var(--ds-primary);
  font-size: 16px;
  margin-top: 1px;
  flex-shrink: 0;
}

/* Success Panel */
.success-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 28px 20px;
}

.success-icon {
  display: grid;
  width: 58px;
  height: 58px;
  font-size: 28px;
  color: #fff;
  place-items: center;
  background: var(--ds-success);
  border-radius: 50%;
  margin-bottom: 12px;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
}

.success-report-num {
  display: inline-block;
  padding: 4px 12px;
  margin: 8px 0;
  font-size: 13px;
  color: var(--ds-primary-hover);
  background: var(--ds-primary-soft);
  border: 1px solid var(--ds-primary-200);
  border-radius: 999px;
}

.success-text {
  max-width: 520px;
  margin: 6px auto 14px;
  color: var(--ds-text-muted);
  font-size: 13.5px;
  line-height: 1.55;
}

.success-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
  margin-top: 18px;
}

/* Alerts */
.alert {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 14px 0 0;
  padding: 10px 12px;
  font-size: 13px;
  border-radius: 10px;
}

.alert.error {
  color: var(--ds-danger-text);
  background: var(--ds-danger-soft);
  border: 1px solid var(--ds-danger-border);
}

/* Navigation Buttons */
.form-nav {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-top: 18px;
  padding-top: 14px;
  border-top: 1px solid var(--ds-surface-muted);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 42px;
  padding: 9px 18px;
  font-size: 13.5px;
  font-weight: 600;
  border-radius: 12px;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;
}

.btn:focus-visible {
  outline: 2px solid var(--ds-primary);
  outline-offset: 2px;
}

.btn.primary {
  color: #fff;
  background: var(--ds-primary);
  border: 0;
  box-shadow: var(--ds-shadow-sm);
}

.btn.ghost {
  color: var(--ds-text-secondary);
  background: #fff;
  border: 1px solid var(--ds-border);
}

.btn.primary:hover:not(:disabled) {
  background: var(--ds-primary-hover);
}

.btn.ghost:hover:not(:disabled) {
  color: var(--ds-primary-hover);
  background: var(--ds-primary-soft);
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.45;
  box-shadow: none;
}

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #ffffff70;
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 575px) {
  .panel {
    padding: 16px 14px;
  }
  .step-name {
    font-size: 10.5px;
  }
  .review-list > div {
    grid-template-columns: 1fr;
    gap: 3px;
  }
  .severity-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .success-actions {
    flex-direction: column;
    width: 100%;
  }
  .success-actions .btn {
    width: 100%;
  }
}
</style>
