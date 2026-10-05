import api from '@/assets/axios';

import type {
  ChallengeEvidencePayload,
  CreateTribunalCasePayload,
  DeclareJurorConflictPayload,
  SubmitTribunalResponsePayload,
  TribunalCaseListResponse,
  TribunalCaseResponse,
  TribunalEvidenceChallengeResponse,
  TribunalEvidenceListResponse,
  TribunalEvidenceResponse,
  TribunalJurorCasesResponse,
  TribunalJuryAssignmentResponse,
} from '@/types/tribunal';

export const tribunalService = {
  async createCase(
    payload: CreateTribunalCasePayload
  ): Promise<TribunalCaseResponse> {
    const response = await api.post<TribunalCaseResponse>(
      '/tribunal/cases',
      payload
    );

    return response.data;
  },

  async getCases(): Promise<TribunalCaseListResponse> {
    const response = await api.get<TribunalCaseListResponse>(
      '/tribunal/cases'
    );

    return response.data;
  },

  async getCase(id: number): Promise<TribunalCaseResponse> {
    const response = await api.get<TribunalCaseResponse>(
      `/tribunal/cases/${id}`
    );

    return response.data;
  },

  async acknowledgeCase(id: number): Promise<TribunalCaseResponse> {
    const response = await api.post<TribunalCaseResponse>(
      `/tribunal/cases/${id}/acknowledge`
    );

    return response.data;
  },

  async submitResponse(
    id: number,
    payload: SubmitTribunalResponsePayload
  ): Promise<TribunalCaseResponse> {
    const response = await api.post<TribunalCaseResponse>(
      `/tribunal/cases/${id}/response`,
      payload
    );

    return response.data;
  },

  // Evidence APIs
  async getEvidence(caseId: number): Promise<TribunalEvidenceListResponse> {
    const response = await api.get<TribunalEvidenceListResponse>(
      `/tribunal/cases/${caseId}/evidence`
    );

    return response.data;
  },

  async uploadEvidence(
    caseId: number,
    formData: FormData,
    onUploadProgress?: (progressEvent: any) => void
  ): Promise<TribunalEvidenceResponse> {
    const response = await api.post<TribunalEvidenceResponse>(
      `/tribunal/cases/${caseId}/evidence`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        onUploadProgress,
      }
    );

    return response.data;
  },

  async challengeEvidence(
    caseId: number,
    evidenceId: number,
    payload: ChallengeEvidencePayload
  ): Promise<TribunalEvidenceChallengeResponse> {
    const response = await api.post<TribunalEvidenceChallengeResponse>(
      `/tribunal/cases/${caseId}/evidence/${evidenceId}/challenge`,
      payload
    );

    return response.data;
  },

  async downloadEvidence(
    caseId: number,
    evidenceId: number,
    suggestedFilename?: string
  ): Promise<void> {
    const response = await api.get(
      `/tribunal/cases/${caseId}/evidence/${evidenceId}/download`,
      {
        responseType: 'blob',
      }
    );

    const blob = new Blob([response.data]);
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = suggestedFilename || `evidence_${evidenceId}`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },

  // Jury APIs
  async getJurorCases(): Promise<TribunalJurorCasesResponse> {
    const response = await api.get<TribunalJurorCasesResponse>(
      '/tribunal/jury/cases'
    );

    return response.data;
  },

  async declareJuryConflict(
    caseId: number,
    payload: DeclareJurorConflictPayload
  ): Promise<TribunalJuryAssignmentResponse> {
    const response = await api.post<TribunalJuryAssignmentResponse>(
      `/tribunal/cases/${caseId}/jury/conflict`,
      payload
    );

    return response.data;
  },

  async acceptJuryAssignment(
    caseId: number
  ): Promise<TribunalJuryAssignmentResponse> {
    const response = await api.post<TribunalJuryAssignmentResponse>(
      `/tribunal/cases/${caseId}/jury/accept`
    );

    return response.data;
  },

  async recuseJuryAssignment(
    caseId: number,
    recusalReason: string
  ): Promise<TribunalJuryAssignmentResponse> {
    const response = await api.post<TribunalJuryAssignmentResponse>(
      `/tribunal/cases/${caseId}/jury/recuse`,
      { recusal_reason: recusalReason }
    );

    return response.data;
  },

  // Batch 3: Capabilities & Professional Verification
  async getCapabilities(): Promise<any> {
    const response = await api.get('/tribunal/me');
    return response.data;
  },

  async applyProfessionalVerification(
    formData: FormData
  ): Promise<any> {
    const response = await api.post(
      '/professional-verifications',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );

    return response.data;
  },

  async getMyProfessionalVerification(): Promise<any> {
    const response = await api.get('/professional-verifications/me');
    return response.data;
  },

  // Admin Verification Methods
  async adminGetVerifications(params?: Record<string, any>): Promise<any> {
    const response = await api.get('/admin/professional-verifications', {
      params,
    });
    return response.data;
  },

  async adminGetVerification(id: number): Promise<any> {
    const response = await api.get(
      `/admin/professional-verifications/${id}`
    );
    return response.data;
  },

  async adminApproveVerification(id: number): Promise<any> {
    const response = await api.post(
      `/admin/professional-verifications/${id}/approve`
    );
    return response.data;
  },

  async adminRejectVerification(id: number, reason: string): Promise<any> {
    const response = await api.post(
      `/admin/professional-verifications/${id}/reject`,
      { rejection_reason: reason }
    );
    return response.data;
  },

  async adminSuspendVerification(id: number, reason: string): Promise<any> {
    const response = await api.post(
      `/admin/professional-verifications/${id}/suspend`,
      { suspension_reason: reason }
    );
    return response.data;
  },

  async adminDownloadDocument(id: number, docType: string, filename?: string): Promise<void> {
    const response = await api.get(
      `/admin/professional-verifications/${id}/documents/${docType}`,
      {
        responseType: 'blob',
      }
    );

    const blob = new Blob([response.data]);
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = filename || `verification_${id}_${docType}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(downloadUrl);
  },

  // Batch 4: Legal Representation & Representatives
  async getVerifiedRepresentatives(caseId?: number, search?: string): Promise<any> {
    const params: Record<string, any> = {};
    if (caseId) params.case_id = caseId;
    if (search) params.search = search;
    const response = await api.get('/tribunal/representatives', { params });
    return response.data;
  },

  async requestRepresentation(caseId: number, data: { representative_user_id: number; message?: string }): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/representation-requests`, data);
    return response.data;
  },

  async getLawyerRequests(status?: string, page?: number): Promise<any> {
    const params: Record<string, any> = {};
    if (status && status !== 'all') params.status = status;
    if (page) params.page = page;
    const response = await api.get('/tribunal/representation-requests', { params });
    return response.data;
  },

  async acceptRepresentationRequest(requestId: number, data?: { response_note?: string }): Promise<any> {
    const response = await api.post(`/tribunal/representation-requests/${requestId}/accept`, data || {});
    return response.data;
  },

  async declineRepresentationRequest(requestId: number, data: { reason: string }): Promise<any> {
    const response = await api.post(`/tribunal/representation-requests/${requestId}/decline`, data);
    return response.data;
  },

  async endRepresentation(caseId: number, data?: { reason?: string }): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/representation/end`, data || {});
    return response.data;
  },

  async getRepresentedCases(page?: number): Promise<any> {
    const params: Record<string, any> = {};
    if (page) params.page = page;
    const response = await api.get('/tribunal/represented-cases', { params });
    return response.data;
  },

  async getCaseRepresentation(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/representation`);
    return response.data;
  },

  // Batch 4: Private Client-Representative Conversations
  async getCaseRepresentativeConversation(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/representative-conversation`);
    return response.data;
  },

  async getConversationMessages(conversationId: number, page?: number): Promise<any> {
    const params: Record<string, any> = {};
    if (page) params.page = page;
    const response = await api.get(`/tribunal/conversations/${conversationId}/messages`, { params });
    return response.data;
  },

  async sendConversationMessage(conversationId: number, data: { body: string }): Promise<any> {
    const response = await api.post(`/tribunal/conversations/${conversationId}/messages`, data);
    return response.data;
  },

  // Batch 5: Shared Case Room & Procedural Communication
  async getCaseRoom(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/case-room`);
    return response.data;
  },

  async getCaseRoomMessages(caseId: number, page?: number): Promise<any> {
    const params: Record<string, any> = {};
    if (page) params.page = page;
    const response = await api.get(`/tribunal/cases/${caseId}/case-room/messages`, { params });
    return response.data;
  },

  async sendCaseRoomMessage(
    caseId: number,
    data: { body: string; related_evidence_id?: number | null }
  ): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/case-room/messages`, data);
    return response.data;
  },

  async postProceduralNotice(caseId: number, data: { body: string }): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/case-room/procedural-notices`, data);
    return response.data;
  },

  async askAdjudicatorQuestion(
    caseId: number,
    data: { body: string; target_side: 'complainant' | 'respondent' | 'both' }
  ): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/case-room/questions`, data);
    return response.data;
  },

  async respondToQuestion(caseId: number, questionId: number, data: { body: string }): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/case-room/questions/${questionId}/responses`, data);
    return response.data;
  },

  // Batch 5: Structured Mediation & Settlement
  async getMediation(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/mediation`);
    return response.data;
  },

  async requestMediation(caseId: number): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/mediation/request`);
    return response.data;
  },

  async offerMediation(caseId: number): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/mediation/offer`);
    return response.data;
  },

  async respondToMediation(mediationId: number, data: { response: 'accepted' | 'declined' }): Promise<any> {
    const response = await api.post(`/tribunal/mediations/${mediationId}/respond`, data);
    return response.data;
  },

  async endMediation(mediationId: number, data: { reason: string }): Promise<any> {
    const response = await api.post(`/tribunal/mediations/${mediationId}/end`, data);
    return response.data;
  },

  async createSettlementProposal(mediationId: number, data: { terms: string }): Promise<any> {
    const response = await api.post(`/tribunal/mediations/${mediationId}/proposals`, data);
    return response.data;
  },

  async counterSettlementProposal(
    mediationId: number,
    proposalId: number,
    data: { terms: string }
  ): Promise<any> {
    const response = await api.post(`/tribunal/mediations/${mediationId}/proposals/${proposalId}/counter`, data);
    return response.data;
  },

  async acceptSettlementProposal(proposalId: number): Promise<any> {
    const response = await api.post(`/tribunal/settlement-proposals/${proposalId}/accept`);
    return response.data;
  },

  async rejectSettlementProposal(proposalId: number): Promise<any> {
    const response = await api.post(`/tribunal/settlement-proposals/${proposalId}/reject`);
    return response.data;
  },
};
