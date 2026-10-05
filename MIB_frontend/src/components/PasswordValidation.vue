<template>
<Popover ref="op" :closeOnEscape="false" id="listOfValidations">
            <div class="flex flex-col gap-4">
                 <div>
                    <p :class="{ passed: hasMinLength }"> Is at least 12 characters long</p>
                    <p :class="{ passed: hasUpperCase }"> Has at least 1 uppercase letter</p>
                    <p :class="{ passed: hasLowerCase }"> Has at least 1 lowercase letter</p>
                    <p :class="{ passed: hasNumber }"> Has at least 1 number</p>
                    <p :class="{ passed: hasSpecialChar }"> Has at least 1 special character</p>
                </div>
            </div>
        </Popover>
</template>
<script lang="ts" setup>
import Popover from 'primevue/popover';
import { ref, computed, defineProps,defineExpose } from 'vue';

const op = ref< InstanceType<typeof Popover> | null>(null);
const props = defineProps({
    password: {
        type: String,
        required: true
    }
})

const toggle = (event:any) =>{ if(op.value){op.value.toggle(event);}}
const password = computed(() => props.password);

const hasMinLength = computed(() => password.value.length >= 12)
const hasUpperCase = computed(() => /[A-Z]/.test(password.value))
const hasLowerCase = computed(() => /[a-z]/.test(password.value))
const hasNumber = computed(() => /\d/.test(password.value))
const hasSpecialChar = computed(() => /[\W_]/.test(password.value));

defineExpose({
    toggle
})
</script>
<style scoped>
    .passed {
        color: green;
        text-decoration: line-through;
    }


</style>