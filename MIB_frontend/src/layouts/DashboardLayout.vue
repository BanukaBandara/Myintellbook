<template>
    <!--
      Shared 3-column shell for every route with meta.layout === 'dashboard' (see App.vue).
      It stays mounted while navigating between those routes, so the sidebars and their
      sticky behaviour persist; only the middle column (the routed page) changes.
    -->
    <div class="dashboard-page dashboard-layout">
        <!-- align-items-start is required: a stretched column has no room to stick. -->
        <div class="row g-3 align-items-start">
            <aside
                class="col-md-4 col-xl-3 d-none d-md-block sticky-sidebar no-print"
                aria-label="Your profile and site navigation"
                v-sticky-sidebar
            >
                <ProfileDetails />
                <addSiteDeails class="mt-3" />
            </aside>

            <div class="col-12 col-md-8 col-xl-6 dashboard-main">
                <slot />
            </div>

            <aside
                class="col-xl-3 d-none d-xl-block sticky-sidebar no-print"
                aria-label="Latest updates and profiles"
                v-sticky-sidebar
            >
                <latestUpdates class="dashboard-card" />
                <ProfileList class="mt-3" />
            </aside>
        </div>
    </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import ProfileDetails from '@/components/HomePage/ProfileDetails.vue';
import addSiteDeails from '@/components/commonComponents/addSiteDeails.vue';

// The right column is secondary; load its code separately so the page content isn't held up.
const latestUpdates = defineAsyncComponent(() => import('@/components/commonComponents/latestUpdates.vue'));
const ProfileList = defineAsyncComponent(() => import('@/components/HomePage/ProfileList.vue'));
</script>

<style scoped>
.dashboard-layout { padding-bottom: 32px; }

/* Pages written as standalone screens (e.g. the tribunal views use .container py-4) sit inside
   the middle column now: drop their outer gutter so they line up with the sidebars. */
.dashboard-main > :deep(.container),
.dashboard-main > :deep(.container-fluid) {
    max-width: none;
    padding-right: 0;
    padding-left: 0;
}
.dashboard-main > :deep(.container.py-4),
.dashboard-main > :deep(.container-fluid.py-4) { padding-top: 0 !important; }
</style>
