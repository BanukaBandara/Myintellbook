<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import { adminInternalReportService } from '@/services/adminInternalReportService';
import type { AdminInternalReportItem, InternalPenaltyType, InternalReportStatus, RestrictedFeature } from '@/types/internalReport';

const route = useRoute();
const reportId = Number(route.params.id);

const report = ref<AdminInternalReportItem | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

// Modals State
const showStatusModal = ref(false);
const statusForm = ref<{
  status: InternalReportStatus;
  notes: string;
  decision_reason: string;
}>({
  status: 'UnderReview',
  notes: '',
  decision_reason: '',
});
const isUpdatingStatus = ref(false);

const showPenaltyModal = ref(false);
const penaltyForm = ref<{
  action_type: InternalPenaltyType;
  penalty_value: RestrictedFeature;
  restriction_duration_type: 'temporary' | 'permanent';
  duration_days: number;
  reason: string;
  notes: string;
}>({
  action_type: 'Warning',
  penalty_value: 'community_posting',
  restriction_duration_type: 'temporary',
  duration_days: 7,
  reason: '',
  notes: '',
});
const isApplyingPenalty = ref(false);

const fetchDossier = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    report.value = await adminInternalReportService.getReport(reportId);
    if (report.value) {
      statusForm.value.status = report.value.status;
      statusForm.value.notes = report.value.admin_notes || '';
      statusForm.value.decision_reason = report.value.decision_reason || '';
    }
  } catch (err: any) {
    errorMessage.value = 'Failed to load report dossier.';
  } finally {
    isLoading.value = false;
  }
};

const handleUpdateStatus = async () => {
  isUpdatingStatus.value = true;
  try {
    const updated = await adminInternalReportService.updateStatus(reportId, {
      status: statusForm.value.status,
      notes: statusForm.value.notes || undefined,
      decision_reason: statusForm.value.decision_reason || undefined,
    });
    report.value = updated;
    showStatusModal.value = false;
    Swal.fire({
      icon: 'success',
      title: 'Status Updated',
      text: `Report status moved to ${statusForm.value.status}. Reporter has been notified.`,
      timer: 2000,
      showConfirmButton: false,
    });
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Update Failed',
      text: err.response?.data?.message || 'Failed to update report status.',
    });
  } finally {
    isUpdatingStatus.value = false;
  }
};

const handleApplyPenalty = async () => {
  if (!penaltyForm.value.reason.trim()) {
    Swal.fire({
      icon: 'warning',
      title: 'Reason Required',
      text: 'Please provide a formal justification for this administrative action.',
    });
    return;
  }

  isApplyingPenalty.value = true;
  try {
    const payload: any = {
      action_type: penaltyForm.value.action_type,
      reason: penaltyForm.value.reason.trim(),
      notes: penaltyForm.value.notes.trim() || undefined,
    };
    if (penaltyForm.value.action_type === 'Temporary Suspension') {
      payload.duration_days = Number(penaltyForm.value.duration_days) || 7;
    } else if (penaltyForm.value.action_type === 'Feature Restriction') {
      payload.penalty_value = penaltyForm.value.penalty_value;
      payload.restriction_duration_type = penaltyForm.value.restriction_duration_type;
      if (penaltyForm.value.restriction_duration_type === 'temporary') {
        payload.duration_days = Number(penaltyForm.value.duration_days) || 7;
      }
    } else if (penaltyForm.value.action_type === 'Professional Eligibility Suspension') {
      payload.restriction_duration_type = penaltyForm.value.restriction_duration_type;
      if (penaltyForm.value.restriction_duration_type === 'temporary') {
        payload.duration_days = Number(penaltyForm.value.duration_days) || 7;
      }
    }

    const res = await adminInternalReportService.applyPenalty(reportId, payload);
    report.value = res.report;
    showPenaltyModal.value = false;
    penaltyForm.value.reason = '';
    penaltyForm.value.notes = '';
    penaltyForm.value.duration_days = 7;
    penaltyForm.value.penalty_value = 'community_posting';
    penaltyForm.value.restriction_duration_type = 'temporary';

    Swal.fire({
      icon: 'success',
      title: 'Sanction Applied',
      text: `${res.action_type} successfully recorded and sanitized notification sent to the reported user.`,
    });
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err.response?.data?.message || 'Failed to apply disciplinary action.',
    });
  } finally {
    isApplyingPenalty.value = false;
  }
};

