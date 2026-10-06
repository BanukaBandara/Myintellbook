import { defineStore } from 'pinia';
import api from '@/assets/axios';
import type { TribunalJuryAssignedCase, TribunalJuryCaseDetail } from '@/types/tribunal';

export interface JuryPanelInfo {
  id: number;
  panel_code: string;
  panel_name: string;
  status: 'active' | 'inactive' | 'suspended';
  assigned_cases_count?: number;
  active_cases_count?: number;
}

export const useJuryPanelStore = defineStore('juryPanel', {
  state: () => ({
    panel: null as JuryPanelInfo | null,
    loading: false,
    assignedCases: [] as TribunalJuryAssignedCase[],
    currentCase: null as TribunalJuryCaseDetail | null,
    loadingCase: false,
    caseError: null as string | null,
    blockedMessage: null as string | null,
    blockedStatus: null as string | null,
  }),

  getters: {
    isBlocked: (state): boolean => Boolean(state.blockedStatus && state.blockedStatus !== 'active'),
    panelCode: (state): string => state.panel?.panel_code || '',
    panelName: (state): string => state.panel?.panel_name || '',
    panelStatus: (state): string => state.panel?.status || '',
    assignedCasesCount: (state): number => state.panel?.assigned_cases_count ?? state.assignedCases.length,
    activeCasesCount: (state): number => state.panel?.active_cases_count ?? state.assignedCases.length,
  },

  actions: {
    async fetchMe() {
      this.loading = true;
      this.blockedMessage = null;
      this.blockedStatus = null;

      try {
        const response = await api.get('/jury/me');
        if (response.data?.panel) {
          this.panel = response.data.panel;
        }
        return response.data;
      } catch (err: any) {
        if (err?.response?.status === 403) {
          this.blockedStatus = err.response.data?.panel_status || 'blocked';
          this.blockedMessage = err.response.data?.message || 'Jury Panel access is blocked.';
        }
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async fetchAssignedCases() {
      this.loading = true;
      try {
        const response = await api.get('/jury/cases');
        this.assignedCases = response.data?.data || [];
        return this.assignedCases;
      } catch (err: any) {
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async fetchCaseDetail(caseId: number | string) {
      this.loadingCase = true;
      this.caseError = null;
      try {
        const response = await api.get(`/jury/cases/${caseId}`);
        if (response.data?.data) {
          this.currentCase = response.data.data;
        }
        return this.currentCase;
      } catch (err: any) {
        this.caseError = err?.response?.data?.message || 'Failed to load case details.';
        throw err;
      } finally {
        this.loadingCase = false;
      }
    },

    async logout() {
      try {
        await api.get('/log-out');
      } catch {
        // ignore network or session errors during logout
      } finally {
        localStorage.removeItem('userToken');
        localStorage.removeItem('userData');
        this.panel = null;
        this.assignedCases = [];
        this.currentCase = null;
        this.blockedMessage = null;
        this.blockedStatus = null;
      }
    },
  },
});
