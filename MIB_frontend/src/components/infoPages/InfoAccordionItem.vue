<template>
  <div class="accordion-item-card" :class="{ open: isOpen }">
    <h2 class="item-heading">
      <button
        :id="`${id}-trigger`"
        type="button"
        class="item-trigger"
        :aria-expanded="isOpen"
        :aria-controls="`${id}-panel`"
        @click="api?.toggle(id)"
      >
        <span v-if="number" class="item-number">{{ number }}</span>
        <i v-else-if="icon" :class="['bi', icon, 'item-icon']" aria-hidden="true"></i>
        <span class="item-title">{{ title }}</span>
        <i class="bi bi-chevron-down item-chevron" aria-hidden="true"></i>
      </button>
    </h2>
    <div
      v-show="isOpen"
      :id="`${id}-panel`"
      class="item-panel"
      role="region"
      :aria-labelledby="`${id}-trigger`"
      data-print-expand
    >
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted } from 'vue';
import { ACCORDION_KEY } from './accordion';

const props = defineProps<{ id: string; title: string; number?: string | number; icon?: string }>();
const api = inject(ACCORDION_KEY, null);

const isOpen = computed(() => api?.isOpen(props.id) ?? true);

onMounted(() => api?.register(props.id));
onBeforeUnmount(() => api?.unregister(props.id));
</script>

<style scoped>
.accordion-item-card { border: 1px solid transparent; border-radius: 14px; transition: background-color .2s ease, border-color .2s ease; }
.accordion-item-card.open { background: #fcfcfd; border-color: var(--ds-surface-muted); }

.item-heading { margin: 0; font-size: inherit; }

.item-trigger {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 12px;
  padding: 12px 12px;
  color: var(--ds-text);
  font-size: 14.5px;
  font-weight: 650;
  text-align: left;
  background: none;
  border: 0;
  border-radius: 14px;
  transition: background-color .2s ease, color .2s ease;
}

.item-trigger:hover { color: var(--ds-primary-hover); background: var(--ds-primary-soft); }
.item-trigger:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: -2px; }

.item-number {
  display: grid;
  flex: 0 0 28px;
  width: 28px;
  height: 28px;
  color: var(--ds-primary-hover);
  font-size: 12px;
  font-weight: 800;
  place-items: center;
  background: var(--ds-primary-soft);
  border-radius: 8px;
}

.item-icon { flex: 0 0 28px; color: var(--ds-primary); font-size: 16px; text-align: center; }
.item-title { flex: 1; min-width: 0; line-height: 1.4; }
.item-chevron { color: var(--ds-text-subtle); font-size: 13px; transition: transform .2s ease; }
.open .item-chevron { color: var(--ds-primary); transform: rotate(180deg); }

.item-panel { padding: 0 16px 16px 52px; color: var(--ds-text-secondary); font-size: 14px; line-height: 1.7; }
.item-panel :deep(p) { margin: 0 0 10px; }
.item-panel :deep(p:last-child) { margin-bottom: 0; }
.item-panel :deep(ul), .item-panel :deep(ol) { margin: 6px 0 10px; padding-left: 18px; }
.item-panel :deep(li) { margin-bottom: 4px; }
.item-panel :deep(li::marker) { color: var(--ds-primary); }
.item-panel :deep(strong) { color: var(--ds-text); }
.item-panel :deep(em) { color: var(--ds-text-secondary); }

@media (max-width: 575px) {
  .item-panel { padding-left: 16px; }
}

@media (prefers-reduced-motion: reduce) {
  .accordion-item-card, .item-trigger, .item-chevron { transition: none; }
}
</style>
