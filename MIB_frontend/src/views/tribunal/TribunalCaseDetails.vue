<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';

import api from '@/assets/axios';
import { useTribunalStore } from '@/stores/tribunal';
import TribunalClientChatModal from '@/components/tribunal/TribunalClientChatModal.vue';
import type {
  TribunalCase,
  TribunalEvidence,
  TribunalEvidenceType,
  TribunalParty,
  TribunalResponsePosition,
  VerifiedRepresentative,
  TribunalCaseRoom,
  TribunalCaseMessage,
  TribunalMediation,
  TribunalSettlementProposal,
  TribunalSettlementAgreement,
} from '@/types/tribunal';
import PartyHearingTab from '@/components/tribunal/PartyHearingTab.vue';
import PartyDecisionTab from '@/components/tribunal/PartyDecisionTab.vue';

const route = useRoute();
const router = useRouter();
const tribunalStore = useTribunalStore();

const currentUserId = ref<number | null>(null);
const unauthorizedError = ref(false);
const notFoundError = ref(false);

const acknowledging = ref(false);
const responding = ref(false);

// Response form fields
const position = ref<TribunalResponsePosition | ''>('');
const responseText = ref('');

// Evidence Upload Modal and Form fields
const showEvidenceModal = ref(false);
const uploadingEvidence = ref(false);
const evidenceType = ref<TribunalEvidenceType>('document');
const evidenceTitle = ref('');
const evidenceDescription = ref('');
const evidenceExternalUrl = ref('');
const evidenceFileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const downloadingId = ref<number | null>(null);
const challengingId = ref<number | null>(null);

// Batch 4: Legal Representation state
const showDirectoryModal = ref(false);
const showChatModal = ref(false);
const representativeSearch = ref('');
const selectedLawyer = ref<VerifiedRepresentative | null>(null);
const requestMessage = ref('');
const submittingRequest = ref(false);

// Batch 5: Tab Navigation state
const activeTab = ref<'overview' | 'evidence' | 'case_room' | 'mediation' | 'hearing' | 'representation' | 'decision'>('overview');

// Batch 5: Shared Case Room state
const caseRoomMessageBody = ref('');
const selectedEvidenceId = ref<number | null>(null);
const sendingRoomMessage = ref(false);
const caseRoomFilter = ref<'all' | 'notices' | 'questions'>('all');

const showNoticeModal = ref(false);
const noticeBody = ref('');
const postingNotice = ref(false);

const showQuestionModal = ref(false);
const questionBody = ref('');
const questionTargetSide = ref<'complainant' | 'respondent' | 'both'>('both');
const askingQuestion = ref(false);

const replyingToQuestionId = ref<number | null>(null);
const replyBody = ref('');
const submittingReply = ref(false);

// Batch 5: Mediation & Settlement state
const requestingMediation = ref(false);
const offeringMediation = ref(false);
const respondingToConsent = ref(false);
const showProposalModal = ref(false);
const proposalTerms = ref('');
const parentProposalId = ref<number | null>(null);
const isCountering = ref(false);
const submittingProposal = ref(false);
const endingMediation = ref(false);

const resolveCurrentUserId = async () => {
  const stored = localStorage.getItem('userData');
  if (stored) {
    try {
      const parsed = JSON.parse(stored);
      if (parsed.id) {
        currentUserId.value = parsed.id;
        return;
      }
    } catch {}
  }

  try {
    const res = await api.get('/user');
    if (res.data?.data?.id) {
      currentUserId.value = res.data.data.id;
    }
  } catch {}
};

const caseData = computed<TribunalCase | null>(() => tribunalStore.selectedCase);

const complainantParty = computed<TribunalParty | undefined>(() =>
  caseData.value?.parties.find((p) => p.role === 'complainant')
);

const respondentParty = computed<TribunalParty | undefined>(() =>
  caseData.value?.parties.find((p) => p.role === 'respondent')
);

const isComplainant = computed(() => {
  if (!currentUserId.value || !caseData.value) return false;
  return complainantParty.value?.user?.id === currentUserId.value;
});

const isRespondent = computed(() => {
  if (!currentUserId.value || !caseData.value) return false;
  return respondentParty.value?.user?.id === currentUserId.value;
});

const isAcceptedJuror = computed(() => {
  return caseData.value?.current_user_role === 'juror';
});

const isParty = computed(() => isComplainant.value || isRespondent.value);

const hasAcknowledged = computed(() => {
  return !!caseData.value?.response?.acknowledgement_at;
});

const hasResponded = computed(() => {
  return !!caseData.value?.response?.submitted_at;
});

const canSubmitResponse = computed(() => {
  return (
    position.value !== '' &&
    responseText.value.trim().length >= 20 &&
    !responding.value
  );
});

const evidenceList = computed<TribunalEvidence[]>(() => {
  return tribunalStore.evidenceList || caseData.value?.evidence || [];
});

const formatStatus = (status?: string): string => {
  if (!status) return '-';
  if (status === 'jury_selection') return 'Awaiting Jury Panel Assignment';
  return status
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char: string) => char.toUpperCase());
};

const formatPosition = (pos?: string | null): string => {
  if (!pos) return '-';
  switch (pos) {
    case 'accept':
      return 'Accepted Claim';
    case 'deny':
      return 'Denied Claim';
    case 'partially_accept':
      return 'Partially Accepted Claim';
    default:
      return formatStatus(pos);
  }
};

const formatDateTime = (dateStr?: string | null): string => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};

const formatFileSize = (bytes?: number | null): string => {
  if (!bytes) return '-';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};

const getStatusBadgeClass = (status?: string): string => {
  switch (status) {
    case 'submitted':
      return 'bg-secondary text-white';
    case 'awaiting_respondent':
      return 'bg-warning text-dark';
    case 'response_received':
    case 'jury_selection':
      return 'bg-info text-dark';
    case 'evidence_collection':
      return 'bg-primary text-white';
    case 'decided':
    case 'settled':
    case 'closed':
      return 'bg-success text-white';
    default:
      return 'bg-secondary text-white';
  }
};

const getEvidenceBadgeClass = (status: string): string => {
  switch (status) {
    case 'submitted':
      return 'bg-secondary-subtle text-secondary border';
    case 'challenged':
      return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    case 'accepted':
      return 'bg-success-subtle text-success border border-success-subtle';
    case 'rejected':
      return 'bg-danger-subtle text-danger border border-danger-subtle';
    default:
      return 'bg-light text-dark border';
  }
};

const getEvidenceTypeIcon = (type: string): string => {
  switch (type) {
    case 'image':
      return 'bi-file-earmark-image text-primary';
    case 'video':
      return 'bi-file-earmark-play text-danger';
    case 'audio':
      return 'bi-file-earmark-music text-info';
    case 'document':
      return 'bi-file-earmark-pdf text-danger';
    case 'link':
      return 'bi-link-45deg text-success';
    case 'statement':
      return 'bi-file-earmark-text text-secondary';
    default:
      return 'bi-file-earmark text-muted';
  }
};

const handleAcknowledge = async () => {
  if (!caseData.value) return;

  const result = await Swal.fire({
    title: 'Acknowledge Case?',
    text: 'By acknowledging, you confirm receipt of this tribunal dispute.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Acknowledge',
    cancelButtonText: 'Cancel',
  });

  if (!result.isConfirmed) return;

  acknowledging.value = true;
  const updated = await tribunalStore.acknowledgeCase(caseData.value.id);
  acknowledging.value = false;

  if (updated) {
    await Swal.fire({
      icon: 'success',
      title: 'Acknowledged',
      text: 'You have acknowledged receipt of this case.',
    });
  } else {
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: tribunalStore.error ?? 'Failed to acknowledge case.',
    });
  }
};

const handleSubmitResponse = async () => {
  if (!caseData.value || !canSubmitResponse.value || !position.value) return;

  responding.value = true;
  const updated = await tribunalStore.submitResponse(caseData.value.id, {
    position: position.value as TribunalResponsePosition,
    response_text: responseText.value.trim(),
  });
  responding.value = false;

  if (updated) {
    await Swal.fire({
      icon: 'success',
      title: 'Response Submitted',
      text: 'Your formal response has been submitted to the tribunal. Jury selection has been initiated.',
    });
  } else {
    await Swal.fire({
      icon: 'error',
      title: 'Submission Failed',
      text: tribunalStore.error ?? 'Failed to submit response.',
    });
  }
};

// Evidence file selection
const onFileSelected = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0];
  } else {
    selectedFile.value = null;
  }
};

const openAddEvidenceModal = () => {
  evidenceType.value = 'document';
  evidenceTitle.value = '';
  evidenceDescription.value = '';
  evidenceExternalUrl.value = '';
  selectedFile.value = null;
  if (evidenceFileInput.value) {
    evidenceFileInput.value.value = '';
  }
  showEvidenceModal.value = true;
};

const handleUploadEvidence = async () => {
  if (!caseData.value) return;

  if (!evidenceTitle.value.trim()) {
    await Swal.fire({ icon: 'warning', title: 'Missing Title', text: 'Please enter a title for your evidence.' });
    return;
  }

  if (evidenceType.value === 'link' && !evidenceExternalUrl.value.trim()) {
    await Swal.fire({ icon: 'warning', title: 'Missing URL', text: 'Please provide a valid external URL.' });
    return;
  }

  if (
    ['image', 'document', 'audio', 'video'].includes(evidenceType.value) &&
    !selectedFile.value
  ) {
    await Swal.fire({ icon: 'warning', title: 'Missing File', text: 'Please select a file to upload.' });
    return;
  }

  const formData = new FormData();
  formData.append('type', evidenceType.value);
  formData.append('title', evidenceTitle.value.trim());
  if (evidenceDescription.value.trim()) {
    formData.append('description', evidenceDescription.value.trim());
  }
  if (evidenceType.value === 'link' && evidenceExternalUrl.value.trim()) {
    formData.append('external_url', evidenceExternalUrl.value.trim());
  }
  if (selectedFile.value) {
    formData.append('file', selectedFile.value);
  }

  uploadingEvidence.value = true;
  const created = await tribunalStore.uploadEvidence(caseData.value.id, formData);
  uploadingEvidence.value = false;

  if (created) {
    showEvidenceModal.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Evidence Submitted',
      text: `Evidence item ${created.evidence_number} has been securely uploaded.`,
    });
  } else {
    await Swal.fire({
      icon: 'error',
      title: 'Upload Failed',
      text: tribunalStore.error ?? 'Failed to upload evidence.',
    });
  }
};

const handleDownloadEvidence = async (item: TribunalEvidence) => {
  if (!caseData.value) return;

  downloadingId.value = item.id;
  const success = await tribunalStore.downloadEvidence(
    caseData.value.id,
    item.id,
    item.original_filename || undefined
  );
  downloadingId.value = null;

  if (!success) {
    await Swal.fire({
      icon: 'error',
      title: 'Download Failed',
      text: tribunalStore.error ?? 'Could not download evidence file.',
    });
  }
};

const handleChallengeEvidence = async (item: TribunalEvidence) => {
  if (!caseData.value) return;

  const { value: reason, isConfirmed } = await Swal.fire({
    title: `Challenge Evidence ${item.evidence_number}`,
    text: `Specify the legal or factual grounds for challenging "${item.title}". This will be submitted to the Tribunal panel.`,
    input: 'textarea',
    inputPlaceholder: 'State your objection (e.g. Inauthentic document, irrelevant, cropped context, hearsay)...',
    inputAttributes: {
      minlength: '10',
      maxlength: '5000',
    },
    inputValidator: (val) => {
      if (!val || val.trim().length < 10) {
        return 'Please enter a challenge reason of at least 10 characters.';
      }
      return null;
    },
    showCancelButton: true,
    confirmButtonText: 'Submit Challenge',
    confirmButtonColor: '#dc3545',
    cancelButtonText: 'Cancel',
  });

  if (!isConfirmed || !reason) return;

  challengingId.value = item.id;
  const challenge = await tribunalStore.challengeEvidence(caseData.value.id, item.id, {
    reason: reason.trim(),
  });
  challengingId.value = null;

  if (challenge) {
    await Swal.fire({
      icon: 'success',
      title: 'Challenge Registered',
      text: `Your objection to evidence ${item.evidence_number} has been recorded for Tribunal panel review.`,
    });
  } else {
    await Swal.fire({
      icon: 'error',
      title: 'Challenge Failed',
      text: tribunalStore.error ?? 'Failed to challenge evidence.',
    });
  }
};

