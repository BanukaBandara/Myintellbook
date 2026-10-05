<template>
    <Divider  layout="horizontal" align="right"
        :pt="{
            content: {
                style: {
                    backgroundColor: '#f4f0f0',
                },
            }
        }"
    >
        
        <span class="font-size">Sort By:<b>{{sortingKey}}</b></span>
        <i class="pi pi-sort-down-fill cursor-pointer sorting-icon" @click="showHideGroup($event)"></i>
        <i class="pi pi-info-circle px-2 sorting-icon text-dark" @click="openPopover"/>
        <Popover ref="popOver">
            <div>
                <ul class="list-unstyled m-0 p-2">
                    <li class="font-size"><i class="pi pi-angle-right"></i>You can sort the activites by <b>Dificulty or Recent</b>.</li>
                </ul>
            </div>
        </Popover>
            <Menu  ref="menu" :model="items" :popup="true">
            <template #item="{ item, props }" v-bind="props.action">
                <span class="text-dark mb-2 p-2">{{ item.label }}</span>
            </template>
            </Menu>
    </Divider>
</template>
<script setup lang="ts">
import {ref } from 'vue';
import Divider from 'primevue/divider';
import Menu from 'primevue/menu';
import Popover from 'primevue/popover';
const menu = ref< InstanceType<typeof Menu> | null>(null);
const sortingKey = ref('Dificulty');
const popOver = ref<InstanceType<typeof Popover> | null>(null);
const items = ref([
    { label: 'Dificulty', 
        command:() =>{
            sortingKey.value = 'Dificulty';
        }
     },
    { label: 'Recent',
        command: () => {
            sortingKey.value = 'Recent';
        }
     }
]);


const showHideGroup = (event: any) => {
   if(menu.value)
   {
        menu.value?.toggle(event);
   }  
};

const openPopover = (event: any) => {
    if(popOver.value)
    {
        popOver.value?.toggle(event);
    }
};
</script>
<style scoped>
.font-size{
    font-size:13px;
}
.sorting-icon{
    font-size:11px;
    cursor: pointer;
}
</style>