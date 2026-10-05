import { defineStore } from 'pinia';
import type {basicUserDetails} from '@/types/basicUserDetails';
import type {userGeneralInfoType} from '../../types/userGeneralInfoType'
import type {workExperianceType} from '../../types/workExperianceType'
import type {educationType} from '../../types/educationType';
import type {profileCompleteType} from '../../types/profileCompleteType';
import instance from '@/assets/axios';
import { text } from '@primeuix/themes/aura/inlinemessage';
import axios from 'axios';
// import {useApiService} from '../apiStore';

export const useUserProfile = defineStore('userProfile', {

    state: ():{
        userDetails : basicUserDetails, 
        category:Number, 
        userGeneralInfo:userGeneralInfoType,
        workExperiance:workExperianceType,
        experiance_id:Number,
        showExperianceEdit:boolean,
        educationDetails:educationType,
        education_id:Number,
        skillDetails:Object,
        skill_id:Number,
        profile_image:{
            image:String
        },
        profile_image_set:string,
        cover_image:{
            image:string
        },
        cover_image_set:string,
        profileComplete:profileCompleteType,
        summaryDetails:any,
        userProfiles:any
        userComment:{
            text: string,
            isLike: boolean
        },
        answer: {
            question_id: number,
            selected_option_index: number,
        },
        isAnswered:boolean,
        SearchKey:string,
        slug:String,
        isEditable:boolean
    } => ({
       userDetails:{
        first_name:'',
        last_name:'',
        gender:0,
        category:0,
        profession:0,
        birth_date:''
       },
        userGeneralInfo:{
             first_name: '',
            last_name: '',
            gender: 0,
            birth_date: '',
            profile_image:'',
            cover_image:'',
            school:'',
            total_points:0,
            rank:0,
            visibility:{},
            profession:{
                company:'',
                location:'',
                profession:''
            },
            slug:''
        },
        workExperiance:{
            title:'',
            company:'',
            currently_working:0,
            location:'',
            selectEmpType:0,
            locationType:0,
            startingDate:null,
            endDate:null,
            position:'',
            visibility:{}
        },
        educationDetails:{
            school:'',
            degree:'',
            field_of_study:'',
            degree_category:''
        },
       category:0,
       experiance_id:0,
       education_id:0,
       showExperianceEdit:false,
       skillDetails:{
        skill:''
       },
       skill_id:0,
       profile_image:{
        image:''
       },
       profile_image_set:'',
       cover_image:{
        image:''
       },
       cover_image_set:'',
       profileComplete:{
        generalInfo:{
            first_name:true,
            last_name:true,
            birth_date:false,
            gender:false
        },
        coverImage:false,
        profileImage:false,
        workExperiance:false,
        education:false,
        skills:false
       },
       summaryDetails:{
        first_name:'',
        last_name:'',
        profile_image:'',
        Hip:0,
        cover_image:'',
        currently_working:[
            {
                company:'',
                location:''
            }
        ]
       },
         userProfiles:[],
         userComment:{
            text: '',
            isLike: false
         },
         answer: {
            question_id: 0,
                selected_option_index: 0,
         },
         isAnswered:false,
         SearchKey:'',
         slug:'',
         isEditable:false
    }),
    getters:{
        experianceEdit:(state)=> state.showExperianceEdit,
        getProfileImage:(state)=> state.profile_image_set,
        getCoverImage:(state)=> state.cover_image_set,
        getProfiileComplete:(state)=>state.profileComplete,
        getUserBasicInfo:(state)=>state.userGeneralInfo,
        getSummaryDetails:(state)=>state.summaryDetails,
        getProfiles:(state)=>state.userProfiles,
        getIsAnswered:(state)=> state.isAnswered,
        getIsEditable:(state)=>state.isEditable
    },
    actions:{

        setIsEdit(value:boolean)
        {
            this.isEditable = value;
        },
        async submitUserDetails() {
            try{
                let response = await instance.post('/insert-profile', this.userDetails);
                if(response.data.code == 200){
                    return response.data;
                } else{
                    new Error("User details submission failed");
                }
                
            }catch(e){
                console.error("Error in user details submission", e);
                  return {
                      code: 500,
                      message: "User details submission failed",
                  };
            } 

        },
        async getCategories() {
            try{
                let response = await instance.get('/categories');
                if(response.data.code == 200){
                    
                    return response.data;
                } else{
                    new Error("categories getting failed");
                }
            }catch(e){
                console.error("Error in user details submission", e);
                  return {
                      code: 500,
                      message: "categories getting failed",
                  };
            }
        },

        async getProfessions() {
            try{
                let response = await instance.get('/professions/'+this.category);
                if(response.data.code == 200){
                    
                    return response.data;
                } else{
                    new Error("professions getting failed");
                }
            }catch(e){
                console.error("Error in user details submission", e);
                  return {
                      code: 500,
                      message: "professions getting failed",
                  };
            }
        },

        async editUserGeneralInfo(){
            try{
                let response = await instance.post('/edit-general-info',this.userGeneralInfo);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('general info edit faild');
                }
            }catch(e){
                console.error("Error in user details submission", e);
                  return {
                      code: 500,
                      message: "general info edit fail",
                  };
            }
        },

        async getGeneralInfo(){
            try{
            let response = await instance.get('get-general-info');
             if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('general info getting failed');
                }
            }catch(e){
                    console.error("Error in user details submission", e);
                  return {
                      code: 500,
                      message: "general info getting fail",
                  };
            }
            
        },

        async addWorkExperiance(){
            try{
                let response = await instance.post('/add-work-experiance',this.workExperiance);
                 if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('work experiance add failed');
                }

            }catch(e){
                 console.error("Error in user details submission", e);
                  return {
                      code: 500,
                      message: "getting work experiance fail",
                  };
            }
        },

        async getExperiance(){
            try{
                    let response = await instance.get('get-work-experiances/'+this.slug);
                    if(response.data.code == 200)
                    {
                        return response.data;
                    }else{
                        new Error('Error in getting experiance data');
                    }
            }catch(e){
                 console.error("Error in getting experiance data", e);
                  return {
                      code: 500,
                      message: "getting work experiance fail",
                  };
            }
        },

        async getExperianceDetails()
        {
             try{
                    let response = await instance.get('/get-details-experiance/'+this.experiance_id);
                    if(response.data.code == 200)
                    {
                        return response.data;
                    }else{
                        new Error('Error in getting experiance data');
                    }
            }catch(e){
                 console.error("Error in getting experiance data", e);
                  return {
                      code: 500,
                      message: "getting work experiance fail",
                  };
            }
        },

        async editExperiance(){
            try{

                    let response = await instance.post('/edit-details-experiance',this.workExperiance);
                    if(response.data.code == 200)
                    {
                        return response.data;
                    }else{
                        new Error('Error in editting experiance data');
                    }
            }catch(e){
                 console.error("Error in editting experiance data", e);
                  return {
                      code: 500,
                      message: "editting work experiance fail",
                  };
            }
        },

        async deleteExperiance(){
            try{

                let response = await instance.get('/delete-experiance/'+this.experiance_id);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in deleting experiance data');
                }
            }catch(e){
                 console.error("Error in deleting experiance data", e);
                  return {
                      code: 500,
                      message: "deleting work experiance fail",
                  };
            }
        },

        async submitEducationData()
        {
            try{

                let response = await instance.post('/add-education-details/',this.educationDetails);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in adding education data');
                }
            }catch(e){
                 console.error("Error in adding education data", e);
                  return {
                      code: 500,
                      message: "adding education fail",
                  };
            }
        },

        async getEducationInfo()
        {
            try{

                let response = await instance.get('/get-education-details/'+this.slug);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in getting education data');
                }
            }catch(e){
                 console.error("Error in getting education data", e);
                  return {
                      code: 500,
                      message: "getting education fail",
                  };
            }
        },

        async getEducationDetail(){
            try{
                let response = await instance.get('/get-education-detail/'+this.education_id);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in getting education data');
                }
            }catch(e){
                 console.error("Error in getting education data", e);
                  return {
                      code: 500,
                      message: "getting education fail",
                  };
            }
        },

        async deleteEducation()
        {
             try{

                let response = await instance.get('/delete-education/'+this.education_id);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in deleting education data');
                }
            }catch(e){
                 console.error("Error in deleting education data", e);
                  return {
                      code: 500,
                      message: "deleting education fail",
                  };
            }
        },

        async editEducation()
        {
            try{
                
                let response = await instance.post('/edit-education-detail/',this.educationDetails);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in eddtting education data');
                }
            }catch(e){
                 console.error("Error in eddtting education data", e);
                  return {
                      code: 500,
                      message: "eddtting education fail",
                  };
            }
        },

        async addSkill()
        {
            try{
                let response = await instance.post('/add-skill',this.skillDetails);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in adding skill data');
                }
            }catch(e){
                 console.error("Error in adding skill data", e);
                  return {
                      code: 500,
                      message: "adding skill fail",
                  };
            }
        },

        async getSkills()
        {
            try{
                let response = await instance.get('/get-skills');
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in getting skills data');
                }
            }catch(e){
                 console.error("Error in getting skills data", e);
                  return {
                      code: 500,
                      message: "getting skills fail",
                  };
            }
        },

        async deleteSkill()
        {
           try{
                let response = await instance.get('/delete-skill/'+this.skill_id);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in deleting skills data');
                }
            }catch(e){
                 console.error("Error in deleting skills data", e);
                  return {
                      code: 500,
                      message: "deleting skills fail",
                  };
            } 
        },

        async saveProfileImage(){
             try{
                let response = await instance.post('/upload-profile-image/',this.profile_image);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in uploading profile image data');
                }
            }catch(e){
                 console.error("Error in uploading profile image data", e);
                  return {
                      code: 500,
                      message: axios.isAxiosError(e)
                          ? e.response?.data?.errors?.image?.[0] ?? e.response?.data?.message ?? "Profile photo upload failed."
                          : "Profile photo upload failed.",
                  };
            } 
        },

        async saveCoverPhoto()
        {
            try{
                let response = await instance.post('/upload-cover-image/',this.cover_image);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in uploading cover image data');
                }
            }catch(e){
                 console.error("Error in uploading cover image data", e);
                  return {
                      code: 500,
                      message: axios.isAxiosError(e)
                          ? e.response?.data?.errors?.image?.[0] ?? e.response?.data?.message ?? "Cover photo upload failed."
                          : "Cover photo upload failed.",
                  };
            } 
        },
        async getProfileComplete()
        {
             try{
                let response = await instance.get('/profile-completed-status');

                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in get profile complete image data');
                }
            }catch(e){
                 console.error("Error in get profile complete image data", e);
                  return {
                      code: 500,
                      message: "get profile complete image fail",
                  };
            } 
        },
        async basicInfo()
        {
            try{
                let response = await instance.get('/get-user-summary');

                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in get basic info image data');
                }
            }catch(e){
                 console.error("Error in get basic info image data", e);
                  return {
                      code: 500,
                      message: "get basic info image fail",
                  };
            } 
        },
        async getUserProfiles(){
            try{
                let response = await instance.get('/profile-list');
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in getting user profiles');
                }
            }catch(e){
                 console.error("Error in getting user profiles", e);
                  return {
                      code: 500,
                      message: "getting user profiles fail",
                  };
            }
        },

        // Function to set user comment
        async setUserComment() {

            try{

                let response = await instance.post('/set-comment', this.userComment);
                if(response.data.code == 200) {
                } else {
                throw new Error('Error in setting user comment');
                }
            }catch(e){
                console.error("Error in setting user comment", e);
                return {
                    code: 500,
                    message: "Setting user comment failed",
                };
            }
        },

        async setUserAnswer(){
            try{
                let response = await instance.post('/set-user-answer', this.answer);
                if(response.data.code == 200) {
                    return response.data
                } 
                else {
                    throw new Error('Error in setting user answer');
                }
            }catch(e){
                console.error("Error in setting user answer", e);
                return {
                    code: 500,
                    message: "Setting user answer failed",
                };
            }
        },

        async getScores() {
            try {
                let response = await instance.get('/get-scores');
               
                if (response.data.code == 200) {
                    
                    return response.data;
                } else {
                    throw new Error('Error in getting scores');
                }
            } catch (e) {
                console.error("Error in getting scores", e);
                return {
                    code: 500,
                    message: "Getting scores failed",
                };
            }
        },

        async getNotifications(){
            try {
                let response = await instance.get('/notifications');
                if (response.data.code == 200) {
                    return response.data;
                } else {
                    throw new Error('Error in getting notifications');
                }
            } catch (e) {
                console.error("Error in getting notifications", e);
                return {
                    code: 500,
                    message: "Getting notifications failed",
                };
            }
        },

        async getAllNotifications(){
             try {
                let response = await instance.get('/allNotifications');
                if (response.data.code == 200) {
                    return response.data;
                } else {
                    throw new Error('Error in getting notifications');
                }
            } catch (e) {
                console.error("Error in getting notifications", e);
                return {
                    code: 500,
                    message: "Getting notifications failed",
                };
            }
        },

        async getTopUsers(){
              try {
                let response = await instance.get('/topScores');
                if (response.data.code == 200) {
                    return response.data;
                } else {
                    throw new Error('Error in getting Top users');
                }
            } catch (e) {
                console.error("Error in getting Top users", e);
                return {
                    code: 500,
                    message: "Getting Top users failed",
                };
            }
        },

        async searchProfile()
        {
            try {
               
                let response = await instance.post('/search',{'key':this.SearchKey});
                if (response.data.code == 200) {
                    return response.data;
                } else {
                    throw new Error('Error in getting search data');
                }
            } catch (e) {
                console.error("Error in getting search data", e);
                return {
                    code: 500,
                    message: "Getting search data failed",
                };
            }
        },

        async getPaginatedProfiles(page:number){
            try{
                let response = await instance.get('/profile-list-paginated?page='+page);
                if(response.data.code == 200)
                {
                    return response.data;
                }else{
                    new Error('Error in getting user profiles');
                }
            }catch(e){
                 console.error("Error in getting user profiles", e);
                  return {
                      code: 500,
                      message: "getting user profiles fail",
                  };
            }
        },

        async getUserInfomations(){

            try{
                let response = await instance.get('/get-user-infomations/'+this.slug)

                if(response.data.code == 200)
                {
                    return response.data
                }else{
                    new Error('Error in fetching data ');
                }
            }catch(e){
                console.error("Error in getting user data", e);
                  return {
                      code: 500,
                      message: "getting user data fail",
                  };
            }
        },

        async getUserById(id: string | number) {
            try {
                const response = await instance.get(`/users/${encodeURIComponent(String(id))}`);
                return {
                    code: 200,
                    user: response.data.user,
                };
            } catch (e) {
                console.error("Error in getting user profile by ID", e);
                return {
                    code: 500,
                    message: "Getting user profile failed",
                };
            }
        },

        async followCategory(categoryId:number){

            try{
                let response = await instance.get('/add-category-user/'+categoryId)

                if(response.data.code == 200)
                {
                    return response.data
                }else{
                    new Error('Error in fetching data ');
                }
            }catch(e){
                console.error("Error in getting user data", e);
                  return {
                      code: 500,
                      message: "getting user data fail",
                  };
            }
        },

        async deleteAccount(){
            try{
                let response = await instance.get('/delete-account')

                if(response.data.code == 200)
                {
                    return response.data
                }else{
                    new Error('Error in deleting account ');
                }
            }catch(e){
                console.error("Error in deleting account", e);
                  return {
                      code: 500,
                      message: "deleting account fail",
                  };
            }   
        },

        async submitComplains(submitComplain:{
            from:number,
            defendent:number,
            category:string
        })
        {
             try{
                let response = await instance.post('/submit-complains',submitComplain)

                if(response.data.code == 200)
                {
                    return response.data
                }else{
                    new Error('Error in submit complain ');
                }
            }catch(e){
                console.error("Error in submit complain", e);
                  return {
                      code: 500,
                      message: "submit complain fail",
                  };
            }  
        },

        async getComplains(status:number)
        {
             try{
                let response = await instance.get('/get-complains/'+status)

                if(response.data.code == 200)
                {
                    return response.data
                }else{
                    new Error('Error in get complains ');
                }
            }catch(e){
                console.error("Error in get complains", e);
                  return {
                      code: 500,
                      message: "get complains fail",
                  };
            }  
        }
    }
});