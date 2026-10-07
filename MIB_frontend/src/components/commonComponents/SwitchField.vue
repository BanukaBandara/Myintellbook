<template>
    <label class="switch-field">
        <input
            class="switch-input"
            type="checkbox"
            role="switch"
            :checked="modelValue"
            :aria-checked="modelValue"
            @change="onChange"
        >
        <span class="switch-track" aria-hidden="true">
            <span class="switch-thumb"></span>
        </span>
        <span class="switch-label">{{ label }}</span>
    </label>
</template>
<script lang="ts" setup>
defineProps<{ modelValue: boolean; label: string }>();
const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'change', value: boolean): void;
}>();

const onChange = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    emit('update:modelValue', checked);
    emit('change', checked);
};
</script>
<style scoped>
.switch-field {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    user-select: none;
}

.switch-input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.switch-track {
    position: relative;
    display: inline-block;
    flex: 0 0 auto;
    width: 44px;
    height: 24px;
    background: #e2e8f0; /* slate-200 */
    border-radius: 999px;
    transition: background-color .2s var(--ds-ease, ease);
}

.switch-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .2);
    transition: transform .2s var(--ds-ease, ease);
}

.switch-input:checked + .switch-track { background: #e11d48; /* rose-600 */ }
.switch-input:checked + .switch-track .switch-thumb { transform: translateX(20px); }
.switch-input:focus-visible + .switch-track { box-shadow: 0 0 0 3px rgba(244, 63, 94, .25); }
.switch-field:hover .switch-input:not(:checked) + .switch-track { background: #cbd5e1; }

.switch-label {
    color: #334155; /* slate-700 */
    font-size: 14px;
    font-weight: 500;
}

@media (prefers-reduced-motion: reduce) {
    .switch-track, .switch-thumb { transition: none; }
}
</style>
