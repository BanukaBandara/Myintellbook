<template>
<div class="login-page-wrapper d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <formComponent />
            </div>
        </div>
    </div>
</div>
</template>
<script setup lang="ts">
import formComponent from '@/components/userLogin/formComponent.vue';
</script>
<style scoped>
.login-page-wrapper {
    background-color: var(--ds-surface-subtle);
    background-image: 
        radial-gradient(at 0% 0%, rgba(160, 56, 41, 0.05) 0px, transparent 50%),
        radial-gradient(at 100% 100%, rgba(160, 56, 41, 0.03) 0px, transparent 50%);
}
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) {
    height: 100% !important;
}
</style>