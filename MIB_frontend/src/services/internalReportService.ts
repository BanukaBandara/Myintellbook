import api from '@/assets/axios';
import type {
  InternalReportItem,
  ReportUserSummary,
} from '@/types/internalReport';

export const internalReportService = {
  async searchUsers(query: string): Promise<ReportUserSummary[]> {
    const response = await api.get<{ status: boolean; data: ReportUserSummary[] }>(
      '/internal-reports/users/search',
      { params: { q: query } }
    );
    return response.data.data;
  },

  async submitReport(formData: FormData): Promise<InternalReportItem> {
    const response = await api.post<{ status: boolean; data: InternalReportItem }>(
      '/internal-reports',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );
    return response.data.data;
  },

  async getMyReports(page = 1): Promise<{ data: InternalReportItem[]; meta: any }> {
    const response = await api.get<{ status: boolean; data: InternalReportItem[]; meta: any }>(
      '/internal-reports',
      { params: { page } }
    );
    return response.data;
  },

  async getReport(id: number): Promise<InternalReportItem> {
    const response = await api.get<{ status: boolean; data: InternalReportItem }>(
      `/internal-reports/${id}`
    );
    return response.data.data;
  },

  async downloadEvidence(evidenceId: number): Promise<Blob> {
    const response = await api.get(`/internal-reports/evidence/${evidenceId}/download`, {
      responseType: 'blob',
    });
    return response.data;
  },
};
