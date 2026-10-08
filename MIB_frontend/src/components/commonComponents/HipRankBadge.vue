<template>
    <span
        class="hip-rank-badge"
        :class="[`tier-${tierClass}`, size]"
        :style="{ '--tier-color': color || fallbackColor }"
        v-tooltip="'Human Intelligence Portfolio rank, relative to all users'"
    >
        <i class="bi bi-award-fill" aria-hidden="true"></i>
        <span class="score">HIP {{ formattedScore }}</span>
        <span v-if="rank" class="separator" aria-hidden="true">·</span>
        <span v-if="rank" class="rank">Rank {{ rank }}</span>
    </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    score?: number | string | null;
    rank?: string | null;
    tier?: string | null;
    color?: string | null;
    size?: 'small' | 'large';
}>(), { score: 0, rank: null, tier: null, color: null, size: 'small' });

const COLORS: Record<string, string> = {
    platinum: '#E5E4E2',
    gold: '#FFD700',
    silver: '#C0C0C0',
    bronze: '#CD7F32',
};

const tierClass = computed(() => (props.tier || props.rank?.split(' ')[0] || 'none').toLowerCase());
const fallbackColor = computed(() => COLORS[tierClass.value] ?? '#e5e7eb');
const formattedScore = computed(() => new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}).format(Number(props.score ?? 0) || 0));
</script>

<style scoped>
    .hip-rank-badge {
        display: inline-flex;
        max-width: 100%;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        color: var(--ds-text);
        font-size: 12px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        background: color-mix(in srgb, var(--tier-color) 32%, #fff);
        border: 1px solid color-mix(in srgb, var(--tier-color) 75%, var(--ds-text-muted));
        border-radius: 999px;
    }

    .hip-rank-badge i { color: color-mix(in srgb, var(--tier-color) 55%, var(--ds-text)); }
    .hip-rank-badge.large { gap: 8px; padding: 8px 16px; font-size: 15px; }
    .hip-rank-badge.large i { font-size: 18px; }
    .separator { opacity: .5; }
    .rank { overflow: hidden; text-overflow: ellipsis; }

    /* Metallic sheen for the top tiers. */
    .tier-platinum { background: linear-gradient(135deg, var(--ds-surface-subtle), #e5e4e2 55%, var(--ds-surface-muted)); }
    .tier-gold { background: linear-gradient(135deg, #fff7cc, #ffe55c 55%, #fff3b0); }
</style>
