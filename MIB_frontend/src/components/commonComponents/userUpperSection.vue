<template>
    <Card :pt="{
               body:{
                class:'p-0',
                style:'height:345px;'
               }
            }">
                <template #content>
                    <div class="profile-container mb-4 position-relative">

                        <div
                            class="cover-photo p-4 text-end"
                            @click="()=>{ router.push('/profileEdit/addCoverImage')}"
                            :style="{ backgroundImage: `url(${(userGeneralInfo.cover_image)?userGeneralInfo.cover_image:coverImageSet})` }"
                        >
                            <!-- <i class="pi pi-pencil cover-icon fs-5 border rounded-circle p-2 bg-light text-primary" /> -->
                        </div>

                        <!-- Profile Image (absolute, left bottom corner of cover) -->
                        <div
                            class="avatar-wrapper position-absolute"
                            style="left: 20px; top: calc(100% - 60px);" 
                            @click="()=>{ router.push('/profileEdit/addProfileImage')}"
                        >
                            <!-- <i class="pi pi-camera fs-5 border rounded-circle p-2 bg-light text-primary profile-image-icon position-absolute" /> -->
                            <Avatar
                            :image="(userGeneralInfo.profile_image) ? userGeneralInfo.profile_image : userPng"
                            class="profile-image"
                            shape="circle"
                            />
                        </div>

                        <!-- Info Row -->
                        
                    </div>
                   
                    <div class="card mt-2 shadow-sm border-0 rounded-3">
                      <div class="card-body">
                        <div class="row">
                          
                          <!-- Left Column -->
                          <div class="col-md-6">
                                <h3 class="mb-1 fw-bold mb-2">{{ userGeneralInfo.first_name + " " + userGeneralInfo.last_name }}</h3>

                                <p class="text-muted mb-2 fw-semibold">
                                <i class="bi bi-person-badge"></i>  {{ userGeneralInfo.profession.profession+" ," }} <i class="bi bi-geo-alt"></i> {{ userGeneralInfo.profession.location }}
                                </p>

                                <p class="text-muted mb-1 fs-6" >  
                                    <span v-tooltip="'Human Intelligence Protfolio'"> HIP</span>
                                </p>

                              <!-- <p class="text-muted mb-1">
                                <i class="bi bi-star"></i> Total Points  {{ userGeneralInfo.total_points }}
                                </p> -->
                          </div>
                          
                          <!-- Right Column -->
                          <div class="col-md-6 text-md-end">
    
                            <p class="fw-semibold text-primary mb-1">
                              <i class="bi bi-building"></i>{{ userGeneralInfo.profession.company }}
                            </p>

                            <p class="text-muted mb-1">
                              <i class="bi bi-book"></i> {{userGeneralInfo.school }}
                            </p>
                             
                          </div>
                          
                        </div>
                      </div>
                    </div>

                </template>
            </Card>
    </template>
<script lang="ts" setup>

import { useRouter } from 'vue-router';
import userPng from '../../assets/user.png';
import type {userGeneralInfoType} from '../../types/userGeneralInfoType';
import {ref, type PropType ,computed} from 'vue';
import coverImageSet from '../../assets/default-cover-2.jpg';
import Avatar from 'primevue/avatar';
import Card from 'primevue/card';

const router = useRouter();
const props = defineProps({
    userGeneralInfo:{
        type:Object as PropType<userGeneralInfoType>,
        default:{}
    }
});
const userGeneralInfo = computed(()=> props.userGeneralInfo)
</script>
<style>
.profile-container {
  position: relative; /* very important */
  width: 100%;
  margin: auto;
}


.cover-photo {
  height: 200px;
  background-image: url('../../assets/cover.jpg');
  background-size: cover;
  background-position: center;
  background-color:red;
}

.profile-image {
  position: absolute;
  bottom: -92px; /* overlaps cover */
  left: 20px; /* small margin from left edge */
  width: 150px;
  height: 150px;
  border-radius: 50%;
  border: 4px solid white;
  object-fit: cover;
}

@media (max-width: 768px) {
  .profile-image {
    width: 120px;
    height: 120px;
    bottom: -40px;
    left: 15px;
  }
}

@media (max-width: 768px) {
  .profile-image {
    width: 120px;
    height: 120px;
    bottom: -40px;
    left: 20px; /* stays in same spot */
  }
}

@media (max-width: 480px) {
  .profile-image {
    width: 100px;
    height: 100px;
    bottom: -90px;
    left: -2px;
  }
}
.menuBar > div:hover{
    cursor:pointer;
    color:orange;
    border-bottom:orange 2px solid;
}
.underline{
    border-bottom:orange 2px solid;
}
.name-profile{
    position: absolute;
    bottom: 40px;
    left: 34%;
    font-size: 25px;
    font-weight: 600;
}
.cover-photo:hover{
    cursor: pointer;
    opacity: 0.7;
}

.cover-icon{
    display: none;
}
.cover-photo:hover .cover-icon{
    display:inline;
}

.avatar-wrapper {
  /* width: fit-content;
  height: fit-content; */
  position: relative;
  display: inline-block;
}

.profile-image-icon {
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 2;
  display: none;
}

.avatar-wrapper:hover{
    cursor: pointer;
}

.avatar-wrapper:hover .profile-image-icon{
     display:inline-block;
}

/* Push the content below the avatar */
.info-row {
  margin-top: 80px; /* adjust based on avatar size */
}

@media (max-width: 768px) {
  .info-row {
    margin-top: 70px;
  }
  .menuBar{
    margin-top:150px;
  }
}

@media (max-width: 480px) {
  .info-row {
    margin-top: 60px;
  }
  .menuBar{
    margin-top:110px;
  }
}


</style>