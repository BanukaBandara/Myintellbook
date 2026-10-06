<template>
    <!-- <div class="mt-3 "> -->
        <div class="row flex-grow-1 overflow-auto  m-0 justify-content-center mb-3 gap-1">
            <div class="col-md-1"></div>
            <div class="col-md-2 mt-3 d-none d-md-block">
                <ProfileDetails />
                <addSiteDeails class="mt-4 w-100"/>
            </div>
            <div class="col-md-4 mt-3">
                <!-- <NewPost /> -->
                <!-- <SortingMenu /> -->
                  <todayPost 
                    v-if="tquestion"
                    :profileImage="basicInfo.posts?.[0]?.profile_image || userPng"
                    :postName="`Today's Question`"
                    :postDate="tquestion?.post_at"
                    :questionCat="tquestion.category || 'General'"
                    :Level="tquestion?.difficulty_level || 'Beginner'"
                    :content="tquestion?.question || 'No question available'"
                    :answers="tquestion?.options || ''"
                  />

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

            <div class="col-md-3 mt-3 d-none d-md-block">
                
                <latestUpdates />
                <Divider class="w-75"/>
                <!-- <myExams  @createExam="showExamCreate" :visible="examsCreateShow"/> -->
                <Divider class="w-75" />
                <!-- <categoriesShow /> -->
                <Divider class="w-75" />
                <ProfileList />
            </div> 
            <div class="col-md-2"></div>
        </div>
        
    <!-- </div> -->
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
import todayPost from '@/components/HomePage/todayPost.vue';
import addSiteDeails from '@/components/commonComponents/addSiteDeails.vue';

const userProfile = useUserProfile();
const basicInfo = computed(()=> userProfile.getSummaryDetails);
const tquestion = computed(()=> userProfile.getSummaryDetails.tquestion);
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
