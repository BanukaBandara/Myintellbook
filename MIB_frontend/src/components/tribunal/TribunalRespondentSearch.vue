<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import type { TribunalRespondentSearchResult } from '@/types/tribunal';
import { tribunalService } from '@/services/tribunalService';
import userPng from '@/assets/user.png';

interface Props {
  modelValue?: number | null;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  disabled: false,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void;
  (e: 'select', user: TribunalRespondentSearchResult | null): void;
}>();

const searchQuery = ref('');
const results = ref<TribunalRespondentSearchResult[]>([]);
const isLoading = ref(false);
const hasSearched = ref(false);
const errorMessage = ref<string | null>(null);
const selectedUser = ref<TribunalRespondentSearchResult | null>(null);

let debounceTimeout: ReturnType<typeof setTimeout> | null = null;

const onImageError = (event: Event) => {
  const target = event.target as HTMLImageElement;
  if (target && target.src !== userPng) {
    target.src = userPng;
  }
};

const handleInput = () => {
  if (debounceTimeout) {
    clearTimeout(debounceTimeout);
  }

  const query = searchQuery.value.trim();

  if (query.length < 3) {
    results.value = [];
    hasSearched.value = false;
    errorMessage.value = null;
    isLoading.value = false;
    return;
  }

  debounceTimeout = setTimeout(() => {
    executeSearch(query);
  }, 350);
};

const executeSearch = async (query: string) => {
  if (query.length < 3) {
    return;
  }

  isLoading.value = true;
  errorMessage.value = null;

  try {
    const response = await tribunalService.searchRespondents(query);
    results.value = response.data || [];
    hasSearched.value = true;
  } catch (err: any) {
    console.error('Respondent search failed:', err);
    errorMessage.value = 'Unable to search users. Please try again.';
    results.value = [];
    hasSearched.value = false;
  } finally {
    isLoading.value = false;
  }
};

const selectUser = (user: TribunalRespondentSearchResult) => {
  selectedUser.value = user;
  emit('update:modelValue', user.id);
  emit('select', user);
  searchQuery.value = '';
  results.value = [];
  hasSearched.value = false;
};

const clearSelection = () => {
  selectedUser.value = null;
  emit('update:modelValue', null);
  emit('select', null);
  searchQuery.value = '';
  results.value = [];
  hasSearched.value = false;
  errorMessage.value = null;
};

const clearQuery = () => {
  searchQuery.value = '';
  results.value = [];
  hasSearched.value = false;
  errorMessage.value = null;
};

// If external modelValue changes to null/empty, clear local selectedUser
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal === null && selectedUser.value !== null) {
      selectedUser.value = null;
    }
  }
);

onBeforeUnmount(() => {
  if (debounceTimeout) {
    clearTimeout(debounceTimeout);
  }
});
</script>

<template>
  <div class="tribunal-respondent-search">
    <!-- 1. SELECTED RESPONDENT CARD -->
    <div v-if="selectedUser" class="card border-primary bg-primary-subtle bg-opacity-10 shadow-sm">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-3">
            <img
              :src="selectedUser.profile_photo_url || userPng"
              :alt="selectedUser.name"
              class="rounded-circle border object-fit-cover shadow-sm respondent-avatar"
              @error="onImageError"
            />
            <div>
              <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-dark">{{ selectedUser.name }}</h6>
                <span v-if="selectedUser.is_verified_lawyer" class="badge bg-primary">
                  Verified Attorney
                </span>
              </div>
              <div class="text-muted small">
                <span>@{{ selectedUser.username }}</span>
                <span class="mx-1">•</span>
                <span>{{ selectedUser.public_subtitle }}</span>
              </div>
              <div class="text-success small fw-medium mt-1">
                <i class="bi bi-check-circle-fill me-1"></i> Selected as Respondent
              </div>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <a
              v-if="selectedUser.profile_url"
              :href="`/showUserProfile/${selectedUser.profile_url}`"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-outline-secondary btn-sm"
              title="Open profile in a new tab"
            >
              <i class="bi bi-person-bounding-box me-1"></i> View Profile
            </a>
            <button
              type="button"
              class="btn btn-outline-danger btn-sm"
              :disabled="disabled"
              @click="clearSelection"
            >
              <i class="bi bi-x-circle me-1"></i> Change Respondent
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. SEARCH INPUT & RESULTS (when no user is selected) -->
    <div v-else class="search-container">
      <div class="input-group">
        <span class="input-group-text bg-white text-muted border-end-0">
          <i class="bi bi-search"></i>
        </span>
        <input
          v-model="searchQuery"
          type="text"
          class="form-control border-start-0 border-end-0 ps-0"
          placeholder="Search respondent by name or username (min. 3 characters)..."
          :disabled="disabled"
          autocomplete="off"
          @input="handleInput"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="btn btn-outline-secondary border-start-0"
          :disabled="disabled"
          @click="clearQuery"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <!-- Helper / Guidance / Feedback state messages -->
      <div class="mt-2">
        <small v-if="!searchQuery && !hasSearched" class="text-muted d-block">
          <i class="bi bi-info-circle me-1"></i>
          Search for the user you want to file the case against.
        </small>

        <small
          v-else-if="searchQuery.trim().length > 0 && searchQuery.trim().length < 3"
          class="text-secondary d-block"
        >
          <i class="bi bi-chat-dots me-1"></i>
          Enter at least 3 characters.
        </small>

        <small v-else-if="isLoading" class="text-primary d-flex align-items-center gap-2">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          <span>Searching users...</span>
        </small>

        <div v-else-if="errorMessage" class="alert alert-danger py-2 px-3 small mt-2 mb-0" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          {{ errorMessage }}
        </div>

        <div
          v-else-if="hasSearched && results.length === 0"
          class="alert alert-light border py-2 px-3 small mt-2 mb-0 text-muted"
        >
          <i class="bi bi-search me-1"></i>
          No matching users found.
        </div>
      </div>

      <!-- SEARCH RESULT LIST -->
      <div v-if="results.length > 0 && !isLoading" class="results-list mt-3">
        <div class="text-muted small fw-semibold mb-2">Matching Profiles:</div>
        <div class="d-flex flex-column gap-2">
          <div
            v-for="user in results"
            :key="user.id"
            class="card result-card border shadow-sm hover-shadow"
          >
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <!-- User Information -->
                <div class="d-flex align-items-center gap-3">
                  <img
                    :src="user.profile_photo_url || userPng"
                    :alt="user.name"
                    class="rounded-circle border object-fit-cover respondent-avatar"
                    @error="onImageError"
                  />
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <span class="fw-semibold text-dark">{{ user.name }}</span>
                      <span v-if="user.is_verified_lawyer" class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        Lawyer
                      </span>
                    </div>
                    <div class="text-muted small">
                      <span>@{{ user.username }}</span>
                      <span class="mx-1">•</span>
                      <span>{{ user.public_subtitle }}</span>
                    </div>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex align-items-center gap-2 ms-auto">
                  <a
                    v-if="user.profile_url"
                    :href="`/showUserProfile/${user.profile_url}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-outline-secondary btn-sm"
                    title="View public profile in new tab"
                  >
                    View Profile
                  </a>
                  <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    :disabled="disabled"
                    @click="selectUser(user)"
                  >
                    Select Respondent
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tribunal-respondent-search {
  width: 100%;
}

.respondent-avatar {
  width: 48px;
  height: 48px;
  min-width: 48px;
}

.result-card {
  transition: all 0.2s ease-in-out;
}

.result-card:hover {
  border-color: #0d6efd !important;
  background-color: #f8faff;
}

.hover-shadow:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}
</style>
