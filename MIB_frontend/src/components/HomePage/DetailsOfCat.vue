<template>
    <Dialog  :visible="visible"  @update:visible="emit('update:visible', $event)" modal :header="title" :style="{ width: '25rem' }">
                <div class="d-flex flex-column">
                    <Message 
                        severity="error" 
                        :closable="true" 
                        class="mb-2" 
                        v-if="showAlertError" 
                        :life="2000" 
                        @life-end="showAlertError = false" 
                        @close="showAlertError = false"
                        >
                        Please select at least one sub category
                    </Message>
                    <p>  This is the details of the category you selecting. and you can add subCategories to this category.</p>
                    <div v-for="category of categories" :key="category.key" class="d-flex align-items-center gap-2">
                        <Checkbox v-model="selectedCategories" :inputId="category.key" name="category" :value="category.name" />
                        <label :for="category.key">{{ category.name }}</label>
                    </div>
                    <span>
                        <p class="text-secondary opacity-75 fw-semibold" style="font-size:12px;">Select the sub categories you want to add to this category.</p>
                    </span>
                    
                </div>
                    <template #footer>
                   
                    <Button label="Add to Category List" outlined severity="secondary" class="w-100" @click="addCategory" autofocus />
                </template>
            
    </Dialog>
</template>
<script lang="ts" setup>
import { ref, defineProps, computed, watch, defineEmits } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import showAlert from '@/composables/showAlert';
import Message from 'primevue/message';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: 'Edit Profile'
    }
});

const emit = defineEmits(['update:visible']);

const visible = computed(() => props.visible);
const title = computed(() => props.title || 'Edit Profile');
const selectedCategories = ref<string[]>([]);
const showAlertError = ref(false);

const categories = ref([
    {name: "Accounting", key: "A"},
    {name: "Marketing", key: "M"},
    {name: "Production", key: "P"},
    {name: "Research", key: "R"}
]);

const addCategory = async() => {
    // Logic to add the selected categories
    if(selectedCategories.value.length === 0) {
        showAlertError.value = true;
        return;
    }
        showAlertError.value = false;


    emit('update:visible', false); // Close the dialog after saving
};
</script>