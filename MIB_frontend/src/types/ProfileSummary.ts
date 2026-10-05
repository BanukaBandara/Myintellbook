export interface ProfileSummary {
    first_name: string;
    last_name: string;
    profile_image: string;
    currently_working?: Array<{
        location: string;
        company: string;
    }>;
    id: string;
    user_id?: string;
}