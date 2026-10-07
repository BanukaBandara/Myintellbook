<template>
    <div ref="root" class="visibility" @keydown.esc="open = false">
        <button
            type="button"
            class="visibility-pill"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-label="`Visibility: ${visibility}. Change`"
            @click="open = !open"
        >
            <Lock v-if="visibility === 'Only Me'" :size="12" aria-hidden="true" />
            <Globe v-else :size="12" aria-hidden="true" />
            <span>{{ visibility }}</span>
            <ChevronDown :size="12" class="chevron" :class="{ open }" aria-hidden="true" />
        </button>

        <Transition name="visibility-menu">
            <ul v-if="open" class="visibility-menu" role="listbox" aria-label="Who can see this?">
                <li class="menu-heading" role="presentation">Who can see this?</li>
                <li
                    v-for="option in visibilityTypes"
                    :key="option.id"
                    role="option"
                    :aria-selected="visibility === option.label"
                    class="menu-option"
                    :class="{ selected: visibility === option.label }"
                    tabindex="0"
                    @click="select(option.label)"
                    @keydown.enter.prevent="select(option.label)"
                    @keydown.space.prevent="select(option.label)"
                >
                    <Lock v-if="option.label === 'Only Me'" :size="14" aria-hidden="true" />
                    <Globe v-else :size="14" aria-hidden="true" />
                    <span>{{ option.label }}</span>
                    <Check v-if="visibility === option.label" :size="14" class="check" aria-hidden="true" />
                </li>
            </ul>
        </Transition>
    </div>
</template>
<script lang="ts" setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { Check, ChevronDown, Globe, Lock } from 'lucide-vue-next';

const visibility = ref<string>('Public');
const open = ref(false);
const root = ref<HTMLElement | null>(null);
const emit = defineEmits(['visibilityChange']);
const visibilityTypes = ref([
    { label: 'Public', id: 1 },
    { label: 'Only Me', id: 2 },
])

const props = defineProps({
    field: {
        type: String,
        default: ''
    },
    visibility:{
        type: String,
        default:'Public'
    }
});

const setVisibility = computed(()=> props.visibility);


watch(setVisibility,()=>{
    visibility.value = setVisibility.value
});

const toggle = () => {
    let visibilityValue = visibilityTypes.value.find((item)=> item.label === visibility.value)
    if (visibilityValue) {
        emit('visibilityChange', { field: props.field, value:visibilityValue.label });
    }
}

const select = (label: string) => {
    open.value = false;
    if (visibility.value === label) return;
    visibility.value = label;
    toggle();
}

const closeOnOutsideClick = (event: MouseEvent) => {
    if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false;
}

onMounted(()=>{
    visibility.value = setVisibility.value
    document.addEventListener('click', closeOnOutsideClick);
})

onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick));

</script>
<style scoped>
.visibility {
    position: relative;
    display: inline-flex;
    align-self: flex-start;
}

/* text-xs text-slate-500 hover:text-rose-600 font-medium */
.visibility-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.4;
    cursor: pointer;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 999px;
    transition: color .15s ease, background-color .15s ease, border-color .15s ease;
}

.visibility-pill:hover,
.visibility-pill[aria-expanded="true"] {
    color: #e11d48;
    background: #fff1f2;
    border-color: #ffe4e6;
}

.visibility-pill:focus-visible,
.menu-option:focus-visible {
    outline: 2px solid #fda4af;
    outline-offset: 1px;
}

.chevron { transition: transform .15s ease; }
.chevron.open { transform: rotate(180deg); }

.visibility-menu {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    z-index: 30;
    min-width: 180px;
    margin: 0;
    padding: 6px;
    list-style: none;
    background: #fff;
    border: 1px solid rgba(226, 232, 240, .8);
    border-radius: 12px;
    box-shadow: 0 10px 25px -8px rgba(15, 23, 42, .18), 0 2px 6px -2px rgba(15, 23, 42, .06);
}

.menu-heading {
    padding: 6px 10px 4px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.menu-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    color: #334155;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border-radius: 8px;
    transition: background-color .12s ease, color .12s ease;
}

.menu-option:hover { background: #f8fafc; color: #0f172a; }
.menu-option.selected { color: #e11d48; }
.check { margin-left: auto; }

.visibility-menu-enter-active, .visibility-menu-leave-active { transition: opacity .15s ease, transform .15s ease; }
.visibility-menu-enter-from, .visibility-menu-leave-to { opacity: 0; transform: translateY(-4px); }

@media (prefers-reduced-motion: reduce) {
    .chevron, .visibility-pill, .menu-option,
    .visibility-menu-enter-active, .visibility-menu-leave-active { transition: none; }
}
</style>
