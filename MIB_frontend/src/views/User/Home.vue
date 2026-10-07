<template>
        <main class="dashboard-page flex-grow-1">
        <div class="row g-3 align-items-start">
            <div class="col-md-4 col-xl-3 d-none d-md-block">
                <ProfileDetails />
                <addSiteDeails class="mt-4 w-100"/>
            </div>
            <div class="col-12 col-md-8 col-xl-6">
                <!-- <NewPost /> -->
                <!-- <SortingMenu /> -->
                <DailyQuestionCard class="dashboard-card mb-3" @day-changed="refreshHistoryAfterMidnight" />
                <DailyQuestionHistory ref="dailyHistory" class="dashboard-card mb-3" />

                    <div v-for="(post ,index) in basicInfo.posts"
                    :key="index">
                        <PostActivity 
                        :profileImage="basicInfo.posts?.[0]?.profile_image || userPng"
                        :questionCat="post.category"
                        :postName="post.post_by"
                        :postDate="post.posted_at"
                        :content="post.post_content"
                        :postImage="post.post_image"
                        :comments="post.comments"
                        :post_id ="post.post_id"
                        />
                    </div>
               
                <!-- <userProfiles /> -->
            </div>

            <div class="col-xl-3 d-none d-xl-block">
                
                <latestUpdates class="dashboard-card" />
                <Divider class="w-100"/>
                <!-- <myExams  @createExam="showExamCreate" :visible="examsCreateShow"/> -->
                <Divider class="w-100" />
                <!-- <categoriesShow /> -->
                <Divider class="w-100" />
                <ProfileList />
            </div> 
        </div>
        </main>
        
</template>
<script setup lang="ts">
import { ref , computed, onMounted, onBeforeUnmount} from 'vue';
import ProfileDetails from '@/components/HomePage/ProfileDetails.vue';
import Divider from 'primevue/divider';
import ProfileList from '@/components/HomePage/ProfileList.vue';
import PostActivity from '@/components/HomePage/PostActivity.vue';
import latestUpdates from '@/components/commonComponents/latestUpdates.vue';
import myExams from '@/components/HomePage/MyExams.vue';
import { useUserProfile} from '../../stores/User/userProfile';
import userPng from '../../assets/user.png';
import createExam from '@/components/commonComponents/createExam.vue';
import DailyQuestionCard from '@/components/DailyQuestionCard.vue';
import addSiteDeails from '@/components/commonComponents/addSiteDeails.vue';
import DailyQuestionHistory from '@/components/DailyQuestionHistory.vue';

const userProfile = useUserProfile();
const dailyHistory = ref<InstanceType<typeof DailyQuestionHistory> | null>(null);
let historyRefreshTimer: ReturnType<typeof setTimeout> | undefined;

// At midnight yesterday's answer moves into the history; refresh again shortly after as a fallback.
const refreshHistoryAfterMidnight = () => {
    void dailyHistory.value?.reload();
    if (historyRefreshTimer) clearTimeout(historyRefreshTimer);
    historyRefreshTimer = setTimeout(() => void dailyHistory.value?.reload(), 2 * 60 * 1000);
};

onBeforeUnmount(() => {
    if (historyRefreshTimer) clearTimeout(historyRefreshTimer);
});
const basicInfo = computed(()=> userProfile.getSummaryDetails);
const examCreate = ref<InstanceType<typeof createExam> | null>(null);
const examsCreateShow   = ref(false);
const showExamCreate = ()=>
{
    if(examCreate.value)
    {
       examsCreateShow.value = true;
    }
}
</script>
