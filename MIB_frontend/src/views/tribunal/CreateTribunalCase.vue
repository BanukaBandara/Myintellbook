<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { useTribunalStore } from '@/stores/tribunal';
import api from '@/assets/axios';

interface UserOption {
  id: number;
  name: string;
}

const router = useRouter();
const tribunalStore = useTribunalStore();

const users = ref<UserOption[]>([]);
const usersLoading = ref(false);

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

const loadUsers = async () => {
  usersLoading.value = true;

  try {
    const response = await api.get('/profile-list');

    users.value = response.data.data.map((user: any) => ({
      id: user.id,
      name:
        user.full_name ??
        `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim() ??
        `User #${user.id}`,
    }));
  } catch (error) {
    console.error(error);

    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Unable to load users.',
    });
  } finally {
    usersLoading.value = false;
  }
};

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

onMounted(() => {
  loadUsers();
});
</script>

<template>
  <div class="container py-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="mb-2">
          Submit an External Tribunal Case
        </h2>

        <p class="text-muted mb-4">
          Provide the basic details of your dispute.
          Evidence and representatives will be added later.
        </p>

        <div class="mb-3">
          <label class="form-label">
            Respondent
          </label>

          <select
            v-model="respondentId"
            class="form-select"
            :disabled="usersLoading"
          >
            <option
              :value="null"
              disabled
            >
              Select respondent
            </option>

            <option
              v-for="user in users"
              :key="user.id"
              :value="user.id"
            >
              {{ user.name }}
            </option>
          </select>

          <small
            v-if="usersLoading"
            class="text-muted"
          >
            Loading users...
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label">
            Case Category
          </label>

          <select
            v-model="category"
            class="form-select"
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

        <div class="mb-3">
          <label class="form-label">
            Case Title
          </label>

          <input
            v-model="title"
            type="text"
            maxlength="180"
            class="form-control"
            placeholder="Enter a short title for the case"
          />
        </div>

        <div class="mb-3">
          <label class="form-label">
            Description
          </label>

          <textarea
            v-model="description"
            rows="6"
            maxlength="10000"
            class="form-control"
            placeholder="Explain what happened..."
          />

          <small class="text-muted">
            Minimum 20 characters.
          </small>
        </div>

        <div class="mb-4">
          <label class="form-label">
            Requested Resolution
          </label>

          <textarea
            v-model="requestedResolution"
            rows="3"
            maxlength="3000"
            class="form-control"
            placeholder="What outcome are you requesting?"
          />
        </div>

        <div
          v-if="tribunalStore.error"
          class="alert alert-danger"
        >
          {{ tribunalStore.error }}
        </div>

        <button
          type="button"
          class="btn btn-primary"
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
</template>

<style scoped>
.card {
  max-width: 850px;
  margin: 0 auto;
}
</style>