const loadCase = async () => {
  unauthorizedError.value = false;
  notFoundError.value = false;

  const id = Number(route.params.id);
  if (!Number.isInteger(id)) {
    notFoundError.value = true;
    return;
  }

  try {
    await tribunalStore.fetchCase(id);
    if (!tribunalStore.selectedCase && tribunalStore.error) {
      if (tribunalStore.error.includes('403') || tribunalStore.error.toLowerCase().includes('unauthorized')) {
        unauthorizedError.value = true;
      }
    } else if (tribunalStore.selectedCase) {
      tribunalStore.fetchCaseRoom(id).catch(() => {});
      tribunalStore.fetchCaseRoomMessages(id).catch(() => {});
      tribunalStore.fetchMediation(id).catch(() => {});

      if (route.query.tab && ['overview', 'evidence', 'case_room', 'mediation', 'hearing', 'representation'].includes(route.query.tab as string)) {
        activeTab.value = route.query.tab as any;
      }
    }
  } catch (err: any) {
    if (err?.response?.status === 403) {
      unauthorizedError.value = true;
    } else if (err?.response?.status === 404) {
      notFoundError.value = true;
    }
  }
};

const isRepresentative = computed(() => {
  return caseData.value?.current_user_role === 'representative' || 
         caseData.value?.representation?.is_representative_for_case === true;
});

const hasActiveRepresentation = computed(() => {
  return !!caseData.value?.representation?.has_active_representation;
});

const activeAssignment = computed(() => {
  return caseData.value?.representation?.active_assignment;
});

const pendingRequest = computed(() => {
  return caseData.value?.representation?.pending_request;
});

const representativesList = computed(() => {
  return tribunalStore.representatives || [];
});

const userCaseRole = computed(() => {
  if (isComplainant.value) return 'complainant';
  if (isRespondent.value) return 'respondent';
  if (isRepresentative.value) {
    return caseData.value?.representation?.my_represented_party === 'complainant'
      ? 'complainant_representative'
      : 'respondent_representative';
  }
  return undefined;
});

const userCaseSide = computed(() => {
  if (isComplainant.value || caseData.value?.representation?.my_represented_party === 'complainant') {
    return 'complainant';
  }
  if (isRespondent.value || caseData.value?.representation?.my_represented_party === 'respondent') {
    return 'respondent';
  }
  return undefined;
});

const openDirectoryModal = async () => {
  if (!caseData.value) return;
  selectedLawyer.value = null;
  requestMessage.value = '';
  await tribunalStore.fetchRepresentatives(caseData.value.id, representativeSearch.value);
  showDirectoryModal.value = true;
};

const handleSearchRepresentatives = async () => {
  if (!caseData.value) return;
  await tribunalStore.fetchRepresentatives(caseData.value.id, representativeSearch.value);
};

const handleSelectLawyer = (lawyer: VerifiedRepresentative) => {
  selectedLawyer.value = lawyer;
};

const submitRepresentationRequest = async () => {
  if (!caseData.value || !selectedLawyer.value) return;
  submittingRequest.value = true;
  try {
    await tribunalStore.requestRepresentation(
      caseData.value.id,
      selectedLawyer.value.id,
      requestMessage.value.trim() || undefined
    );
    showDirectoryModal.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Representation Requested',
      text: `Your representation request has been sent to ${selectedLawyer.value.name}. You will be notified when they respond.`,
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Request Failed',
      text: err?.response?.data?.message || 'Failed to submit representation request.',
    });
  } finally {
    submittingRequest.value = false;
  }
};

const handleEndRepresentation = async () => {
  if (!caseData.value) return;
  const result = await Swal.fire({
    title: 'End Legal Representation?',
    text: 'This will terminate active representation and close confidential privileged communications for this case.',
    icon: 'warning',
    input: 'textarea',
    inputPlaceholder: 'Reason for ending representation (optional)...',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, End Representation',
  });

  if (result.isConfirmed) {
    try {
      await tribunalStore.endRepresentation(caseData.value.id, result.value || undefined);
      await Swal.fire({
        icon: 'success',
        title: 'Representation Concluded',
        text: 'The legal representation has been formally ended.',
      });
    } catch (err: any) {
      await Swal.fire({
        icon: 'error',
        title: 'Action Failed',
        text: err?.response?.data?.message || 'Failed to end representation.',
      });
    }
  }
};

// Batch 5: Case Room Computed & Handlers
const caseRoom = computed<TribunalCaseRoom | null>(() => tribunalStore.caseRoom);
const caseRoomMessages = computed<TribunalCaseMessage[]>(() => tribunalStore.caseRoomMessages);

const filteredCaseRoomMessages = computed(() => {
  if (caseRoomFilter.value === 'notices') {
    return caseRoomMessages.value.filter((m) => m.message_type === 'procedural_notice');
  }
  if (caseRoomFilter.value === 'questions') {
    return caseRoomMessages.value.filter((m) => m.message_type === 'adjudicator_question');
  }
  return caseRoomMessages.value;
});

const getRoleBadgeClass = (role?: string): string => {
  switch (role) {
    case 'complainant':
      return 'bg-primary-subtle text-primary border border-primary-subtle';
    case 'respondent':
      return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    case 'complainant_representative':
      return 'bg-info-subtle text-info-emphasis border border-info-subtle';
    case 'respondent_representative':
      return 'bg-purple-subtle text-purple border border-purple-subtle';
    case 'adjudicator':
      return 'bg-dark-subtle text-dark border border-secondary-subtle';
    default:
      return 'bg-secondary-subtle text-secondary border';
  }
};

const canRespondToQuestion = (question: TribunalCaseMessage): boolean => {
  if (isAcceptedJuror.value) return false;
  if (question.target_side === 'both') return isParty.value || isRepresentative.value;

  const representedSide = caseData.value?.representation?.my_represented_party;
  if (question.target_side === 'complainant') {
    return isComplainant.value || (isRepresentative.value && representedSide === 'complainant');
  }
  if (question.target_side === 'respondent') {
    return isRespondent.value || (isRepresentative.value && representedSide === 'respondent');
  }
  return false;
};

const handleSendCaseRoomMessage = async () => {
  if (!caseData.value || !caseRoomMessageBody.value.trim()) return;
  sendingRoomMessage.value = true;
  try {
    await tribunalStore.sendCaseRoomMessage(
      caseData.value.id,
      caseRoomMessageBody.value.trim(),
      selectedEvidenceId.value || undefined
    );
    caseRoomMessageBody.value = '';
    selectedEvidenceId.value = null;
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Message Failed',
      text: err?.response?.data?.message || 'Failed to send message to Case Room.',
    });
  } finally {
    sendingRoomMessage.value = false;
  }
};

const handlePostProceduralNotice = async () => {
  if (!caseData.value || !noticeBody.value.trim()) return;
  postingNotice.value = true;
  try {
    await tribunalStore.postProceduralNotice(caseData.value.id, noticeBody.value.trim());
    showNoticeModal.value = false;
    noticeBody.value = '';
    await Swal.fire({
      icon: 'success',
      title: 'Procedural Notice Issued',
      text: 'The formal procedural notice has been posted to the shared Case Room.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Notice Failed',
      text: err?.response?.data?.message || 'Failed to post procedural notice.',
    });
  } finally {
    postingNotice.value = false;
  }
};

const handleAskQuestion = async () => {
  if (!caseData.value || !questionBody.value.trim()) return;
  askingQuestion.value = true;
  try {
    await tribunalStore.askAdjudicatorQuestion(
      caseData.value.id,
      questionBody.value.trim(),
      questionTargetSide.value
    );
    showQuestionModal.value = false;
    questionBody.value = '';
    questionTargetSide.value = 'both';
    await Swal.fire({
      icon: 'success',
      title: 'Question Submitted',
      text: 'Your question has been directed to the specified parties in the Case Room.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed to Post Question',
      text: err?.response?.data?.message || 'Failed to submit adjudicator question.',
    });
  } finally {
    askingQuestion.value = false;
  }
};

const startReplyingToQuestion = (questionId: number) => {
  replyingToQuestionId.value = questionId;
  replyBody.value = '';
};

const handleRespondToQuestion = async (questionId: number) => {
  if (!caseData.value || !replyBody.value.trim()) return;
  submittingReply.value = true;
  try {
    await tribunalStore.respondToQuestion(caseData.value.id, questionId, replyBody.value.trim());
    replyingToQuestionId.value = null;
    replyBody.value = '';
    await Swal.fire({
      icon: 'success',
      title: 'Response Submitted',
      text: 'Your response to the Adjudicator has been recorded on the procedural record.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed to Respond',
      text: err?.response?.data?.message || 'Failed to submit response.',
    });
  } finally {
    submittingReply.value = false;
  }
};

// Batch 5: Mediation Computed & Handlers
const mediation = computed<TribunalMediation | null>(() => tribunalStore.mediation);

const mediationStatusBadge = computed(() => {
  if (!mediation.value) return null;
  switch (mediation.value.status) {
    case 'offered':
    case 'awaiting_consent':
      return { label: 'Consent Needed', class: 'bg-warning text-dark' };
    case 'active':
      return { label: 'Active', class: 'bg-success text-white' };
    case 'settled':
      return { label: 'Settled', class: 'bg-primary text-white' };
    case 'failed':
      return { label: 'Failed', class: 'bg-danger text-white' };
    case 'declined':
      return { label: 'Declined', class: 'bg-secondary text-white' };
    default:
      return { label: mediation.value.status, class: 'bg-secondary text-white' };
  }
});

const canActOnConsent = computed(() => {
  return !!mediation.value?.can_consent;
});

const handleRequestMediation = async () => {
  if (!caseData.value) return;
  const result = await Swal.fire({
    title: 'Request Voluntary Mediation?',
    text: 'This will initiate a request for structured settlement negotiation. Both you and the opposing party must consent before mediation commences.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Request Mediation',
    confirmButtonColor: '#0d6efd',
  });
  if (!result.isConfirmed) return;

  requestingMediation.value = true;
  try {
    await tribunalStore.requestMediation(caseData.value.id);
    await Swal.fire({
      icon: 'success',
      title: 'Mediation Requested',
      text: 'Mediation request submitted. The opposing party has been notified to provide consent.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Request Failed',
      text: err?.response?.data?.message || 'Failed to request mediation.',
    });
  } finally {
    requestingMediation.value = false;
  }
};

const handleOfferMediation = async () => {
  if (!caseData.value) return;
  const result = await Swal.fire({
    title: 'Offer Voluntary Mediation?',
    text: 'As the presiding Adjudicator, you will offer voluntary mediation to both parties. If both accept, the case will enter the Mediation phase.',
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Offer Mediation',
    confirmButtonColor: '#0d6efd',
  });
  if (!result.isConfirmed) return;

  offeringMediation.value = true;
  try {
    await tribunalStore.offerMediation(caseData.value.id);
    await Swal.fire({
      icon: 'success',
      title: 'Mediation Offered',
      text: 'Mediation has been offered to both parties. Awaiting their consent.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Offer Failed',
      text: err?.response?.data?.message || 'Failed to offer mediation.',
    });
  } finally {
    offeringMediation.value = false;
  }
};

const handleRespondMediation = async (response: 'accepted' | 'declined') => {
  if (!mediation.value) return;
  const isAcc = response === 'accepted';
  const result = await Swal.fire({
    title: isAcc ? 'Accept Mediation Offer?' : 'Decline Mediation Offer?',
    text: isAcc
      ? 'By accepting, you agree to engage in good-faith settlement discussions in the Mediation Workspace.'
      : 'By declining, the case will continue along the standard Tribunal adjudication track.',
    icon: isAcc ? 'question' : 'warning',
    showCancelButton: true,
    confirmButtonText: isAcc ? 'Accept Mediation' : 'Decline Mediation',
    confirmButtonColor: isAcc ? '#198754' : '#dc3545',
  });
  if (!result.isConfirmed) return;

  respondingToConsent.value = true;
  try {
    await tribunalStore.respondToMediation(mediation.value.id, response);
    await Swal.fire({
      icon: 'success',
      title: `Mediation ${isAcc ? 'Accepted' : 'Declined'}`,
      text: isAcc
        ? 'Your acceptance has been recorded. Once both parties accept, the Mediation Workspace opens.'
        : 'You have declined mediation. The dispute will proceed through Tribunal adjudication.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err?.response?.data?.message || 'Failed to submit mediation response.',
    });
  } finally {
    respondingToConsent.value = false;
  }
};

const openCreateProposalModal = () => {
  isCountering.value = false;
  parentProposalId.value = null;
  proposalTerms.value = '';
  showProposalModal.value = true;
};

