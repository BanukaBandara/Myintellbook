export interface TribunalUser {
  id: number;
  name: string | null;
}

export interface TribunalParty {
  id: number;
  role: 'complainant' | 'respondent';
  user: TribunalUser;
}

export type TribunalResponsePosition = 'accept' | 'deny' | 'partially_accept';

export interface TribunalCaseRespondentResponse {
  id: number;
  acknowledgement_at: string | null;
  position: TribunalResponsePosition | null;
  response_text: string | null;
  submitted_at: string | null;
}

export type TribunalEvidenceType =
  | 'image'
  | 'video'
  | 'audio'
  | 'document'
  | 'link'
  | 'statement'
  | 'other';

export type TribunalEvidenceStatus =
  | 'submitted'
  | 'challenged'
  | 'accepted'
  | 'rejected'
  | 'verification_required';

export type TribunalEvidenceChallengeStatus = 'pending' | 'resolved' | 'dismissed';

export interface TribunalEvidenceChallenge {
  id: number;
  tribunal_evidence_id: number;
  challenged_by: {
    id: number;
    name: string;
  };
  reason: string;
  status: TribunalEvidenceChallengeStatus;
  reviewed_at: string | null;
  created_at: string;
}

export interface TribunalEvidence {
  id: number;
  tribunal_case_id: number;
  evidence_number: string;
  type: TribunalEvidenceType;
  title: string;
  description: string | null;
  original_filename: string | null;
  mime_type: string | null;
  file_size: number | null;
  sha256_hash: string | null;
  external_url: string | null;
  status: TribunalEvidenceStatus;
  submitted_at: string;
  uploaded_by: {
    id: number;
    name: string;
    is_current_user?: boolean;
  };
  has_file: boolean;
  download_url: string | null;
  challenges?: TribunalEvidenceChallenge[];
  can_challenge?: boolean;
}

export type TribunalJuryRole = 'juror' | 'presiding_member';

export type TribunalJuryAssignmentStatus =
  | 'invited'
  | 'accepted'
  | 'recused'
  | 'replaced'
  | 'completed';

export interface TribunalJurySummary {
  status:
    | 'not_started'
    | 'pending_response'
    | 'awaiting_assignment'
    | 'selection_in_progress'
    | 'assigned';
  label: string;
  juror_name: string | null;
  role: string | null;
  assigned_at?: string | null;
  responded_at?: string | null;
}

export interface TribunalCase {
  id: number;
  case_number: string;
  title: string;
  category: string;
  description: string;
  requested_resolution: string | null;
  status:
    | 'submitted'
    | 'under_review'
    | 'awaiting_respondent'
    | 'response_received'
    | 'mediation'
    | 'jury_selection'
    | 'evidence_collection'
    | 'hearing'
    | 'deliberation'
    | 'decided'
    | 'appeal_window'
    | 'appealed'
    | 'closed'
    | 'settled'
    | 'withdrawn'
    | 'dismissed'
    | 'escalated';
  severity: 'low' | 'medium' | 'high' | 'critical';
  submitted_at: string | null;
  parties: TribunalParty[];
  response?: TribunalCaseRespondentResponse | null;
  evidence?: TribunalEvidence[];
  jury?: TribunalJurySummary | null;
  representation?: {
    has_active_representation: boolean;
    active_assignment?: {
      id: number;
      representative_user_id: number;
      representative_name: string;
      side: 'complainant' | 'respondent';
      accepted_at: string | null;
    } | null;
    pending_request?: {
      id: number;
      representative_user_id: number;
      representative_name: string;
      status: string;
      requested_at: string | null;
    } | null;
    is_representative_for_case: boolean;
    my_represented_party?: 'complainant' | 'respondent' | null;
  } | null;
  current_user_role?: 'complainant' | 'respondent' | 'juror' | 'representative' | 'none';
  case_room?: {
    id: number;
    status: string;
    opened_at: string | null;
  } | null;
  active_mediation?: {
    id: number;
    status: string;
    initiation_type: string;
    started_at: string | null;
  } | null;
  settlement_agreement?: {
    id: number;
    agreement_number: string;
    finalized_at: string;
  } | null;
  decision?: Record<string, any> | null;
  reports?: TribunalCaseReport[];
}

export interface CreateTribunalCasePayload {
  respondent_id: number;
  title: string;
  category: string;
  description: string;
  requested_resolution?: string | null;
}

export interface SubmitTribunalResponsePayload {
  position: TribunalResponsePosition;
  response_text: string;
}

