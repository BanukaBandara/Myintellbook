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

               <div class="mb-2 px-3 me-0 d-flex flex-column">
                       <p class="text-dark px-3 mb-2 fw-semibold" style="font-size:16px;">{{ "Category: "+content.category }}</p>
                       <p class="text-dark px-3 mb-2 fw-semibold" style="font-size:16px;">{{ "Level: "+ content.level }}</p>
                       <p class="text-dark px-3 mb-2" style="font-size:16px;">{{ 'Q. '+content.question || 'No question available' }}</p>
                       <ul v-for="(item, index) in content.options" :key="index" class="list-unstyled px-3 pe-3">
                            <li v-if="item.trim() !== ''" class="text-dark fs-6 d-flex align-items-center gap-2" style="font-size:13px;"> 
                            
                            {{ index +". "+ item.trim() }}
                            </li>
                        </ul>
                        <p class="text-dark px-3"><i class="pi pi-check" ></i> {{ 'Correct Answer: '+ content.answer }}</p>
                        <p class="text-dark fw-semibold px-3">{{ 'Background & Explanation: ' }}</p>
                        <div class="w-100 d-flex align-items-end justify-content-end px-3 pt-2 pb-2">

                        {{ "Your Score: "+ content.points + " added"}}
                        </div>
                        
               </div>
             

            </div>
                <div class="d-flex flex-row align-items-center justify-content-around text-center border-top p-2 border-bottom like-buttons">
                    <i class="pi pi-thumbs-up text-dark" ></i>
                    <i class="pi pi-comment text-dark" @click="showHideComment"> Your argument</i>
                </div>
        </template>
        <template #footer>
                <div class="d-flex flex-column mt-4" v-if="openCommentBox">
                     <span class="fw-semibold">{{ 'Arguments:' }}</span>
                    <div v-for="(comment, index) in comments" :key="index" class="d-flex flex-column mb-2 mt-4">
                       
                          <div class="d-flex flex-row align-items-center">
                            <Avatar
                            class="w-5 h-auto"
                            :image="(comment.profile_image) ? comment.profile_image: userPng"
                            size="large"
                            shape="circle"
                            /> 
                            <div class="w-100 border px-3 rounded-5 ms-3 d-flex flex-column p-2" style="font-size:13px;">
                                <div class="fw-semibold" style="font-size:14px;">{{ comment.comment_by }}</div>
                                <div>{{ comment.comment }}</div>
                                
                                
                            </div>
                            
                          </div>
                          <div class="text-dark me-2 mx-5 mt-0 px-4" style="font-size:13px;"> {{ comment.created_at }}</div>
                         
                         
                    </div>
                    <Divider class="w-100 my-2" />
                    <div class="d-flex flex-row align-items-center">
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
import { ref, computed } from 'vue';
import Divider from 'primevue/divider';
import userPng from '../../assets/user.png';
import { useUserProfile } from '@/stores/User/userProfile';
import Textarea from 'primevue/textarea';
import type { commentsType} from '../../types/commentsType'
import { type PropType } from 'vue'

const props = defineProps({
    post_id: {
        type: Number,
        default: 0
    },
    profileImage: {
        type: String,
        default: ''
    },
    postName:{
        type:String,
        default:''
    },
    content:{
        type:Object,
        default:{}
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
        type:String,
        default:''
    },
    questionCat: {
        type: String,
        default: ''
    },
    Level: {
        type: String,
        default: ''
    },
    comments: {
        type: Array as PropType<commentsType[]>,
        default:[]
    }

});
const profileImage = computed(()=>props.profileImage);
const postName = computed(()=> props.postName);
const content = computed(()=>props.content);
const postDate = computed(()=> props.postDate);
const postImage = computed(()=>props.postImage);
const answers = computed(()=>props.answers);
const questionCat = computed(()=>props.questionCat);
const comments = computed(() => props.comments || []);
const Level = computed(()=>props.Level);
const openCommentBox = ref(false);
const commentText = ref('');
const isLike = ref(false);
const  postId = computed(() => props.post_id || '');
const userProfile = useUserProfile();

const showHideComment = () => {
    openCommentBox.value = !openCommentBox.value;
};

const submitComment = async() => {
    // Logic to submit the comment
      const newComment = {
        text: commentText.value,
        isLike: isLike.value,
        post_id:postId.value,
        type:'post'
    }

    userProfile.userComment = newComment;
    await userProfile.setUserComment();
    commentText.value = ''; // Clear the comment input after submission
    openCommentBox.value = false; // Close the comment box
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