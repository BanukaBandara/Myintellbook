<template>
        <Dialog v-model:visible="visible" modal header="Edit Profile" :style="{ width: '25rem' }">
            <NewExamCreate />
            <div class="flex justify-end gap-2">
                <Button type="button" label="Cancel" severity="secondary" @click="visible = false"></Button>
                <Button type="button" label="Save" @click="visible = false"></Button>
            </div>
        </Dialog>

</template>
<script lang="ts" setup>
import { ref, computed } from 'vue';
import Dialog from 'primevue/dialog';
import NewExamCreate from '../HomePage/NewExamCreate.vue';  
const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    }
});
const visible = computed(() => props.visible);
</script>