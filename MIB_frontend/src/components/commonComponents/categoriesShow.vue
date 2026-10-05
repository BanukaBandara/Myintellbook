<template>
     <Card class="mb-3" :pt="{
        root:'w-75'
    }">
        <template #title>
            <span class="fw-semibold" style="font-size:18px;">Exams</span>
            <i class="pi pi-question-circle text-secondary mx-1" style="font-size:12px;"></i>
        </template>
        <template #content>
            <Tabs value="0" class="w-100">
                <TabList>
                    <Tab value="0" class="col-md-6">Newe</Tab>
                    <Tab value="1" class="col-md-6">My</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="0">
                        <ul class="list-unstyled m-0 p-0 category-list">
                            <li class="p-2 d-flex flex-column" @click="toggle('Category 1')">
                                <span class="fw-semibold">Category 1</span>
                                <span class="text-secondary opacity-75 fw-semibold" style="font-size:12px;"> created 6 hours ago</span>
                            </li>
                            <li class="p-2 d-flex flex-column" @click="toggle('Category 2')">  
                                <span class="fw-semibold">Category 2</span>
                                <span class="text-secondary opacity-75 fw-semibold" style="font-size:12px;"> created 8 hours ago</span>
                            </li>
                            <li class="p-2 d-flex flex-column" @click="toggle('Category 3')">
                                <span class="fw-semibold">Category 3</span>
                                <span class="text-secondary opacity-75 fw-semibold" style="font-size:12px;"> created 10 hours ago</span>
                            </li>
                        </ul>
                        
                    </TabPanel>
                    <TabPanel value="1">
                        <ul class="list-unstyled m-0 p-0 category-list">
                            <li class="p-2 d-flex flex-column" @click="toggleMyCat('Category 4')">
                                <span class="fw-semibold">Category 4</span>
                                <span class="text-secondary opacity-75 fw-semibold" style="font-size:12px;"> Added to my categories 6 hours ago</span>
                            </li>
                            <li class="p-2 d-flex flex-column" @click="toggleMyCat('Category 5')">  
                                <span class="fw-semibold">Category 5</span>
                                <span class="text-secondary opacity-75 fw-semibold" style="font-size:12px;"> Added to my categories 8 hours ago</span>
                            </li>
                            <li class="p-2 d-flex flex-column" @click="toggleMyCat('Category 6')">
                                <span class="fw-semibold">Category 6</span>
                                <span class="text-secondary opacity-75 fw-semibold" style="font-size:12px;"> Added to my categories 10 hours ago</span>
                            </li>
                        </ul>
                        
                    </TabPanel>
                </TabPanels>
            </Tabs>
            
        </template>
        
    </Card>
    <DetailsOfCat :visible="visible" @update:visible="visible = false" :title="title" />
    <DetailsOfMyCat :visible="visibleCat" @update:visible="visibleCat = false" :title="catTitle"/>
</template>
<script lang="ts" setup>
import { ref } from 'vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import DetailsOfCat from '@/components/HomePage/DetailsOfCat.vue';
import DetailsOfMyCat from '@/components/HomePage/DetailsOfMyCat.vue';
import Card from 'primevue/card';

const visible = ref(false);
const title = ref('');
const catTitle = ref('');
const visibleCat = ref(false);

const toggle = (header:string) => {
    visible.value = true;
    title.value = header;
}

const toggleMyCat = (header:string) => {
    visibleCat.value = true;
    catTitle.value = header;
}
</script>
<style scoped>
.category-list{
    cursor: pointer;
}
</style>