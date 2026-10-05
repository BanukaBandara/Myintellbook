<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import { useTribunalStore } from '@/stores/tribunal';
import type { ProfessionalVerification } from '@/types/tribunal';

const router = useRouter();
const tribunalStore = useTribunalStore();

const verification = ref<ProfessionalVerification | null>(null);
const loading = ref(true);
const submitting = ref(false);
const isReapplying = ref(false);

// Form Fields
const form = ref({
  profession_type: 'attorney_at_law',
  registration_number: '',
  enrollment_number: '',
  issuing_authority: '',
  years_of_experience: 3,
});

const qualificationDoc = ref<File | null>(null);
const identityDoc = ref<File | null>(null);
const additionalDoc = ref<File | null>(null);

const onFileChange = (e: Event, type: 'qualification' | 'identity' | 'additional') => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    const file = target.files[0];
    if (file.size > 10 * 1024 * 1024) {
      Swal.fire({
        icon: 'warning',
        title: 'File Too Large',
        text: 'Maximum file size allowed is 10 MB.',
      });
      target.value = '';
      return;
    }
    if (type === 'qualification') qualificationDoc.value = file;
    if (type === 'identity') identityDoc.value = file;
    if (type === 'additional') additionalDoc.value = file;
  }
};

const loadData = async () => {
  loading.value = true;
  try {
    const data = await tribunalStore.fetchMyVerification();
    verification.value = data;
    await tribunalStore.fetchCapabilities();
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadData();
});

const submitApplication = async () => {
  if (!qualificationDoc.value || !identityDoc.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Required Documents',
      text: 'Please upload both your Qualification Document and Identity Document.',
    });
    return;
  }

  if (!form.value.issuing_authority.trim()) {
    await Swal.fire({
      icon: 'warning',
      title: 'Missing Field',
      text: 'Please specify the Issuing Authority (e.g. Bar Association, Supreme Court).',
    });
    return;
  }

  const formData = new FormData();
  formData.append('profession_type', form.value.profession_type);
  formData.append('issuing_authority', form.value.issuing_authority.trim());
  formData.append('years_of_experience', form.value.years_of_experience.toString());

  if (form.value.registration_number.trim()) {
    formData.append('registration_number', form.value.registration_number.trim());
  }
  if (form.value.enrollment_number.trim()) {
    formData.append('enrollment_number', form.value.enrollment_number.trim());
  }

  formData.append('qualification_document', qualificationDoc.value);
  formData.append('identity_document', identityDoc.value);
  if (additionalDoc.value) {
    formData.append('additional_document', additionalDoc.value);
  }

  submitting.value = true;
  const res = await tribunalStore.submitVerificationApplication(formData);
  submitting.value = false;

  if (res) {
    await Swal.fire({
      icon: 'success',
      title: 'Application Submitted',
      text: 'Your professional verification application has been submitted to the Tribunal Review Committee.',
    });
    isReapplying.value = false;
    await loadData();
  } else {
    await Swal.fire({
      icon: 'error',
      title: 'Submission Failed',
      text: tribunalStore.error ?? 'Could not submit verification application.',
    });
  }
};

const formatDate = (dateStr?: string | null): string => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};
</script>

