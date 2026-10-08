<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { tribunalService } from '@/services/tribunalService';

const route = useRoute();
const router = useRouter();

const verificationCodeInput = ref('');
const verifying = ref(false);
const verifiedResult = ref<any | null>(null);
const errorMessage = ref<string | null>(null);

// File hash check state
const activeMode = ref<'code' | 'file'>('code');
const selectedFile = ref<File | null>(null);
const fileVerifying = ref(false);
const fileResult = ref<any | null>(null);
const fileError = ref<string | null>(null);

const handleVerifyCode = async (codeToVerify?: string) => {
  const code = (codeToVerify || verificationCodeInput.value).trim();
  if (!code) {
    errorMessage.value = 'Please enter a valid Report Number or Verification Code.';
    return;
  }

  verifying.value = true;
  errorMessage.value = null;
  verifiedResult.value = null;

  try {
    const res = await tribunalService.verifyReportCode(code);
    verifiedResult.value = res;
    // Update input
    verificationCodeInput.value = code;
  } catch (err: any) {
    if (err?.response?.status === 404) {
      verifiedResult.value = {
        valid: false,
        message: 'INVALID OR UNVERIFIED REPORT',
        details: null,
      };
    } else {
      errorMessage.value = err?.response?.data?.message || 'Verification service encountered an error. Please try again.';
    }
  } finally {
    verifying.value = false;
  }
};

const handleFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0];
    fileResult.value = null;
    fileError.value = null;
  }
};

const handleVerifyFile = async () => {
  if (!selectedFile.value) {
    fileError.value = 'Please select a downloaded PDF report file to verify.';
    return;
  }

  fileVerifying.value = true;
  fileError.value = null;
  fileResult.value = null;

  try {
    const res = await tribunalService.verifyReportFile(selectedFile.value, verificationCodeInput.value || undefined);
    fileResult.value = res;
  } catch (err: any) {
    if (err?.response?.status === 422 && err?.response?.data?.valid_hash === false) {
      fileResult.value = err.response.data;
    } else {
      fileError.value = err?.response?.data?.message || 'File verification failed. Please ensure the file is an authentic PDF.';
    }
  } finally {
    fileVerifying.value = false;
  }
};

const clearVerification = () => {
  verificationCodeInput.value = '';
  verifiedResult.value = null;
  errorMessage.value = null;
  selectedFile.value = null;
  fileResult.value = null;
  fileError.value = null;
};

onMounted(() => {
  const codeParam = route.params.verificationCode as string;
  if (codeParam) {
    verificationCodeInput.value = codeParam;
    handleVerifyCode(codeParam);
  }
});

watch(
  () => route.params.verificationCode,
  (newCode) => {
    if (newCode && typeof newCode === 'string') {
      verificationCodeInput.value = newCode;
      handleVerifyCode(newCode);
    }
  }
);
</script>

