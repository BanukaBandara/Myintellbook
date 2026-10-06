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
  CreateTribunalJuryPanelPayload,
  UpdateTribunalJuryPanelPayload,
  TribunalJuryPanelListResponse,
  TribunalJuryPanelResponse,
  TribunalRespondentSearchResult,
} from '@/types/tribunal';

export const tribunalService = {
  async searchRespondents(query: string): Promise<{ data: TribunalRespondentSearchResult[] }> {
    const response = await api.get<{ data: TribunalRespondentSearchResult[] }>('/tribunal/respondents/search', {
      params: { q: query },
    });
    return response.data;
  },

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

  // Admin Jury Panel Methods
  async adminGetJuryPanels(params?: Record<string, any>): Promise<TribunalJuryPanelListResponse> {
    const response = await api.get<TribunalJuryPanelListResponse>('/admin/tribunal/jury-panels', {
      params,
    });
    return response.data;
  },

  async adminCreateJuryPanel(payload: CreateTribunalJuryPanelPayload): Promise<TribunalJuryPanelResponse> {
    const response = await api.post<TribunalJuryPanelResponse>('/admin/tribunal/jury-panels', payload);
    return response.data;
  },

  async adminGetJuryPanel(id: number): Promise<TribunalJuryPanelResponse> {
    const response = await api.get<TribunalJuryPanelResponse>(`/admin/tribunal/jury-panels/${id}`);
    return response.data;
  },

  async adminUpdateJuryPanel(id: number, payload: UpdateTribunalJuryPanelPayload): Promise<TribunalJuryPanelResponse> {
    const response = await api.patch<TribunalJuryPanelResponse>(`/admin/tribunal/jury-panels/${id}`, payload);
    return response.data;
  },

  async adminActivateJuryPanel(id: number): Promise<TribunalJuryPanelResponse> {
    const response = await api.post<TribunalJuryPanelResponse>(`/admin/tribunal/jury-panels/${id}/activate`);
    return response.data;
  },

  async adminDeactivateJuryPanel(id: number): Promise<TribunalJuryPanelResponse> {
    const response = await api.post<TribunalJuryPanelResponse>(`/admin/tribunal/jury-panels/${id}/deactivate`);
    return response.data;
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

  // Jury Panel Portal (Step 2 & 3)
  async getJuryMe(): Promise<any> {
    const response = await api.get('/jury/me');
    return response.data;
  },

  async getJuryCases(params?: Record<string, any>): Promise<any> {
    const response = await api.get('/jury/cases', { params });
    return response.data;
  },

  async getJuryCaseDetail(caseId: number): Promise<any> {
    const response = await api.get(`/jury/cases/${caseId}`);
    return response.data;
  },

  // Jury Panel Case Room & Mediation (Step 4)
  async getJuryCaseRoomMessages(caseId: number, params?: Record<string, any>): Promise<any> {
    const response = await api.get(`/jury/cases/${caseId}/case-room/messages`, { params });
    return response.data;
  },

  async sendJuryCaseRoomMessage(caseId: number, data: { body: string; related_evidence_id?: number | null }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/case-room/messages`, data);
    return response.data;
  },

  async postJuryProceduralNotice(caseId: number, data: { body: string }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/case-room/procedural-notices`, data);
    return response.data;
  },

  async askJuryQuestion(
    caseId: number,
    data: { body: string; target_side: 'complainant' | 'respondent' | 'both' }
  ): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/case-room/questions`, data);
    return response.data;
  },

  async getJuryMediation(caseId: number): Promise<any> {
    const response = await api.get(`/jury/cases/${caseId}/mediation`);
    return response.data;
  },

  async offerJuryMediation(caseId: number): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/mediation/offer`);
    return response.data;
  },

  async endJuryMediation(caseId: number, data: { reason: string }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/mediation/end`, data);
    return response.data;
  },

  // ==========================================
  // Hearing & Witness Management (Step 5)
  // ==========================================

  // Jury Portal endpoints
  async getJuryHearings(caseId: number): Promise<any> {
    const response = await api.get(`/jury/cases/${caseId}/hearings`);
    return response.data;
  },

  async scheduleJuryHearing(caseId: number, data: {
    hearing_type?: string;
    scheduled_at?: string;
    location_type?: string;
    meeting_link?: string;
    notes?: string;
  }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/hearings`, data);
    return response.data;
  },

  async getJuryHearing(hearingId: number): Promise<any> {
    const response = await api.get(`/jury/hearings/${hearingId}`);
    return response.data;
  },

  async startJuryHearing(hearingId: number): Promise<any> {
    const response = await api.post(`/jury/hearings/${hearingId}/start`);
    return response.data;
  },

  async recessJuryHearing(hearingId: number): Promise<any> {
    const response = await api.post(`/jury/hearings/${hearingId}/recess`);
    return response.data;
  },

  async resumeJuryHearing(hearingId: number): Promise<any> {
    const response = await api.post(`/jury/hearings/${hearingId}/resume`);
    return response.data;
  },

  async closeJuryHearing(hearingId: number): Promise<any> {
    const response = await api.post(`/jury/hearings/${hearingId}/close`);
    return response.data;
  },

  async getJuryWitnesses(caseId: number): Promise<any> {
    const response = await api.get(`/jury/cases/${caseId}/witnesses`);
    return response.data;
  },

  async approveJuryWitness(caseId: number, witnessId: number): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/witnesses/${witnessId}/approve`);
    return response.data;
  },

  async rejectJuryWitness(caseId: number, witnessId: number, reason: string): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/witnesses/${witnessId}/reject`, { reason });
    return response.data;
  },

  async addJuryHearingEntry(hearingId: number, data: {
    entry_type: string;
    body: string;
    related_evidence_id?: number | null;
    related_witness_id?: number | null;
    parent_entry_id?: number | null;
  }): Promise<any> {
    const response = await api.post(`/jury/hearings/${hearingId}/entries`, data);
    return response.data;
  },

  async askJuryHearingQuestion(hearingId: number, data: {
    body: string;
    target_side?: 'complainant' | 'respondent' | 'both' | 'witness';
    related_witness_id?: number | null;
    related_evidence_id?: number | null;
  }): Promise<any> {
    const response = await api.post(`/jury/hearings/${hearingId}/questions`, data);
    return response.data;
  },

  async recordJuryWitnessTestimony(hearingId: number, witnessId: number, testimony: string): Promise<any> {
    const response = await api.post(`/jury/hearings/${hearingId}/witnesses/${witnessId}/testimony`, { testimony });
    return response.data;
  },

  // Normal Tribunal endpoints (parties & counsel)
  async getTribunalHearings(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/hearings`);
    return response.data;
  },

  async getTribunalHearing(hearingId: number): Promise<any> {
    const response = await api.get(`/tribunal/hearings/${hearingId}`);
    return response.data;
  },

  async getTribunalWitnesses(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/witnesses`);
    return response.data;
  },

  async proposeTribunalWitness(caseId: number, data: {
    witness_name: string;
    witness_email?: string | null;
    relationship_to_case?: string | null;
    statement_summary?: string | null;
    witness_user_id?: number | null;
    tribunal_hearing_id?: number | null;
  }): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/witnesses`, data);
    return response.data;
  },

  async addTribunalHearingEntry(hearingId: number, data: {
    entry_type: string;
    body: string;
    related_evidence_id?: number | null;
    related_witness_id?: number | null;
    parent_entry_id?: number | null;
  }): Promise<any> {
    const response = await api.post(`/tribunal/hearings/${hearingId}/entries`, data);
    return response.data;
  },

  async respondToTribunalHearingQuestion(hearingId: number, questionId: number, data: {
    body: string;
    related_evidence_id?: number | null;
  }): Promise<any> {
    const response = await api.post(`/tribunal/hearings/${hearingId}/questions/${questionId}/responses`, data);
    return response.data;
  },

  async recordTribunalWitnessTestimony(hearingId: number, witnessId: number, testimony: string): Promise<any> {
    const response = await api.post(`/tribunal/hearings/${hearingId}/witnesses/${witnessId}/testimony`, { testimony });
    return response.data;
  },

  // ==========================================
  // STEP 6: DELIBERATION, FINDINGS & DECISION
  // ==========================================

  async getJuryDeliberation(caseId: number): Promise<any> {
    const response = await api.get(`/jury/cases/${caseId}/deliberation`);
    return response.data;
  },

  async addJuryDeliberationNote(caseId: number, data: {
    note_type: string;
    body: string;
  }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/deliberation/notes`, data);
    return response.data;
  },

  async updateJuryDeliberationNote(caseId: number, noteId: number, data: {
    note_type?: string;
    body?: string;
  }): Promise<any> {
    const response = await api.patch(`/jury/cases/${caseId}/deliberation/notes/${noteId}`, data);
    return response.data;
  },

  async deleteJuryDeliberationNote(caseId: number, noteId: number): Promise<any> {
    const response = await api.delete(`/jury/cases/${caseId}/deliberation/notes/${noteId}`);
    return response.data;
  },

  async addJuryFinding(caseId: number, data: {
    finding_type: string;
    title?: string | null;
    finding_text: string;
    conclusion: string;
    is_public?: boolean;
    display_order?: number;
    evidence_ids?: number[];
    hearing_entry_ids?: number[];
    witness_ids?: number[];
  }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/findings`, data);
    return response.data;
  },

  async updateJuryFinding(caseId: number, findingId: number, data: {
    finding_type?: string;
    title?: string | null;
    finding_text?: string;
    conclusion?: string;
    is_public?: boolean;
    display_order?: number;
    evidence_ids?: number[];
    hearing_entry_ids?: number[];
    witness_ids?: number[];
  }): Promise<any> {
    const response = await api.patch(`/jury/cases/${caseId}/findings/${findingId}`, data);
    return response.data;
  },

  async deleteJuryFinding(caseId: number, findingId: number): Promise<any> {
    const response = await api.delete(`/jury/cases/${caseId}/findings/${findingId}`);
    return response.data;
  },

  async getJuryDecision(caseId: number): Promise<any> {
    const response = await api.get(`/jury/cases/${caseId}/decision`);
    return response.data;
  },

  async saveJuryDecisionDraft(caseId: number, data: {
    outcome?: string;
    summary?: string;
    reasoning?: string;
  }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/decision`, data);
    return response.data;
  },

  async updateJuryDecisionDraft(caseId: number, data: {
    outcome?: string;
    summary?: string;
    reasoning?: string;
  }): Promise<any> {
    const response = await api.patch(`/jury/cases/${caseId}/decision`, data);
    return response.data;
  },

  async addJuryDecisionOrder(caseId: number, data: {
    order_type: string;
    title: string;
    description: string;
    target_side?: string | null;
    deadline_at?: string | null;
  }): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/decision/orders`, data);
    return response.data;
  },

  async updateJuryDecisionOrder(caseId: number, orderId: number, data: {
    order_type?: string;
    title?: string;
    description?: string;
    target_side?: string | null;
    deadline_at?: string | null;
    status?: string;
  }): Promise<any> {
    const response = await api.patch(`/jury/cases/${caseId}/decision/orders/${orderId}`, data);
    return response.data;
  },

  async deleteJuryDecisionOrder(caseId: number, orderId: number): Promise<any> {
    const response = await api.delete(`/jury/cases/${caseId}/decision/orders/${orderId}`);
    return response.data;
  },

  async publishJuryDecision(caseId: number): Promise<any> {
    const response = await api.post(`/jury/cases/${caseId}/decision/publish`);
    return response.data;
  },

  // Party / Counsel Decision view
  async getTribunalDecision(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/decision`);
    return response.data;
  },

  // Official Tribunal Case Reports & Verification
  async getCaseReports(caseId: number): Promise<any> {
    const response = await api.get(`/tribunal/cases/${caseId}/reports`);
    return response.data;
  },

  async generateFinalCaseReport(caseId: number, regenerate: boolean = false): Promise<any> {
    const response = await api.post(`/tribunal/cases/${caseId}/reports/final`, { regenerate });
    return response.data;
  },

  async getReportDetails(reportId: number): Promise<any> {
    const response = await api.get(`/tribunal/reports/${reportId}`);
    return response.data;
  },

  async downloadReportPdf(reportId: number, filename?: string): Promise<void> {
    const response = await api.get(`/tribunal/reports/${reportId}/download`, {
      responseType: 'blob',
    });
    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename || `MIB-RPT-${reportId}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  },

  async verifyReportCode(code: string): Promise<any> {
    const response = await api.get(`/tribunal/reports/verify/${encodeURIComponent(code)}`);
    return response.data;
  },

  async verifyReportFile(file: File, code?: string): Promise<any> {
    const formData = new FormData();
    formData.append('report_file', file);
    if (code) {
      formData.append('verification_code', code);
    }
    const response = await api.post('/tribunal/reports/verify-file', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
  },
};

