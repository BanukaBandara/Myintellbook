export interface profileListSearch {
    id: number;
    full_name:string;
    profile_image: string;
    profile_url?:string;
    profession?:string;
    points?:string | number;
    hip_score?:number;
    rank:string | number;
}