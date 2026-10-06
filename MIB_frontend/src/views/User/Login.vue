<template>
<div class="flex-grow-1 overflow-auto">
    <div class="row h-100 me-0 mx-0 d-flex justify-content-center align-items-center">

        <div class="align-content-center col-md-6">
          <formComponent />
        </div>
    </div>
</div>
</template>
<script setup lang="ts">
import formComponent from '@/components/userLogin/formComponent.vue';
</script>
<style scoped>
.image-section{
    background-color:#ebe5d4;
    color:#a03829;
}
@media screen {
    @media (min-width: 768px) {
        .image-section{
            display:none;
        }
    }
    /* @media (min-width: 768px) {
        .image-section{
            display:none;
        }
    } */
}
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) {
    height: 100% !important;
}
</style>