<template>
    <div class="container mt-5 bg-color">
        <div class="py-20 d-flex justify-content-center align-items-center row" style="margin-top:5%;" >
                <div class="text-center col-md-6 col-sm-12">
                    <h6 class="opacity-50 fs-6 mb-4">Professional Network Platform</h6>
                    <h1 class="mb-4 fs-large fw-700 font-size-header">Welcome to <span class="fw-bolder" style="color:rgb(160, 56, 41);">MyIntelliBook</span></h1>
                    <p class="mb-4 opacity-75 fs-5">
                        Connect, grow, and showcase your professional journey. Build your comprehensive profile, take knowledge assessments, and climb the professional rankings.
                    </p>
                    <div class="d-flex justify-content-center flex-column align-items-center mb-4 gap-3">
                        <Button  severity="contrast"  class="w-50" label="Login with email" outlined size="normal" @click="$router.push('/login')" style="border:rgb(160, 56, 41); color:white;background-color:rgb(160, 56, 41);" />
                        <GoogleLogin :callback="handleLogin" class="w-50 rounded-5" prompt auto-login/>
                        
                    </div>
                    <p>New To MyIntelliBook? <Button size="large" class="mx-2 fw-semibold p-2 px-4" severity="link" label="Join now" @click="$router.push('/register')"/></p>
                    
                </div>
        </div>
        <div class="py-10 d-flex justify-content-center align-items-center flex-column" style="margin-top: 6%;">
                <div class="text-center md-4 col-sm-12">
                    <h3 class="mb-4 fs-large fw-semibold font-size">Everything You Need for Professional Growth</h3>
                    <p class="mb-4 opacity-75 fs-5">
                       Comprehensive tools to build, showcase, and enhance your professional profile
                    </p>
                </div>
                <div class="row mt-5 w-75">
                    <div class="col-md-3 col-sm-12 mb-4">
                        <i class="pi pi-users fs-1 text-primary mb-3"></i>
                        <h5 class="fw-semibold">Complete Profile Management</h5>
                        <p class="opacity-50 fs-6">Build a comprehensive professional profile with education, experience, skills, and certifications</p>
                        <ul>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Personal & Contact Information</li>
                            <li class="opacity-75 fs-6 mb-1" style="font-size:14px;">Education & Academic Records</li>
                            <li class="opacity-75 fs-6 mb-1" style="font-size:14px;">Professional Experience</li>
                            <li class="opacity-75 fs-6 mb-1" style="font-size:14px;">Skills & Expertise Assessment</li>
                        </ul>
                    </div>
                    <div class="col-md-3 col-sm-12 mb-4">
                        <i class="pi pi-book fs-1 text-primary mb-3"></i>
                        <h5 class="fw-semibold">Knowledge Assessments</h5>
                        <p class="opacity-50 fs-6">Test your expertise in various domains with our comprehensive evaluation system</p>
                        <ul>
                            <li  class="opacity-75 mb-1" style="font-size:14px;">IQ &amp; Cognitive Assessment</li>
                            <li  class="opacity-75 mb-1" style="font-size:14px;">Technical Evaluations</li>
                            <li  class="opacity-75 mb-1" style="font-size:14px;">Philosophy &amp; Critical Thinking</li>
                            <li  class="opacity-75 mb-1" style="font-size:14px;">Mathematics &amp; Analytics</li>
                            <li  class="opacity-75 mb-1" style="font-size:14px;">Timed &amp; Secure Testing</li>
                        </ul>
                    </div>
                    <div class="col-md-3 col-sm-12 mb-4">
                        <i class="pi pi-star fs-1 text-warning mb-3"></i>
                        <h5 class="fw-semibold">Professional Rankings</h5>
                        <p class="opacity-50 fs-6">Get rated based on your profile completeness, test scores, and professional achievements</p>
                        <ul>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Multi-factor Rating System</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Peer Comparison &amp; Rankings</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Progress Tracking</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Achievement Badges</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Leaderboards</li>
                        </ul>
                    </div>
                     <div class="col-md-3 col-sm-12 mb-4">
                        <i class="pi pi-wave-pulse fs-1 text-warning mb-3"></i>
                        <h5 class="fw-semibold">Growth Analytics</h5>
                        <p class="opacity-50 fs-6">Track your professional growth with detailed analytics and insights</p>
                        <ul>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Profile Completion Tracking</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Skill Development Progress</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Test Performance Analytics</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Ranking Progression</li>
                            <li class="opacity-75 mb-1" style="font-size:14px;">Goal Setting &amp; Milestones</li>
                        </ul>
                    </div>

                </div>
        </div>
        <div class="row d-flex justify-content-center align-items-center text-white p-5 text-center" style="background-color:rgb(160, 56, 41);">
            <h2 class="fw-bold mb-4">Ready to Build Your Professional Profile?</h2>
            <p class="text-white fs-5">Join thousands of professionals who are advancing their careers with MyIntelliBook</p>
            <Button size="small" class="mx-2 fw-semibold text-dark w-auto pt-2 pb-2 px-3 mt-2" severity="secondary" to="/register" >
                    <router-link to="/register" class="text-decoration-none text-dark fw-bold ">
                        Start Your Journey
                        <i class="pi pi-arrow-right ms-2"></i>
                    </router-link>  
            </Button>
        </div>
    </div>
       
</template>
<script lang="ts" setup>
import Button from 'primevue/button';
import { checkAuth } from '../services/auth';
import {ref ,onMounted} from 'vue';
import { useRouter } from 'vue-router'; 
import { useUserStore } from '@/stores/User/userStore';

const userStore = useUserStore();
const isLoggedIn = ref<boolean>(true);
const router = useRouter();
const checkAuthUser = async() => {
    let result = await checkAuth();

    if(result){
        isLoggedIn.value = true;
        router.push('/home');
    }else{
        isLoggedIn.value = false;
        router.push('/');
    }
}

const handleLogin = async(response: any) => {

     let request = {
        token: response.credential
     }
     let result = await userStore.googleLogin(request);
        if(result.code  === 200){
        localStorage.setItem('userToken', result.token);
        localStorage.setItem('userData', JSON.stringify(result.user));
        let re = await userStore.getUserData();
            if(re.data != null){
                router.push('/home');
            }else{
                router.push('/basicDetails-fill')
            }
    }

}


onMounted(async() => {
    await checkAuthUser();
})
</script>
<style scoped>
.container {
    max-width: 100%;
    margin: 0 auto;
    padding: 20px;
    max-height: 100%;
    position: relative;
}
.font-size-header{
     font-size: clamp(2rem, 6vw, 4.75rem);
    /*line-height: 1; */
    font-family: 'Arial', sans-serif;

}
.bottom-div {
  position: absolute;
  bottom: 0;
  width: 100%; /* optional */
}

#container-div{
    width:100%;
}
</style>