<template>
  <div class="tribunal-verification-page py-5 bg-light min-vh-100">
    <div class="container" style="max-width: 820px;">
      <!-- Title & Header Banner -->
      <div class="text-center mb-5">
        <div class="d-inline-flex align-items-center justify-content-center p-3 bg-white rounded-circle shadow-sm mb-3">
          <i class="bi bi-shield-check text-primary fs-1" />
        </div>
        <h2 class="fw-bold text-dark mb-1">Myintellibook_live Tribunal</h2>
        <h5 class="text-secondary fw-semibold mb-2">Official Report Authenticity Verification</h5>
        <p class="text-muted small mx-auto" style="max-width: 580px;">
          Verify the authenticity, integrity, and cryptographic record of official determinations issued by the Myintellibook Tribunal Division.
        </p>
      </div>

      <!-- Mode Selector Tabs -->
      <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom-0 p-3 pb-0">
          <ul class="nav nav-pills nav-fill bg-light p-1 rounded-pill" role="tablist">
            <li class="nav-item">
              <button
                class="nav-link rounded-pill py-2 fw-semibold"
                :class="{ 'active bg-primary text-white shadow-sm': activeMode === 'code' }"
                type="button"
                @click="activeMode = 'code'"
              >
                <i class="bi bi-qr-code me-2" />Verification Code or Report No.
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link rounded-pill py-2 fw-semibold"
                :class="{ 'active bg-primary text-white shadow-sm': activeMode === 'file' }"
                type="button"
                @click="activeMode = 'file'"
              >
                <i class="bi bi-file-earmark-pdf me-2" />PDF Document Hash Match
              </button>
            </li>
          </ul>
        </div>

        <div class="card-body p-4 p-md-5">
          <!-- MODE 1: VERIFICATION CODE SEARCH -->
          <div v-if="activeMode === 'code'">
            <form @submit.prevent="handleVerifyCode()">
              <label class="form-label fw-bold text-dark mb-2">
                Enter Verification Code or Report Number
              </label>
              <div class="input-group input-group-lg mb-2">
                <span class="input-group-text bg-light border-end-0">
                  <i class="bi bi-key-fill text-muted" />
                </span>
                <input
                  v-model="verificationCodeInput"
                  type="text"
                  class="form-control font-monospace border-start-0 border-end-0 text-uppercase"
                  placeholder="e.g. VER-8X2L-KP91 or MIB-RPT-2026-000001"
                  :disabled="verifying"
                  required
                />
                <button
                  type="submit"
                  class="btn btn-primary px-4 fw-bold d-flex align-items-center gap-2"
                  :disabled="verifying || !verificationCodeInput.trim()"
                >
                  <span v-if="verifying" class="spinner-border spinner-border-sm" role="status" />
                  <i v-else class="bi bi-shield-lock" />
                  <span>{{ verifying ? 'Verifying...' : 'Verify Authenticity' }}</span>
                </button>
              </div>
              <div class="form-text text-muted small">
                The verification code is printed in the header, footer, and QR code of every official Tribunal report.
              </div>
            </form>

            <div v-if="errorMessage" class="alert alert-danger rounded-4 mt-4 shadow-sm" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2" />{{ errorMessage }}
            </div>
          </div>

          <!-- MODE 2: PDF DOCUMENT HASH VERIFICATION -->
          <div v-else>
            <form @submit.prevent="handleVerifyFile()">
              <div class="mb-3">
                <label class="form-label fw-bold text-dark">
                  Select Downloaded Tribunal PDF Report
                </label>
                <input
                  type="file"
                  class="form-control form-control-lg rounded-3"
                  accept="application/pdf"
                  :disabled="fileVerifying"
                  @change="handleFileChange"
                />
                <div class="form-text text-muted small mt-1">
                  We will compute the document's SHA-256 cryptographic digest locally and verify it against our immutable registry.
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold text-secondary small">
                  Optional Verification Code
                </label>
                <input
                  v-model="verificationCodeInput"
                  type="text"
                  class="form-control font-monospace text-uppercase"
                  placeholder="e.g. VER-8X2L-KP91"
                  :disabled="fileVerifying"
                />
              </div>

              <button
                type="submit"
                class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2"
                :disabled="fileVerifying || !selectedFile"
              >
                <span v-if="fileVerifying" class="spinner-border spinner-border-sm" role="status" />
                <i v-else class="bi bi-hash" />
                <span>{{ fileVerifying ? 'Checking Cryptographic Hash...' : 'Verify Document Hash' }}</span>
              </button>
            </form>

            <div v-if="fileError" class="alert alert-danger rounded-4 mt-4 shadow-sm" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2" />{{ fileError }}
            </div>

            <!-- FILE VERIFICATION RESULT -->
            <div v-if="fileResult" class="mt-4">
              <div
                v-if="fileResult.valid_hash"
                class="card border-success bg-success bg-opacity-10 rounded-4 p-4 shadow-sm"
              >
                <div class="d-flex align-items-center gap-3 mb-3">
                  <div class="rounded-circle bg-success text-white p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-patch-check-fill fs-3" />
                  </div>
                  <div>
                    <span class="badge bg-success text-uppercase px-3 py-1 fw-bold mb-1">Cryptographic Match</span>
                    <h5 class="fw-bold text-success mb-0">AUTHENTIC &amp; UNTAMPERED DOCUMENT</h5>
                  </div>
                </div>
                <p class="text-secondary small mb-3">
                  {{ fileResult.message }}
                </p>
                <div class="p-3 bg-white rounded-3 border small font-monospace text-break mb-2">
                  <strong class="text-dark d-block">Verified SHA-256 Hash:</strong>
                  <span class="text-success">{{ fileResult.calculated_hash }}</span>
                </div>
                <div class="small text-muted">
                  Report: <strong>{{ fileResult.report_number }}</strong> &bull; Code: <strong>{{ fileResult.verification_code }}</strong> (v{{ fileResult.version }})
                </div>
              </div>

              <div
                v-else
                class="card border-danger bg-danger bg-opacity-10 rounded-4 p-4 shadow-sm"
              >
                <div class="d-flex align-items-center gap-3 mb-3">
                  <div class="rounded-circle bg-danger text-white p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-shield-x fs-3" />
                  </div>
                  <div>
                    <span class="badge bg-danger text-uppercase px-3 py-1 fw-bold mb-1">Integrity Failure</span>
                    <h5 class="fw-bold text-danger mb-0">HASH MISMATCH / UNVERIFIED DOCUMENT</h5>
                  </div>
                </div>
                <p class="text-danger mb-2 small">
                  {{ fileResult.message }}
                </p>
                <div class="p-3 bg-white rounded-3 border small font-monospace text-break">
                  <strong class="text-dark d-block">Calculated Digest:</strong>
                  <span class="text-danger">{{ fileResult.calculated_hash }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- VERIFICATION RESULT CARD (For Code Mode) -->
      <div v-if="verifiedResult" class="mt-4">
        <!-- VALID REPORT -->
        <div v-if="verifiedResult.valid && verifiedResult.details" class="card border-0 shadow rounded-4 bg-white overflow-hidden">
          <div class="bg-success text-white p-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-white text-success p-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
                <i class="bi bi-shield-fill-check fs-2" />
              </div>
              <div>
                <span class="badge bg-white text-success text-uppercase fw-bold px-3 py-1 rounded-pill mb-1">
                  Authenticity Confirmed
                </span>
                <h4 class="fw-bold mb-0">VALID MYINTELLIBOOK_LIVE TRIBUNAL REPORT</h4>
              </div>
            </div>
            <span class="badge bg-light text-dark font-monospace px-3 py-2 fs-6 rounded-pill">
              {{ verifiedResult.details.status.toUpperCase() }}
            </span>
          </div>

          <div class="p-4 p-md-5">
            <div class="alert alert-success bg-success bg-opacity-10 border-0 rounded-4 p-3 mb-4 d-flex align-items-center gap-3">
              <i class="bi bi-check-circle-fill text-success fs-3" />
              <div class="small text-dark">
                {{ verifiedResult.details.confirmation_statement }}
              </div>
            </div>

            <!-- Safe Identification Metadata Table -->
            <div class="table-responsive mb-4">
              <table class="table table-bordered align-middle">
                <tbody>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase" style="width: 35%;">Report Number</th>
                    <td class="font-monospace fw-bold text-primary">{{ verifiedResult.details.report_number }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase">Case Number</th>
                    <td class="font-monospace fw-bold">{{ verifiedResult.details.case_number }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase">Report Type</th>
                    <td>{{ verifiedResult.details.report_type.replace('_', ' ').toUpperCase() }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase">Report Version</th>
                    <td>Version {{ verifiedResult.details.version }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase">Issued Timestamp</th>
                    <td>{{ verifiedResult.details.issued_at_formatted || verifiedResult.details.issued_at }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase">Decision Number</th>
                    <td class="font-monospace fw-bold text-dark">{{ verifiedResult.details.decision_number }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase">Decision Published</th>
                    <td>{{ verifiedResult.details.decision_published_at }}</td>
                  </tr>
                  <tr v-if="verifiedResult.details.adjudicated_by">
                    <th class="bg-light text-secondary small text-uppercase">Adjudicating Panel</th>
                    <td>{{ verifiedResult.details.adjudicated_by }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light text-secondary small text-uppercase">Document SHA-256 Digest</th>
                    <td class="font-monospace small text-break text-muted">{{ verifiedResult.details.file_hash }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Privacy Protection Guarantee Notice -->
            <div class="p-3 bg-light rounded-4 text-center text-muted small">
              <i class="bi bi-shield-shaded text-primary me-1" />
              For privacy protection, substantive evidence, participant personal information, and proceedings records are restricted to authorized case parties and are not exposed publicly.
            </div>

            <div class="text-center mt-4">
              <button
                type="button"
                class="btn btn-outline-secondary rounded-pill px-4"
                @click="clearVerification"
              >
                <i class="bi bi-arrow-repeat me-1" />
                Verify Another Report
              </button>
            </div>
          </div>
        </div>

        <!-- INVALID REPORT -->
        <div v-else class="card border-0 shadow rounded-4 bg-white overflow-hidden">
          <div class="bg-danger text-white p-4 d-flex align-items-center gap-3">
            <div class="rounded-circle bg-white text-danger p-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
              <i class="bi bi-shield-fill-x fs-2" />
            </div>
            <div>
              <span class="badge bg-white text-danger text-uppercase fw-bold px-3 py-1 rounded-pill mb-1">
                Authenticity Verification Failed
              </span>
              <h4 class="fw-bold mb-0">INVALID OR UNVERIFIED REPORT</h4>
            </div>
          </div>

          <div class="p-4 p-md-5 text-center">
            <div class="mb-4 text-secondary">
              <p class="fs-5 fw-semibold text-danger mb-2">
                The provided identifier could not be validated against the Myintellibook official Tribunal registry.
              </p>
              <p class="text-muted small mx-auto" style="max-width: 520px;">
                This document may be unauthorized, modified, incorrectly transcribed, or expired.
                Ensure you have entered the exact code from the official report (e.g. <code>VER-XXXX-XXXX</code> or <code>MIB-RPT-YYYY-XXXXXX</code>).
              </p>
            </div>

            <button
              type="button"
              class="btn btn-outline-danger rounded-pill px-4"
              @click="clearVerification"
            >
              <i class="bi bi-arrow-left me-1" />
              Try Another Code
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-monospace {
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
}
</style>
