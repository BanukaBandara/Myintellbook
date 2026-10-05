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



