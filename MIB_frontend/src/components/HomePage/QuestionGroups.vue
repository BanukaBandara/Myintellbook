<template>
     <Card class="mb-3" id="group1">
        <template #title>
            <div class="d-flex justify-content-between align-items-center justify-content-center border-bottom pb-2"> 
                <div class="d-flex flex-column">
                    <span class="fs-5"> Question Group 01</span>
                    <span style="font-size:12px;" class="text-secondary fw-semibold"> Category : Category 01 
                    <span class="mx-2">
                            <i class="pi pi-star-fill" style="font-size:12px;color:#a03829;"/>
                            <i class="pi pi-star-fill" style="font-size:12px;color:#a03829;"/>
                            <i class="pi pi-star-fill" style="font-size:12px;color:#a03829;"/>
                    </span>
                    </span>
                </div>
                <i class="pi pi-ellipsis-v cursor-pointer sorting-icon" style="font-size:14px;" @click="toggle($event)"></i>
                <Menu ref="menuShow" :model="items" :popup="true">
                    <template #start>
                        <div class="d-flex justify-content-end align-items-center">
                            
                            
                        </div>
                    </template>
                    <template #item="{ item, props }" v-bind="props.action">
                        <div class="p-2 d-flex align-items-center justify-content-between">
                            <span>
                                <span :class="item.icon" />
                                <span class="text-dark m-2">{{ item.label }}</span>
                            </span>
                        </div>
                        
                    </template>
                </Menu>
                
            </div>
                
        </template>
        <template #content>
            <p class="m-0">
                orem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.
            </p>
        </template>
    </Card>
</template>
<script setup lang="ts">
import Card from 'primevue/card';
import { ref } from 'vue';
import Menu from 'primevue/menu';
const menuShow = ref<InstanceType<typeof Menu> | null>(null);
const items = ref([
    { label: 'Skip', command: () => { console.log('Sorting by Recent'); },icon:'pi pi-forward'},
    { label: 'Start Test', command: () => { console.log('Sorting by Recent'); }, icon:'pi pi-play'},
    { label: 'Open Test', command: () => { console.log('Sorting by Recent'); }, icon:'pi pi-folder-open'}
]);

const toggle = (event: Event) => {
    // Logic to show or hide question groups
    menuShow.value?.toggle(event);
};

</script>