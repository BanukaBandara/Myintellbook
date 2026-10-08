import { defineStore } from 'pinia';
import { ref } from 'vue';

import { tribunalService } from '@/services/tribunalService';

import type {
  ChallengeEvidencePayload,
  CreateTribunalCasePayload,
  DeclareJurorConflictPayload,
  JurorCasesData,
  SubmitTribunalResponsePayload,
  TribunalCase,
  TribunalEvidence,
  TribunalEvidenceChallenge,
  TribunalJuryAssignment,
  TribunalCaseRoom,
  TribunalCaseMessage,
  TribunalMediation,
  TribunalSettlementProposal,
  TribunalSettlementAgreement,
} from '@/types/tribunal';

export const useTribunalStore = defineStore('tribunal', () => {
  const cases = ref<TribunalCase[]>([]);
  const selectedCase = ref<TribunalCase | null>(null);
  const evidenceList = ref<TribunalEvidence[]>([]);
  const jurorCases = ref<JurorCasesData>({ pending: [], active: [] });
  const capabilities = ref<any | null>(null);
  const myVerification = ref<any | null>(null);

  // Batch 4 state
  const representatives = ref<any[]>([]);
  const lawyerRequests = ref<any>({ data: [] });
  const representedCases = ref<any>({ data: [] });
  const activeConversation = ref<any | null>(null);
  const conversationMessages = ref<any[]>([]);
  const isSendingMessage = ref(false);

  // Batch 5 state
  const caseRoom = ref<TribunalCaseRoom | null>(null);
  const caseRoomMessages = ref<TribunalCaseMessage[]>([]);
  const caseRoomPagination = ref<any>(null);
  const mediation = ref<TribunalMediation | null>(null);
  const isCaseRoomLoading = ref(false);
  const isMediationLoading = ref(false);

  const loading = ref(false);
  const uploadProgress = ref<number | null>(null);
  const error = ref<string | null>(null);

  const createCase = async (
    payload: CreateTribunalCasePayload
  ): Promise<TribunalCase | null> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.createCase(payload);
      cases.value.unshift(response.data);
      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to submit tribunal case.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  const fetchCases = async (): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.getCases();
      cases.value = response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to load tribunal cases.';
    } finally {
      loading.value = false;
    }
  };

  const fetchCase = async (id: number): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.getCase(id);
      selectedCase.value = response.data;
      if (response.data.evidence) {
        evidenceList.value = response.data.evidence;
      }
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to load tribunal case.';
    } finally {
      loading.value = false;
    }
  };

  const acknowledgeCase = async (id: number): Promise<TribunalCase | null> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.acknowledgeCase(id);
      selectedCase.value = response.data;

      const idx = cases.value.findIndex((c) => c.id === id);
      if (idx !== -1) {
        cases.value[idx] = response.data;
      }

      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to acknowledge tribunal case.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  const submitResponse = async (
    id: number,
    payload: SubmitTribunalResponsePayload
  ): Promise<TribunalCase | null> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.submitResponse(id, payload);
      selectedCase.value = response.data;

      const idx = cases.value.findIndex((c) => c.id === id);
      if (idx !== -1) {
        cases.value[idx] = response.data;
      }

      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to submit response to tribunal case.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  // Evidence Actions
  const fetchEvidence = async (caseId: number): Promise<void> => {
    try {
      const response = await tribunalService.getEvidence(caseId);
      evidenceList.value = response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to load evidence for this case.';
    }
  };

  const uploadEvidence = async (
    caseId: number,
    formData: FormData
  ): Promise<TribunalEvidence | null> => {
    loading.value = true;
    uploadProgress.value = 0;
    error.value = null;

    try {
      const response = await tribunalService.uploadEvidence(
        caseId,
        formData,
        (progressEvent) => {
          if (progressEvent.total) {
            uploadProgress.value = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
          }
        }
      );

      evidenceList.value.unshift(response.data);
      if (selectedCase.value && selectedCase.value.id === caseId) {
        selectedCase.value.evidence = evidenceList.value;
      }

      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to upload evidence.';
      return null;
    } finally {
      loading.value = false;
      uploadProgress.value = null;
    }
  };

  const challengeEvidence = async (
    caseId: number,
    evidenceId: number,
    payload: ChallengeEvidencePayload
  ): Promise<TribunalEvidenceChallenge | null> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.challengeEvidence(caseId, evidenceId, payload);

      // Update evidence item locally
      const item = evidenceList.value.find((e) => e.id === evidenceId);
      if (item) {
        item.status = 'challenged';
        item.can_challenge = false;
        if (!item.challenges) {
          item.challenges = [];
        }
        item.challenges.unshift(response.data);
      }

      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to submit evidence challenge.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  const downloadEvidence = async (
    caseId: number,
    evidenceId: number,
    filename?: string
  ): Promise<boolean> => {
    try {
      await tribunalService.downloadEvidence(caseId, evidenceId, filename);
      return true;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to download evidence file.';
      return false;
    }
  };

  // Juror Actions
  const fetchJurorCases = async (): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.getJurorCases();
      jurorCases.value = response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to load juror cases.';
    } finally {
      loading.value = false;
    }
  };

  const declareJuryConflict = async (
    caseId: number,
    payload: DeclareJurorConflictPayload
  ): Promise<TribunalJuryAssignment | null> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.declareJuryConflict(caseId, payload);
      await fetchJurorCases();
      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to submit conflict declaration.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  // Batch 3: Capabilities & Professional Verification
  const fetchCapabilities = async (): Promise<any> => {
    try {
      const data = await tribunalService.getCapabilities();
      capabilities.value = data;
      return data;
    } catch (err: any) {
      console.error('Failed to fetch tribunal capabilities', err);
      return null;
    }
  };

  const fetchMyVerification = async (): Promise<any> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.getMyProfessionalVerification();
      myVerification.value = response.data;
      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to load verification status.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  const submitVerificationApplication = async (
    formData: FormData
  ): Promise<any> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await tribunalService.applyProfessionalVerification(formData);
      myVerification.value = response.data;
      await fetchCapabilities();
      return response.data;
    } catch (err: any) {
      error.value =
        err?.response?.data?.message ??
        'Failed to submit verification application.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  // Batch 4 Actions
  const fetchRepresentatives = async (caseId?: number, search?: string): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.getVerifiedRepresentatives(caseId, search);
      representatives.value = response.data || [];
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load representatives.';
    } finally {
      loading.value = false;
    }
  };

  const requestRepresentation = async (caseId: number, representativeUserId: number, message?: string): Promise<any> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.requestRepresentation(caseId, {
        representative_user_id: representativeUserId,
        message,
      });
      await fetchCase(caseId);
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to request legal representation.';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const fetchLawyerRequests = async (status?: string, page?: number): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.getLawyerRequests(status, page);
      lawyerRequests.value = response;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load representation requests.';
    } finally {
      loading.value = false;
    }
  };

  const acceptRepresentationRequest = async (requestId: number, responseNote?: string): Promise<any> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.acceptRepresentationRequest(requestId, { response_note: responseNote });
      await fetchCapabilities();
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to accept representation request.';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const declineRepresentationRequest = async (requestId: number, reason: string): Promise<any> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.declineRepresentationRequest(requestId, { reason });
      await fetchCapabilities();
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to decline representation request.';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const endRepresentation = async (caseId: number, reason?: string): Promise<any> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.endRepresentation(caseId, { reason });
      await fetchCase(caseId);
      await fetchCapabilities();
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to end legal representation.';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const fetchRepresentedCases = async (page?: number): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.getRepresentedCases(page);
      representedCases.value = response;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load represented cases.';
    } finally {
      loading.value = false;
    }
  };

  const fetchCaseConversation = async (caseId: number): Promise<any> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await tribunalService.getCaseRepresentativeConversation(caseId);
      activeConversation.value = response.data;
      if (response.data?.id) {
        await fetchConversationMessages(response.data.id);
      }
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load conversation.';
      return null;
    } finally {
      loading.value = false;
    }
  };

  const fetchConversationMessages = async (conversationId: number): Promise<void> => {
    try {
      const response = await tribunalService.getConversationMessages(conversationId);
      conversationMessages.value = response.data || [];
    } catch (err: any) {
      console.error('Failed to load messages', err);
    }
  };

  const sendMessage = async (conversationId: number, body: string): Promise<any> => {
    isSendingMessage.value = true;
    try {
      const response = await tribunalService.sendConversationMessage(conversationId, { body });
      conversationMessages.value.push(response.data);
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to send message.';
      throw err;
    } finally {
      isSendingMessage.value = false;
    }
  };

  // Batch 5 Actions: Case Room
  const fetchCaseRoom = async (caseId: number): Promise<any> => {
    isCaseRoomLoading.value = true;
    try {
      const response = await tribunalService.getCaseRoom(caseId);
      caseRoom.value = response.data;
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load case room.';
      throw err;
    } finally {
      isCaseRoomLoading.value = false;
    }
  };

  const fetchCaseRoomMessages = async (caseId: number, page?: number): Promise<any> => {
    isCaseRoomLoading.value = true;
    try {
      const response = await tribunalService.getCaseRoomMessages(caseId, page);
      if (page && page > 1) {
        caseRoomMessages.value.push(...response.data);
      } else {
        caseRoomMessages.value = response.data;
      }
      caseRoomPagination.value = response.meta;
      return response;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load case room messages.';
      throw err;
    } finally {
      isCaseRoomLoading.value = false;
    }
  };

  const sendCaseRoomMessage = async (
    caseId: number,
    body: string,
    relatedEvidenceId?: number | null
  ): Promise<any> => {
    isSendingMessage.value = true;
    try {
      const response = await tribunalService.sendCaseRoomMessage(caseId, {
        body,
        related_evidence_id: relatedEvidenceId,
      });
      caseRoomMessages.value.push(response.data);
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to post message.';
      throw err;
    } finally {
      isSendingMessage.value = false;
    }
  };

  const postProceduralNotice = async (caseId: number, body: string): Promise<any> => {
    isSendingMessage.value = true;
    try {
      const response = await tribunalService.postProceduralNotice(caseId, { body });
      caseRoomMessages.value.push(response.data);
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to post procedural notice.';
      throw err;
    } finally {
      isSendingMessage.value = false;
    }
  };

  const askAdjudicatorQuestion = async (
    caseId: number,
    body: string,
    targetSide: 'complainant' | 'respondent' | 'both'
  ): Promise<any> => {
    isSendingMessage.value = true;
    try {
      const response = await tribunalService.askAdjudicatorQuestion(caseId, {
        body,
        target_side: targetSide,
      });
      caseRoomMessages.value.push(response.data);
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to post question.';
      throw err;
    } finally {
      isSendingMessage.value = false;
    }
  };

  const respondToQuestion = async (
    caseId: number,
    questionId: number,
    body: string
  ): Promise<any> => {
    isSendingMessage.value = true;
    try {
      const response = await tribunalService.respondToQuestion(caseId, questionId, { body });
      const question = caseRoomMessages.value.find((m) => m.id === questionId);
      if (question) {
        if (!question.responses) question.responses = [];
        question.responses.push(response.data);
      } else {
        caseRoomMessages.value.push(response.data);
      }
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to post response.';
      throw err;
    } finally {
      isSendingMessage.value = false;
    }
  };

  // Batch 5 Actions: Mediation
  const fetchMediation = async (caseId: number): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.getMediation(caseId);
      mediation.value = response.data;
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load mediation.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const requestMediation = async (caseId: number): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.requestMediation(caseId);
      mediation.value = response.data;
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to request mediation.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const offerMediation = async (caseId: number): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.offerMediation(caseId);
      mediation.value = response.data;
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to offer mediation.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const respondToMediation = async (
    mediationId: number,
    responseValue: 'accepted' | 'declined'
  ): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.respondToMediation(mediationId, { response: responseValue });
      mediation.value = response.data;
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to respond to mediation.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const endMediation = async (mediationId: number, reason: string): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.endMediation(mediationId, { reason });
      mediation.value = response.data;
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to end mediation.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const createSettlementProposal = async (mediationId: number, terms: string): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.createSettlementProposal(mediationId, { terms });
      if (mediation.value) {
        if (!mediation.value.proposals) mediation.value.proposals = [];
        mediation.value.proposals.unshift(response.data);
      }
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to submit proposal.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const counterSettlementProposal = async (
    mediationId: number,
    proposalId: number,
    terms: string
  ): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.counterSettlementProposal(mediationId, proposalId, { terms });
      if (mediation.value) {
        if (!mediation.value.proposals) mediation.value.proposals = [];
        const parent = mediation.value.proposals.find((p) => p.id === proposalId);
        if (parent) parent.status = 'countered';
        mediation.value.proposals.unshift(response.data);
      }
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to submit counter-proposal.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const acceptSettlementProposal = async (proposalId: number): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.acceptSettlementProposal(proposalId);
      if (response.data.settled) {
        if (mediation.value) {
          mediation.value.status = 'settled';
          mediation.value.settlement_agreement = response.data.agreement;
        }
        if (selectedCase.value) {
          selectedCase.value.status = 'settled' as any;
        }
      } else if (mediation.value && mediation.value.proposals) {
        const prop = mediation.value.proposals.find((p) => p.id === proposalId);
        if (prop) {
          prop.acceptances = response.data.proposal.acceptances;
          prop.accepted_by_complainant = response.data.proposal.accepted_by_complainant;
          prop.accepted_by_respondent = response.data.proposal.accepted_by_respondent;
        }
      }
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to accept proposal.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  const rejectSettlementProposal = async (proposalId: number): Promise<any> => {
    isMediationLoading.value = true;
    try {
      const response = await tribunalService.rejectSettlementProposal(proposalId);
      if (mediation.value && mediation.value.proposals) {
        const prop = mediation.value.proposals.find((p) => p.id === proposalId);
        if (prop) prop.status = 'rejected';
      }
      return response.data;
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to reject proposal.';
      throw err;
    } finally {
      isMediationLoading.value = false;
    }
  };

  return {
    cases,
    selectedCase,
    evidenceList,
    jurorCases,
    capabilities,
    myVerification,
    representatives,
    lawyerRequests,
    representedCases,
    activeConversation,
    conversationMessages,
    isSendingMessage,
    caseRoom,
    caseRoomMessages,
    caseRoomPagination,
    mediation,
    isCaseRoomLoading,
    isMediationLoading,
    loading,
    uploadProgress,
    error,

    createCase,
    fetchCases,
    fetchCase,
    acknowledgeCase,
    submitResponse,

    fetchEvidence,
    uploadEvidence,
    challengeEvidence,
    downloadEvidence,

    fetchJurorCases,
    declareJuryConflict,

    fetchCapabilities,
    fetchMyVerification,
    submitVerificationApplication,

    fetchRepresentatives,
    requestRepresentation,
    fetchLawyerRequests,
    acceptRepresentationRequest,
    declineRepresentationRequest,
    endRepresentation,
    fetchRepresentedCases,
    fetchCaseConversation,
    fetchConversationMessages,
    sendMessage,

    // Batch 5
    fetchCaseRoom,
    fetchCaseRoomMessages,
    sendCaseRoomMessage,
    postProceduralNotice,
    askAdjudicatorQuestion,
    respondToQuestion,
    fetchMediation,
    requestMediation,
    offerMediation,
    respondToMediation,
    endMediation,
    createSettlementProposal,
    counterSettlementProposal,
    acceptSettlementProposal,
    rejectSettlementProposal,
  };
});
