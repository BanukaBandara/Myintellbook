<template>
<div class="flex-grow-1 overflow-auto">
    <div class="row h-100 me-0 mx-0 d-flex justify-content-center align-items-center">
        <div class="col-md-6 align-content-center">
            <formComponent />
        </div>
    </div>
</div>
</template>
<script setup lang="ts">
import {onMounted} from 'vue';
import formComponent from '@/components/userRegister/formComponent.vue';
</script>
<style scoped>
.image-section{
    background-color:#ebe5d4;
    color:#a03829;
}

</style>