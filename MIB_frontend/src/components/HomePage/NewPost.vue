<template>
    <div>
        <Card class="mb-3" :pt="{
            footer:'border-top',
            body:'pb-0'
            }">
            <template #content>
                <div class="d-flex flex-row align-items-center text-secondary share-post" >
                    <Avatar
                    class="w-10 h-auto"
                    :image="(basicInfo.profile_image)? basicInfo.profile_image : userPng"
                    size="large"
                    shape="circle"
                    
                    />
                    <span class="opacity-75 mx-3" @click="visible = true">Share your thoughts with the community . . . . .</span>
                </div>
            </template>
            <template #footer>
                <i class="pi pi-camera text-secondary p-3 px-1 opacity-50" />
                    <i class="pi pi-video text-secondary p-3 opacity-50" />
            </template>
        </Card>
        <Dialog v-model:visible="visible" modal header="Create a post" :style="{ width: '50rem' }">
           <div class="d-flex flex-row gap-2 align-items-center">
            <Avatar :image="basicInfo.profile_image"  style="width:50px;height:50px;" shape="circle" @click="toggle"/>
            <div>
                <p class="m-0">{{ basicInfo.first_name }}</p>
                <Button label="Public" icon="pi pi-globe" outlined severity="secondary" class="w-100"  size="small" @click="toggle"/>
                <Popover ref="op">
                    <div class="d-flex flex-column gap-2">
                        Who can See your Post?
                        <div class="d-flex gap-2 align-items-center justify-content-between">
                           <div class="d-flex align-items-center gap-2">
                                <i class="pi pi-globe"></i>
                                <label for="ingredient1">public</label>
                           </div>
                            <RadioButton v-model="visiblity" inputId="ingredient1" name="pizza" value="Cheese" />
                        </div>
                        <div class="d-flex gap-2 align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="pi pi-lock"></i>
                                <label for="ingredient1">Only Me</label>
                            </div>
                             <RadioButton v-model="visiblity" inputId="ingredient1" name="pizza" value="Cheese" />
                        </div>
                    </div>
                </Popover>
            </div>
                
           </div>
           <Textarea v-model="postText" rows="5" size="large" :pt="{root:'w-100 mt-3 border-0'}"  placeholder="What's on your mind ...."/>
            <template #footer>
                <Button label="Post" severity="info" @click="visible = false" autofocus />
            </template>
        </Dialog>
    </div>
</template>
<script lang="ts" setup>
import Card from 'primevue/card';
import Avatar from 'primevue/avatar';
import Dialog from 'primevue/dialog';
import {ref, computed} from 'vue';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import RadioButton from 'primevue/radiobutton';
import { useUserProfile } from '../../stores/User/userProfile';
import userPng from '../../assets/user.png';

const userProfile = useUserProfile();
const basicInfo = computed(()=> userProfile.getSummaryDetails);
const visible = ref(false);
const op = ref<InstanceType<typeof Popover> | null>(null);
const visiblity = ref(null);
const postText = ref('');

const toggle = (event:any) => {
    if(op.value)
    {
        op.value.toggle(event)
    }
}
</script>
<style scoped>
.share-post {

    padding: 3px;
    cursor: pointer;
}
.share-post:hover {
    background-color: #f0f0f0;
}
textarea {
  resize: none;
}
</style>