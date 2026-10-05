export interface ScoreDetails {
    daily_questions: Array<DailyQuestions>;
    profile_update:{
        Education:Array<education>
        Experiance:Array<education>

    };
    exam:Array<DailyQuestions>;
    totalScore:Number,
}

export interface DailyQuestions{
    
        id: string;
        activity_id: string;
        activity_type:string;
        points:Number;
        name?:string;
        added_date?:string;
        question_date?:string;
        question?:string;
        exam?:string;
        score?:Number;
        total_questions?:Number;
        diffuculty_level?:string;

 
}

export interface education{
        id: string;
        activity_id: string;
        activity_type:string;
        points:Number;
        name?:string;
        added_date?:string;
        question_date?:string;
        question?:string;
        exam?:string;
        score?:Number;
        total_questions?:Number;
        diffuculty_level?:string;
}