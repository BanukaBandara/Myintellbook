<template>
    <Card class="mb-3" :pt="{
        body:'p-0',
        footer:'px-3 pb-2'
    }">

        <template #content>
            <div class="d-flex flex-column">
               <div class="d-flex flex-row  text-secondary mb-3 px-3 pt-3 border-bottom pb-3">
                 <Avatar
                    class="w-10 h-auto me-2"
                    :image="profileImage"
                    size="large"
                    shape="circle"
                />
                    <div class="d-flex flex-column">
                        <span class="fw-semibold">{{ postName }}</span>
                        <span style="font-size:13px;opacity:0.8">{{ postDate }}</span>
                    </div>
                    <i class="pi pi-ellipsis-h ms-auto text-secondary opacity-50" style="font-size:16px;cursor:pointer;" />
               </div>
               <p class="text-dark px-3 mb-0 fw-semibold" style="font-size:16px;">{{ "Category: "+questionCat }}</p>
               <p class="text-dark px-3 pt-0 mt-0 fw-semibold" style="font-size:16px;">{{ "Level: "+Level }}</p>
               <p class="w-100 mb-2 px-3 me-0" :class="(!postImage)? 'text-center':''">{{content}}</p>
               <ul v-for="(item, index) in answers" :key="index" class="list-unstyled px-3 pe-3" v-if="answers">
                   <li v-if="item.trim() !== ''" class="text-dark fs-6 d-flex align-items-center gap-2" style="font-size:13px;"> 
                    <RadioButton :inputId="index" v-model="answer" name="pizza" :value="index" @change="submitAnswer"/>
                    <label for="ingredient1">{{ item.trim() }}</label>
                    </li>

               </ul>
               <Image v-if="postImage" :src="postImage" class="pb-2 text-center" width="100%" preview/>
               <div class="w-100 d-flex align-items-end justify-content-end px-3 pt-2 pb-4">

               {{ "Your Score- Score out of 5"}}
               </div>

            </div>
                <!-- <div class="d-flex flex-row align-items-center justify-content-between px-3 pt-2">
                    <span class="text-secondary" style="font-size:12px;">2 Likes</span>
                    <span class="text-secondary" style="font-size:12px;" @click="showHideComment">2 Comments</span>
                </div> -->
                <div class="d-flex flex-row align-items-center justify-content-around text-center border-top p-2 border-bottom like-buttons">
                    <i :class="(isLike) ?'pi pi-thumbs-up-fill text-primary': 'pi pi-thumbs-up text-dark'" @click="isLike = !isLike;"></i>
                    <i class="pi pi-comment text-dark" @click="showHideComment"> Your argument</i>
                </div>
        </template>
        <template #footer>
                <div class="d-flex flex-row align-items-center" v-if="openCommentBox">
                    <Avatar
                    class="w-10 h-auto me-2"
                    :image="profileImage"
                    size="large"
                    shape="circle"
                /> 
                    <div class="w-100  border p-2 rounded d-flex flex-row align-items-center justify-content-between" @click="openCommentBox = true">
                        <Textarea
                            v-model="commentText"
                            autoResize
                            rows="1"
                            class="w-100"
                            v-if="openCommentBox"
                            placeholder="Write a comment..."
                        ></Textarea>
                        <Button v-if="openCommentBox" label="Post" class="p-button-text p-button-secondary" @click="submitComment" />
                       
                    </div>
                </div>                              
        </template>
    </Card>
</template>
<script lang="ts" setup>
import Card from 'primevue/card'; 
import Avatar from 'primevue/avatar';  
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Image from 'primevue/image';
import Textarea from 'primevue/textarea';
import { ref, computed } from 'vue';
import { useUserProfile } from '@/stores/User/userProfile';
import RadioButton from 'primevue/radiobutton';



const props = defineProps({
    profileImage: {
        type: String,
        default: ''
    },
    postName:{
        type:String,
        default:''
    },
    content:{
        type:String,
        default:''
    },
    postDate:{
        type:String,
        default:''
    },
    postImage:{
        type:String,
        default:''
    },
    answers:{
        type:Object,
        default:{}
    },
    questionCat: {
        type: String,
        default: ''
    },
    Level: {
        type: String,
        default: ''
    }
});
const profileImage = computed(()=>props.profileImage);
const postName = computed(()=> props.postName);
const content = computed(()=>props.content);
const postDate = computed(()=> props.postDate);
const postImage = computed(()=>props.postImage);
const answers = computed(()=>props.answers);
const questionCat = computed(()=>props.questionCat);
const Level = computed(()=>props.Level);
const openCommentBox = ref(false);
const commentText = ref('');
const isLike = ref(false);
const answer = ref('');
const userProfile = useUserProfile();
const showHideComment = () => {
    openCommentBox.value = !openCommentBox.value;
};

const submitComment = async() => {
    // Logic to submit the comment
      const newComment = {
        text: commentText.value,
        isLike: isLike.value,
        post_id:userProfile.getSummaryDetails.tquestion.id
    }

    userProfile.userComment = newComment;
    await userProfile.setUserComment();
    commentText.value = ''; // Clear the comment input after submission
    openCommentBox.value = false; // Close the comment box
};

const submitAnswer = async() => {
    let questionId = userProfile.getSummaryDetails.tquestion.id;
    userProfile.answer = {
        question_id: questionId,
        answer: answer.value
    };
    let answerRes = await userProfile.setUserAnswer();
    if(answerRes?.code == 200){
        userProfile.isAnswered = true;
    }
    answer.value = ''; // Clear the answer input after submission
};

</script>
<style scoped>
.like-buttons i {
    cursor: pointer;
}
.comment-image{
    width:10px;
}
</style>