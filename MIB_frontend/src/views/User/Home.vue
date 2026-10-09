<template>
        <!-- Middle-column feed only: the sidebars come from layouts/DashboardLayout.vue. -->
        <div class="home-feed">
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

</template>
<script setup lang="ts">
import { ref , computed, onMounted, onBeforeUnmount} from 'vue';
import PostActivity from '@/components/HomePage/PostActivity.vue';
import myExams from '@/components/HomePage/MyExams.vue';
import { useUserProfile} from '../../stores/User/userProfile';
import userPng from '../../assets/user.png';
import createExam from '@/components/commonComponents/createExam.vue';
import DailyQuestionCard from '@/components/DailyQuestionCard.vue';
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
