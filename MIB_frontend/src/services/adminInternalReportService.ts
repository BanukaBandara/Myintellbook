import api from '@/assets/axios';
import type {
  AdminInternalReportItem,
} from '@/types/internalReport';

export const adminInternalReportService = {
  async getReports(params: {
    status?: string;
    category?: string;
    search?: string;
    page?: number;
  }): Promise<{ data: AdminInternalReportItem[]; meta: any }> {
    const response = await api.get<{ status: boolean; data: AdminInternalReportItem[]; meta: any }>(
      '/admin/internal-reports',
      { params }
    );
    return response.data;
  },

  async getReport(id: number): Promise<AdminInternalReportItem> {
    const response = await api.get<{ status: boolean; data: AdminInternalReportItem }>(
      `/admin/internal-reports/${id}`
    );
    return response.data.data;
  },

  async updateStatus(
    id: number,
    payload: { status: string; notes?: string; decision_reason?: string }
  ): Promise<AdminInternalReportItem> {
    const response = await api.patch<{ status: boolean; data: AdminInternalReportItem }>(
      `/admin/internal-reports/${id}/status`,
      payload
    );
    return response.data.data;
  },

  async applyPenalty(
    id: number,
    payload: { action_type: string; reason: string; notes?: string }
  ): Promise<{ penalty_id: number; action_type: string; report: AdminInternalReportItem }> {
    const response = await api.post<{
      status: boolean;
      data: { penalty_id: number; action_type: string; report: AdminInternalReportItem };
    }>(`/admin/internal-reports/${id}/penalties`, payload);
    return response.data.data;
  },

  async reversePenalty(
    penaltyId: number,
    reason: string
  ): Promise<any> {
    const response = await api.post(`/admin/internal-reports/penalties/${penaltyId}/reverse`, {
      reversal_reason: reason,
    });
    return response.data;
  },

  async downloadEvidence(evidenceId: number): Promise<Blob> {
    const response = await api.get(`/admin/internal-reports/evidence/${evidenceId}/download`, {
      responseType: 'blob',
    });
    return response.data;
  },
};