<template>
  <div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
      <div>
        <h2 class="fw-bold mb-1 text-primary">
          <i class="bi bi-patch-check-fill me-2" />Professional Verification
        </h2>
        <p class="text-muted mb-0">
          Verify your legal credentials to become eligible for appointments as a Tribunal Adjudicator or Authorized Legal Representative.
        </p>
      </div>
      <div>
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill px-3 me-2"
          @click="router.push('/tribunal/cases')"
        >
          <i class="bi bi-briefcase me-1" />
          My Disputes
        </button>
        <button
          v-if="tribunalStore.capabilities?.adjudicator?.eligible"
          type="button"
          class="btn btn-outline-primary rounded-pill px-3"
          @click="router.push('/tribunal/jury')"
        >
          <i class="bi bi-bank me-1" />
          Adjudicator Portal
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5 bg-white rounded-4 shadow-sm">
      <div class="spinner-border text-primary" role="status" />
      <p class="mt-3 text-muted">
        Checking professional verification status...
      </p>
    </div>

    <!-- CASE A: Existing Verified Professional -->
    <div v-else-if="verification && verification.verification_status === 'verified' && !isReapplying">
      <div class="row g-4">
        <!-- Verification Card -->
        <div class="col-12 col-lg-8">
          <div class="card border-0 rounded-4 shadow-sm p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
              <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold fs-6">
                  <i class="bi bi-check-circle-fill me-1" />
                  {{ verification.badge_title || 'Verified Legal Professional' }}
                </span>
              </div>
              <div class="text-muted small">
                Verified: <span class="fw-semibold text-dark">{{ formatDate(verification.verified_at) }}</span>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-sm-6">
                <div class="text-muted small">
                  Profession Type
                </div>
                <div class="fw-bold fs-6 text-dark">
                  {{ verification.profession_label }}
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">
                  Issuing Authority
                </div>
                <div class="fw-bold fs-6 text-dark">
                  {{ verification.issuing_authority }}
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">
                  Registration Number
                </div>
                <div class="font-monospace fw-bold text-dark">
                  {{ verification.masked_registration_number || 'N/A' }}
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">
                  Enrollment Number
                </div>
                <div class="font-monospace fw-bold text-dark">
                  {{ verification.masked_enrollment_number || 'N/A' }}
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">
                  Years of Practice
                </div>
                <div class="fw-bold text-dark">
                  {{ verification.years_of_experience }} Years
                </div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">
                  Expiration
                </div>
                <div class="fw-bold" :class="verification.is_expired ? 'text-danger' : 'text-success'">
                  {{ verification.expires_at ? formatDate(verification.expires_at) : 'Active / Permanent' }}
                </div>
              </div>
            </div>

            <div class="alert alert-info border-0 rounded-3 mb-0 d-flex align-items-center gap-2">
              <i class="bi bi-shield-lock-fill fs-4 text-primary" />
              <div class="small">
                Your credentials are cryptographically secured on private storage. Masked numbers are used for external badge displays.
              </div>
            </div>
          </div>
        </div>

        <!-- Adjudicator Status Side Card -->
        <div class="col-12 col-lg-4">
          <div class="card border-0 rounded-4 shadow-sm p-4 h-100 bg-light">
            <h5 class="fw-bold mb-3 text-dark">
              <i class="bi bi-award me-1" />Tribunal Eligibility
            </h5>

            <div class="mb-3">
              <div class="text-muted small">
                Adjudicator Status
              </div>
              <span
                v-if="verification.adjudicator_profile?.status === 'eligible'"
                class="badge bg-success px-3 py-2 rounded-pill mt-1"
              >
                Eligible Adjudicator
              </span>
              <span
                v-else-if="verification.adjudicator_profile?.status === 'suspended'"
                class="badge bg-danger px-3 py-2 rounded-pill mt-1"
              >
                Suspended
              </span>
              <span
                v-else
                class="badge bg-warning text-dark px-3 py-2 rounded-pill mt-1"
              >
                {{ verification.adjudicator_profile?.status || 'Pending Qualification' }}
              </span>
            </div>

            <div class="mb-3">
              <div class="text-muted small">
                Tribunal Qualification
              </div>
              <div class="fw-semibold text-dark mt-1">
                {{ verification.adjudicator_profile?.qualification_status || 'Pending' }}
                <span v-if="verification.adjudicator_profile?.qualification_score">
                  ({{ verification.adjudicator_profile?.qualification_score }}%)
                </span>
              </div>
            </div>

            <div class="mb-4">
              <div class="text-muted small">
                Availability for Selection
              </div>
              <div class="fw-semibold text-dark mt-1">
                <span v-if="verification.adjudicator_profile?.available" class="text-success">
                  <i class="bi bi-circle-fill me-1 small" />Available for Selection
                </span>
                <span v-else class="text-muted">
                  <i class="bi bi-dash-circle me-1" />Currently Unavailable
                </span>
              </div>
            </div>

            <button
              v-if="verification.adjudicator_profile?.is_eligible"
              type="button"
              class="btn btn-primary rounded-pill w-100 mt-auto"
              @click="router.push('/tribunal/jury')"
            >
              <i class="bi bi-bank me-1" />
              Open Adjudicator Portal
            </button>
            <div v-else class="text-muted small mt-auto">
              Once Tribunal qualification is passed, your profile will be placed into the active adjudicator selection pool.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CASE B: Application Pending or Under Review -->
    <div v-else-if="verification && (verification.verification_status === 'pending' || verification.verification_status === 'under_review') && !isReapplying">
      <div class="card border-0 rounded-4 shadow-sm p-5 text-center max-w-700 mx-auto">
        <div class="mb-4">
          <div class="spinner-grow text-warning" style="width: 3rem; height: 3rem;" role="status" />
        </div>
        <h4 class="fw-bold mb-2 text-dark">
          Verification Application Under Review
        </h4>
        <p class="text-muted mb-4">
          Your credentials as a <strong>{{ verification.profession_label }}</strong> were submitted on
          <strong>{{ formatDate(verification.submitted_at) }}</strong>. Our Tribunal Review Committee is verifying your submitted documents with {{ verification.issuing_authority }}.
        </p>

        <div class="bg-light p-3 rounded-4 mb-4 text-start">
          <div class="row g-2 small">
            <div class="col-sm-6">
              <span class="text-muted">Status:</span>
              <span class="badge bg-warning text-dark ms-2 text-uppercase">{{ verification.verification_status }}</span>
            </div>
            <div class="col-sm-6">
              <span class="text-muted">Issuing Authority:</span>
              <span class="fw-bold text-dark ms-1">{{ verification.issuing_authority }}</span>
            </div>
            <div class="col-sm-6">
              <span class="text-muted">Registration Number:</span>
              <span class="font-monospace ms-1">{{ verification.masked_registration_number || 'N/A' }}</span>
            </div>
            <div class="col-sm-6">
              <span class="text-muted">Years of Experience:</span>
              <span class="fw-bold ms-1">{{ verification.years_of_experience }} Years</span>
            </div>
          </div>
        </div>

        <p class="text-muted small mb-0">
          You will receive an in-app notification as soon as a decision is made.
        </p>
      </div>
    </div>

    <!-- CASE C: Application Rejected -->
    <div v-else-if="verification && verification.verification_status === 'rejected' && !isReapplying">
      <div class="card border-0 rounded-4 shadow-sm p-4 border-start border-danger border-4">
        <div class="d-flex align-items-start gap-3">
          <i class="bi bi-x-circle-fill text-danger fs-1" />
          <div class="flex-grow-1">
            <h4 class="fw-bold text-danger mb-1">
              Professional Verification Rejected
            </h4>
            <p class="text-muted mb-3">
              Your application could not be verified by the Tribunal Review Committee.
            </p>
            <div class="alert alert-danger border-0 rounded-3 mb-4">
              <strong>Reason for Rejection:</strong>
              <div class="mt-1">
                {{ verification.rejection_reason || 'Insufficient or unverified credentials provided.' }}
              </div>
            </div>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4"
              @click="isReapplying = true"
            >
              <i class="bi bi-arrow-repeat me-1" />
              Re-apply with Corrected Credentials
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- CASE D: Suspended -->
    <div v-else-if="verification && verification.verification_status === 'suspended' && !isReapplying">
      <div class="card border-0 rounded-4 shadow-sm p-4 border-start border-danger border-4">
        <div class="d-flex align-items-start gap-3">
          <i class="bi bi-slash-circle-fill text-danger fs-1" />
          <div>
            <h4 class="fw-bold text-danger mb-1">
              Professional Verification Suspended
            </h4>
            <p class="text-muted mb-3">
              Your verified legal professional status has been temporarily suspended.
            </p>
            <div class="alert alert-danger border-0 rounded-3 mb-3">
              <strong>Suspension Reason:</strong>
              <div class="mt-1">
                {{ verification.suspension_reason || 'Administrative or regulatory review.' }}
              </div>
            </div>
            <p class="small text-muted mb-0">
              Please contact the Tribunal Administration if you have questions or wish to present updated documentation.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- CASE E: No Application Yet OR User is Reapplying (FORM) -->
    <div v-else>
      <div class="card border-0 rounded-4 shadow-sm p-4 max-w-800 mx-auto">
        <div class="mb-4 pb-3 border-bottom">
          <h4 class="fw-bold text-dark mb-1">
            Apply for Professional Verification
          </h4>
          <p class="text-muted mb-0">
            Submit your authentic legal credentials for Tribunal verification. All uploaded documents are kept on private storage and accessed exclusively by authorized administrators.
          </p>
        </div>

        <form @submit.prevent="submitApplication">
          <!-- Profession Type -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Profession Type <span class="text-danger">*</span></label>
            <select v-model="form.profession_type" class="form-select rounded-3" required>
              <option value="attorney_at_law">
                Attorney-at-Law (Adjudicator & Representative Eligible)
              </option>
              <option value="judge">
                Judge / Judicial Officer (Adjudicator Eligible)
              </option>
              <option value="legal_officer">
                Legal Officer (Adjudicator Eligible upon approval)
              </option>
              <option value="mediator">
                Certified Mediator
              </option>
              <option value="other_legal_professional">
                Other Legal Professional
              </option>
            </select>
          </div>

          <div class="row g-3 mb-3">
            <!-- Registration Number -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Bar / Registration Number</label>
              <input
                v-model="form.registration_number"
                type="text"
                class="form-control rounded-3"
                placeholder="e.g. BAR/2020/12345"
              >
            </div>
            <!-- Enrollment Number -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Enrollment / License Number</label>
              <input
                v-model="form.enrollment_number"
                type="text"
                class="form-control rounded-3"
                placeholder="e.g. ENR-99881"
              >
            </div>
          </div>

          <div class="row g-3 mb-4">
            <!-- Issuing Authority -->
            <div class="col-md-8">
              <label class="form-label fw-semibold">Issuing Authority <span class="text-danger">*</span></label>
              <input
                v-model="form.issuing_authority"
                type="text"
                class="form-control rounded-3"
                placeholder="e.g. Supreme Court Bar Council / State Licensing Board"
                required
              >
            </div>
            <!-- Years of Experience -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Years of Practice <span class="text-danger">*</span></label>
              <input
                v-model.number="form.years_of_experience"
                type="number"
                min="0"
                max="75"
                class="form-control rounded-3"
                required
              >
            </div>
          </div>

          <!-- Document Uploads -->
          <div class="mb-4">
            <h6 class="fw-bold text-dark mb-3">
              <i class="bi bi-file-earmark-lock-fill me-1 text-primary" />Confidential Verification Documents
            </h6>

            <!-- Qualification Document -->
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Qualification Document (Bar Certificate / Degree) <span class="text-danger">*</span>
              </label>
              <input
                type="file"
                class="form-control rounded-3"
                accept=".pdf,.png,.jpg,.jpeg"
                required
                @change="(e) => onFileChange(e, 'qualification')"
              >
              <div class="form-text">
                Accepts PDF, PNG, JPG up to 10 MB.
              </div>
            </div>

            <!-- Identity Document -->
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Identity Document (National ID / Passport / Bar Card) <span class="text-danger">*</span>
              </label>
              <input
                type="file"
                class="form-control rounded-3"
                accept=".pdf,.png,.jpg,.jpeg"
                required
                @change="(e) => onFileChange(e, 'identity')"
              >
              <div class="form-text">
                Accepts PDF, PNG, JPG up to 10 MB.
              </div>
            </div>

            <!-- Additional Document -->
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Additional Supporting Document (Optional)
              </label>
              <input
                type="file"
                class="form-control rounded-3"
                accept=".pdf,.png,.jpg,.jpeg"
                @change="(e) => onFileChange(e, 'additional')"
              >
              <div class="form-text">
                Recommendation letter, certificate of good standing, etc.
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="d-flex justify-content-between align-items-center pt-2">
            <button
              v-if="isReapplying"
              type="button"
              class="btn btn-outline-secondary rounded-pill px-4"
              @click="isReapplying = false"
            >
              Cancel
            </button>
            <div v-else />

            <button
              type="submit"
              class="btn btn-primary rounded-pill px-5 py-2 fw-semibold"
              :disabled="submitting"
            >
              <span v-if="submitting" class="spinner-border spinner-border-sm me-1" role="status" />
              Submit Application
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.max-w-700 {
  max-width: 700px;
}
.max-w-800 {
  max-width: 800px;
}
</style>
