import { defineStore } from 'pinia';
import type { CategoryType, subCategoryType } from '@/types/CategoryType';
import instance from '@/assets/axios';

export const useuserExams = defineStore('userExams', {

    state: (): {
        categories: CategoryType[],
        subCategory: subCategoryType[],
        catId:number
        isExamCreated:boolean
        categorykey:number,
        ExamSavedIndex:number,
        isBooked:boolean,
        examEndTime:string,
        examId:number,
        from:string,
        searchKey:string
    } => ({
        categories: [{
            id: 0,
            name: ''
        }],
        subCategory: [{
          id:0,
          name:''  // Provide default values for subCategoryType properties here if needed
        }],
        catId:0,
        isExamCreated:false,
        categorykey:0,
        ExamSavedIndex:0,
        isBooked:false,
        examEndTime:'',
        examId:0,
        from:'',
        searchKey:''
    }),
    getters:{
        getExamCreated:(state)=> state.isExamCreated,
        getExamEndTime:(state)=> state.examEndTime,
        getExamId:(state)=>state.examId
    },
    actions:{
        async getCategories()
        {
            try{

                let result = await instance.get('/categories');

                 if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("category getting failed");
                }

            }catch(e){
                console.error("Error in getting categories", e);
                  return {
                      code: 500,
                      message: "Error in getting categories failed",
                  };
            }

        },

        async getSubCategories()
        {
            try{

                let result = await instance.get('/professions/'+this.catId);

                 if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("Sub category getting failed");
                }

            }catch(e){
                console.error("Error in getting Sub categories", e);
                  return {
                      code: 500,
                      message: "Error in getting Sub categories failed",
                  };
            }
        },

        async createExam(exam:any)
        {
            try{

                let result = await instance.post('/create_exam/',exam);

                 if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("Sub category getting failed");
                }

            }catch(e){
                console.error("Error in getting Sub categories", e);
                  return {
                      code: 500,
                      message: "Error in getting Sub categories failed",
                  };
            }
        },

        async getExams()
        {
            try{

                let result = await instance.get('/get_exams',{
                    params:{
                        'category':this.categorykey,
                        'serchKey':this.searchKey
                    }});

                 if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("exams getting failed");
                }

            }catch(e){
                console.error("Error in getting exams", e);
                  return {
                      code: 500,
                      message: "Error in getting exams failed",
                  };
            }
        },

        async getFormatedCat()
        {
            try{

                let result = await instance.get('/categories_with_professions/');

                    if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("categories getting failed");
                }

            }catch(e){
                console.error("Error in getting categories", e);
                    return {
                        code: 500,
                        message: "Error in getting categories failed",
                    };
            }
        },

         async saveExam()
        {
            try{

                let result = await instance.post('/save_exam',{'ExamSavedIndex':this.ExamSavedIndex,'isBooked':this.isBooked});

                if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("exam saving error");
                }

            }catch(e){
                console.error("exam saving error", e);
                    return {
                        code: 500,
                        message: "exam saving error",
                    };
            }
        },

        async getMyExams()
        {
            try{

                let result = await instance.get('/my_exams',{
                    params:{
                        'category':this.categorykey,
                        'serchKey':this.searchKey
                    }});

                if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("exams getting error");
                }

            }catch(e){
                console.error("exams getting error", e);
                    return {
                        code: 500,
                        message: "exams getting error",
                    };
            }
        },
        async getUserFormatedCat()
        {
            try{

                let result = await instance.get('/my_caetgories');

                if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("exams getting error");
                }

            }catch(e){
                console.error("exams getting error", e);
                    return {
                        code: 500,
                        message: "exams getting error",
                    };
            }
        },
        async getExamData(examId:any)
        {
            try{

                
                let result = await instance.get('/get_exam_data/',{
                    params:{
                        'from':this.from,
                        'examId':examId
                    }
                });

                if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("getting exam data error");
                }

            }catch(e){
                console.error("getting exam data error", e);
                    return {
                        code: 500,
                        message: "getting exam data error",
                    };
            }
        },
        async submitAnswers(answers:any)
        {
            try{

                let result = await instance.post('/submit_answers',answers);

                if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("error in submitting answers");
                }

            }catch(e){
                console.error("error in submitting answers", e);
                    return {
                        code: 500,
                        message: "error in submitting answers",
                    };
            }
        },
        async checkAnswer(answer:any,id:number)
        {
            try{

                const params =  [ answer , id]

                let result = await instance.post('/check_answer',params);

                if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("error in submitting answers");
                }

            }catch(e){
                console.error("error in submitting answers", e);
                    return {
                        code: 500,
                        message: "error in submitting answers",
                    };
            }
        },
        async getSummaryExam(examId:number)
        {
            try{

                let result = await instance.get('/get-exam-summary/'+examId);

                if(result.data.code == 200){
                    return result.data;
                } else{
                    new Error("error in submitting answers");
                }

            }catch(e){
                console.error("error in submitting answers", e);
                    return {
                        code: 500,
                        message: "error in submitting answers",
                    };
            }
        }
    }
    
});