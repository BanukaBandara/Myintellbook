<template>
  <span :class="badgeClass" class="d-inline-flex align-items-center gap-1.5 fw-semibold">
    <i :class="badgeIcon" aria-hidden="true" />
    <span>{{ statusLabel }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    status: string;
    size?: 'sm' | 'md' | 'lg';
  }>(),
  {
    size: 'md',
  }
);

const statusLabel = computed(() => {
  switch (props.status) {
    case 'Submitted':
      return 'Submitted';
    case 'UnderReview':
      return 'Under Review';
    case 'NeedsMoreInformation':
      return 'Needs More Info';
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

const badgeIcon = computed(() => {
  switch (props.status) {
    case 'Submitted':
      return 'bi bi-inbox';
    case 'UnderReview':
      return 'bi bi-hourglass-split';
    case 'NeedsMoreInformation':
      return 'bi bi-question-circle-fill';
    case 'Valid':
      return 'bi bi-shield-check';
    case 'Invalid':
      return 'bi bi-shield-x';
    case 'Closed':
      return 'bi bi-archive-fill';
    default:
      return 'bi bi-info-circle';
  }
});

const badgeClass = computed(() => {
  const sizeClasses =
    props.size === 'sm'
      ? 'badge px-2 py-0.5 rounded-pill small'
      : props.size === 'lg'
      ? 'badge px-3 py-1.5 rounded-pill fs-6'
      : 'badge px-2.5 py-1 rounded-pill';

  switch (props.status) {
    case 'Submitted':
      return `${sizeClasses} bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25`;
    case 'UnderReview':
      return `${sizeClasses} bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-50`;
    case 'NeedsMoreInformation':
      return `${sizeClasses} bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25`;
    case 'Valid':
      return `${sizeClasses} bg-success bg-opacity-10 text-success border border-success border-opacity-25`;
    case 'Invalid':
      return `${sizeClasses} bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25`;
    case 'Closed':
      return `${sizeClasses} bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25`;
    default:
      return `${sizeClasses} bg-light text-secondary border`;
  }
});
</script>