export interface UploadEvidencePayload {
  type: TribunalEvidenceType;
  title: string;
  description?: string;
  external_url?: string;
  file?: File | null;
}

export interface ChallengeEvidencePayload {
  reason: string;
}

export interface DeclareJurorConflictPayload {
  has_conflict: boolean;
  conflict_reason?: string;
}

export interface TribunalJuryAssignmentCase {
  id: number;
  case_number: string;
  title: string;
  category: string;
  status: string;
  complainant_name: string;
  respondent_name: string;
}

export interface TribunalJuryAssignment {
  id: number;
  tribunal_case_id: number;
  role: TribunalJuryRole;
  status: TribunalJuryAssignmentStatus;
  assigned_at: string;
  responded_at: string | null;
  case: TribunalJuryAssignmentCase | null;
}

export interface JurorCasesData {
  pending: TribunalJuryAssignment[];
  active: TribunalJuryAssignment[];
}

export interface TribunalCaseResponse {
  code: number;
  status: boolean;
  message?: string;
  data: TribunalCase;
}

export interface TribunalCaseListResponse {
  code: number;
  status: boolean;
  data: TribunalCase[];
}

export interface TribunalEvidenceResponse {
  code: number;
  status: boolean;
  message?: string;
  data: TribunalEvidence;
}

export interface TribunalEvidenceListResponse {
  code: number;
  status: boolean;
  data: TribunalEvidence[];
}

export interface TribunalEvidenceChallengeResponse {
  code: number;
  status: boolean;
  message?: string;
  data: TribunalEvidenceChallenge;
}

export interface TribunalJurorCasesResponse {
  code: number;
  status: boolean;
  data: JurorCasesData;
}

export interface TribunalJuryAssignmentResponse {
  code: number;
  status: boolean;
  message?: string;
  data: TribunalJuryAssignment;
}

// Batch 3: Professional Verification & Adjudicator Eligibility Types
export type ProfessionalType =
  | 'attorney_at_law'
  | 'judge'
  | 'legal_officer'
  | 'mediator'
  | 'other_legal_professional';

export type ProfessionalVerificationStatus =
  | 'draft'
  | 'pending'
  | 'under_review'
  | 'verified'
  | 'rejected'
  | 'suspended'
  | 'expired';

export type AdjudicatorStatus = 'pending' | 'eligible' | 'suspended' | 'inactive';

export type QualificationStatus =
  | 'not_started'
  | 'pending'
  | 'passed'
  | 'failed'
  | 'exempted';

export interface ProfessionalVerificationEvent {
  id: number;
  event_type: string;
  actor_id: number | null;
  metadata: Record<string, any> | null;
  created_at: string;
}

export interface AdjudicatorProfileData {
  id: number;
  status: AdjudicatorStatus;
  qualification_status: QualificationStatus;
  qualification_score: number | null;
  qualified_at: string | null;
  available: boolean;
  is_eligible: boolean;
}

export interface ProfessionalVerification {
  id: number;
  user_id: number;
  profession_type: ProfessionalType;
  profession_label: string;
  verification_status: ProfessionalVerificationStatus;
  is_verified: boolean;
  badge_title: string | null;
  issuing_authority: string;
  years_of_experience: number;
  masked_registration_number: string | null;
  masked_enrollment_number: string | null;
  registration_number?: string | null;
  enrollment_number?: string | null;
  submitted_at: string | null;
  verified_at: string | null;
  expires_at: string | null;
  is_expired: boolean;
  rejection_reason?: string | null;
  suspension_reason?: string | null;
  reviewed_at?: string | null;
  has_qualification_document?: boolean;
  has_identity_document?: boolean;
  has_additional_document?: boolean;
  user?: {
    id: number;
    email: string;
    name: string;
  };
  adjudicator_profile?: AdjudicatorProfileData | null;
  events?: ProfessionalVerificationEvent[];
}

export interface TribunalCapabilities {
  can_submit_cases: boolean;
  has_cases_as_complainant: boolean;
  has_cases_as_respondent: boolean;
  is_admin_reviewer: boolean;
  is_jury_panel?: boolean;
  can_act_as_representative: boolean;
  professional_verification: {
    id: number;
    profession_type: ProfessionalType;
    profession_label: string;
    status: ProfessionalVerificationStatus;
    is_verified: boolean;
    badge_title: string | null;
  } | null;
  adjudicator: {
    eligible: boolean;
    available: boolean;
    status: AdjudicatorStatus | null;
    qualification_status: QualificationStatus | null;
    pending_assignments: number;
    active_assignments: number;
  };
  representative?: {
    eligible: boolean;
    pending_requests: number;
    active_cases: number;
  };
}

