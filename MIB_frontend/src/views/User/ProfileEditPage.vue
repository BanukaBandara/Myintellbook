<template>
        <div class="row m-0 overflow-hidden">
            <div class="col-md-1 flex-grow-0 d-none d-md-flex"></div>
            <div class="col-md-3 mt-4 d-none d-md-block">
                <Card :pt="{
                    root: 'd-none d-md-flex'
                }">
                    <template #content>
                       <div v-if="isEditShow" class="d-flex justify-content-between align-items-center">
                         
                        <Button severity="secondary" outlined size="small"><i class="pi pi-user" /><RouterLink class="text-decoration-none text-dark" to="/profile">View Profile</RouterLink></Button>
                        <h5 class="p-0 m-0">Edit </h5>
                       </div>
                       <div v-else>
                        <PanelMenu  :model="items" class="w-100" :pt="{
                            panel:'border-0',
                            item:'mt-2'
                        }" /> 
                        </div>
                         
                    </template>
                </Card>
            </div>
            <div class="col-md-7 h-100">
                <div class="h-100 d-flex flex-column">
                    <Menubar :model="items" :pt="{
                        root:'d-flex d-md-none',
                        content:'p-2'
                    }" />
                    <router-view />
                </div>
            </div>
        </div>
</template>
<script lang="ts" setup>
import Card from 'primevue/card';
import {ref, computed} from 'vue';
import Button from 'primevue/button';
import PanelMenu from 'primevue/panelmenu';
import Menubar from 'primevue/menubar';
import { useRoute, useRouter } from 'vue-router';
const route = useRoute();
const router = useRouter();

const items = ref([
        {
        label: 'View Profile',
        icon: 'pi pi-user',
            command: () => {
            router.push('/profile');
        },
    },
    {
        label: 'Profile Information',
        icon: '',
        items: [
            {
                label: 'General Information',
                icon: 'pi pi-user',
                command: () => {
                    router.push('/profileEdit/generalInfo');
                },
            },
            {
                label: 'Work Experiance',
                icon: 'pi pi-briefcase',
                command: () => {
                    router.push('/profileEdit/workExperience');
                },
            },
             {
                label: 'Education Information',
                icon: 'pi pi-book',
                command: () => {
                    router.push('/profileEdit/educationInfo');
                },
            },
             {
                label: 'Skills Information',
                icon: 'pi pi-globe',
                  command: () => {
                    router.push('/profileEdit/skillsInfo');
                },
            },
        ]
    },
    {
        label: 'Profile Photo',
        icon: 'pi pi-camera',
        command: () => {
                    router.push('/profileEdit/addProfileImage');
        },
    },
    {
        label: 'Cover Photo',
        icon: 'pi pi-image',
        command:()=>{
            router.push('/profileEdit/addCoverImage');
        }
    }
]);

const isEditShow = computed(()=> route.params.id);
</script>