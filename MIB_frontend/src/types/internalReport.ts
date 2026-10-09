export type InternalReportCategory =
  | 'Identity & Profile Fraud'
  | 'Qualification / Professional Fraud'
  | 'Harassment & Inappropriate Behaviour'
  | 'Scam / Security / Privacy Violation'
  | 'Academic & Score Manipulation'
  | 'Tribunal / Legal Process Misconduct'
  | 'Content & Community Abuse'
  | 'Other Platform Misconduct';

export type InternalReportStatus =
  | 'Submitted'
  | 'UnderReview'
  | 'NeedsMoreInformation'
  | 'Valid'
  | 'Invalid'
  | 'Closed';

export type InternalPenaltyType =
  | 'Warning'
  | 'Formal Warning'
  | 'Profile Correction Required'
  | 'Temporary Suspension'
  | 'Permanent Suspension'
  | 'Feature Restriction'
  | 'Verification Revoked'
  | 'Professional Eligibility Suspension'
  | 'Jury Panel Deactivation';

export type RestrictedFeature =
  | 'tribunal_participation'
  | 'community_posting'
  | 'daily_question_access'
  | 'exam_access'
  | 'profile_editing';

export interface ReportUserSummary {
  id: number;
  name: string;
  username: string;
  email?: string;
  profile_image?: string | null;
  is_jury_panel?: boolean;
}

export interface InternalReportEvidenceItem {
  id: number;
  original_name: string;
  mime_type: string;
  size: number;
  sha256: string;
  download_url: string;
  created_at?: string;
}

export interface InternalReportItem {
  id: number;
  report_number: string;
  reported_user: ReportUserSummary;
  category: InternalReportCategory;
  subject: string;
  description: string;
  status: InternalReportStatus;
  severity: string;
  decision_reason?: string | null;
  evidence?: InternalReportEvidenceItem[];
  created_at: string;
  updated_at: string;
}

export interface InternalReportReviewItem {
  id: number;
  from_status: InternalReportStatus;
  to_status: InternalReportStatus;
  notes?: string | null;
  reviewer_name?: string | null;
  created_at: string;
}

export interface InternalPenaltyItem {
  id: number;
  action_type: InternalPenaltyType;
  penalty_value?: RestrictedFeature | string | null;
  reason: string;
  notes?: string | null;
  applied_by?: number | null;
  applied_by_name?: string | null;
  applied_at: string;
  starts_at?: string | null;
  ends_at?: string | null;
  reversed_at?: string | null;
}

export interface InternalReportAuditItem {
  id: number;
  action: string;
  performed_by?: number | null;
  performer_name?: string | null;
  details?: Record<string, any> | null;
  created_at: string;
}

export interface AdminInternalReportItem {
  id: number;
  report_number: string;
  reporter: ReportUserSummary;
  reported_user: ReportUserSummary;
  category: InternalReportCategory;
  subject: string;
  description: string;
  status: InternalReportStatus;
  severity: string;
  admin_notes?: string | null;
  decision_reason?: string | null;
  reviewed_by?: number | null;
  reviewer_name?: string | null;
  reviewed_at?: string | null;
  closed_at?: string | null;
  evidence?: InternalReportEvidenceItem[];
  reviews?: InternalReportReviewItem[];
  penalties?: InternalPenaltyItem[];
  audits?: InternalReportAuditItem[];
  created_at: string;
  updated_at: string;
}
