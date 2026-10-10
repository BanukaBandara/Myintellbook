import { defineStore } from 'pinia';
import type userRegisterType from '@/types/userRegisterType';
import type verifyEmailType from '@/types/verifyEmail';
import instance from '@/assets/axios';

export const useUserStore = defineStore('user', {
    state: ():{userData : userRegisterType,verifyEmail: verifyEmailType ,email:string,isShowNavBar:boolean } => ({
        userData: {
            email: '',
            password: '',
            
        },
        verifyEmail: {
            email: '',
            token: ''
        },
        email:'',
        isShowNavBar:true,
      }),
    getters:{
        getNavBarShow:(state)=> state.isShowNavBar,
    },
      actions: {
        async submitUserData() {
          try{
              let response = await instance.post('/register', this.userData);
              if(response.data.code == 200){
                  return response.data;
              } else{
                  new Error("User registration failed");
              }
              
          }catch(e){
              console.error("Error in user registration", e);
                return {
                    code: 500,
                    message: "User registration failed",
                };
          } 
      },
      async loginUser() {
        try{
            let response = await instance.post('/login', this.userData);
           
            if(response.data.code == 200){
                return response.data;
            } else{
                new Error("User login failed");
            }
            
        }catch(e: any){
            console.error("Error in user login", e);
            if (e?.response?.data) {
                return e.response.data;
            }
            return {
                code: 500,
                message: "User login failed",
            };
        }
    },

    async emailVeryfied(){
        try{
            let response = await instance.post('verify-email', this.verifyEmail);
            if(response.data.code == 200){
                return response.data;
            } else{
                new Error("Email verification failed");
            }
            
        }catch(e){
            console.error("Error in email verification", e);
              return {
                  code: 500,
                  message: "Email verification failed",
              };
        }
    },
    async resetPassword() {
        try{
            let response = await instance.post('/password/reset', {email:this.email});
           
            if(response.data.code == 200){
                return response.data;
            } else{
                new Error("User password reset failed");
            }
            
        }catch(e:any){
            console.error("Error in user password reset", e);
              return {
                  code: e?.response?.status ?? 500,
                  message: e?.response?.data?.message ?? "Couldn't reach the server. Please try again.",
              };
        }
    },
    async changePassword(token:string) {
        try{
            let response = await instance.post('/password/reset/'+token, this.userData);
           
            if(response.data.code == 200){
                return response.data;
            } else{
                new Error("User password change failed");
            }
            
        }catch(e:any){
            console.error("Error in user password change", e);
              return {
                  code: e?.response?.status ?? 500,
                  // e.g. 422 "This password reset link is invalid or has expired."
                  message: e?.response?.data?.message ?? "Couldn't reach the server. Please try again.",
              };
        }
    },

    async checkUserStatus() {
        try {
            let response = await instance.get('/check-request-status');
            if (response.data.code == 200) {
                return response.data;
            } else {
                throw new Error("Request status check failed");
            }
        } catch (e: any) {
            console.error("Error in checking request status", e);
            return {
                code: 500,
                message: e.response.data.message,
            };
        }
    },
    async getUserData() {
        try {
            let response = await instance.get('/user-data');
            if (response.status == 200) {
                return response.data;
            } else {
                throw new Error("User data retrieval failed");
            }
        } catch (e: any) {
            console.error("Error in retrieving user data", e);
            return {
                code: 500,
                message: e.response.data.message,
            };
        }
     },

     async logOut(){
        try{
            let response = await instance.get('/log-out');
            if(response.status == 200)
            {
                return response.data;
            }else{
                throw new Error('log out not work correctly');
            }
        }catch(e: any){
        console.error("Error in retrieving user data", e);
                    return {
                        code: 500,
                        message: e.response.data.message,
                    };
                }
            },

        async googleLogin(googleData:any) {
            try{
                let response = await instance.post('/auth/google', googleData);
            
                if(response.data.code == 200){
                    return response.data;
                } else{
                    new Error("Google login failed");
                }
                
            }catch(e){
                console.error("Error in Google login", e);
                return {
                    code: 500,
                    message: "Google login failed",
                };
            }   
        },

        async submitSettings(settings:any)
        {
            try{
                let response = await instance.post('/set_settings', settings);
            
                if(response.data.code == 200){
                    return response.data;
                } else{
                    new Error("Google login failed");
                }
                
            }catch(e){
                console.error("Error in Google login", e);
                return {
                    code: 500,
                    message: "Google login failed",
                };
            }  
        },

        async getUserSettings(){
                try{
                        let response = await instance.get('/get_settings');
                        if(response.status == 200)
                        {
                            return response.data;
                        }else{
                            throw new Error('getting user Settings');
                        }
                }catch(e: any){
                console.error("getting user Settings", e);
                            return {
                                code: 500,
                                message: e.response.data.message,
                            };
                }
        }
    }
});