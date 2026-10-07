import instance from '@/assets/axios';

export type TestamentStatus = 'draft' | 'awaiting_witness' | 'sealed';

export type Beneficiary = { name: string; relationship: string; share: number };

export type HealthDeclaration = {
    sound_mind: boolean;
    no_duress: boolean;
    physician_name: string | null;
    assessment_date: string | null;
    notes: string | null;
};

export type AuditEntry = {
    id: number;
    event: string;
    actor: string;
    details: Record<string, unknown> | null;
    hash: string;
    created_at: string;
};

export type Testament = {
    id: number;
    status: TestamentStatus;
    title: string | null;
    instructions: string | null;
    health: HealthDeclaration | null;
    beneficiaries: Beneficiary[];
    witness: { id: number; name: string; status: 'pending' | 'confirmed' | 'declined' | null; responded_at: string | null } | null;
    tribunal_status: 'not_submitted' | 'awaiting_review';
    consent_given_at: string | null;
    sealed_at: string | null;
    updated_at: string | null;
    content_hash: string | null;
    verification: { encrypted_at_rest: boolean; content_hash_matches: boolean; audit_chain_intact: boolean };
    audit: AuditEntry[];
};

export type TestamentOverview = {
    testament: Testament | null;
    consent_statement: string;
    pending_witness_requests: number;
};

export type DraftInput = {
    title: string;
    instructions: string;
    health: HealthDeclaration;
    beneficiaries: Beneficiary[];
    witness_user_id: number | null;
};

export type WitnessRequest = { id: number; testator: string; requested_at: string | null };

export type TestamentResourceNote = {
    id: number;
    title: string;
    description: string;
    category: string;
    phone: string | null;
    email: string | null;
    location: string | null;
    status: 'active';
    created_at: string | null;
    owner_name: string;
    is_owner: boolean;
};

export type TestamentResourceNoteInput = {
    title: string;
    description: string;
    category: string;
    phone: string | null;
    email: string | null;
    location: string | null;
};

export const testamentApi = {
    async overview(): Promise<TestamentOverview> {
        const { data } = await instance.get('/testament');
        return data;
    },
    async save(input: DraftInput): Promise<Testament> {
        const { data } = await instance.put('/testament', input);
        return data.testament;
    },
    async submit(): Promise<Testament> {
        const { data } = await instance.post('/testament/submit', { consent: true });
        return data.testament;
    },
    async recall(): Promise<Testament> {
        const { data } = await instance.post('/testament/recall');
        return data.testament;
    },
    async withdraw(): Promise<void> {
        await instance.post('/testament/withdraw', { confirm: true });
    },
    async witnessRequests(): Promise<{ attestation_statement: string; requests: WitnessRequest[] }> {
        const { data } = await instance.get('/testament/witness-requests');
        return data;
    },
    async respond(id: number, decision: 'confirm' | 'decline'): Promise<void> {
        await instance.post(`/testament/witness-requests/${id}/${decision}`, decision === 'confirm' ? { attestation: true } : {});
    },
    async resourceNotes(): Promise<TestamentResourceNote[]> {
        const { data } = await instance.get('/testament/notes');
        return data.notes;
    },
    async createResourceNote(input: TestamentResourceNoteInput): Promise<TestamentResourceNote> {
        const { data } = await instance.post('/testament/notes', input);
        return data.note;
    },
    async myResourceNotes(): Promise<TestamentResourceNote[]> {
        const { data } = await instance.get('/testament/my-notes');
        return data.notes;
    },
    async updateResourceNote(id: number, input: TestamentResourceNoteInput): Promise<TestamentResourceNote> {
        const { data } = await instance.put(`/testament/notes/${id}`, input);
        return data.note;
    },
    async deleteResourceNote(id: number): Promise<void> {
        await instance.delete(`/testament/notes/${id}`);
    },
};

/** First server-side message (validation or business rule) from an Axios error. */
export function errorMessage(error: any, fallback: string): string {
    const data = error?.response?.data;
    if (Array.isArray(data?.problems) && data.problems.length) return data.problems.join(' ');
    const firstValidation = data?.errors ? Object.values(data.errors).flat()[0] : null;
    return (firstValidation as string) || data?.message || fallback;
}
