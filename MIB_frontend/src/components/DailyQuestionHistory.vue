<template>
    <section class="history-card" aria-labelledby="daily-history-title">
        <header class="history-heading">
            <p id="daily-history-title" class="eyebrow">PREVIOUS DAILY QUESTIONS</p>
            <span v-if="totalPoints !== null" class="total-points">{{ formatPoints(totalPoints) }} pts earned</span>
        </header>

        <div v-if="loading" aria-label="Loading history">
            <div v-for="item in 3" :key="item" class="skeleton-row"></div>
        </div>

        <p v-else-if="loadError" class="empty-state">
            {{ loadError }}
            <button class="link-button" type="button" @click="reload">Try again</button>
        </p>

        <p v-else-if="items.length === 0" class="empty-state">
            Your answered questions will appear here after they are evaluated at midnight.
        </p>

        <ol v-else class="timeline">
            <li v-for="item in items" :key="item.id" class="timeline-item">
                <span class="timeline-dot" :class="dotClass(item)" aria-hidden="true"></span>
                <div class="timeline-body">
                    <div class="item-meta">
                        <time :datetime="item.scheduled_date ?? undefined">{{ formatDate(item.scheduled_date) }}</time>
                        <span v-if="item.category" class="category">{{ item.category }}</span>
                    </div>
                    <p class="item-question">{{ item.question_text || 'Question unavailable' }}</p>
                    <p class="item-answer">
                        <span class="label">Your answer:</span> {{ item.selected_option || '—' }}
                    </p>
                </div>
                <div class="item-result">
                    <template v-if="item.status === 'evaluated'">
                        <span class="points" :class="{ zero: !item.points }">+{{ formatPoints(item.points ?? 0) }}</span>
                        <span class="result-badge" :class="item.is_correct ? 'correct' : 'incorrect'">
                            {{ item.is_correct ? 'Correct' : 'Incorrect' }}
                        </span>
                    </template>
                    <span v-else class="result-badge pending">Evaluating</span>
                </div>
            </li>
        </ol>
    </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import instance from '@/assets/axios';

type HistoryItem = {
    id: number;
    scheduled_date: string | null;
    category: string | null;
    question_text: string | null;
    selected_option: string | null;
    status: string;
    is_correct: boolean | null;
    points: number | null;
};

const items = ref<HistoryItem[]>([]);
const loading = ref(true);
const loadError = ref('');

const totalPoints = computed(() => items.value.length === 0
    ? null
    : items.value.reduce((sum, item) => sum + (item.points ?? 0), 0));

function formatPoints(value: number): string {
    return Number.isInteger(value) ? `${value}` : value.toFixed(2);
}

function formatDate(value: string | null): string {
    if (!value) return '';
    // Parse as a local calendar date so the label does not shift with the timezone.
    const [year, month, day] = value.split('-').map(Number);
    return new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
        .format(new Date(year, month - 1, day));
}

function dotClass(item: HistoryItem): string {
    if (item.status !== 'evaluated') return 'pending';
    return item.is_correct ? 'correct' : 'incorrect';
}

async function reload(): Promise<void> {
    loadError.value = '';
    try {
        const { data } = await instance.get('/daily-questions/history');
        if (data?.status !== 'success' || !Array.isArray(data.data)) {
            throw new Error('Invalid history response');
        }
        items.value = data.data.map((row: Record<string, unknown>) => ({
            id: Number(row.id),
            scheduled_date: typeof row.scheduled_date === 'string' ? row.scheduled_date : null,
            category: typeof row.category === 'string' ? row.category : null,
            question_text: typeof row.question_text === 'string' ? row.question_text : null,
            selected_option: typeof row.selected_option === 'string' ? row.selected_option : null,
            status: typeof row.status === 'string' ? row.status : 'pending_evaluation',
            is_correct: typeof row.is_correct === 'boolean' ? row.is_correct : null,
            points: row.points !== null && Number.isFinite(Number(row.points)) ? Number(row.points) : null,
        }));
    } catch {
        loadError.value = 'Your history could not be loaded.';
    } finally {
        loading.value = false;
    }
}

defineExpose({ reload });

onMounted(() => {
    void reload();
});
</script>

<style scoped>
    .history-card {
        padding: 20px 24px;
        color: var(--ds-text);
        background: #fff;
        border: 1px solid var(--ds-border);
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
    }

    .history-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .eyebrow { margin: 0; color: var(--ds-text-muted); font-size: 10px; font-weight: 700; letter-spacing: .12em; }
    .total-points { color: var(--ds-danger-text); font-size: 12px; font-weight: 700; }

    .empty-state { margin: 0; padding: 8px 0; color: var(--ds-text-muted); font-size: 13px; line-height: 1.5; }
    .link-button { padding: 0; margin-left: 6px; color: var(--ds-primary-hover); font-weight: 600; background: none; border: 0; }

    .timeline { position: relative; margin: 0; padding: 0; list-style: none; }
    .timeline-item { position: relative; display: flex; gap: 14px; padding: 12px 0 12px 22px; border-top: 1px solid var(--ds-surface-muted); }
    .timeline-item:first-child { border-top: 0; }
    .timeline-item::before { position: absolute; top: 0; bottom: 0; left: 4px; width: 1px; content: ''; background: var(--ds-border); }
    .timeline-item:first-child::before { top: 18px; }
    .timeline-item:last-child::before { bottom: calc(100% - 18px); }
    .timeline-dot { position: absolute; top: 15px; left: 0; width: 9px; height: 9px; background: var(--ds-border-strong); border-radius: 50%; box-shadow: 0 0 0 3px #fff; }
    .timeline-dot.correct { background: var(--ds-success); }
    .timeline-dot.incorrect { background: var(--ds-primary-300); }
    .timeline-dot.pending { background: #fbbf24; }

    .timeline-body { flex: 1; min-width: 0; }
    .item-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: var(--ds-text-muted); font-size: 11px; }
    .category { padding: 1px 8px; color: var(--ds-primary-hover); font-weight: 600; background: var(--ds-danger-soft); border-radius: 999px; }
    .item-question { margin: 5px 0 4px; color: var(--ds-text); font-size: 14px; font-weight: 600; line-height: 1.4; }
    .item-answer { margin: 0; color: var(--ds-text-secondary); font-size: 12px; line-height: 1.45; }
    .item-answer .label { color: var(--ds-text-subtle); }

    .item-result { display: flex; flex: 0 0 auto; flex-direction: column; align-items: flex-end; gap: 5px; }
    .points { color: var(--ds-success-text); font-size: 16px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .points.zero { color: var(--ds-text-subtle); }
    .result-badge { padding: 2px 8px; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; border-radius: 999px; }
    .result-badge.correct { color: var(--ds-success-text); background: var(--ds-success-soft); }
    .result-badge.incorrect { color: var(--ds-danger-text); background: var(--ds-danger-soft); }
    .result-badge.pending { color: var(--ds-warning-text); background: var(--ds-warning-soft); }

    .skeleton-row { height: 58px; margin-top: 8px; background: linear-gradient(90deg, var(--ds-surface-muted), var(--ds-border), var(--ds-surface-muted)); background-size: 200% 100%; border-radius: 8px; animation: shimmer 1.4s ease-in-out infinite; }
    @keyframes shimmer { to { background-position: -200% 0; } }

    @media (max-width: 575px) {
        .history-card { padding: 18px 16px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .skeleton-row { animation: none; }
    }
</style>
