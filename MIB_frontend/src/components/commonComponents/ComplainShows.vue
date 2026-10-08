<template>
    <div class="d-flex gap-2 mb-3 mt-4 flex-wrap">
        <div class="d-flex flex-column">
            <div class="row g-3 mb-3"></div>
        </div>

        <div class="d-flex gap-2">
            <Button
                label="Submitted"
                icon="pi pi-clock"
                class="p-button-sm"
                :class="{ 'p-button-primary': activeStatus === 'submitted', 'p-button-outlined': activeStatus !== 'submitted' }"
                @click="getStatusComplains('submitted')"
            />

            <Button
                label="In-Progress"
                icon="pi pi-spinner"
                class="p-button-sm"
                :class="{ 'p-button-primary': activeStatus === 'inprogress', 'p-button-outlined': activeStatus !== 'inprogress' }"
                @click="getStatusComplains('inprogress')"
            />

            <Button
                label="Solved"
                icon="pi pi-check-circle"
                class="p-button-sm"
                :class="{ 'p-button-success': activeStatus === 'solved', 'p-button-outlined': activeStatus !== 'solved' }"
                @click="getStatusComplains('solved')"
            />
        </div>
    </div>

    <!-- INTERNAL Complaint List -->
    <div class="mt-4">
        <h6 class="fw-bold text-secondary mb-3">Previous Complaints</h6>

        <div
            v-for="(complain, index) in ComplainsArray"
            :key="index"
            class="complain-card p-4 rounded-4 shadow-sm mb-3 border bg-white"
        >
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted">#{{ index + 1 }}</small>
                    <h6 class="fw-bold mb-1">👤 {{ 'from: '+ complain.complain_from }}</h6>
                    <h6 class="fw-bold mb-1">👤 {{ 'to: '+ complain.user }}</h6>
                </div>

                <!-- STATUS BADGE -->
                <span
                    class="badge px-3 py-2 rounded-pill"
                    :class="{
                        'bg-secondary text-white': complain.status === 'submitted',
                        'bg-warning text-dark': complain.status === 'inprogress',
                        'bg-success': complain.status === 'solved'
                    }"
                    style="font-size: 0.75rem;"
                >
                    {{ complain.status }}
                </span>
            </div>

            <!-- ⭐ JURY PANEL SECTION -->
            <div class="mt-3" v-if="activeStatus =='inprogress'">
                <h6 class="fw-semibold text-secondary mb-2">Jury Panel</h6>

                <div class="d-flex flex-wrap gap-2" >
                    <Button
                        v-for="(jury, jIndex) in complain.jury"
                        :key="jIndex"
                        severity="secondary"
                        style="min-width: 150px;"
                        label="Secondary"
                        @click="()=> router.push(`/showUserProfile/${jury.profile_url}`)"
                    >
                         <Avatar :image="jury.profile_image" class="mr-2" size="large" shape="circle" />
                        <div class="d-flex flex-column">
                           
                            <span class="fw-semibold">{{ jury.name }}</span>
                        </div>
                    </Button>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                <small class="text-muted">📅 {{ complain.date }}</small>

                <Button
                    v-if="complain.status === 'submitted'"
                    label="Start Processing"
                    icon="pi pi-play"
                    class="p-button-sm p-button-text text-primary"
                />
            </div>
        </div>

        <div v-if="!ComplainsArray.length" class="text-center text-muted mt-4">
            <i class="pi pi-inbox fs-3 d-block mb-2"></i>
            No complaints {{ activeStatus }} yet.
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, defineProps, defineEmits } from 'vue';
import type { PropType } from 'vue';
import Button from 'primevue/button';
import Avatar from 'primevue/avatar';
import router from '@/router';

const props = defineProps({
    activeStatus:{
        type: String,
        default: 'submitted'
    },
    ComplainsArray:{
        type: Array as PropType<Array<{
            user: string;
            complain_from: string;
            complain: string;
            status: string;
            date: string;
            jury:Array<{
                name:string,
                profile_image:string,
                profile_url:string

            }>;
        }>>,
        default: () => []
    }
});

const emits = defineEmits(['statusChange']);
const getStatusComplains = (status:string) =>{
    // Fetch complains based on status
   emits('statusChange', status);
}

</script>
