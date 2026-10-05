<template>
    <Inplace :pt="{
                    display: 'p-0'
                }">
        <template #display>  
            <span class="opacity-50" style="font-size:12px;"> {{visibility}}</span>
            <span class="fw-semibold mx-1" style="font-size:12px;">Change</span>
            
        </template>
        <template #content="{ closeCallback }">
            <div class="d-flex flex-column gap-2">
                Select Who to see ?
                <div class="d-flex flex-row align-items-center gap-2">
                    <RadioButton v-model="visibility" inputId="ingredient1" name="pizza" value="Public" size="small" @change="toggle"/>
                    <label for="ingredient1">Public</label>
                </div>
                <div class="d-flex flex-row align-items-center gap-2">
                    <RadioButton v-model="visibility" inputId="ingredient1" name="pizza" value="Only Me" size="small" @change="toggle"/>
                    <label for="ingredient1">Only Me</label>
                </div>
                <Button  label="close" severity="secondary" size="small" @click="closeCallback" class="p-1" style="width:100px"/>
            </div>
        </template>
    </Inplace>
</template>
<script lang="ts" setup>
import { ref, defineEmits, defineProps, computed, onMounted, watch } from 'vue';
import Inplace from 'primevue/inplace';
import RadioButton from 'primevue/radiobutton';
import Button from 'primevue/button';
const visibility = ref<string>('Public');
const emit = defineEmits(['visibilityChange']);
const visibilityTypes = ref([
    { label: 'Public', id: 1 },
    { label: 'Only Me', id: 2 },
])

const props = defineProps({
    field: {
        type: String,
        default: ''
    },
    visibility:{
        type: String,
        default:'Public'
    }
});

const setVisibility = computed(()=> props.visibility);


watch(setVisibility,()=>{
    visibility.value = setVisibility.value
});

const toggle = () => {
    let visibilityValue = visibilityTypes.value.find((item)=> item.label === visibility.value)
    if (visibilityValue) {
        emit('visibilityChange', { field: props.field, value:visibilityValue.label });
    }
}

onMounted(()=>{
    console.log(props.visibility);
visibility.value = setVisibility.value
})

</script>