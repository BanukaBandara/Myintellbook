<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { useTribunalStore } from '@/stores/tribunal';
import TribunalRespondentSearch from '@/components/tribunal/TribunalRespondentSearch.vue';

const router = useRouter();
const tribunalStore = useTribunalStore();

const respondentId = ref<number | null>(null);
const title = ref('');
const category = ref('');
const description = ref('');
const requestedResolution = ref('');

const categories = [
  'Harassment',
  'Defamation',
  'Platform Misconduct',
  'Professional Dispute',
  'Content Dispute',
  'Privacy Complaint',
  'Other',
];

const canSubmit = computed(() => {
  return (
    respondentId.value !== null &&
    title.value.trim().length > 0 &&
    category.value.length > 0 &&
    description.value.trim().length >= 20 &&
    !tribunalStore.loading
  );
});

const submitCase = async () => {
  if (!canSubmit.value || respondentId.value === null) {
    return;
  }

  const createdCase = await tribunalStore.createCase({
    respondent_id: respondentId.value,
    title: title.value.trim(),
    category: category.value,
    description: description.value.trim(),
    requested_resolution:
      requestedResolution.value.trim() || null,
  });

  if (!createdCase) {
    await Swal.fire({
      icon: 'error',
      title: 'Submission Failed',
      text:
        tribunalStore.error ??
        'Unable to submit the tribunal case.',
    });

    return;
  }

  await Swal.fire({
    icon: 'success',
    title: 'Case Submitted',
    text: `Your case number is ${createdCase.case_number}`,
  });

  router.push('/tribunal/cases');
};
</script>

<template>
  <div class="container py-4">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h2 class="mb-2">
          Submit an External Tribunal Case
        </h2>

        <p class="text-muted mb-4">
          Provide the basic details of your dispute.
          Evidence and representatives can be added once the case is initialized.
        </p>

        <!-- 1. RESPONDENT SELECTION -->
        <div class="mb-4">
          <label class="form-label fw-semibold">
            1. Respondent <span class="text-danger">*</span>
          </label>
          <TribunalRespondentSearch
            v-model="respondentId"
            :disabled="tribunalStore.loading"
          />
        </div>

        <!-- 2. CASE CATEGORY -->
        <div class="mb-4">
          <label class="form-label fw-semibold">
            2. Case Category <span class="text-danger">*</span>
          </label>

          <select
            v-model="category"
            class="form-select"
            :disabled="tribunalStore.loading"
          >
            <option
              value=""
              disabled
            >
              Select category
            </option>

            <option
              v-for="item in categories"
              :key="item"
              :value="item"
            >
              {{ item }}
            </option>
          </select>
        </div>

        <!-- 3. CASE TITLE -->
        <div class="mb-4">
          <label class="form-label fw-semibold">
            3. Case Title <span class="text-danger">*</span>
          </label>

          <input
            v-model="title"
            type="text"
            maxlength="180"
            class="form-control"
            placeholder="Enter a short, descriptive title for the case"
            :disabled="tribunalStore.loading"
          />
        </div>

        <!-- 4. DESCRIPTION -->
        <div class="mb-4">
          <label class="form-label fw-semibold">
            4. Description <span class="text-danger">*</span>
          </label>

          <textarea
            v-model="description"
            rows="6"
            maxlength="10000"
            class="form-control"
            placeholder="Explain what happened in detail..."
            :disabled="tribunalStore.loading"
          />

          <div class="d-flex justify-content-between align-items-center mt-1">
            <small :class="description.trim().length >= 20 ? 'text-success' : 'text-muted'">
              {{ description.trim().length < 20 ? 'Minimum 20 characters required.' : 'Minimum length met.' }}
            </small>
            <small class="text-muted">
              {{ description.trim().length }} / 10000
            </small>
          </div>
        </div>

        <!-- 5. REQUESTED RESOLUTION -->
        <div class="mb-4">
          <label class="form-label fw-semibold">
            5. Requested Resolution <span class="text-muted fw-normal">(Optional)</span>
          </label>

          <textarea
            v-model="requestedResolution"
            rows="3"
            maxlength="3000"
            class="form-control"
            placeholder="What outcome or remedy are you seeking from the Tribunal?"
            :disabled="tribunalStore.loading"
          />
        </div>

        <!-- 6. ERROR & SUBMISSION -->
        <div
          v-if="tribunalStore.error"
          class="alert alert-danger"
        >
          {{ tribunalStore.error }}
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
          <button
            type="button"
            class="btn btn-outline-secondary"
            :disabled="tribunalStore.loading"
            @click="router.push('/tribunal/cases')"
          >
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-primary px-4"
            :disabled="!canSubmit"
            @click="submitCase"
          >
            <span
              v-if="tribunalStore.loading"
              class="spinner-border spinner-border-sm me-2"
            />

            {{
              tribunalStore.loading
                ? 'Submitting...'
                : 'Submit Case'
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.card {
  max-width: 850px;
  margin: 0 auto;
}
</style>
