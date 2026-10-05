<template>
<span>
        <div 
            :class="(completed) ? 
            'd-flex justify-content-between align-items-center text-dark mx-2':
            'd-flex justify-content-between align-items-center text-secondary opacity-75 mx-2'"
        >                  
            <span 
                :class="(completed) ? 'rounded-circle border p-1 d-flex justify-content-center align-items-center completed-bg':
                'rounded-circle border p-1 d-flex justify-content-center align-items-center border-secondary opacity-75'" 
                style="width:20px;"
            >
                <i class="pi pi-check text-white completed-color" style="font-size:12px;"></i>
            </span>
            <router-link :to="link" class="fw-semibold w-100 mx-2 text-decoration-none text-dark"><span class="fw-semibold w-100 mx-2" style="font-size:13px;">{{description}}</span></router-link> 
            <span :class="(completed) ? 'fw-semibold completed-color':'fw-semibold text-secondary opacity-75'">{{completedquantity}}/{{totalSteps}}</span>
        </div>
        <div style="width:2px;height:10px;" :class="(completed) ? 'mx-3 mt-1 mb-1 completed-bg':'mx-3 mt-1 mb-1 bg-secondary opacity-50'"></div>
    </span>
   
</template>
<script lang="ts" setup>
import { ref, defineProps, computed } from 'vue';
import Card from 'primevue/card';
const props = defineProps({
    description: {
        type: String,
        default: ''
    },
    totalSteps: {
        type: Number,
        default: 3
    },
    completedquantity: {
        type: Number,
        default: 0
    },
    completed: {
        type: Boolean,
        default: false
    },
    link:{
        type: String,
        default: ''
    }
});

const description = computed(() => props.description || 'General Information');
const totalSteps = computed(() => props.totalSteps || 3);
const completedquantity = computed(() => props.completedquantity || 0);
const completed = computed(() => props.completed || false);
</script>
<style scoped>

</style>
