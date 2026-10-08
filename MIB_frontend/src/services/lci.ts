import instance from '@/assets/axios';
import { useUserProfile } from '@/stores/User/userProfile';

export type LciBreakdownItem = { key: string; label: string; score: number };

export type LciSummary = {
    lci_score: number;
    hip_rank: string;
    rank_tier: 'Platinum' | 'Gold' | 'Silver' | 'Bronze';
    rank_badge_color: string;
    percent_below_max: number;
    highest_lci: number;
    lowest_lci: number;
    breakdown: LciBreakdownItem[];
};

/**
 * Fetches the signed-in user's LCI and HIP rank, and pushes the new values into the
 * profile summary so the sidebar score card updates without a reload.
 */
export async function refreshLci(): Promise<LciSummary> {
    const { data } = await instance.get('/scores/lci');
    if (data?.success !== true) throw new Error('Invalid LCI response');

    const userProfile = useUserProfile();
    if (userProfile.summaryDetails && typeof userProfile.summaryDetails === 'object') {
        userProfile.summaryDetails = {
            ...userProfile.summaryDetails,
            hip_score: data.lci_score,
            lci_score: data.lci_score,
            hip_rank: data.hip_rank,
            rank_tier: data.rank_tier,
            rank_badge_color: data.rank_badge_color,
        };
    }

    return data as LciSummary;
}
