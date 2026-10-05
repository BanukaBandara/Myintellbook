<template>
    <div :class="(notificationviewed) ? 'd-flex flex-row align-items-center p-2 mb-2': 'd-flex flex-row align-items-center bg-light p-2 mb-2'" @click="viewNotification">
        <!-- <Avatar :image="profileImage" class=" mr-2 me-2" size="xlarge" style="width:auto;height:40px;" /> -->
        <div class="d-flex flex-column justify-content-center">
            <p class="m-0" style='font-size:13px;'> {{ name +" ,"+message }}</p>
            <p class="m-0 opacity-50" style="font-size:12px;"> {{ created_at }}</p>
        </div>
    </div>
</template>
<script lang="ts" setup>
import Avatar from 'primevue/avatar';
import { ref , defineProps, computed} from 'vue';
import Image from 'primevue/image';
import userPng from '@/assets/user.png';

const notificationviewed = ref<boolean>(false);
const props = defineProps({
    message:{
        type: String,
        default:''
    },
    created_at:{
        type:String,
        default:''
    },
    profileImage:{
        type:String,
        default:userPng
    },
    name:{
        type:String,
        default:''
    }
});

const message = computed(()=> props.message);
const created_at = computed(()=> props.created_at);
const profileImage = computed(()=> props.profileImage);
const name = computed(()=>props.name);

const viewNotification = () => {
    notificationviewed.value = true;
}
</script>
<style scoped>
div{
    cursor: pointer;
}
</style>