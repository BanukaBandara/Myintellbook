<template>
  <section class="info-accordion" :aria-label="label">
    <div class="accordion-toolbar no-print">
      <span class="toolbar-count">{{ ids.length }} sections</span>
      <button type="button" class="toolbar-button" @click="allOpen ? collapseAll() : expandAll()">
        <i :class="['bi', allOpen ? 'bi-arrows-collapse' : 'bi-arrows-expand']" aria-hidden="true"></i>
        {{ allOpen ? 'Collapse all' : 'Expand all' }}
      </button>
    </div>
    <div class="accordion-items">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, provide, reactive, ref } from 'vue';
import { ACCORDION_KEY } from './accordion';

const props = withDefaults(defineProps<{ label?: string; defaultOpen?: string[] }>(), {
  label: 'Sections',
  defaultOpen: () => [],
});

const ids = ref<string[]>([]);
const open = reactive(new Set<string>(props.defaultOpen));

const allOpen = computed(() => ids.value.length > 0 && ids.value.every((id) => open.has(id)));

function expandAll(): void { ids.value.forEach((id) => open.add(id)); }
function collapseAll(): void { open.clear(); }

provide(ACCORDION_KEY, {
  register: (id: string) => { if (!ids.value.includes(id)) ids.value.push(id); },
  unregister: (id: string) => { ids.value = ids.value.filter((item) => item !== id); open.delete(id); },
  isOpen: (id: string) => open.has(id),
  toggle: (id: string) => { if (open.has(id)) open.delete(id); else open.add(id); },
});
</script>

<style scoped>
.info-accordion {
  padding: 8px 8px 10px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.accordion-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 10px 10px; }
.toolbar-count { color: var(--ds-text-subtle); font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }

.toolbar-button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  color: var(--ds-primary-hover);
  font-size: 12.5px;
  font-weight: 700;
  background: none;
  border: 0;
  border-radius: 8px;
}

.toolbar-button:hover { background: var(--ds-primary-soft); }
.toolbar-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 1px; }
.accordion-items { display: grid; gap: 6px; }
</style>
