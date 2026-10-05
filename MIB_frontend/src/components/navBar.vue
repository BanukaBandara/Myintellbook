<template>
    <header class="app-navbar w-full h-16 shrink-0 bg-white border-b border-gray-200 z-50 flex items-center px-6 shadow-sm navbar-desktop d-none d-xl-flex justify-content-center" v-if="!routeParam">
          <div class="navbar-inner d-flex justify-content-between align-items-center">
            <RouterLink to="/home" class="navbar-brand-lockup text-decoration-none">
              <img src="../assets/webIcon.jpeg" alt="MyIntelliBook" class="logo" />
              <span>MyIntelli<span>Book</span></span>
            </RouterLink>
            <div class="d-flex justify-content-end align-items-center gap-3">
                <navBarSearch />
                <navBarItems />
                <navBarDropDown />
            </div>
          </div>
    </header>
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
.navbar-inner {
    width: min(100%, 1500px);
    min-height: 4rem;
    padding: 0.6rem clamp(0.75rem, 2vw, 2rem);
}
.navbar-brand-lockup {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    color: var(--ds-text);
    font-size: 1.08rem;
    font-weight: 750;
    white-space: nowrap;
}
.navbar-brand-lockup > span > span {
    color: var(--ds-primary);
}
.navbar-brand-lockup .logo {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.75rem;
    object-fit: cover;
}
.navbar-user-trigger {
    min-height: 2.8rem;
    padding: 0.25rem 0.65rem;
    border: 1px solid transparent;
    border-radius: 999px;
    transition: background-color 180ms ease, border-color 180ms ease;
}
.navbar-user-trigger:hover {
    border-color: var(--ds-primary-100);
    background: var(--ds-primary-soft);
}
.navbar-user-name {
    max-width: 10rem;
    overflow: hidden;
    color: var(--ds-text);
    font-size: 0.8rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dashboard-link {
    color: var(--ds-primary);
    font-size: 0.85rem;
    font-weight: 650;
}
.dashboard-link:hover {
    color: var(--ds-primary-hover);
}
.nav-label {
    font-size: 0.8rem;
    color: var(--ds-text-secondary);
    cursor: pointer;
}
.nav-item {
    text-align: center;
    min-width: 4.25rem;
    padding: 0.45rem 0.65rem;
    border-radius: 0.8rem;
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: color 180ms ease, background-color 180ms ease, box-shadow 180ms ease;
}
.nav-item:hover {
    color: var(--ds-primary);
    background-color: var(--ds-primary-soft);
}
.nav-item.is-active {
    color: var(--ds-primary);
    background-color: var(--ds-primary-soft);
    box-shadow: inset 0 -2px 0 var(--ds-primary);
}
.nav-item.is-active .nav-label {
    color: var(--ds-primary);
    font-weight: 700;
}
.nav-item > i,
.nav-item .p-overlay-badge > i {
    margin: 0 auto 0.2rem;
}
.nav-mobile .p-button {
    border-radius: 999px;
}
.mobile-dropdown {
    position: fixed;
    top: 4rem;
    right: 0;
    left: 0;
    z-index: 49;
    max-height: calc(100vh - 4rem);
    overflow-y: auto;
}
.mobile-dropdown li {
    border-radius: 0.75rem;
    padding: 0.65rem 0.75rem;
    transition: background-color 160ms ease, color 160ms ease;
}
.mobile-dropdown li:hover {
    color: var(--ds-primary);
    background: var(--ds-primary-soft);
}
.nav-list {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.25rem;
    list-style: none;
    padding: 0;
    margin: 0;
}
</style>