export interface ProfessionalVerificationResponse {
  has_application?: boolean;
  message?: string;
  data: ProfessionalVerification;
}

export interface AdminVerificationListResponse {
  data: ProfessionalVerification[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface VerifiedRepresentative {
  id: number;
  name: string;
  email: string;
  profession_type: string;
  profession_label: string;
  masked_registration_number: string | null;
  masked_enrollment_number: string | null;
  years_of_experience: number;
  issuing_authority: string;
  badge_title: string;
  verified_at: string | null;
  is_available: boolean;
}

export interface RepresentationRequest {
  id: number;
  tribunal_case_id: number;
  requested_by: number;
  client_user_id: number;
  representative_user_id: number;
  side: 'complainant' | 'respondent';
  status: 'pending' | 'accepted' | 'declined' | 'cancelled';
  message: string | null;
  requested_at: string;
  responded_at: string | null;
  decline_reason: string | null;
  client?: {
    id: number;
    name: string;
    email?: string;
  };
  representative?: VerifiedRepresentative;
  case?: TribunalCase;
}

export interface RepresentationAssignment {
  id: number;
  tribunal_case_id: number;
  client_user_id: number;
  representative_user_id: number;
  side: 'complainant' | 'respondent';
  status: 'active' | 'ended';
  accepted_at: string;
  ended_at: string | null;
  ended_by: number | null;
  end_reason: string | null;
  client?: {
    id: number;
    name: string;
  };
  representative?: VerifiedRepresentative;
  case?: TribunalCase;
}

export interface TribunalConversation {
  id: number;
  tribunal_case_id: number;
  type: string;
  client_user_id: number;
  representative_user_id: number;
  active: boolean;
  created_at: string;
  client?: {
    id: number;
    name: string;
  };
  representative?: {
    id: number;
    name: string;
  };
}

export interface TribunalMessage {
  id: number;
  conversation_id: number;
  sender_user_id: number;
  sender_name: string;
  is_mine: boolean;
  body: string;
  created_at: string;
}

// Batch 5 - Shared Case Room & Procedural Communication
export interface TribunalCaseRoom {
  id: number;
  tribunal_case_id: number;
  status: 'active' | 'closed';
  opened_at: string;
  closed_at: string | null;
  created_at: string;
  messages_count?: number;
}

export type TribunalCaseMessageType =
  | 'message'
  | 'procedural_notice'
  | 'adjudicator_question'
  | 'question_response'
  | 'mediation_notice'
  | 'system_notice';

export interface TribunalCaseMessage {
  id: number;
  tribunal_case_room_id: number;
  tribunal_case_id: number;
  sender_id: number;
  sender_name: string;
  sender_case_role: string;
  sender_role_label: string;
  is_me: boolean;
  message_type: TribunalCaseMessageType;
  body: string;
  target_side: 'complainant' | 'respondent' | 'both' | null;
  parent_message_id: number | null;
  related_evidence_id: number | null;
  related_evidence?: {
    id: number;
    evidence_number: string;
    title: string;
  } | null;
  procedural: boolean;
  responses?: TribunalCaseMessage[];
  created_at: string;
}

// Batch 5 - Mediation & Settlement
export type TribunalMediationStatus =
  | 'offered'
  | 'awaiting_consent'
  | 'active'
  | 'settled'
  | 'declined'
  | 'failed'
  | 'cancelled';

export interface TribunalMediationConsent {
  id: number;
  tribunal_mediation_id: number;
  user_id: number;
  user_name: string;
  side: 'complainant' | 'respondent';
  response: 'pending' | 'accepted' | 'declined';
  responded_at: string | null;
}

export interface TribunalSettlementProposal {
  id: number;
  tribunal_mediation_id: number;
  proposed_by: number;
  proposer_name: string;
  proposed_by_side: 'complainant' | 'respondent';
  parent_proposal_id: number | null;
  version_number: number;
  terms: string;
  status: 'pending' | 'accepted' | 'rejected' | 'countered' | 'withdrawn' | 'superseded';
  is_mine: boolean;
  acceptances: Array<{
    id: number;
    user_id: number;
    side: string;
    accepted_at: string;
  }>;
  accepted_by_complainant: boolean;
  accepted_by_respondent: boolean;
  created_at: string;
  updated_at: string;
}

export interface TribunalSettlementAgreement {
  id: number;
  tribunal_case_id: number;
  tribunal_mediation_id: number;
  settlement_proposal_id: number;
  agreement_number: string;
  terms_snapshot: string;
  complainant_accepted_at: string | null;
  respondent_accepted_at: string | null;
  finalized_at: string;
  created_at: string;
}

export interface TribunalMediation {
  id: number;
  tribunal_case_id: number;
  initiated_by: number;
  initiator_name: string;
  initiation_type: 'party_request' | 'adjudicator_offer';
  status: TribunalMediationStatus;
  previous_case_status: string;
  offered_at: string;
  started_at: string | null;
  ended_at: string | null;
  failure_reason: string | null;
  consents: TribunalMediationConsent[];
  proposals?: TribunalSettlementProposal[];
  settlement_agreement?: TribunalSettlementAgreement | null;
  my_consent?: TribunalMediationConsent | null;
  can_consent?: boolean;
  created_at: string;
  updated_at: string;
}

export type TribunalJuryPanelStatus = 'active' | 'inactive' | 'suspended';

export interface TribunalJuryPanelEvent {
  id: number;
  event_type: string;
  actor_id: number | null;
  metadata: Record<string, any> | null;
  created_at: string;
}

export interface TribunalJuryPanel {
  id: number;
  panel_code: string;
  panel_name: string;
  status: TribunalJuryPanelStatus;
  login_user_id: number;
  login_email?: string;
  created_by: number;
  creator?: {
    id: number;
    email: string;
    name?: string;
  };
  events?: TribunalJuryPanelEvent[];
  assigned_cases_count?: number;
  active_cases_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CreateTribunalJuryPanelPayload {
  panel_name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface UpdateTribunalJuryPanelPayload {
  panel_name?: string;
  status?: TribunalJuryPanelStatus;
}

export interface TribunalJuryPanelListResponse {
  data: TribunalJuryPanel[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface TribunalJuryPanelResponse {
  message?: string;
  data: TribunalJuryPanel;
}

export interface TribunalJuryAssignedCase {
  id: number;
  case_number: string;
  title: string;
  category: string;
  status: string;
  severity: string;
  complainant_summary: {
    id?: number;
    user_id?: number;
    name: string;
  };
  respondent_summary: {
    id?: number;
    user_id?: number;
    name: string;
  };
  assigned_at: string;
  assignment_status: string;
  updated_at: string;
}

export interface TribunalJuryEvidenceChallenge {
  id: number;
  reason: string;
  status: string;
  challenger_name: string;
  created_at: string;
}

export interface TribunalJuryEvidenceItem {
  id: number;
  evidence_number: string;
  title: string;
  description: string;
  category: string;
  original_filename: string;
  file_size: number;
  mime_type: string;
  uploaded_by: {
    id: number;
    name: string;
  };
  status: string;
  download_url: string;
  created_at: string;
  challenges: TribunalJuryEvidenceChallenge[];
}

export interface TribunalJuryCaseDetail {
  id: number;
  case_number: string;
  title: string;
  category: string;
  description: string;
  requested_resolution: string;
  severity: string;
  status: string;
  submitted_at: string;
  assigned_panel: {
    panel_id: number;
    panel_code: string;
    panel_name: string;
    assigned_at: string;
    assignment_method: string;
  };
  parties: {
    complainant: {
      id?: number;
      user_id?: number;
      name: string;
    };
    respondent: {
      id?: number;
      user_id?: number;
      name: string;
    };
  };
  representation: {
    complainant_lawyer: {
      id: number;
      representative_user_id: number;
      representative_name: string;
      assigned_at: string;
      bar_number?: string;
      jurisdiction?: string;
    } | null;
    respondent_lawyer: {
      id: number;
      representative_user_id: number;
      representative_name: string;
      assigned_at: string;
      bar_number?: string;
      jurisdiction?: string;
    } | null;
  };
  response: {
    id: number;
    acknowledgement_at?: string;
    position: string;
    response_text: string;
    submitted_at?: string;
  } | null;
  evidence: TribunalJuryEvidenceItem[];
  timeline: Array<{
    id: number;
    event_type: string;
    actor_name: string;
    metadata: Record<string, any> | null;
    created_at: string;
  }>;
  mediation_summary: {
    id: number;
    status: string;
    started_at?: string;
  } | null;
}

// ==========================================
// Step 5: Hearing & Witnesses Types
// ==========================================

export type TribunalHearingType = 'formal' | 'preliminary' | 'continuation';
export type TribunalHearingStatus = 'scheduled' | 'active' | 'recessed' | 'completed' | 'cancelled';
export type TribunalHearingLocationType = 'online' | 'physical' | 'hybrid';
export type TribunalWitnessStatus = 'proposed' | 'approved' | 'rejected' | 'withdrawn' | 'testified';
export type TribunalHearingParticipantType =
  | 'complainant'
  | 'respondent'
  | 'complainant_representative'
  | 'respondent_representative'
  | 'jury_panel'
  | 'witness';

export type TribunalHearingEntryType =
  | 'opening_statement'
  | 'response_statement'
  | 'jury_question'
  | 'party_answer'
  | 'witness_testimony'
  | 'witness_question'
  | 'witness_answer'
  | 'evidence_reference'
  | 'procedural_direction'
  | 'closing_statement'
  | 'system_event';

export interface TribunalHearingParticipant {
  id: number;
  tribunal_hearing_id: number;
  user_id: number | null;
  participant_type: TribunalHearingParticipantType;
  side: string | null;
  display_name: string;
  invited_by: number | null;
  attendance_status: string;
  joined_at: string | null;
  left_at: string | null;
  user?: {
    id: number;
    name: string;
    profile?: any;
  } | null;
}

export interface TribunalWitness {
  id: number;
  tribunal_case_id: number;
  tribunal_hearing_id: number | null;
  proposed_by: number;
  side: 'complainant' | 'respondent' | 'neutral';
  witness_user_id: number | null;
  witness_name: string;
  witness_email: string | null;
  relationship_to_case: string | null;
  statement_summary: string | null;
  status: TribunalWitnessStatus;
  approved_by_panel_at: string | null;
  rejected_reason: string | null;
  created_at: string;
  updated_at?: string;
  proposer?: {
    id: number;
    name: string;
  };
}

export interface TribunalHearingEntry {
  id: number;
  tribunal_hearing_id: number;
  sender_id: number | null;
  participant_type: string;
  side: string | null;
  entry_type: TribunalHearingEntryType;
  body: string;
  related_witness_id: number | null;
  related_evidence_id: number | null;
  sequence_number: number;
  target_side: string | null;
  parent_entry_id: number | null;
  created_at: string;
  sender?: {
    id: number;
    name: string;
    profile?: any;
  } | null;
  relatedWitness?: TribunalWitness | null;
  relatedEvidence?: {
    id: number;
    evidence_number: string;
    title: string;
  } | null;
  responses?: TribunalHearingEntry[];
}

export interface TribunalHearing {
  id: number;
  tribunal_case_id: number;
  tribunal_jury_panel_id: number;
  hearing_number: string;
  hearing_type: TribunalHearingType;
  status: TribunalHearingStatus;
  scheduled_at: string | null;
  started_at: string | null;
  ended_at: string | null;
  location_type: TribunalHearingLocationType;
  meeting_link: string | null;
  notes: string | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  juryPanel?: {
    id: number;
    panel_name: string;
    panel_code: string;
  };
  participants?: TribunalHearingParticipant[];
  entries?: TribunalHearingEntry[];
  witnesses?: TribunalWitness[];
}

// ==========================================
// STEP 6: DELIBERATION, FINDINGS & DECISION
// ==========================================

export type TribunalDeliberationStatus = 'open' | 'completed';

export type TribunalDeliberationNoteType =
  | 'general'
  | 'evidence_analysis'
  | 'witness_analysis'
  | 'credibility'
  | 'issue_analysis'
  | 'remedy_consideration';

export type TribunalFindingType =
  | 'fact'
  | 'issue'
  | 'credibility'
  | 'evidence'
  | 'procedural';

export type TribunalFindingConclusion =
  | 'established'
  | 'not_established'
  | 'partially_established'
  | 'not_applicable';

export type TribunalDecisionStatus = 'draft' | 'final';

export type TribunalDecisionOutcome =
  | 'complaint_upheld'
  | 'complaint_partially_upheld'
  | 'complaint_not_upheld'
  | 'dismissed';

export type TribunalDecisionOrderType =
  | 'no_action'
  | 'warning'
  | 'corrective_action'
  | 'content_action'
  | 'account_action'
  | 'compensation_recommendation'
  | 'compliance_requirement'
  | 'other';

export type TribunalDecisionOrderStatus =
  | 'pending'
  | 'active'
  | 'complied'
  | 'disputed'
  | 'waived';

export interface TribunalDeliberationNote {
  id: number;
  tribunal_deliberation_id: number;
  author_user_id: number;
  note_type: TribunalDeliberationNoteType;
  body: string;
  created_at: string;
  updated_at?: string;
  author?: {
    id: number;
    name: string;
  };
}

export interface TribunalFinding {
  id: number;
  tribunal_case_id: number;
  tribunal_deliberation_id: number;
  finding_number: string;
  finding_type: TribunalFindingType;
  title: string | null;
  finding_text: string;
  conclusion: TribunalFindingConclusion;
  display_order: number;
  is_public: boolean;
  created_by: number;
  created_at: string;
  updated_at: string;
  evidence?: Array<{
    id: number;
    evidence_number: string;
    title: string;
    type: string;
  }>;
  hearing_entries?: Array<{
    id: number;
    entry_type: string;
    body: string;
    sequence_number: number;
  }>;
  witnesses?: Array<{
    id: number;
    witness_name: string;
    side: string;
  }>;
}

export interface TribunalDecisionOrder {
  id: number;
  tribunal_decision_id: number;
  order_number: string;
  order_type: TribunalDecisionOrderType;
  title: string;
  description: string;
  target_side: 'complainant' | 'respondent' | 'both' | null;
  deadline_at: string | null;
  status: TribunalDecisionOrderStatus;
  created_at: string;
  updated_at: string;
}

export interface TribunalDecision {
  id: number;
  tribunal_case_id: number;
  tribunal_jury_panel_id: number;
  decision_number: string;
  status: TribunalDecisionStatus;
  outcome: TribunalDecisionOutcome | null;
  summary: string | null;
  reasoning: string | null;
  published_at: string | null;
  appeal_deadline: string | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  jury_panel?: {
    id: number;
    panel_name: string;
    panel_code: string;
  };
  orders?: TribunalDecisionOrder[];
  findings?: TribunalFinding[];
  case?: {
    id: number;
    case_number: string;
    title: string;
    status: string;
  };
}

export interface TribunalDeliberation {
  id: number;
  tribunal_case_id: number;
  tribunal_jury_panel_id: number;
  status: TribunalDeliberationStatus;
  opened_at: string;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  notes?: TribunalDeliberationNote[];
  jury_panel?: {
    id: number;
    panel_name: string;
    panel_code: string;
  };
}

export interface JuryDeliberationData {
  deliberation: TribunalDeliberation;
  findings: TribunalFinding[];
  decision: TribunalDecision | null;
  dossier: {
    case: {
      id: number;
      case_number: string;
      title: string;
      category: string;
      description: string;
      requested_resolution: string | null;
      status: string;
      complainant: { id: number; name: string };
      respondent: { id: number; name: string };
    };
    response: {
      position: string;
      response_text: string;
    } | null;
    evidence: Array<{
      id: number;
      evidence_number: string;
      title: string;
      type: string;
      status: string;
      challenge_status: string;
      is_flagged: boolean;
      created_at: string;
    }>;
    witnesses: Array<{
      id: number;
      witness_name: string;
      side: string;
      relationship_to_case: string | null;
      statement_summary: string | null;
      status: string;
    }>;
    hearing: {
      id: number;
      hearing_number: string;
      status: string;
      started_at: string | null;
      ended_at: string | null;
    } | null;
    hearing_entries: Array<{
      id: number;
      sequence_number: number;
      entry_type: string;
      sender_name: string;
      side: string | null;
      body: string;
      created_at: string;
    }>;
  };
}

export interface TribunalCaseReport {
  id: number;
  tribunal_case_id: number;
  case_number?: string;
  tribunal_decision_id: number;
  decision_number?: string;
  report_number: string;
  verification_code: string;
  report_type: string;
  status: 'generated' | 'superseded' | 'revoked';
  version: number;
  file_hash: string;
  issued_at: string;
  generated_at: string;
  last_downloaded_at: string | null;
  download_count: number;
  download_url: string;
  verify_url: string;
}

export interface TribunalReportListResponse {
  case_id: number;
  case_number: string;
  reports: TribunalCaseReport[];
  active_report: TribunalCaseReport | null;
  has_final_decision: boolean;
}

export interface TribunalReportVerifyResponse {
  valid: boolean;
  message: string;
  details: {
    report_number: string;
    case_number: string;
    report_type: string;
    issued_at: string;
    issued_at_formatted?: string;
    decision_number: string;
    decision_published_at: string;
    adjudicated_by?: string;
    status: string;
    version: number;
    file_hash: string;
    authenticity_confirmed: boolean;
    confirmation_statement?: string;
  } | null;
}
