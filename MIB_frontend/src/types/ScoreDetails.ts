/** One scored record on a Score page tab, as returned by GET /get-scores. */
export interface ScoreItem {
    id: number | string;
    title: string | null;
    subtitle: string | null;
    date: string | null;
    status: string;
    points: number;
}

export type ScoreSectionKey =
    | 'identity'
    | 'education'
    | 'experience'
    | 'formal_recognition'
    | 'daily_questions'
    | 'exams'
    | 'others';

export type ScoreDetails = Record<ScoreSectionKey, ScoreItem[]> & {
    totalScore: number;
};