const handleReversePenalty = async (penaltyId: number) => {
  const { value: reason } = await Swal.fire({
    title: 'Reverse Administrative Action?',
    input: 'textarea',
    inputLabel: 'Reason for Reversal',
    inputPlaceholder: 'Explain why this action is being reversed...',
    inputValidator: (val) => {
      if (!val || val.trim().length < 5) {
        return 'Please provide a reversal reason (min 5 characters).';
      }
    },
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Confirm Reversal',
  });

  if (!reason) return;

  try {
    await adminInternalReportService.reversePenalty(penaltyId, reason);
    await fetchDossier();
    Swal.fire({
      icon: 'success',
      title: 'Action Reversed',
      text: 'The penalty was reversed and audited.',
    });
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Reversal Failed',
      text: err.response?.data?.message || 'Failed to reverse penalty.',
    });
  }
};

const downloadEvidence = async (evidenceId: number, filename: string) => {
  try {
    const blob = await adminInternalReportService.downloadEvidence(evidenceId);
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
      text: 'Unable to stream evidence file.',
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
  fetchDossier();
});
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header / Back -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <router-link
          to="/admin/internal-reports"
          class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1 mb-2 font-medium"
        >
          &larr; Back to Reports Dashboard
        </router-link>
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-900 font-mono">
            {{ report?.report_number || 'Loading...' }}
          </h1>
          <span
            v-if="report"
            :class="[
              'px-3 py-0.5 text-xs font-semibold rounded-full border',
              getStatusBadgeClass(report.status)
            ]"
          >
            {{ report.status }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="report" class="flex items-center gap-3">
        <button
          type="button"
          @click="showStatusModal = true"
          class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium rounded-lg text-xs shadow-sm transition-colors"
        >
          Change Status
        </button>
        <button
          type="button"
          @click="showPenaltyModal = true"
          class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg text-xs shadow-sm transition-colors"
        >
          Apply Action / Sanction
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-24 bg-white rounded-xl border border-gray-200">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <p class="mt-2 text-xs text-gray-500">Loading misconduct dossier...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="text-center py-12 bg-white rounded-xl border border-red-200 p-6">
      <p class="text-sm text-red-600 font-medium">{{ errorMessage }}</p>
    </div>

    <!-- Dossier Content -->
    <div v-else-if="report" class="space-y-6">

      <!-- Parties Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Reporter Card -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Complainant / Reporter</h3>
            <span class="text-[10px] bg-blue-50 text-blue-700 font-bold px-2 py-0.5 rounded">Protected Whistleblower</span>
          </div>
          <div class="mt-4 space-y-2 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-400">Name:</span>
              <span class="font-semibold text-gray-900">{{ report.reporter?.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Email:</span>
              <span class="font-mono text-gray-700">{{ report.reporter?.email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">User ID:</span>
              <span class="font-mono text-gray-700">#{{ report.reporter?.id }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Username:</span>
              <span class="text-gray-700">@{{ report.reporter?.username }}</span>
            </div>
          </div>
        </div>

        <!-- Reported Member Card -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Reported Member</h3>
            <span v-if="report.reported_user?.is_jury_panel" class="text-[10px] bg-purple-100 text-purple-700 font-bold px-2 py-0.5 rounded">
              Jury Panel Account
            </span>
            <span v-else class="text-[10px] bg-gray-100 text-gray-700 font-bold px-2 py-0.5 rounded">Platform User</span>
          </div>
          <div class="mt-4 space-y-2 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-400">Name:</span>
              <span class="font-semibold text-gray-900">{{ report.reported_user?.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Email:</span>
              <span class="font-mono text-gray-700">{{ report.reported_user?.email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">User ID:</span>
              <span class="font-mono text-gray-700">#{{ report.reported_user?.id }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Username:</span>
              <span class="text-gray-700">@{{ report.reported_user?.username }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Report Details Card -->
      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-gray-100">
          <div>
            <div class="text-xs text-gray-400 uppercase font-semibold">Category</div>
            <div class="text-sm font-bold text-gray-900">{{ report.category }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-400 uppercase font-semibold">Severity</div>
            <div class="text-xs font-bold uppercase tracking-wider text-red-600">{{ report.severity }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-400 uppercase font-semibold">Submitted At</div>
            <div class="text-xs text-gray-700">{{ new Date(report.created_at).toLocaleString() }}</div>
          </div>
          <div v-if="report.reviewed_at">
            <div class="text-xs text-gray-400 uppercase font-semibold">Last Reviewed</div>
            <div class="text-xs text-gray-700">{{ new Date(report.reviewed_at).toLocaleString() }} by {{ report.reviewer_name }}</div>
          </div>
        </div>

        <div>
          <div class="text-xs text-gray-400 uppercase font-semibold mb-1">Subject</div>
          <div class="text-base font-bold text-gray-900">{{ report.subject }}</div>
        </div>

        <div>
          <div class="text-xs text-gray-400 uppercase font-semibold mb-1">Incident Description</div>
          <p class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 p-4 rounded-lg border border-gray-100 leading-relaxed">
            {{ report.description }}
          </p>
        </div>

        <!-- Evidence Vault -->
        <div class="pt-2">
          <div class="text-xs text-gray-400 uppercase font-semibold mb-2">
            Evidence Vault ({{ report.evidence?.length || 0 }} files)
          </div>
          <div v-if="!report.evidence || report.evidence.length === 0" class="text-xs text-gray-400 italic">
            No files attached.
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="ev in report.evidence"
              :key="ev.id"
              class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg shadow-2xs text-xs"
            >
              <div class="truncate mr-2">
                <div class="font-medium text-gray-800 truncate">{{ ev.original_name }}</div>
                <div class="text-[10px] text-gray-400 font-mono truncate">
                  {{ (ev.size / (1024 * 1024)).toFixed(2) }} MB &bull; {{ ev.sha256.substring(0, 12) }}...
                </div>
              </div>
              <button
                type="button"
                @click="downloadEvidence(ev.id, ev.original_name)"
                class="px-2 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 rounded"
              >
                Download
              </button>
            </div>
          </div>
        </div>

        <!-- Admin Notes / Decision Reason if present -->
        <div v-if="report.admin_notes || report.decision_reason" class="pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-if="report.admin_notes" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs">
            <span class="font-semibold text-yellow-800 block mb-1">Internal Admin Notes (Private):</span>
            <span class="text-yellow-900">{{ report.admin_notes }}</span>
          </div>
          <div v-if="report.decision_reason" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs">
            <span class="font-semibold text-blue-800 block mb-1">Official Decision Reason:</span>
            <span class="text-blue-900">{{ report.decision_reason }}</span>
          </div>
        </div>

      </div>

      <!-- Applied Sanctions / Penalties Section -->
      <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">
          Applied Sanctions & Actions ({{ report.penalties?.length || 0 }})
        </h3>
        <div v-if="!report.penalties || report.penalties.length === 0" class="text-xs text-gray-400 italic">
          No sanctions have been applied yet for this report.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="p in report.penalties"
            :key="p.id"
            class="p-4 border rounded-lg bg-red-50/30 border-red-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-red-700 uppercase tracking-wider">{{ p.action_type }}</span>
                <span v-if="p.penalty_value" class="px-2 py-0.5 text-[10px] font-mono bg-amber-100 text-amber-800 rounded border border-amber-300">
                  {{ p.penalty_value }}
                </span>
                <span class="text-gray-400">&bull;</span>
                <span class="text-gray-500">{{ new Date(p.applied_at).toLocaleString() }} by {{ p.applied_by_name }}</span>
              </div>
              <div class="text-gray-800"><span class="font-semibold">Reason:</span> {{ p.reason }}</div>
              <div v-if="p.notes" class="text-gray-500 italic"><span class="font-semibold">Notes:</span> {{ p.notes }}</div>
              <div v-if="p.starts_at || p.ends_at" class="text-gray-600 text-[11px] flex items-center gap-2">
                <span v-if="p.starts_at">Starts: {{ new Date(p.starts_at).toLocaleString() }}</span>
                <span v-if="p.starts_at && p.ends_at">&bull;</span>
                <span v-if="p.ends_at">Expires: {{ new Date(p.ends_at).toLocaleString() }}</span>
                <span v-else-if="!p.ends_at">(Indefinite)</span>
              </div>
              <div v-if="p.reversed_at" class="text-amber-700 font-semibold text-[11px]">
                [Reversed on {{ new Date(p.reversed_at).toLocaleString() }}]
              </div>
            </div>
            <button
              v-if="!p.reversed_at"
              type="button"
              @click="handleReversePenalty(p.id)"
              class="text-xs text-red-600 hover:text-red-800 border border-red-300 hover:bg-red-50 px-2.5 py-1 rounded font-medium whitespace-nowrap self-start sm:self-center"
            >
              Reverse Action
            </button>
          </div>
        </div>
      </div>

      <!-- Audit History & Review History Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Review Transitions -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
          <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Review Transitions</h3>
          <div v-if="!report.reviews || report.reviews.length === 0" class="text-xs text-gray-400 italic">
            No status transitions recorded.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="rev in report.reviews"
              :key="rev.id"
              class="p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-xs"
            >
              <div class="font-semibold text-gray-800">
                {{ rev.from_status }} &rarr; {{ rev.to_status }}
              </div>
              <div class="text-gray-400 text-[10px]">
                {{ new Date(rev.created_at).toLocaleString() }} by {{ rev.reviewer_name }}
              </div>
              <div v-if="rev.notes" class="text-gray-600 mt-1 italic text-[11px]">{{ rev.notes }}</div>
            </div>
          </div>
        </div>

        <!-- Full Audit Trail -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
          <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Immutable Audit Trail</h3>
          <div v-if="!report.audits || report.audits.length === 0" class="text-xs text-gray-400 italic">
            No audit logs available.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="aud in report.audits"
              :key="aud.id"
              class="p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-xs"
            >
              <div class="font-semibold text-gray-800">{{ aud.action }}</div>
              <div class="text-gray-400 text-[10px]">
                {{ new Date(aud.created_at).toLocaleString() }} &bull; {{ aud.performer_name }}
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Modal 1: Update Status Modal -->
    <div
      v-if="showStatusModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-lg w-full p-6 space-y-4">
        <h3 class="text-base font-bold text-gray-900">Change Report Status</h3>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">New Workflow Status</label>
          <select
            v-model="statusForm.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-2 focus:ring-blue-500"
          >
            <option value="Submitted">Submitted</option>
            <option value="UnderReview">Under Review</option>
            <option value="NeedsMoreInformation">Needs More Information</option>
            <option value="Valid">Valid</option>
            <option value="Invalid">Invalid</option>
            <option value="Closed">Closed</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Internal Admin Notes (Private)</label>
          <textarea
            v-model="statusForm.notes"
            rows="3"
            placeholder="Confidential notes visible only to Super Admins..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Official Decision Reason (Visible if Valid/Invalid/Closed)</label>
          <textarea
            v-model="statusForm.decision_reason"
            rows="2"
            placeholder="Summary outcome explanation..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
          <button
            type="button"
            @click="showStatusModal = false"
            class="px-4 py-2 text-xs text-gray-600 hover:text-gray-800"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleUpdateStatus"
            :disabled="isUpdatingStatus"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-xs disabled:opacity-50"
          >
            {{ isUpdatingStatus ? 'Saving...' : 'Save Status' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal 2: Apply Action / Penalty Modal -->
    <div
      v-if="showPenaltyModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-lg w-full p-6 space-y-4">
        <h3 class="text-base font-bold text-gray-900">Apply Administrative Action / Sanction</h3>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Sanction Type</label>
          <select
            v-model="penaltyForm.action_type"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-2 focus:ring-red-500"
          >
            <option value="Warning">Warning</option>
            <option value="Formal Warning">Formal Warning</option>
            <option value="Profile Correction Required">Profile Correction Required</option>
            <option value="Temporary Suspension">Temporary Suspension</option>
            <option value="Permanent Suspension">Permanent Suspension</option>
            <option value="Feature Restriction">Feature Restriction</option>
            <option value="Verification Revoked">Verification Revoked</option>
            <option value="Professional Eligibility Suspension">Professional Eligibility Suspension</option>
          </select>
        </div>

        <!-- Verification Revoked Warning Notice -->
        <div
          v-if="penaltyForm.action_type === 'Verification Revoked'"
          class="p-2.5 bg-red-50 border border-red-200 rounded-lg text-[11px] text-red-800"
        >
          <strong>Notice:</strong> This action suspends the professional's verification, terminates any active legal representation assignments, and deactivates client-lawyer representation chats. Credential re-verification requires formal re-review through the Professional Verifications portal.
        </div>

        <!-- Professional Eligibility Suspension Configuration -->
        <div v-if="penaltyForm.action_type === 'Professional Eligibility Suspension'" class="space-y-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              Eligibility Suspension Duration Mode <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-4">
              <label class="inline-flex items-center text-xs text-gray-700">
                <input
                  type="radio"
                  v-model="penaltyForm.restriction_duration_type"
                  value="temporary"
                  class="mr-1.5 text-indigo-600 focus:ring-indigo-500"
                />
                Temporary (Specific days)
              </label>
              <label class="inline-flex items-center text-xs text-gray-700">
                <input
                  type="radio"
                  v-model="penaltyForm.restriction_duration_type"
                  value="permanent"
                  class="mr-1.5 text-indigo-600 focus:ring-indigo-500"
                />
                Permanent (Indefinite)
              </label>
            </div>
          </div>

          <div v-if="penaltyForm.restriction_duration_type === 'temporary'">
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              Suspension Duration (Days) <span class="text-red-500">*</span>
            </label>
            <input
              v-model.number="penaltyForm.duration_days"
              type="number"
              min="1"
              max="365"
              placeholder="7"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500"
            />
            <p class="text-[11px] text-gray-500 mt-1">
              Enter 1 to 365 days. The user cannot receive new representation requests during this period. Existing active representations continue undisturbed.
            </p>
          </div>
          <div v-else class="text-[11px] text-indigo-800">
            <strong>Permanent:</strong> Prevents new representation requests indefinitely until manually reversed by an administrator. Existing active cases continue undisturbed.
          </div>
        </div>

        <!-- Temporary Suspension Duration -->
        <div v-if="penaltyForm.action_type === 'Temporary Suspension'">
          <label class="block text-xs font-semibold text-gray-700 mb-1">
            Suspension Duration (Days) <span class="text-red-500">*</span>
          </label>
          <input
            v-model.number="penaltyForm.duration_days"
            type="number"
            min="1"
            max="365"
            placeholder="7"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-red-500"
          />
          <p class="text-[11px] text-gray-500 mt-1">
            Enter 1 to 365 days. Existing tokens will be revoked immediately and login blocked until expiry.
          </p>
        </div>

        <!-- Permanent Suspension Warning Notice -->
        <div
          v-if="penaltyForm.action_type === 'Permanent Suspension'"
          class="p-2.5 bg-red-50 border border-red-200 rounded-lg text-[11px] text-red-800"
        >
          <strong>Notice:</strong> Permanent suspension revokes all active tokens immediately and prevents login indefinitely until reversed by a Super Administrator.
        </div>

        <!-- Feature Restriction Configuration -->
        <div v-if="penaltyForm.action_type === 'Feature Restriction'" class="space-y-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              Restricted Feature <span class="text-red-500">*</span>
            </label>
            <select
              v-model="penaltyForm.penalty_value"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-2 focus:ring-amber-500"
            >
              <option value="tribunal_participation">Tribunal Participation (Cases, evidence, mediation, hearing)</option>
              <option value="community_posting">Community Posting (Resource notes, comments)</option>
              <option value="daily_question_access">Daily Question Access (Answering daily questions)</option>
              <option value="exam_access">Exam Access (Taking exams & submitting answers)</option>
              <option value="profile_editing">Profile Editing (General info, experience, education, skills, photos)</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              Restriction Duration Mode <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-4">
              <label class="inline-flex items-center text-xs text-gray-700">
                <input
                  type="radio"
                  v-model="penaltyForm.restriction_duration_type"
                  value="temporary"
                  class="mr-1.5 text-amber-600 focus:ring-amber-500"
                />
                Temporary (Specific days)
              </label>
              <label class="inline-flex items-center text-xs text-gray-700">
                <input
                  type="radio"
                  v-model="penaltyForm.restriction_duration_type"
                  value="permanent"
                  class="mr-1.5 text-amber-600 focus:ring-amber-500"
                />
                Permanent (Indefinite)
              </label>
            </div>
          </div>

          <div v-if="penaltyForm.restriction_duration_type === 'temporary'">
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              Restriction Duration (Days) <span class="text-red-500">*</span>
            </label>
            <input
              v-model.number="penaltyForm.duration_days"
              type="number"
              min="1"
              max="365"
              placeholder="7"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-amber-500"
            />
            <p class="text-[11px] text-gray-500 mt-1">
              Enter 1 to 365 days. The user can still log in and use other platform features, but this specific feature will be blocked until expiry.
            </p>
          </div>
          <div v-else class="text-[11px] text-amber-800">
            <strong>Permanent:</strong> The user can use all other features, but access to this feature will remain blocked indefinitely until manually reversed by a Super Administrator.
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">
            Formal Justification / Reason <span class="text-red-500">*</span>
          </label>
          <p class="text-[11px] text-gray-500 mb-1">
            Note: This text is included in the sanitized policy notice dispatched to the reported member.
          </p>
          <textarea
            v-model="penaltyForm.reason"
            rows="3"
            placeholder="Official explanation of policy violation and corrective requirement..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-red-500"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Internal Admin Notes (Private)</label>
          <textarea
            v-model="penaltyForm.notes"
            rows="2"
            placeholder="Private administrative context..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-red-500"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
          <button
            type="button"
            @click="showPenaltyModal = false"
            class="px-4 py-2 text-xs text-gray-600 hover:text-gray-800"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleApplyPenalty"
            :disabled="isApplyingPenalty"
            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg text-xs disabled:opacity-50"
          >
            {{ isApplyingPenalty ? 'Applying...' : 'Apply Sanction & Notify' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