const openCounterProposalModal = (parent: TribunalSettlementProposal) => {
  isCountering.value = true;
  parentProposalId.value = parent.id;
  proposalTerms.value = parent.terms;
  showProposalModal.value = true;
};

const handleSubmitProposal = async () => {
  if (!mediation.value || !proposalTerms.value.trim()) return;
  submittingProposal.value = true;
  try {
    if (isCountering.value && parentProposalId.value) {
      await tribunalStore.counterSettlementProposal(
        mediation.value.id,
        parentProposalId.value,
        proposalTerms.value.trim()
      );
    } else {
      await tribunalStore.createSettlementProposal(
        mediation.value.id,
        proposalTerms.value.trim()
      );
    }
    showProposalModal.value = false;
    proposalTerms.value = '';
    parentProposalId.value = null;
    isCountering.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Proposal Submitted',
      text: 'Your settlement proposal has been recorded and submitted to the opposing party.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Submission Failed',
      text: err?.response?.data?.message || 'Failed to submit settlement proposal.',
    });
  } finally {
    submittingProposal.value = false;
  }
};

const handleAcceptProposal = async (proposal: TribunalSettlementProposal) => {
  const result = await Swal.fire({
    title: 'Accept Settlement Proposal (Binding)?',
    html: `
      <p class="text-danger fw-bold">EXPLICIT LEGAL ACCEPTANCE REQUIRED</p>
      <p class="small text-muted">Accepting this proposal will finalize a legally binding Settlement Agreement (v${proposal.version_number}). Once both parties accept, the dispute is formally settled.</p>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Explicitly Accept Terms',
    confirmButtonColor: '#198754',
  });
  if (!result.isConfirmed) return;

  try {
    const res = await tribunalStore.acceptSettlementProposal(proposal.id);
    if (res.settled) {
      await Swal.fire({
        icon: 'success',
        title: 'Settlement Finalized!',
        text: `Both parties have explicitly accepted the terms. Settlement Agreement ${res.agreement?.agreement_number || ''} has been finalized.`,
      });
    } else {
      await Swal.fire({
        icon: 'success',
        title: 'Proposal Accepted',
        text: 'Your acceptance has been registered. The settlement will be finalized once the opposing party accepts.',
      });
    }
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Acceptance Failed',
      text: err?.response?.data?.message || 'Failed to accept proposal.',
    });
  }
};

const handleRejectProposal = async (proposal: TribunalSettlementProposal) => {
  const result = await Swal.fire({
    title: 'Reject Settlement Proposal?',
    text: `This will mark proposal v${proposal.version_number} as rejected. You may submit a counter-proposal or continue discussions.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Reject Proposal',
    confirmButtonColor: '#dc3545',
  });
  if (!result.isConfirmed) return;

  try {
    await tribunalStore.rejectSettlementProposal(proposal.id);
    await Swal.fire({
      icon: 'info',
      title: 'Proposal Rejected',
      text: 'The settlement proposal has been rejected.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err?.response?.data?.message || 'Failed to reject proposal.',
    });
  }
};

const handleEndMediation = async () => {
  if (!mediation.value) return;
  const result = await Swal.fire({
    title: 'Declare Mediation Failed / Conclude?',
    text: 'This will terminate voluntary mediation without settlement and restore the dispute to its prior procedural Tribunal track.',
    icon: 'warning',
    input: 'textarea',
    inputPlaceholder: 'Reason for concluding mediation (e.g. parties irreconcilably deadlocked)...',
    inputValidator: (val) => {
      if (!val || val.trim().length < 5) {
        return 'Please provide a clear reason (minimum 5 characters).';
      }
      return null;
    },
    showCancelButton: true,
    confirmButtonText: 'End Mediation',
    confirmButtonColor: '#dc3545',
  });
  if (!result.isConfirmed) return;

  endingMediation.value = true;
  try {
    await tribunalStore.endMediation(mediation.value.id, result.value);
    await Swal.fire({
      icon: 'info',
      title: 'Mediation Concluded',
      text: 'Mediation has concluded without settlement. The case has returned to the Tribunal process.',
    });
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err?.response?.data?.message || 'Failed to conclude mediation.',
    });
  } finally {
    endingMediation.value = false;
  }
};

onMounted(async () => {
  await resolveCurrentUserId();
  await loadCase();
});
</script>

