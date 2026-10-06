<template>
    <div class="col-md-12 d-flex justify-content-center align-items-center border-bottom-1 border p-0 bg-white" v-if="!routeParam">
          <div class="d-none d-xl-flex justify-content-between align-items-center" style="width: 65%;">
            <img src="../assets/webIcon.jpeg" alt="Logo" class="logo" style="width:45px; " />
            <div class="d-flex justify-content-end align-items-center gap-2 w-100">
                <navBarSearch />
                <navBarItems />
                <navBarDropDown />
            </div>
          </div>
          <!-- Mobile Dropdown Menu -->
    </div>
    <navBarMobile />
</template>
<script setup lang="ts">

import navBarMobile from './commonComponents/navBarMobile.vue';
import { computed } from "vue";
import { useRoute } from 'vue-router';
import navBarDropDown from './commonComponents/navBarDropDown.vue';
import navBarSearch from './commonComponents/navBarSearch.vue';
import navBarItems from './commonComponents/navBarItems.vue';

const route = useRoute();
const routeParam = computed(()=>route.meta.hideNavBar);
</script>
<style>
.nav-label {
    font-size: 0.8rem;
    color: #333;
    cursor: pointer;
}
.nav-item {
    text-align: center;
    padding: 0.5rem;
    transition: background-color 0.3s ease;
}
.nav-item:hover {
    background-color: #f0f0f0;
}
.nav-list {
    display: flex;
    justify-content: space-between;
    align-items: center;
    list-style: none;
    padding: 0;
    margin: 0;
}
</style>
