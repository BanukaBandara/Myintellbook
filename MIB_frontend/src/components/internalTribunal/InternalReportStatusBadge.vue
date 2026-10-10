<template>
  <span :class="['internal-status-badge', badgeClass, sizeClass]">
    <i :class="['bi', iconClass]" aria-hidden="true"></i>
    <span>{{ label }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  status: string;
  size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
});

const label = computed(() => {
  switch (props.status) {
    case 'Submitted':
      return 'Submitted';
    case 'UnderReview':
      return 'Under Review';
    case 'NeedsMoreInformation':
      return 'Needs Info';
    case 'Valid':
      return 'Valid';
    case 'Invalid':
      return 'Invalid';
    case 'Closed':
      return 'Closed';
    default:
      return props.status || 'Unknown';
  }
});

const badgeClass = computed(() => {
  switch (props.status) {
    case 'Submitted':
      return 'badge-submitted';
    case 'UnderReview':
      return 'badge-under-review';
    case 'NeedsMoreInformation':
      return 'badge-needs-info';
    case 'Valid':
      return 'badge-valid';
    case 'Invalid':
      return 'badge-invalid';
    case 'Closed':
      return 'badge-closed';
    default:
      return 'badge-default';
  }
});

const iconClass = computed(() => {
  switch (props.status) {
    case 'Submitted':
      return 'bi-clock';
    case 'UnderReview':
      return 'bi-search';
    case 'NeedsMoreInformation':
      return 'bi-question-circle';
    case 'Valid':
      return 'bi-check-circle-fill';
    case 'Invalid':
      return 'bi-x-circle';
    case 'Closed':
      return 'bi-archive';
    default:
      return 'bi-info-circle';
  }
});

const sizeClass = computed(() => `size-${props.size}`);
</script>

<style scoped>
.internal-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-weight: 700;
  border-radius: 999px;
  border: 1px solid transparent;
  white-space: nowrap;
  line-height: 1.2;
}

.size-sm {
  padding: 3px 9px;
  font-size: 11.5px;
}

.size-md {
  padding: 5px 12px;
  font-size: 12.5px;
}

.size-lg {
  padding: 6px 14px;
  font-size: 13.5px;
}

.badge-submitted {
  color: #1d4ed8;
  background-color: #eff6ff;
  border-color: #bfdbfe;
}

.badge-under-review {
  color: #b45309;
  background-color: #fffbeb;
  border-color: #fde68a;
}

.badge-needs-info {
  color: #c2410c;
  background-color: #fff7ed;
  border-color: #fed7aa;
}

.badge-valid {
  color: #047857;
  background-color: #ecfdf5;
  border-color: #a7f3d0;
}

.badge-invalid {
  color: #4b5563;
  background-color: #f3f4f6;
  border-color: #e5e7eb;
}

.badge-closed {
  color: #6b21a8;
  background-color: #faf5ff;
  border-color: #e9d5ff;
}

.badge-default {
  color: #374151;
  background-color: #f9fafb;
  border-color: #e5e7eb;
}
</style>