<template>
  <div class="container py-4">
    <!-- Back Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <button
        type="button"
        class="btn btn-outline-secondary btn-sm rounded-pill px-3"
        @click="isRepresentative ? router.push('/tribunal/represented-cases') : (isAcceptedJuror ? router.push('/tribunal/jury') : router.push('/tribunal/cases'))"
      >
        <i class="bi bi-arrow-left me-1" />
        {{ isRepresentative ? 'Back to Represented Cases' : (isAcceptedJuror ? 'Back to Juror Portal' : 'Back to My Cases') }}
      </button>

      <span v-if="isRepresentative" class="badge bg-info-subtle text-info-emphasis border px-3 py-2 rounded-pill">
        <i class="bi bi-briefcase-fill me-1" />
        Authorized Legal Representative ({{ formatStatus(caseData?.representation?.my_represented_party || '') }})
      </span>
      <span v-else-if="isAcceptedJuror" class="badge bg-dark-subtle text-dark border px-3 py-2 rounded-pill">
        <i class="bi bi-person-badge me-1" />
        Viewing as Assigned Tribunal Juror
      </span>
      <span v-else-if="isComplainant" class="badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill">
        <i class="bi bi-person-check me-1" />
        You are the Complainant
      </span>
      <span v-else-if="isRespondent" class="badge bg-warning-subtle text-warning-emphasis border px-3 py-2 rounded-pill">
        <i class="bi bi-person-exclamation me-1" />
        You are the Respondent
      </span>
    </div>

    <!-- Loading State -->
    <div
      v-if="tribunalStore.loading && !caseData"
      class="text-center py-5 my-4 bg-white rounded-4 shadow-sm"
    >
      <div class="spinner-border text-primary" role="status" />
      <p class="mt-3 text-muted">
        Loading case details and evidence...
      </p>
    </div>

    <!-- 403 Unauthorized State -->
    <div
      v-else-if="unauthorizedError"
      class="card shadow-sm border-0 rounded-4 text-center py-5 px-4"
    >
      <div class="card-body">
        <i class="bi bi-shield-lock-fill text-danger fs-1" />
        <h4 class="mt-3 fw-bold text-dark">
          Access Denied (403 Forbidden)
        </h4>
        <p class="text-muted mb-4">
          You are not authorized to view this dispute. Case records and evidence are strictly confidential to involved parties and assigned jurors.
        </p>
        <button
          type="button"
          class="btn btn-primary px-4 rounded-pill"
          @click="router.push('/tribunal/cases')"
        >
          Return to My Cases
        </button>
      </div>
    </div>

    <!-- 404 Not Found State -->
    <div
      v-else-if="notFoundError"
      class="card shadow-sm border-0 rounded-4 text-center py-5 px-4"
    >
      <div class="card-body">
        <i class="bi bi-search text-muted fs-1" />
        <h4 class="mt-3 fw-bold text-dark">
          Case Not Found (404)
        </h4>
        <p class="text-muted mb-4">
          The requested tribunal case does not exist or has been removed.
        </p>
        <button
          type="button"
          class="btn btn-primary px-4 rounded-pill"
          @click="router.push('/tribunal/cases')"
        >
          Return to My Cases
        </button>
      </div>
    </div>

    <!-- General Error State -->
    <div
      v-else-if="tribunalStore.error && !caseData"
      class="alert alert-danger shadow-sm rounded-3"
    >
      {{ tribunalStore.error }}
    </div>

    <!-- Case Details View -->
    <div v-else-if="caseData">
      <!-- Batch 5 Executive Tab Navigation -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-2">
          <ul class="nav nav-pills nav-fill gap-2">
            <li class="nav-item">
              <button
                class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
                :class="{ active: activeTab === 'overview' }"
                @click="activeTab = 'overview'"
              >
                <i class="bi bi-file-earmark-text me-1" />
                Overview
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
                :class="{ active: activeTab === 'evidence' }"
                @click="activeTab = 'evidence'"
              >
                <i class="bi bi-folder2-open me-1" />
                Evidence
                <span v-if="evidenceList.length" class="badge bg-secondary-subtle text-dark ms-1">
                  {{ evidenceList.length }}
                </span>
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
                :class="{ active: activeTab === 'case_room' }"
                @click="activeTab = 'case_room'"
              >
                <i class="bi bi-chat-square-quote-fill me-1" />
                Case Room
                <span v-if="caseRoomMessages.length" class="badge bg-primary-subtle text-primary ms-1">
                  {{ caseRoomMessages.length }}
                </span>
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center position-relative"
                :class="{ active: activeTab === 'mediation' }"
                @click="activeTab = 'mediation'"
              >
                <i class="bi bi-shield-shaded me-1" />
                Mediation
                <span v-if="mediationStatusBadge" class="badge ms-1" :class="mediationStatusBadge.class">
                  {{ mediationStatusBadge.label }}
                </span>
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center position-relative"
                :class="{ active: activeTab === 'hearing' }"
                @click="activeTab = 'hearing'"
              >
                <i class="bi bi-mic me-1" />
                Hearing
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
                :class="{ active: activeTab === 'representation' }"
                @click="activeTab = 'representation'"
              >
                <i class="bi bi-briefcase-fill me-1" />
                Representation
                <span v-if="hasActiveRepresentation" class="badge bg-success-subtle text-success ms-1">Active</span>
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-sm-center"
                :class="{ active: activeTab === 'decision' }"
                @click="activeTab = 'decision'"
              >
                <i class="bi bi-file-earmark-check me-1" />
                Decision
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div class="row g-4">
        <!-- Main Case Information Column -->
        <div class="col-lg-8">
          <!-- Jury Status Notification Banner (Part 16) -->
          <div
            v-if="caseData.jury"
            class="card border-0 rounded-4 shadow-sm mb-4"
            :class="{
              'bg-success-subtle border-success-subtle': caseData.jury.status === 'assigned',
              'bg-info-subtle border-info-subtle': caseData.jury.status === 'selection_in_progress',
              'bg-warning-subtle border-warning-subtle': caseData.jury.status === 'awaiting_assignment'
            }"
          >
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-3">
                <i
                  v-if="caseData.jury.status === 'assigned'"
                  class="bi bi-person-check-fill fs-3 text-success"
                />
                <i
                  v-else-if="caseData.jury.status === 'selection_in_progress'"
                  class="bi bi-hourglass-split fs-3 text-info"
                />
                <i
                  v-else
                  class="bi bi-clock-history fs-3 text-warning-emphasis"
                />

                <div>
                  <h6 class="fw-bold mb-0 text-dark">
                    {{ caseData.jury.label }}
                  </h6>
                  <small class="text-muted">
                    <template v-if="caseData.jury.status === 'assigned'">
                      Juror: <strong class="text-dark">{{ caseData.jury.juror_name }}</strong> ({{ formatStatus(caseData.jury.role || 'juror') }}) &bull; Accepted on {{ formatDateTime(caseData.jury.responded_at) }}
                    </template>
                    <template v-else-if="caseData.jury.status === 'selection_in_progress'">
                      A qualified panel candidate has been summoned and is completing conflict declaration.
                    </template>
                    <template v-else-if="caseData.jury.status === 'awaiting_assignment'">
                      Respondent response submitted; awaiting jury panel allocation.
                    </template>
                    <template v-else>
                      Jury selection will commence once the respondent submits their formal response.
                    </template>
                  </small>
                </div>
              </div>

              <span
                class="badge rounded-pill px-3 py-2"
                :class="{
                  'bg-success text-white': caseData.jury.status === 'assigned',
                  'bg-info text-dark': caseData.jury.status === 'selection_in_progress',
                  'bg-warning text-dark': caseData.jury.status === 'awaiting_assignment',
                  'bg-secondary text-white': caseData.jury.status === 'pending_response'
                }"
              >
                {{ formatStatus(caseData.jury.status) }}
              </span>
            </div>
          </div>

          <!-- TAB 1: OVERVIEW -->
          <div v-show="activeTab === 'overview'">
            <!-- Case Summary Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
              <div>
                <span class="badge bg-light text-dark border me-2">{{ caseData.category }}</span>
                <span class="badge" :class="getStatusBadgeClass(caseData.status)">
                  {{ formatStatus(caseData.status) }}
                </span>
                <span class="badge bg-secondary ms-2">
                  Severity: {{ caseData.severity.toUpperCase() }}
                </span>
              </div>
              <small class="text-muted">
                Submitted: {{ formatDateTime(caseData.submitted_at) }}
              </small>
            </div>

            <h3 class="fw-bold text-dark mb-1">
              {{ caseData.title }}
            </h3>
            <p class="text-primary font-monospace small mb-4">
              {{ caseData.case_number }}
            </p>

            <hr class="my-4" />

            <div class="mb-4">
              <h6 class="fw-bold text-secondary text-uppercase small">
                Dispute Statement / Description
              </h6>
              <div class="bg-light p-3 rounded-3 text-dark mt-2" style="white-space: pre-wrap;">
                {{ caseData.description }}
              </div>
            </div>

            <div>
              <h6 class="fw-bold text-secondary text-uppercase small">
                Requested Resolution
              </h6>
              <div class="bg-light p-3 rounded-3 text-dark mt-2" style="white-space: pre-wrap;">
                {{ caseData.requested_resolution || 'No specific resolution requested.' }}
              </div>
            </div>
          </div>
        </div>

        <!-- Respondent Workflow Section -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
          <div class="card-header bg-light border-0 py-3 px-4">
            <h5 class="fw-bold mb-0 text-dark">
              <i class="bi bi-chat-left-dots me-2 text-primary" />
              Respondent Response & Acknowledgement
            </h5>
          </div>

          <div class="card-body p-4">
            <!-- Acknowledgement Banner -->
            <div
              v-if="hasAcknowledged"
              class="alert alert-success d-flex align-items-center gap-2 mb-4 rounded-3"
            >
              <i class="bi bi-check-circle-fill fs-5" />
              <div>
                <strong>Receipt Acknowledged:</strong>
                {{ formatDateTime(caseData.response?.acknowledgement_at) }}
              </div>
            </div>

            <!-- RESPONDENT VIEW -->
            <template v-if="isRespondent">
              <!-- Step 1: Needs to Acknowledge -->
              <div
                v-if="!hasAcknowledged"
                class="bg-light p-4 rounded-4 text-center mb-3"
              >
                <i class="bi bi-exclamation-circle text-warning fs-1" />
                <h5 class="fw-bold mt-2">
                  Action Required: Acknowledge Dispute
                </h5>
                <p class="text-muted mb-4">
                  Please acknowledge receipt of this external dispute. After acknowledging, you may submit your formal position and written response.
                </p>
                <button
                  type="button"
                  class="btn btn-warning px-4 rounded-pill shadow-sm"
                  :disabled="acknowledging"
                  @click="handleAcknowledge"
                >
                  <span
                    v-if="acknowledging"
                    class="spinner-border spinner-border-sm me-2"
                  />
                  {{ acknowledging ? 'Acknowledging...' : 'Acknowledge Case Receipt' }}
                </button>
              </div>

              <!-- Step 2: Acknowledged, Needs to Respond -->
              <div v-else-if="!hasResponded">
                <h6 class="fw-bold mb-3">
                  Submit Your Formal Response
                </h6>
                
                <div class="mb-3">
                  <label class="form-label fw-semibold">Your Position on the Claim</label>
                  <div class="row g-2">
                    <div class="col-md-4">
                      <div class="form-check p-3 border rounded-3 bg-light">
                        <input
                          id="pos-deny"
                          v-model="position"
                          class="form-check-input ms-0 me-2"
                          type="radio"
                          value="deny"
                        />
                        <label class="form-check-label fw-semibold" for="pos-deny">
                          I deny the claim
                        </label>
                      </div>
                    </div>

                    <div class="col-md-4">
                      <div class="form-check p-3 border rounded-3 bg-light">
                        <input
                          id="pos-partial"
                          v-model="position"
                          class="form-check-input ms-0 me-2"
                          type="radio"
                          value="partially_accept"
                        />
                        <label class="form-check-label fw-semibold" for="pos-partial">
                          I partially accept
                        </label>
                      </div>
                    </div>

                    <div class="col-md-4">
                      <div class="form-check p-3 border rounded-3 bg-light">
                        <input
                          id="pos-accept"
                          v-model="position"
                          class="form-check-input ms-0 me-2"
                          type="radio"
                          value="accept"
                        />
                        <label class="form-check-label fw-semibold" for="pos-accept">
                          I accept the claim
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label fw-semibold">Written Response</label>
                  <textarea
                    v-model="responseText"
                    rows="6"
                    maxlength="10000"
                    class="form-control"
                    placeholder="Provide your side of the dispute clearly and professionally..."
                  />
                  <small class="text-muted d-flex justify-content-between mt-1">
                    <span>Minimum 20 characters.</span>
                    <span>{{ responseText.length }} / 10000</span>
                  </small>
                </div>

                <button
                  type="button"
                  class="btn btn-primary px-4 rounded-pill shadow-sm"
                  :disabled="!canSubmitResponse"
                  @click="handleSubmitResponse"
                >
                  <span
                    v-if="responding"
                    class="spinner-border spinner-border-sm me-2"
                  />
                  {{ responding ? 'Submitting...' : 'Submit Formal Response' }}
                </button>
              </div>

              <!-- Step 3: Response Finalized -->
              <div v-else>
                <div class="mb-3">
                  <span class="text-muted small">Position Taken:</span>
                  <div class="fw-bold fs-5 text-dark mt-1">
                    <span class="badge bg-primary me-2">
                      {{ formatPosition(caseData.response?.position) }}
                    </span>
                  </div>
                </div>

                <div class="mb-3">
                  <span class="text-muted small">Formal Written Statement:</span>
                  <div class="bg-light p-3 rounded-3 text-dark mt-2" style="white-space: pre-wrap;">
                    {{ caseData.response?.response_text }}
                  </div>
                </div>

                <small class="text-muted">
                  Submitted: {{ formatDateTime(caseData.response?.submitted_at) }}
                </small>
              </div>
            </template>

            <!-- COMPLAINANT / JUROR VIEW -->
            <template v-else>
              <div
                v-if="!hasAcknowledged"
                class="text-muted py-4 text-center"
              >
                <i class="bi bi-clock-history fs-2 text-warning mb-2 d-block" />
                Awaiting receipt acknowledgement from the respondent.
              </div>

              <div
                v-else-if="!hasResponded"
                class="text-muted py-4 text-center"
              >
                <i class="bi bi-hourglass-split fs-2 text-info mb-2 d-block" />
                The respondent has acknowledged the case and is preparing a formal response.
              </div>

              <div v-else>
                <div class="mb-3">
                  <span class="text-muted small">Respondent's Stated Position:</span>
                  <div class="fw-bold fs-5 text-dark mt-1">
                    <span class="badge bg-info text-dark">
                      {{ formatPosition(caseData.response?.position) }}
                    </span>
                  </div>
                </div>

                <div class="mb-3">
                  <span class="text-muted small">Respondent's Written Statement:</span>
                  <div class="bg-light p-3 rounded-3 text-dark mt-2" style="white-space: pre-wrap;">
                    {{ caseData.response?.response_text }}
                  </div>
                </div>

                <small class="text-muted">
                  Submitted: {{ formatDateTime(caseData.response?.submitted_at) }}
                </small>
              </div>
            </template>
          </div>
        </div>

        <!-- Quick Summary Cards for Case Room & Mediation -->
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-chat-square-quote-fill text-primary fs-5" />
                  <strong class="text-dark">Shared Case Room</strong>
                </div>
                <span class="badge bg-primary-subtle text-primary rounded-pill">
                  {{ caseRoomMessages.length }} Messages
                </span>
              </div>
              <p class="text-muted small mb-3">
                Official procedural dialogue, adjudicator notices, and structured questions.
              </p>
              <button
                type="button"
                class="btn btn-outline-primary btn-sm rounded-pill mt-auto"
                @click="activeTab = 'case_room'"
              >
                Enter Case Room
              </button>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-shield-shaded text-success fs-5" />
                  <strong class="text-dark">Mediation & Settlement</strong>
                </div>
                <span v-if="mediationStatusBadge" class="badge rounded-pill" :class="mediationStatusBadge.class">
                  {{ mediationStatusBadge.label }}
                </span>
                <span v-else class="badge bg-secondary-subtle text-dark rounded-pill">
                  Available
                </span>
              </div>
              <p class="text-muted small mb-3">
                Voluntary settlement negotiations and legally binding settlement execution.
              </p>
              <button
                type="button"
                class="btn btn-outline-success btn-sm rounded-pill mt-auto"
                @click="activeTab = 'mediation'"
              >
                Open Mediation Workspace
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: EVIDENCE -->
      <div v-show="activeTab === 'evidence'">
        <!-- EVIDENCE MANAGEMENT SECTION (Part 8) -->
        <div class="card shadow-sm border-0 rounded-4">
          <div class="card-header bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-paperclip me-2 text-primary" />
                Evidence Registry
                <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-2">
                  {{ evidenceList.length }}
                </span>
              </h5>
            </div>
            <div>
              <button
                v-if="isParty || isRepresentative"
                type="button"
                class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm"
                @click="openAddEvidenceModal"
              >
                <i class="bi bi-plus-lg me-1" />
                Add Evidence
              </button>
            </div>
          </div>

          <div class="card-body p-4">
            <!-- Empty State -->
            <div
              v-if="evidenceList.length === 0"
              class="text-center py-5 bg-light rounded-4"
            >
              <i class="bi bi-folder2-open text-muted fs-1 mb-2 d-block" />
              <h6 class="fw-bold text-dark">
                No Evidence Submitted Yet
              </h6>
              <p class="text-muted small mb-3">
                Parties may upload relevant documents, images, audio, video recordings, or statements to support their claims.
              </p>
              <button
                v-if="isParty || isRepresentative"
                type="button"
                class="btn btn-outline-primary btn-sm rounded-pill px-4"
                @click="openAddEvidenceModal"
              >
                Upload First Evidence Item
              </button>
            </div>

            <!-- Evidence Cards -->
            <div v-else class="d-flex flex-column gap-3">
              <div
                v-for="item in evidenceList"
                :key="item.id"
                class="border rounded-4 p-3 bg-white hover-card transition"
              >
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary font-monospace px-2 py-1 rounded">
                      {{ item.evidence_number }}
                    </span>
                    <i :class="['bi fs-5', getEvidenceTypeIcon(item.type)]" />
                    <h6 class="fw-bold mb-0 text-dark">
                      {{ item.title }}
                    </h6>
                  </div>

                  <div class="d-flex align-items-center gap-2">
                    <span
                      class="badge px-3 py-1 rounded-pill"
                      :class="getEvidenceBadgeClass(item.status)"
                    >
                      {{ formatStatus(item.status) }}
                    </span>
                  </div>
                </div>

                <!-- Description / Statement -->
                <p v-if="item.description" class="text-muted small mb-2 bg-light p-2 rounded-2" style="white-space: pre-wrap;">
                  {{ item.description }}
                </p>

                <!-- External URL -->
                <div v-if="item.external_url" class="mb-2 small">
                  <i class="bi bi-link-45deg me-1 text-primary" />
                  <a :href="item.external_url" target="_blank" rel="noopener noreferrer" class="text-primary fw-medium text-break">
                    {{ item.external_url }}
                  </a>
                </div>

                <!-- File details & Meta -->
                <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 border-top gap-2 text-muted small">
                  <div>
                    <span>Submitted by <strong>{{ item.uploaded_by.name }}</strong></span>
                    <span v-if="item.uploaded_by.is_current_user" class="badge bg-secondary-subtle text-dark ms-1">You</span>
                    &bull; <span>{{ formatDateTime(item.submitted_at) }}</span>
                    <span v-if="item.has_file" class="ms-2">
                      <i class="bi bi-file-earmark-check text-success me-1" />
                      {{ item.original_filename }} ({{ formatFileSize(item.file_size) }})
                    </span>
                  </div>

                  <div class="d-flex gap-2">
                    <!-- Download/View File -->
                    <button
                      v-if="item.has_file"
                      type="button"
                      class="btn btn-outline-primary btn-sm rounded-pill px-3"
                      :disabled="downloadingId === item.id"
                      @click="handleDownloadEvidence(item)"
                    >
                      <span
                        v-if="downloadingId === item.id"
                        class="spinner-border spinner-border-sm me-1"
                      />
                      <i v-else class="bi bi-download me-1" />
                      Download File
                    </button>

                    <!-- Challenge Evidence Button (Only if allowed) -->
                    <button
                      v-if="item.can_challenge && isParty && !item.uploaded_by.is_current_user"
                      type="button"
                      class="btn btn-outline-danger btn-sm rounded-pill px-3"
                      :disabled="challengingId === item.id"
                      @click="handleChallengeEvidence(item)"
                    >
                      <span
                        v-if="challengingId === item.id"
                        class="spinner-border spinner-border-sm me-1"
                      />
                      <i v-else class="bi bi-shield-x me-1" />
                      Challenge
                    </button>
                  </div>
                </div>

                <!-- Challenges Display -->
                <div
                  v-if="item.challenges && item.challenges.length > 0"
                  class="mt-3 p-3 bg-danger-subtle rounded-3 border border-danger-subtle small"
                >
                  <div class="fw-bold text-danger mb-2 d-flex align-items-center gap-1">
                    <i class="bi bi-shield-exclamation" />
                    Evidence Challenges Filed:
                  </div>
                  <div
                    v-for="ch in item.challenges"
                    :key="ch.id"
                    class="mb-2 pb-2 border-bottom border-danger-subtle last-no-border"
                  >
                    <div class="d-flex justify-content-between text-dark">
                      <strong>{{ ch.challenged_by.name }}</strong>
                      <span class="text-muted">{{ formatDateTime(ch.created_at) }}</span>
                    </div>
                    <div class="text-dark mt-1">
                      <em>"{{ ch.reason }}"</em>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: SHARED CASE ROOM (Batch 5) -->
      <div v-show="activeTab === 'case_room'">
        <!-- Case Room Header Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
          <div class="card-body p-4 bg-primary-subtle text-primary-emphasis">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                  <i class="bi bi-chat-square-quote-fill fs-4" />
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold mb-0 text-dark">
                      Shared Tribunal Case Room
                    </h4>
                    <span class="badge bg-success rounded-pill px-3 py-1">Active</span>
                  </div>
                  <p class="text-muted small mb-0 mt-1">
                    Official immutable procedural record. Messages, questions, and notices are visible to both parties, legal counsel, and the presiding Tribunal Jury Panel.
                  </p>
                </div>
              </div>

              <!-- Adjudicator Controls -->
              <div v-if="isAcceptedJuror" class="d-flex gap-2">
                <button
                  type="button"
                  class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm text-dark fw-semibold"
                  @click="showNoticeModal = true"
                >
                  <i class="bi bi-pin-angle-fill me-1" />
                  Post Notice
                </button>
                <button
                  type="button"
                  class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm"
                  @click="showQuestionModal = true"
                >
                  <i class="bi bi-question-circle-fill me-1" />
                  Ask Question
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Message Composer Card -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
          <div class="card-body p-3">
            <div class="mb-3">
              <textarea
                v-model="caseRoomMessageBody"
                class="form-control rounded-3"
                rows="3"
                placeholder="Post a message or procedural communication to the Case Room..."
                maxlength="5000"
              ></textarea>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <!-- Related Evidence Select -->
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-link-45deg text-muted fs-5" />
                <select
                  v-model="selectedEvidenceId"
                  class="form-select form-select-sm rounded-pill"
                  style="max-width: 280px;"
                >
                  <option :value="null">No evidence reference</option>
                  <option
                    v-for="ev in evidenceList"
                    :key="ev.id"
                    :value="ev.id"
                  >
                    Ref: {{ ev.evidence_number }} - {{ ev.title.substring(0, 30) }}...
                  </option>
                </select>
              </div>

              <button
                type="button"
                class="btn btn-primary rounded-pill px-4 shadow-sm"
                :disabled="!caseRoomMessageBody.trim() || sendingRoomMessage"
                @click="handleSendCaseRoomMessage"
              >
                <span
                  v-if="sendingRoomMessage"
                  class="spinner-border spinner-border-sm me-2"
                />
                <i v-else class="bi bi-send-fill me-1" />
                {{ sendingRoomMessage ? 'Sending...' : 'Send Message' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Filter Bar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="btn-group btn-group-sm" role="group">
            <button
              type="button"
              class="btn rounded-start-pill px-3"
              :class="caseRoomFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary'"
              @click="caseRoomFilter = 'all'"
            >
              All ({{ caseRoomMessages.length }})
            </button>
            <button
              type="button"
              class="btn px-3"
              :class="caseRoomFilter === 'notices' ? 'btn-warning text-dark' : 'btn-outline-secondary'"
              @click="caseRoomFilter = 'notices'"
            >
              <i class="bi bi-pin-angle-fill me-1" />
              Notices
            </button>
            <button
              type="button"
              class="btn rounded-end-pill px-3"
              :class="caseRoomFilter === 'questions' ? 'btn-info text-dark' : 'btn-outline-secondary'"
              @click="caseRoomFilter = 'questions'"
            >
              <i class="bi bi-question-circle-fill me-1" />
              Questions
            </button>
          </div>

          <small class="text-muted">
            Showing {{ filteredCaseRoomMessages.length }} messages
          </small>
        </div>

        <!-- Messages Stream -->
        <div v-if="filteredCaseRoomMessages.length === 0" class="text-center py-5 bg-white rounded-4 border shadow-sm my-3">
          <i class="bi bi-chat-square-text fs-1 text-muted mb-2 d-block" />
          <h6 class="fw-bold text-dark">No Messages Yet</h6>
          <p class="text-muted small mb-0">
            The Case Room is open. Use the composer above to submit procedural communications.
          </p>
        </div>

        <div v-else class="d-flex flex-column gap-3 mb-4">
          <div
            v-for="msg in filteredCaseRoomMessages"
            :key="msg.id"
            class="card border-0 shadow-sm rounded-4 overflow-hidden"
            :class="{
              'border-start border-4 border-warning bg-warning-subtle': msg.message_type === 'procedural_notice',
              'border-start border-4 border-info bg-light': msg.message_type === 'adjudicator_question',
              'bg-white': msg.message_type !== 'procedural_notice' && msg.message_type !== 'adjudicator_question'
            }"
          >
            <div class="card-body p-4">
              <!-- Header -->
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="fw-bold text-dark fs-6">{{ msg.sender_name }}</span>
                  <span class="badge rounded-pill px-2 py-1" :class="getRoleBadgeClass(msg.sender_case_role)">
                    {{ msg.sender_role_label }}
                  </span>
                  <span v-if="msg.message_type === 'procedural_notice'" class="badge bg-warning text-dark rounded-pill">
                    <i class="bi bi-pin-angle-fill me-1" />
                    Procedural Notice
                  </span>
                  <span v-else-if="msg.message_type === 'adjudicator_question'" class="badge bg-info text-dark rounded-pill">
                    <i class="bi bi-question-circle-fill me-1" />
                    {{ msg.sender_case_role === 'jury_panel' ? 'Jury Panel Question' : 'Adjudicator Question' }}
                  </span>
                </div>

                <small class="text-muted">
                  {{ formatDateTime(msg.created_at) }}
                </small>
              </div>

              <!-- Target Side for Questions -->
              <div v-if="msg.target_side" class="mb-2">
                <span class="badge bg-light text-dark border">
                  Target: <strong class="text-capitalize">{{ msg.target_side === 'both' ? 'Both Parties' : msg.target_side }}</strong>
                </span>
              </div>

              <!-- Body -->
              <div class="text-dark mt-2" style="white-space: pre-wrap;">
                {{ msg.body }}
              </div>

              <!-- Related Evidence Chip -->
              <div v-if="msg.related_evidence" class="mt-3">
                <div class="d-inline-flex align-items-center gap-2 bg-light border rounded-pill px-3 py-1 small">
                  <i class="bi bi-paperclip text-primary" />
                  <span>Referenced Evidence:</span>
                  <strong class="font-monospace text-primary">{{ msg.related_evidence.evidence_number }}</strong>
                  <span class="text-muted">- {{ msg.related_evidence.title }}</span>
                </div>
              </div>

              <!-- Threaded Responses to Adjudicator Questions -->
              <div v-if="msg.message_type === 'adjudicator_question'" class="mt-4 pt-3 border-top">
                <h6 class="fw-bold text-secondary small text-uppercase mb-3">
                  <i class="bi bi-chat-left-text me-1" />
                  Procedural Responses ({{ msg.responses?.length || 0 }})
                </h6>

                <div v-if="msg.responses && msg.responses.length > 0" class="d-flex flex-column gap-2 mb-3">
                  <div
                    v-for="resp in msg.responses"
                    :key="resp.id"
                    class="bg-white border rounded-3 p-3 ms-3"
                  >
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-return-right text-primary" />
                        <strong class="text-dark small">{{ resp.sender_name }}</strong>
                        <span class="badge rounded-pill px-2 py-1 small" :class="getRoleBadgeClass(resp.sender_case_role)">
                          {{ resp.sender_role_label }}
                        </span>
                      </div>
                      <small class="text-muted">{{ formatDateTime(resp.created_at) }}</small>
                    </div>
                    <div class="text-dark small mt-1 ps-4" style="white-space: pre-wrap;">
                      {{ resp.body }}
                    </div>
                  </div>
                </div>

                <!-- Inline Response Button & Form -->
                <div v-if="canRespondToQuestion(msg)">
                  <div v-if="replyingToQuestionId === msg.id" class="ms-3 bg-white p-3 border rounded-3 shadow-sm">
                    <label class="form-label fw-bold small text-dark mb-1">
                      {{ msg.sender_case_role === 'jury_panel' ? 'Your Formal Response to Tribunal Jury Panel' : 'Your Formal Response to Adjudicator' }}
                    </label>
                    <textarea
                      v-model="replyBody"
                      class="form-control rounded-3 form-control-sm mb-2"
                      rows="3"
                      placeholder="State your formal clarification or answer on behalf of your party..."
                      maxlength="5000"
                    ></textarea>
                    <div class="d-flex justify-content-end gap-2">
                      <button
                        type="button"
                        class="btn btn-light btn-sm rounded-pill px-3"
                        :disabled="submittingReply"
                        @click="replyingToQuestionId = null"
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        class="btn btn-primary btn-sm rounded-pill px-3"
                        :disabled="!replyBody.trim() || submittingReply"
                        @click="handleRespondToQuestion(msg.id)"
                      >
                        <span v-if="submittingReply" class="spinner-border spinner-border-sm me-1" />
                        Submit Response
                      </button>
                    </div>
                  </div>
                  <button
                    v-else
                    type="button"
                    class="btn btn-outline-primary btn-sm rounded-pill px-3 mt-1"
                    @click="startReplyingToQuestion(msg.id)"
                  >
                    <i class="bi bi-reply-fill me-1" />
                    Respond to Question
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 4: MEDIATION WORKSPACE (Batch 5) -->
      <div v-show="activeTab === 'mediation'">
        <!-- SETTLED STATE -->
        <div v-if="caseData?.status === 'settled' || mediation?.status === 'settled'" class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
          <div class="card-body p-4 bg-success-subtle text-success-emphasis border-start border-4 border-success">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="avatar bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                <i class="bi bi-shield-check fs-2" />
              </div>
              <div>
                <h4 class="fw-bold mb-0 text-success">
                  Settlement Agreement Finalized
                </h4>
                <span class="badge bg-success font-monospace px-3 py-1 rounded-pill mt-1">
                  {{ mediation?.settlement_agreement?.agreement_number || caseData?.settlement_agreement?.agreement_number || 'MIB-SET-EXECUTED' }}
                </span>
              </div>
            </div>

            <p class="text-dark small mb-3">
              Both principal parties have explicitly accepted the terms below. This binding settlement agreement is recorded as the final resolution for this dispute.
            </p>

            <!-- Finalized Terms Snapshot Box -->
            <div class="bg-white p-3 rounded-3 border shadow-sm mb-3">
              <h6 class="fw-bold text-secondary text-uppercase small mb-2">Final Agreed Terms Snapshot</h6>
              <div class="text-dark" style="white-space: pre-wrap;">
                {{ mediation?.settlement_agreement?.terms_snapshot || 'Settlement terms executed.' }}
              </div>
            </div>

            <div class="row g-2 text-muted small">
              <div class="col-md-4">
                <strong>Complainant Accepted:</strong><br />
                {{ formatDateTime(mediation?.settlement_agreement?.complainant_accepted_at) }}
              </div>
              <div class="col-md-4">
                <strong>Respondent Accepted:</strong><br />
                {{ formatDateTime(mediation?.settlement_agreement?.respondent_accepted_at) }}
              </div>
              <div class="col-md-4">
                <strong>Agreement Finalized:</strong><br />
                {{ formatDateTime(mediation?.settlement_agreement?.finalized_at) }}
              </div>
            </div>
          </div>
        </div>

        <!-- NO ACTIVE MEDIATION (Not offered or requested yet) -->
        <div v-else-if="!mediation || mediation.status === 'failed' || mediation.status === 'declined'" class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-body p-5 text-center">
            <div class="avatar bg-primary-subtle text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
              <i class="bi bi-people-fill fs-2" />
            </div>
            <h4 class="fw-bold text-dark">
              Voluntary Dispute Mediation
            </h4>
            <p class="text-muted mx-auto mb-4" style="max-width: 580px;">
              Mediation is a confidential, voluntary process allowing the complainant and respondent to negotiate a settlement prior to Tribunal hearings. Initiation requires mutual consent from both principal parties.
            </p>

            <!-- Outcome note if previous ended -->
            <div v-if="mediation && (mediation.status === 'failed' || mediation.status === 'declined')" class="alert alert-secondary d-inline-block text-start mb-4 py-2 px-3 small">
              <i class="bi bi-info-circle me-1" />
              Prior mediation concluded: <strong>{{ mediation.failure_reason || formatStatus(mediation.status) }}</strong>. Dispute reverted to Tribunal track.
            </div>

            <div class="d-flex justify-content-center gap-3">
              <button
                v-if="isParty"
                type="button"
                class="btn btn-primary rounded-pill px-4 shadow-sm"
                :disabled="requestingMediation"
                @click="handleRequestMediation"
              >
                <span v-if="requestingMediation" class="spinner-border spinner-border-sm me-2" />
                <i v-else class="bi bi-handshake-fill me-1" />
                Request Voluntary Mediation
              </button>

              <button
                v-if="isAcceptedJuror"
                type="button"
                class="btn btn-warning rounded-pill px-4 shadow-sm text-dark fw-semibold"
                :disabled="offeringMediation"
                @click="handleOfferMediation"
              >
                <span v-if="offeringMediation" class="spinner-border spinner-border-sm me-2" />
                <i v-else class="bi bi-award-fill me-1" />
                Offer Mediation to Parties
              </button>
            </div>
          </div>
        </div>

        <!-- AWAITING CONSENT STATE -->
        <div v-else-if="mediation.status === 'offered' || mediation.status === 'awaiting_consent'" class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-header bg-warning-subtle text-warning-emphasis border-0 py-3 px-4">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-hourglass-split fs-4" />
              <div>
                <h5 class="fw-bold mb-0">Mediation Consent Pending</h5>
                <small>Both principal parties must explicitly accept before the workspace opens.</small>
              </div>
            </div>
          </div>

          <div class="card-body p-4">
            <!-- Party Consent Status Cards -->
            <div class="row g-3 mb-4">
              <div v-for="c in mediation.consents" :key="c.id" class="col-md-6">
                <div class="border rounded-4 p-3 d-flex justify-content-between align-items-center bg-light">
                  <div>
                    <span class="badge rounded-pill px-2 py-1 mb-1" :class="c.side === 'complainant' ? 'bg-primary' : 'bg-warning text-dark'">
                      {{ c.side === 'complainant' ? 'Complainant' : 'Respondent' }}
                    </span>
                    <div class="fw-bold text-dark">{{ c.user_name }}</div>
                    <small class="text-muted">
                      {{ c.responded_at ? 'Responded: ' + formatDateTime(c.responded_at) : 'Awaiting decision' }}
                    </small>
                  </div>

                  <div>
                    <span v-if="c.response === 'accepted'" class="badge bg-success rounded-pill px-3 py-2">
                      <i class="bi bi-check-circle-fill me-1" />
                      Accepted
                    </span>
                    <span v-else-if="c.response === 'declined'" class="badge bg-danger rounded-pill px-3 py-2">
                      <i class="bi bi-x-circle-fill me-1" />
                      Declined
                    </span>
                    <span v-else class="badge bg-secondary rounded-pill px-3 py-2">
                      <i class="bi bi-clock me-1" />
                      Pending
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Callout for User if they can respond -->
            <div v-if="canActOnConsent" class="bg-primary-subtle text-primary-emphasis p-4 rounded-4 text-center">
              <h6 class="fw-bold mb-2">Your Consent is Required</h6>
              <p class="small text-muted mb-3">
                Do you agree to enter voluntary mediation to discuss settlement terms?
              </p>
              <div class="d-flex justify-content-center gap-3">
                <button
                  type="button"
                  class="btn btn-success rounded-pill px-4 shadow-sm"
                  :disabled="respondingToConsent"
                  @click="handleRespondMediation('accepted')"
                >
                  <i class="bi bi-check-lg me-1" />
                  Accept Mediation
                </button>
                <button
                  type="button"
                  class="btn btn-outline-danger rounded-pill px-4"
                  :disabled="respondingToConsent"
                  @click="handleRespondMediation('declined')"
                >
                  <i class="bi bi-x-lg me-1" />
                  Decline
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ACTIVE MEDIATION WORKSPACE -->
        <div v-else-if="mediation.status === 'active'">
          <!-- Workspace Toolbar -->
          <div class="card border-0 shadow-sm rounded-4 mb-4 bg-success-subtle text-success-emphasis">
            <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                  <i class="bi bi-shield-shaded fs-4" />
                </div>
                <div>
                  <h5 class="fw-bold mb-0 text-dark">
                    Active Mediation Workspace
                  </h5>
                  <small class="text-muted">
                    Structured settlement negotiations are active. Tribunal Jury Panel observes procedurally.
                  </small>
                </div>
              </div>

              <div class="d-flex gap-2">
                <button
                  v-if="isParty"
                  type="button"
                  class="btn btn-primary rounded-pill px-4 shadow-sm"
                  @click="openCreateProposalModal"
                >
                  <i class="bi bi-plus-circle-fill me-1" />
                  Submit Proposal
                </button>

                <button
                  v-if="isParty || isAcceptedJuror"
                  type="button"
                  class="btn btn-outline-danger btn-sm rounded-pill px-3"
                  :disabled="endingMediation"
                  @click="handleEndMediation"
                >
                  <i class="bi bi-stop-circle me-1" />
                  Declare Deadlock / End
                </button>
              </div>
            </div>
          </div>

          <!-- Settlement Proposals Feed -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-dark mb-0">
              Settlement Proposals & Negotiation History
            </h6>
            <span class="badge bg-secondary-subtle text-dark">
              {{ mediation.proposals?.length || 0 }} Proposals
            </span>
          </div>

          <div v-if="!mediation.proposals || mediation.proposals.length === 0" class="text-center py-5 bg-white rounded-4 border shadow-sm my-3">
            <i class="bi bi-file-earmark-diff fs-1 text-muted mb-2 d-block" />
            <h6 class="fw-bold text-dark">No Proposals Submitted Yet</h6>
            <p class="text-muted small mb-3">
              Mediation is active. Either party may submit an opening settlement proposal.
            </p>
            <button
              v-if="isParty"
              type="button"
              class="btn btn-primary btn-sm rounded-pill px-4"
              @click="openCreateProposalModal"
            >
              Create Opening Proposal
            </button>
          </div>

          <div v-else class="d-flex flex-column gap-3 mb-4">
            <div
              v-for="prop in mediation.proposals"
              :key="prop.id"
              class="card border-0 shadow-sm rounded-4 overflow-hidden"
              :class="{
                'border-start border-4 border-success': prop.status === 'accepted',
                'border-start border-4 border-warning': prop.status === 'pending',
                'border-start border-4 border-secondary': prop.status === 'countered' || prop.status === 'superseded',
                'border-start border-4 border-danger': prop.status === 'rejected'
              }"
            >
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-dark rounded-pill px-3 py-1 font-monospace">
                      Proposal v{{ prop.version_number }}
                    </span>
                    <span class="fw-bold text-dark">{{ prop.proposer_name }}</span>
                    <span class="badge rounded-pill px-2 py-1" :class="prop.proposed_by_side === 'complainant' ? 'bg-primary' : 'bg-warning text-dark'">
                      {{ prop.proposed_by_side === 'complainant' ? 'Complainant' : 'Respondent' }}
                    </span>
                    <span v-if="prop.parent_proposal_id" class="badge bg-light text-muted border small">
                      Counter to previous version
                    </span>
                  </div>

                  <div>
                    <span
                      class="badge rounded-pill px-3 py-1"
                      :class="{
                        'bg-warning text-dark': prop.status === 'pending',
                        'bg-success': prop.status === 'accepted',
                        'bg-secondary': prop.status === 'countered' || prop.status === 'superseded',
                        'bg-danger': prop.status === 'rejected'
                      }"
                    >
                      {{ formatStatus(prop.status) }}
                    </span>
                  </div>
                </div>

                <!-- Terms -->
                <div class="bg-light p-3 rounded-3 my-3 text-dark font-sans" style="white-space: pre-wrap;">
                  {{ prop.terms }}
                </div>

                <!-- Explicit Acceptances Checklist -->
                <div class="d-flex flex-wrap gap-3 align-items-center py-2 border-top border-bottom my-3 small">
                  <div class="d-flex align-items-center gap-1">
                    <i :class="prop.accepted_by_complainant ? 'bi bi-check-circle-fill text-success' : 'bi bi-dash-circle text-muted'" />
                    <span>Complainant Acceptance: <strong>{{ prop.accepted_by_complainant ? 'Accepted' : 'Pending' }}</strong></span>
                  </div>
                  <div class="d-flex align-items-center gap-1">
                    <i :class="prop.accepted_by_respondent ? 'bi bi-check-circle-fill text-success' : 'bi bi-dash-circle text-muted'" />
                    <span>Respondent Acceptance: <strong>{{ prop.accepted_by_respondent ? 'Accepted' : 'Pending' }}</strong></span>
                  </div>
                </div>

                <!-- Actions on Pending Proposal -->
                <div v-if="prop.status === 'pending'" class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2">
                  <small class="text-muted">
                    Created on {{ formatDateTime(prop.created_at) }}
                  </small>

                  <!-- Opposing Principal Party Actions -->
                  <div v-if="isParty && !prop.is_mine" class="d-flex gap-2">
                    <button
                      type="button"
                      class="btn btn-outline-danger btn-sm rounded-pill px-3"
                      @click="handleRejectProposal(prop)"
                    >
                      Reject
                    </button>
                    <button
                      type="button"
                      class="btn btn-outline-primary btn-sm rounded-pill px-3"
                      @click="openCounterProposalModal(prop)"
                    >
                      <i class="bi bi-arrow-repeat me-1" />
                      Counter-Proposal
                    </button>
                    <button
                      type="button"
                      class="btn btn-success btn-sm rounded-pill px-4 shadow-sm"
                      @click="handleAcceptProposal(prop)"
                    >
                      <i class="bi bi-check2-circle me-1" />
                      Explicitly Accept & Settle
                    </button>
                  </div>

                  <div v-else-if="prop.is_mine" class="badge bg-info-subtle text-info-emphasis rounded-pill px-3 py-2 small">
                    <i class="bi bi-hourglass-split me-1" />
                    You submitted this proposal. Awaiting opposing party decision.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: FORMAL HEARING (Step 5) -->
      <div v-show="activeTab === 'hearing'">
        <PartyHearingTab
          :case-id="caseData.id"
          :case-status="caseData.status"
          :case-number="caseData.case_number"
          :user-role="userCaseRole"
          :user-side="userCaseSide"
          @case-updated="tribunalStore.fetchCase(caseData.id)"
        />
      </div>

      <!-- TAB 5: REPRESENTATION WORKSPACE -->
      <div v-show="activeTab === 'representation'">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-header bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
              <i class="bi bi-briefcase-fill me-2 text-primary" />
              Legal Representation Workspace
            </h5>
            <span v-if="hasActiveRepresentation || isRepresentative" class="badge bg-success rounded-pill px-3 py-1">
              Active Retainer
            </span>
          </div>

          <div class="card-body p-4">
            <div v-if="isRepresentative" class="alert alert-info py-3 px-4 rounded-4 mb-4">
              <div class="d-flex align-items-center gap-3">
                <i class="bi bi-patch-check-fill fs-2" />
                <div>
                  <h6 class="fw-bold mb-1">Authorized Legal Counsel</h6>
                  <p class="mb-0 small">
                    You are representing the <strong>{{ formatStatus(caseData.representation?.my_represented_party || '') }}</strong> in this matter. You may consult your client confidentially, view and submit evidence, and participate in procedural Case Room dialogue.
                  </p>
                </div>
              </div>
              <hr />
              <button
                type="button"
                class="btn btn-primary rounded-pill px-4 shadow-sm"
                @click="showChatModal = true"
              >
                <i class="bi bi-chat-dots-fill me-1" />
                Open Confidential Client Chat
              </button>
            </div>

            <div v-else-if="hasActiveRepresentation && activeAssignment" class="border rounded-4 p-4 mb-4 bg-light">
              <div class="d-flex align-items-start gap-3 mb-3">
                <div class="avatar bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                  <i class="bi bi-person-badge-fill fs-3" />
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-1">{{ activeAssignment.representative_name }}</h5>
                  <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                    Verified Attorney-at-Law
                  </span>
                  <div class="text-muted small mt-1">Retained on {{ formatDateTime(activeAssignment.accepted_at) }}</div>
                </div>
              </div>

              <div class="d-flex gap-2 mt-3">
                <button
                  type="button"
                  class="btn btn-primary rounded-pill px-4 shadow-sm"
                  @click="showChatModal = true"
                >
                  <i class="bi bi-shield-lock-fill me-1" />
                  Privileged Attorney Consultation
                </button>
                <button
                  type="button"
                  class="btn btn-outline-danger rounded-pill px-4"
                  @click="handleEndRepresentation"
                >
                  End Representation
                </button>
              </div>
            </div>

            <div v-else-if="isParty" class="text-center py-5 bg-light rounded-4">
              <i class="bi bi-person-plus-fill fs-1 text-primary mb-2 d-block" />
              <h5 class="fw-bold text-dark">No Legal Representative Retained</h5>
              <p class="text-muted small mx-auto mb-4" style="max-width: 500px;">
                You do not currently have legal counsel assigned to this case. You may request representation from verified Attorneys-at-Law to act and submit evidence on your behalf.
              </p>
              <button
                type="button"
                class="btn btn-primary rounded-pill px-4 shadow-sm"
                @click="openDirectoryModal"
              >
                <i class="bi bi-search me-1" />
                Browse Verified Attorneys Directory
              </button>
            </div>

            <div v-else class="text-muted text-center py-4">
              Representation details and attorney-client communications are private to the involved parties.
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 6: DECISION (Step 6) -->
      <div v-show="activeTab === 'decision'">
        <PartyDecisionTab
          :case-id="caseData.id"
          :case-status="caseData.status"
        />
      </div>
    </div>

      <!-- Parties and Metadata Sidebar Column -->
      <div class="col-lg-4">
        <!-- Involved Parties -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
          <div class="card-header bg-light border-0 py-3 px-4">
            <h6 class="fw-bold mb-0 text-dark">
              <i class="bi bi-people me-2 text-primary" />
              Involved Parties
            </h6>
          </div>

          <div class="card-body p-4">
            <!-- Complainant -->
            <div class="mb-4 pb-3 border-bottom">
              <span class="badge bg-primary mb-2">Complainant</span>
              <div class="fw-bold fs-6 text-dark">
                {{ complainantParty?.user?.name || 'Unknown' }}
              </div>
              <small class="text-muted">
                User ID: #{{ complainantParty?.user?.id }}
                <span v-if="isComplainant" class="badge bg-light text-primary border ms-1">You</span>
              </small>
            </div>

            <!-- Respondent -->
            <div>
              <span class="badge bg-warning text-dark mb-2">Respondent</span>
              <div class="fw-bold fs-6 text-dark">
                {{ respondentParty?.user?.name || 'Unknown' }}
              </div>
              <small class="text-muted">
                User ID: #{{ respondentParty?.user?.id }}
                <span v-if="isRespondent" class="badge bg-light text-warning border ms-1">You</span>
              </small>
            </div>
          </div>
        </div>

        <!-- Legal Representation Card (Batch 4) -->
        <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
          <div class="card-header bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
              <i class="bi bi-shield-shaded me-2 text-primary" />
              Legal Representation
            </h6>
            <span
              v-if="hasActiveRepresentation || isRepresentative"
              class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small"
            >
              Active Counsel
            </span>
            <span
              v-else-if="pendingRequest"
              class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1 small"
            >
              Request Pending
            </span>
          </div>

          <div class="card-body p-4">
            <!-- Case 1: Current User is the Legal Representative -->
            <div v-if="isRepresentative">
              <div class="alert alert-info py-2 px-3 mb-3 small d-flex align-items-center gap-2">
                <i class="bi bi-person-badge-fill fs-5" />
                <div>
                  <strong>You are legal counsel</strong> representing the {{ formatStatus(caseData.representation?.my_represented_party || '') }} in this matter.
                </div>
              </div>

              <div class="d-grid gap-2">
                <button
                  type="button"
                  class="btn btn-primary rounded-pill d-flex align-items-center justify-content-center gap-2 shadow-sm"
                  @click="showChatModal = true"
                >
                  <i class="bi bi-chat-dots-fill" />
                  <span>Confidential Client Consultation</span>
                </button>
                <button
                  type="button"
                  class="btn btn-outline-danger btn-sm rounded-pill"
                  @click="handleEndRepresentation"
                >
                  <i class="bi bi-x-circle me-1" />
                  End Representation
                </button>
              </div>
            </div>

            <!-- Case 2: Client has Active Representation -->
            <div v-else-if="hasActiveRepresentation && activeAssignment">
              <div class="d-flex align-items-start gap-3 mb-3">
                <div class="avatar bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                  <i class="bi bi-briefcase-fill fs-5" />
                </div>
                <div>
                  <h6 class="fw-bold mb-0 text-dark">
                    {{ activeAssignment.representative_name }}
                  </h6>
                  <span class="badge bg-primary-subtle text-primary small">
                    Verified Attorney-at-Law
                  </span>
                  <div class="text-muted small mt-1">
                    Retained on {{ formatDateTime(activeAssignment.accepted_at) }}
                  </div>
                </div>
              </div>

              <div class="d-grid gap-2">
                <button
                  type="button"
                  class="btn btn-primary rounded-pill d-flex align-items-center justify-content-center gap-2 shadow-sm"
                  @click="showChatModal = true"
                >
                  <i class="bi bi-shield-lock-fill" />
                  <span>Consult My Attorney (Privileged)</span>
                </button>
                <button
                  type="button"
                  class="btn btn-outline-danger btn-sm rounded-pill"
                  @click="handleEndRepresentation"
                >
                  <i class="bi bi-x-circle me-1" />
                  End Representation
                </button>
              </div>
            </div>

            <!-- Case 3: Pending Representation Request -->
            <div v-else-if="pendingRequest">
              <div class="alert alert-warning-subtle text-dark border-0 p-3 rounded-3 mb-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-hourglass-split text-warning fs-5" />
                  <strong class="small">Pending Attorney Response</strong>
                </div>
                <div class="small text-muted">
                  Request sent to <strong>{{ pendingRequest.representative_name }}</strong> on {{ formatDateTime(pendingRequest.requested_at) }}. You will be notified when they accept or decline.
                </div>
              </div>
            </div>

            <!-- Case 4: Eligible Party without representation -->
            <div v-else-if="isParty">
              <p class="text-muted small mb-3">
                You do not currently have legal counsel assigned to this case. You may request representation from verified Attorneys-at-Law to act and submit evidence on your behalf.
              </p>
              <button
                type="button"
                class="btn btn-outline-primary w-100 rounded-pill d-flex align-items-center justify-content-center gap-2"
                @click="openDirectoryModal"
              >
                <i class="bi bi-person-plus-fill" />
                <span>Find Legal Representative</span>
              </button>
            </div>

            <!-- Case 5: Juror / Other User -->
            <div v-else class="text-muted small">
              Representation details and attorney-client communications are private to the involved parties.
            </div>
          </div>
        </div>

        <!-- Case Metadata Card -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
          <div class="card-header bg-light border-0 py-3 px-4">
            <h6 class="fw-bold mb-0 text-dark">
              <i class="bi bi-info-circle me-2 text-secondary" />
              Case Information
            </h6>
          </div>

          <div class="card-body p-4">
            <div class="mb-3">
              <small class="text-muted d-block">Case Number</small>
              <strong class="font-monospace text-primary">{{ caseData.case_number }}</strong>
            </div>

            <div class="mb-3">
              <small class="text-muted d-block">Category</small>
              <span>{{ caseData.category }}</span>
            </div>

            <div class="mb-3">
              <small class="text-muted d-block">Status</small>
              <span class="badge" :class="getStatusBadgeClass(caseData.status)">
                {{ formatStatus(caseData.status) }}
              </span>
            </div>

            <div>
              <small class="text-muted d-block">Severity Level</small>
              <span class="text-capitalize">{{ caseData.severity }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: ADD EVIDENCE (Part 8) -->
    <div
      v-if="showEvidenceModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0, 0, 0, 0.55);"
    >
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold text-dark">
              <i class="bi bi-cloud-upload me-2 text-primary" />
              Submit Evidence Item
            </h5>
            <button
              type="button"
              class="btn-close"
              :disabled="uploadingEvidence"
              @click="showEvidenceModal = false"
            />
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label fw-semibold">Evidence Type <span class="text-danger">*</span></label>
              <select v-model="evidenceType" class="form-select rounded-3">
                <option value="document">
                  Document (PDF, DOC, DOCX, TXT)
                </option>
                <option value="image">
                  Image (JPG, PNG, WEBP)
                </option>
                <option value="statement">
                  Written Statement / Testimony
                </option>
                <option value="link">
                  External Link / Web Resource
                </option>
                <option value="audio">
                  Audio Recording (MP3, WAV)
                </option>
                <option value="video">
                  Video Recording (MP4)
                </option>
                <option value="other">
                  Other Documentation
                </option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Evidence Title <span class="text-danger">*</span></label>
              <input
                v-model="evidenceTitle"
                type="text"
                class="form-control rounded-3"
                placeholder="e.g. Contract Agreement Page 3, Chat Screenshot..."
                maxlength="255"
              />
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">
                {{ evidenceType === 'statement' ? 'Statement Content *' : 'Description / Notes (Optional)' }}
              </label>
              <textarea
                v-model="evidenceDescription"
                rows="4"
                class="form-control rounded-3"
                :placeholder="evidenceType === 'statement' ? 'Write your complete formal testimony or statement...' : 'Provide context explaining what this evidence demonstrates...'"
                maxlength="5000"
              />
            </div>

            <!-- External URL (If link) -->
            <div v-if="evidenceType === 'link'" class="mb-3">
              <label class="form-label fw-semibold">External URL <span class="text-danger">*</span></label>
              <input
                v-model="evidenceExternalUrl"
                type="url"
                class="form-control rounded-3"
                placeholder="https://example.com/evidence-document"
              />
            </div>

            <!-- File Upload Input (If file type) -->
            <div
              v-if="['image', 'document', 'audio', 'video', 'other'].includes(evidenceType)"
              class="mb-3"
            >
              <label class="form-label fw-semibold">
                Attach File <span v-if="evidenceType !== 'other'" class="text-danger">*</span>
              </label>
              <input
                ref="evidenceFileInput"
                type="file"
                class="form-control rounded-3"
                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt,.mp3,.wav,.mp4"
                @change="onFileSelected"
              />
              <small class="text-muted d-block mt-1">
                Allowed formats: Images (jpg, png, webp), Documents (pdf, doc, docx, txt), Media (mp3, wav, mp4). Max 20MB.
              </small>
            </div>

            <!-- Upload Progress Bar -->
            <div
              v-if="uploadingEvidence && tribunalStore.uploadProgress !== null"
              class="mt-3"
            >
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Uploading evidence to secure storage...</span>
                <span>{{ tribunalStore.uploadProgress }}%</span>
              </div>
              <div class="progress" style="height: 6px;">
                <div
                  class="progress-bar bg-primary progress-bar-striped progress-bar-animated"
                  role="progressbar"
                  :style="{ width: tribunalStore.uploadProgress + '%' }"
                />
              </div>
            </div>
          </div>

          <div class="modal-footer border-0 pt-0 px-4 pb-4">
            <button
              type="button"
              class="btn btn-light rounded-pill px-4"
              :disabled="uploadingEvidence"
              @click="showEvidenceModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4 shadow-sm"
              :disabled="uploadingEvidence"
              @click="handleUploadEvidence"
            >
              <span
                v-if="uploadingEvidence"
                class="spinner-border spinner-border-sm me-2"
              />
              {{ uploadingEvidence ? 'Submitting...' : 'Upload Evidence' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: CONFIDENTIAL CLIENT-REPRESENTATIVE CHAT -->
    <TribunalClientChatModal
      v-if="caseData"
      :show="showChatModal"
      :case-id="caseData.id"
      :case-number="caseData.case_number"
      :case-title="caseData.title"
      @close="showChatModal = false"
    />

    <!-- MODAL: FIND LEGAL REPRESENTATIVE DIRECTORY -->
    <div
      v-if="showDirectoryModal"
      class="modal-backdrop fade show"
    ></div>
    <div
      v-if="showDirectoryModal"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div class="modal-header bg-dark text-white border-bottom border-secondary py-3 px-4">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-shield-check text-warning fs-4" />
              <div>
                <h5 class="modal-title mb-0 fs-6 fw-bold">
                  Verified Legal Representatives Directory
                </h5>
                <small class="text-white-50">
                  Select a verified Attorney-at-Law to request counsel for Case {{ caseData?.case_number }}
                </small>
              </div>
            </div>
            <button
              type="button"
              class="btn-close btn-close-white"
              aria-label="Close"
              @click="showDirectoryModal = false"
            />
          </div>

          <div class="modal-body p-4 bg-light">
            <!-- Search Bar -->
            <div class="input-group mb-4 shadow-sm rounded-pill overflow-hidden bg-white">
              <span class="input-group-text bg-white border-0 ps-3">
                <i class="bi bi-search text-muted" />
              </span>
              <input
                v-model="representativeSearch"
                type="text"
                class="form-control border-0 py-2"
                placeholder="Search attorneys by name, jurisdiction, or licensing..."
                @input="handleSearchRepresentatives"
              />
            </div>

            <!-- Loading State -->
            <div v-if="tribunalStore.loading" class="text-center py-5">
              <div class="spinner-border text-primary" role="status" />
              <div class="text-muted mt-2 small">Searching verified legal directory...</div>
            </div>

            <!-- Empty Directory -->
            <div
              v-else-if="representativesList.length === 0"
              class="text-center py-5 bg-white rounded-4 border"
            >
              <i class="bi bi-person-x fs-1 text-muted mb-2 d-block" />
              <h6 class="fw-bold text-dark">No Available Legal Representatives Found</h6>
              <p class="text-muted small mb-0">
                There are currently no verified Attorneys-at-Law available matching your search criteria.
              </p>
            </div>

            <!-- Representatives List -->
            <div v-else class="d-flex flex-column gap-3">
              <div
                v-for="rep in representativesList"
                :key="rep.id"
                class="card border-0 shadow-sm rounded-4 p-3 transition"
                :class="selectedLawyer?.id === rep.id ? 'border border-2 border-primary bg-primary-subtle' : 'bg-white'"
                style="cursor: pointer;"
                @click="handleSelectLawyer(rep)"
              >
                <div class="d-flex justify-content-between align-items-start">
                  <div class="d-flex align-items-start gap-3">
                    <div
                      class="avatar rounded-circle d-flex align-items-center justify-content-center fw-bold fs-5 text-white"
                      :class="selectedLawyer?.id === rep.id ? 'bg-primary' : 'bg-secondary'"
                      style="width: 48px; height: 48px;"
                    >
                      {{ rep.name ? rep.name.charAt(0).toUpperCase() : 'A' }}
                    </div>
                    <div>
                      <div class="d-flex align-items-center gap-2">
                        <h6 class="fw-bold mb-0 text-dark">
                          {{ rep.name }}
                        </h6>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small">
                          <i class="bi bi-patch-check-fill me-1" />
                          Verified Attorney
                        </span>
                      </div>
                      <div class="text-muted small mt-1">
                        <span>{{ rep.issuing_authority }}</span>
                        <span v-if="rep.years_of_experience"> &bull; {{ rep.years_of_experience }} years experience</span>
                      </div>
                      <div class="text-muted small font-monospace mt-1">
                        Bar Reg: {{ rep.masked_registration_number || 'Verified on file' }}
                      </div>
                    </div>
                  </div>

                  <div>
                    <input
                      type="radio"
                      name="selected_representative"
                      class="form-check-input fs-5"
                      :checked="selectedLawyer?.id === rep.id"
                      @click.stop="handleSelectLawyer(rep)"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Optional Request Message Form -->
            <div v-if="selectedLawyer" class="mt-4 p-3 bg-white rounded-4 border shadow-sm">
              <label class="form-label fw-bold text-dark small mb-1">
                Brief Introduction / Notes for {{ selectedLawyer.name }} (Optional)
              </label>
              <textarea
                v-model="requestMessage"
                class="form-control rounded-3"
                rows="3"
                placeholder="Briefly state why you are seeking legal counsel for this dispute..."
                maxlength="1000"
              ></textarea>
              <div class="text-muted small mt-1 text-end">
                {{ requestMessage.length }}/1000 characters
              </div>
            </div>
          </div>

          <div class="modal-footer bg-white border-top px-4 py-3">
            <button
              type="button"
              class="btn btn-light rounded-pill px-4"
              :disabled="submittingRequest"
              @click="showDirectoryModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4 shadow-sm"
              :disabled="!selectedLawyer || submittingRequest"
              @click="submitRepresentationRequest"
            >
              <span
                v-if="submittingRequest"
                class="spinner-border spinner-border-sm me-2"
              />
              {{ submittingRequest ? 'Sending Request...' : 'Send Representation Request' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: POST PROCEDURAL NOTICE -->
    <div
      v-if="showNoticeModal"
      class="modal-backdrop fade show"
    ></div>
    <div
      v-if="showNoticeModal"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div class="modal-header bg-dark text-white border-bottom border-secondary py-3 px-4">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-pin-angle-fill text-warning fs-4" />
              <div>
                <h5 class="modal-title mb-0 fs-6 fw-bold">
                  Issue Procedural Notice
                </h5>
                <small class="text-white-50">
                  Case {{ caseData?.case_number }} &bull; Formal Procedural Direction
                </small>
              </div>
            </div>
            <button
              type="button"
              class="btn-close btn-close-white"
              aria-label="Close"
              @click="showNoticeModal = false"
            />
          </div>

          <div class="modal-body p-4 bg-light">
            <div class="alert alert-warning border-0 rounded-3 small mb-3">
              <i class="bi bi-info-circle-fill me-1" />
              <strong>Official Adjudicator Record:</strong> Procedural notices are pinned in the Shared Case Room and sent to both parties and their active legal representatives.
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold text-dark small mb-1">
                Notice Content <span class="text-danger">*</span>
              </label>
              <textarea
                v-model="noticeBody"
                class="form-control rounded-3"
                rows="5"
                placeholder="e.g. Submissions of documentary evidence are ordered to close on 30 October 2026. Parties are instructed to ensure all relevant files are numbered and submitted..."
                maxlength="2000"
              ></textarea>
              <div class="text-muted small mt-1 text-end">
                {{ noticeBody.length }}/2000 characters
              </div>
            </div>
          </div>

          <div class="modal-footer bg-white border-top px-4 py-3">
            <button
              type="button"
              class="btn btn-light rounded-pill px-4"
              :disabled="postingNotice"
              @click="showNoticeModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm"
              :disabled="!noticeBody.trim() || postingNotice"
              @click="handlePostProceduralNotice"
            >
              <span
                v-if="postingNotice"
                class="spinner-border spinner-border-sm me-2"
              />
              {{ postingNotice ? 'Issuing Notice...' : 'Issue Procedural Notice' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: ISSUE ADJUDICATOR QUESTION -->
    <div
      v-if="showQuestionModal"
      class="modal-backdrop fade show"
    ></div>
    <div
      v-if="showQuestionModal"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div class="modal-header bg-dark text-white border-bottom border-secondary py-3 px-4">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-question-diamond-fill text-info fs-4" />
              <div>
                <h5 class="modal-title mb-0 fs-6 fw-bold">
                  Ask Procedural Question
                </h5>
                <small class="text-white-50">
                  Case {{ caseData?.case_number }} &bull; Formal Clarification Question
                </small>
              </div>
            </div>
            <button
              type="button"
              class="btn-close btn-close-white"
              aria-label="Close"
              @click="showQuestionModal = false"
            />
          </div>

          <div class="modal-body p-4 bg-light">
            <div class="mb-3">
              <label class="form-label fw-bold text-dark small mb-1">
                Directed To <span class="text-danger">*</span>
              </label>
              <select v-model="questionTargetSide" class="form-select rounded-3">
                <option value="both">Both Parties (Complainant &amp; Respondent)</option>
                <option value="complainant">Complainant Only (or Counsel)</option>
                <option value="respondent">Respondent Only (or Counsel)</option>
              </select>
              <small class="text-muted d-block mt-1">
                Only the targeted party or their active legal representative will have the authority to respond to this question on the record.
              </small>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold text-dark small mb-1">
                Question / Clarification Request <span class="text-danger">*</span>
              </label>
              <textarea
                v-model="questionBody"
                class="form-control rounded-3"
                rows="5"
                placeholder="e.g. Respondent, please specify the exact date when the disputed communication was dispatched and clarify the relevant context..."
                maxlength="2000"
              ></textarea>
              <div class="text-muted small mt-1 text-end">
                {{ questionBody.length }}/2000 characters
              </div>
            </div>
          </div>

          <div class="modal-footer bg-white border-top px-4 py-3">
            <button
              type="button"
              class="btn btn-light rounded-pill px-4"
              :disabled="askingQuestion"
              @click="showQuestionModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary rounded-pill px-4 shadow-sm"
              :disabled="!questionBody.trim() || askingQuestion"
              @click="handleAskQuestion"
            >
              <span
                v-if="askingQuestion"
                class="spinner-border spinner-border-sm me-2"
              />
              {{ askingQuestion ? 'Posting Question...' : 'Post Formal Question' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: SETTLEMENT PROPOSAL / COUNTER-PROPOSAL -->
    <div
      v-if="showProposalModal"
      class="modal-backdrop fade show"
    ></div>
    <div
      v-if="showProposalModal"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
          <div class="modal-header bg-dark text-white border-bottom border-secondary py-3 px-4">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-file-earmark-check-fill text-success fs-4" />
              <div>
                <h5 class="modal-title mb-0 fs-6 fw-bold">
                  {{ isCountering ? 'Submit Counter-Proposal' : 'Submit Settlement Proposal' }}
                </h5>
                <small class="text-white-50">
                  Case {{ caseData?.case_number }} &bull; Mediation Settlement Terms
                </small>
              </div>
            </div>
            <button
              type="button"
              class="btn-close btn-close-white"
              aria-label="Close"
              @click="showProposalModal = false"
            />
          </div>

          <div class="modal-body p-4 bg-light">
            <div class="alert alert-info border-0 rounded-3 small mb-3">
              <i class="bi bi-info-circle-fill me-1" />
              <strong>Explicit Legal Acceptance:</strong> A settlement becomes legally final only when BOTH principal parties explicitly accept the identical proposal. Once accepted by both sides, the case transitions to <em>Settled</em> and an immutable agreement record is generated.
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold text-dark small mb-1">
                Settlement Terms &amp; Conditions <span class="text-danger">*</span>
              </label>
              <textarea
                v-model="proposalTerms"
                class="form-control rounded-3 font-monospace"
                rows="8"
                placeholder="1. Parties agree to mutual resolution of all claims in Case {{ caseData?.case_number }}.&#10;2. Removal of disputed material from the platform.&#10;3. Written mutual apology without admission of statutory liability.&#10;4. Mutual non-disparagement agreement going forward."
                maxlength="5000"
              ></textarea>
              <div class="text-muted small mt-1 text-end">
                {{ proposalTerms.length }}/5000 characters
              </div>
            </div>
          </div>

          <div class="modal-footer bg-white border-top px-4 py-3">
            <button
              type="button"
              class="btn btn-light rounded-pill px-4"
              :disabled="submittingProposal"
              @click="showProposalModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-success rounded-pill px-4 shadow-sm fw-bold"
              :disabled="!proposalTerms.trim() || submittingProposal"
              @click="handleSubmitProposal"
            >
              <span
                v-if="submittingProposal"
                class="spinner-border spinner-border-sm me-2"
              />
              {{ submittingProposal ? 'Submitting Proposal...' : (isCountering ? 'Submit Counter-Proposal' : 'Submit Proposal') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<style scoped>
.card {
  overflow: hidden;
}

.hover-card:hover {
  border-color: #0d6efd !important;
}

.transition {
  transition: all 0.2s ease-in-out;
}

.last-no-border:last-child {
  border-bottom: none !important;
  margin-bottom: 0 !important;
  padding-bottom: 0 !important;
}
</style>
