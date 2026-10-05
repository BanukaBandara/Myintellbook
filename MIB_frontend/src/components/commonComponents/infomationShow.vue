<template>
<div class="custom-alert d-flex p-0 border-1" :class="borderClass">
  <div class="d-flex align-items-center justify-content-center icon-box" :class="bgClass">
    <i class="bi bi-exclamation-circle-fill text-white fs-5"></i>
  </div>
  <div class="flex-grow-1 d-flex align-items-center px-3">
    <span>
      {{message}}
    </span>
  </div>
</div>
</template>
<script lang="ts" setup>
import { ref, defineProps, computed } from 'vue';
const props = defineProps({
        message:{
            type: String,
            default: ''
        },
        bgClass: {
            type: String,
            default: 'bg-primary'
        },
        borderClass: {
            type: String,
            default: 'border-primary'
        }
});

const message = computed(() => props.message || '');
</script>
<style>
.custom-alert {
  /* border-left: 4px solid #f0ad4e; */
  background-color: #ffff;
  border:1px solid;
  border-radius: 4px;
  overflow: hidden;
  font-size: 14px;
  min-height:40px;
}

.icon-box {
  width: 50px;
}
</style>