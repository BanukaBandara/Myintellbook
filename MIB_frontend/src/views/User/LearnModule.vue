<template>
    <main ref="containerRef" class="learn-page">
        <div v-if="loading" class="learn-card" aria-label="Loading learn module">
            <div class="skeleton-line short"></div>
            <div class="skeleton-line title"></div>
            <div class="category-grid">
                <div v-for="item in 6" :key="item" class="skeleton-tile"></div>
            </div>
        </div>

        <div v-else-if="loadError" class="learn-card message-state">
            <i class="bi bi-exclamation-circle state-icon" aria-hidden="true"></i>
            <h2>The learn module is unavailable</h2>
            <p>{{ loadError }}</p>
            <button class="accent-button" type="button" @click="loadCategories">Try again</button>
        </div>

        <!-- Study mode -->
        <section v-else-if="session" class="learn-card" aria-labelledby="study-title">
            <header class="study-header">
                <div>
                    <p class="eyebrow"><span class="live-dot"></span> CONTINUOUS LEARNING</p>
                    <h1 id="study-title">
                        <i :class="['bi', session.category.icon]" aria-hidden="true"></i>
                        {{ session.category.name }}
                    </h1>
                    <p class="subtle">A randomized set of {{ session.questions.length }} questions from {{ session.pool_size }} available. No LCI or HIP points are awarded.</p>
                </div>
                <button class="ghost-button" type="button" @click="showTopics">All Topics</button>
            </header>

            <div class="study-toolbar">
                <label class="search-box">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <span class="sr-only">Search questions</span>
                    <input v-model.trim="search" type="search" placeholder="Search questions or answers">
                </label>
                <span class="subtle">Showing {{ filteredQuestions.length }} of {{ session.questions.length }}</span>
            </div>

            <ol class="question-list">
                <li v-for="item in filteredQuestions" :key="item.question.id" class="question-item">
                    <p class="question-text">
                        <span class="question-number">{{ item.number }}</span>
                        {{ item.question.question_text }}
                    </p>
                    <ul class="option-list">
                        <li
                            v-for="(option, index) in item.question.options"
                            :key="index"
                            class="option-row"
                            :class="{ correct: option.is_correct }"
                        >
                            <span class="option-letter" aria-hidden="true">{{ letters[index] }}</span>
                            <span class="option-text">{{ option.text }}</span>
                            <span v-if="option.is_correct" class="sr-only">(best answer)</span>
                            <span class="points-pill" :class="pointsClass(option.points)">
                                {{ formatPoints(option.points) }} {{ option.points === 1 ? 'pt' : 'pts' }}
                            </span>
                        </li>
                    </ul>
                </li>
            </ol>
            <p v-if="filteredQuestions.length === 0" class="subtle empty">No questions match “{{ search }}”.</p>
            <div class="finish-bar">
                <p>Finished reviewing? Restart this topic with a freshly randomized set. Learning adds no LCI or HIP points.</p>
                <button class="accent-button" type="button" :disabled="finishing" @click="finishLearning">
                    <span v-if="finishing" class="button-spinner" aria-hidden="true"></span>
                    {{ finishing ? 'Shuffling Questions' : 'Finish / Restart Topic' }}
                </button>
            </div>
            <p v-if="topicError" class="error-alert" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ topicError }}
            </p>
        </section>

        <!-- Category selection -->
        <section v-else class="learn-card" aria-labelledby="categories-title">
            <header class="select-header">
                <p class="eyebrow">LEARN MODULE</p>
                <h1 id="categories-title">Choose a category to study</h1>
                <p class="subtle">
                    Choose any topic to get a randomized set of up to 100 questions. Restart or switch topics at any time.
                </p>
            </header>

            <p v-if="loadingCategoryId !== null" class="notice" role="status">
                <i class="bi bi-shuffle" aria-hidden="true"></i> Loading a randomized set...
            </p>

            <div class="category-grid">
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="category-tile"
                    :class="{ selected: loadingCategoryId === category.id }"
                    :aria-busy="loadingCategoryId === category.id"
                    @click="loadQuestions(category)"
                >
                    <span class="tile-topline">
                        <span class="tile-icon" aria-hidden="true">
                            <svg v-if="category.id === 1" viewBox="0 0 24 24" fill="none">
                                <path d="M12 18v-1.5a6 6 0 1 0-3.8-1.35M9 20h6m-3-3.5v-3" />
                                <path d="M9 3.5a2.5 2.5 0 0 0-2.4 3.2A3 3 0 0 0 5 12m10-8.5a2.5 2.5 0 0 1 2.4 3.2A3 3 0 0 1 19 12M10 13a2 2 0 0 1 4 0" />
                            </svg>
                            <svg v-else-if="category.id === 2" viewBox="0 0 24 24" fill="none">
                                <path d="M20.8 8.6c0 5.2-8.8 10.4-8.8 10.4S3.2 13.8 3.2 8.6A4.6 4.6 0 0 1 12 6.3a4.6 4.6 0 0 1 8.8 2.3Z" />
                                <path d="M3 12h4l2-3 3 6 2-4h2l1 1h4" />
                            </svg>
                            <svg v-else-if="category.id === 3" viewBox="0 0 24 24" fill="none">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m16 0v-2a4 4 0 0 0-3-3.9M14 3.1a4 4 0 0 1 0 7.8" />
                                <circle cx="10" cy="7" r="4" />
                            </svg>
                            <svg v-else-if="category.id === 4" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" />
                                <path d="m15.5 8.5-2.3 5.2-5.2 2.3 2.3-5.2 5.2-2.3Z" />
                            </svg>
                            <svg v-else-if="category.id === 5" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M3 12h18M12 3a15 15 0 0 1 0 18m0-18a15 15 0 0 0 0 18" />
                            </svg>
                            <svg v-else viewBox="0 0 24 24" fill="none">
                                <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                        </span>
                    </span>
                    <span class="tile-name">{{ category.name }}</span>
                    <span class="tile-description">{{ category.description }}</span>
                    <span class="tile-data">
                        <span class="tile-count">{{ category.question_count }} Questions Available</span>
                    </span>
                </button>
            </div>
            <p v-if="topicError" class="error-alert" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ topicError }}
            </p>
        </section>
        <button
            v-if="showScrollTop"
            class="scroll-top-button"
            type="button"
            aria-label="Scroll to top"
            @click="scrollToTop"
        >
            <i class="bi bi-arrow-up" aria-hidden="true"></i>
        </button>
    </main>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { AxiosError } from 'axios';
import instance from '@/assets/axios';

type LearnCategory = {
    id: number;
    name: string;
    icon: string;
    description: string;
    question_count: number;
};

type LearnOption = { text: string; points: number; is_correct: boolean };
type LearnQuestion = { id: number; question_text: string; options: LearnOption[] };
type LearnSession = { category: LearnCategory; pool_size: number; questions: LearnQuestion[] };

const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

const loading = ref(true);
const loadError = ref('');
const finishing = ref(false);
const topicError = ref('');
const categories = ref<LearnCategory[]>([]);
const loadingCategoryId = ref<number | null>(null);
const session = ref<LearnSession | null>(null);
const search = ref('');
const containerRef = ref<HTMLElement | null>(null);
const showScrollTop = ref(false);
let scrollContainer: HTMLElement | null = null;
let requestId = 0;

const filteredQuestions = computed(() => {
    const questions = (session.value?.questions ?? []).map((question, index) => ({ question, number: index + 1 }));
    const term = search.value.toLowerCase();
    if (!term) return questions;
    return questions.filter(({ question }) => question.question_text.toLowerCase().includes(term)
        || question.options.some((option) => option.text.toLowerCase().includes(term)));
});

function formatPoints(value: number): string {
    return Number.isInteger(value) ? `${value}` : value.toFixed(1);
}

function pointsClass(points: number): string {
    if (points >= 5) return 'top';
    if (points >= 3) return 'mid';
    if (points > 0) return 'low';
    return 'zero';
}

async function loadCategories(): Promise<void> {
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await instance.get('/learn/categories');
        if (data?.success !== true || !Array.isArray(data.categories)) throw new Error('Invalid learn response');
        categories.value = data.categories;
    } catch (error) {
        console.error('Failed to load learning topics:', error);
        loadError.value = 'Please try again in a moment.';
    } finally {
        loading.value = false;
    }
}

async function loadQuestions(category: LearnCategory): Promise<void> {
    const currentRequestId = ++requestId;
    loadingCategoryId.value = category.id;
    topicError.value = '';
    try {
        const { data } = await instance.get(`/learn/questions/${category.id}`);
        if (currentRequestId !== requestId) return;
        if (data?.success !== true || !Array.isArray(data.questions)) throw new Error('Invalid learn response');
        session.value = {
            category: data.category,
            pool_size: data.pool_size,
            questions: data.questions,
        };
        search.value = '';
        scrollToTop();
    } catch (error) {
        if (currentRequestId === requestId) {
            topicError.value = (error as AxiosError<any>).response?.data?.message
                || 'Questions for this topic could not be loaded. Please try again.';
        }
    } finally {
        if (currentRequestId === requestId) loadingCategoryId.value = null;
    }
}

async function finishLearning(): Promise<void> {
    if (!session.value || finishing.value) return;
    finishing.value = true;
    try {
        await loadQuestions(session.value.category);
    } finally {
        finishing.value = false;
    }
}

function showTopics(): void {
    ++requestId;
    loadingCategoryId.value = null;
    session.value = null;
    topicError.value = '';
    scrollToTop();
}

function updateScrollTopVisibility(): void {
    showScrollTop.value = (scrollContainer?.scrollTop ?? 0) > 300;
}

function scrollToTop(): void {
    scrollContainer?.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    void loadCategories();
    void nextTick(() => {
        scrollContainer = containerRef.value?.closest<HTMLElement>('.app-content-scroll') ?? null;
        scrollContainer?.addEventListener('scroll', updateScrollTopVisibility, { passive: true });
        updateScrollTopVisibility();
    });
});

onBeforeUnmount(() => {
    scrollContainer?.removeEventListener('scroll', updateScrollTopVisibility);
});
</script>

<style scoped>
    .learn-page { width: min(100%, 1240px); min-height: 100%; margin: 0 auto; padding: clamp(20px, 4vw, 44px) clamp(16px, 3vw, 36px) 56px; background: var(--ds-surface-subtle); }
    .scroll-top-button { position: fixed; right: 1.5rem; bottom: 1.5rem; z-index: 50; display: grid; width: 3rem; height: 3rem; color: #fff; background: var(--ds-primary); border: 0; border-radius: 999px; box-shadow: 0 10px 15px -3px rgb(15 23 42 / 20%); place-items: center; transition: background-color 180ms ease, transform 180ms ease; }
    .scroll-top-button:hover { background: var(--ds-primary); transform: translateY(-2px); }
    .scroll-top-button:focus-visible { outline: 2px solid var(--ds-primary-hover); outline-offset: 3px; }

    .learn-card {
        position: relative;
        padding: clamp(24px, 4vw, 48px);
        color: var(--ds-text);
        background: #fff;
        border: 1px solid rgb(229 229 229 / 80%);
        border-radius: 24px;
        box-shadow: 0 1px 2px rgb(24 24 27 / 5%);
    }

    h1 { margin: 0; color: var(--ds-text); font-size: 20px; font-weight: 650; letter-spacing: -.02em; line-height: 1.4; }
    h1 .bi { margin-right: 6px; color: var(--ds-primary-hover); }
    .eyebrow { display: flex; align-items: center; gap: 8px; margin: 0 0 10px; color: var(--ds-text-muted); font-size: 10px; font-weight: 650; letter-spacing: .12em; }
    .live-dot { width: 7px; height: 7px; background: var(--ds-success); border-radius: 50%; }
    .subtle { margin: 8px 0 0; color: var(--ds-text-muted); font-size: 13px; line-height: 1.6; }

    /* Category selection */
    .select-header { max-width: 680px; margin-bottom: 28px; }
    .category-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }

    .category-tile {
        display: flex;
        min-height: 224px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 20px;
        color: #404040;
        text-align: left;
        background: #fff;
        border: 1px solid var(--ds-border);
        border-radius: 16px;
        box-shadow: 0 1px 2px rgb(24 24 27 / 3%);
        cursor: pointer;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .category-tile:hover { border-color: rgb(244 63 94 / 50%); box-shadow: 0 4px 12px rgb(23 23 23 / 7%); transform: translateY(-2px); }
    .category-tile:focus-visible { outline: 2px solid rgb(244 63 94 / 55%); outline-offset: 3px; }
    .category-tile.selected { border-color: rgb(244 63 94 / 45%); box-shadow: 0 0 0 1px rgb(244 63 94 / 8%); }
    .tile-topline { display: flex; width: 100%; align-items: center; margin-bottom: 8px; }
    .tile-icon { display: grid; width: 40px; height: 40px; color: var(--ds-primary); place-items: center; background: var(--ds-primary-soft); border: 1px solid var(--ds-primary-100); border-radius: 12px; }
    .tile-icon svg { width: 21px; height: 21px; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
    .tile-name { color: #262626; font-size: 16px; font-weight: 600; letter-spacing: -.01em; line-height: 1.4; }
    .tile-description { flex: 1; color: var(--ds-text-muted); font-size: 12px; line-height: 1.65; }
    .tile-data { width: 100%; margin-top: 12px; }
    .tile-count { display: inline-flex; max-width: 100%; color: var(--ds-primary); font-size: 11px; font-weight: 550; line-height: 1.2; white-space: nowrap; }
    .tile-count { padding: 5px 10px; background: rgb(255 241 242 / 80%); border-radius: 999px; }

    .confirm-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding: 14px 16px; background: var(--ds-warning-soft); border: 1px solid var(--ds-warning-border); border-radius: 12px; }
    .confirm-bar p { flex: 1 1 280px; margin: 0; color: #78350f; font-size: 13px; line-height: 1.5; }
    .confirm-actions { display: flex; gap: 8px; }

    .notice { display: flex; gap: 8px; margin: 0 0 16px; padding: 11px 12px; color: #1e3a8a; font-size: 13px; background: var(--ds-info-soft); border: 1px solid var(--ds-info-border); border-radius: 8px; }
    .error-alert { display: flex; gap: 8px; margin: 14px 0 0; padding: 11px 12px; color: var(--ds-danger-text); font-size: 12px; line-height: 1.4; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 8px; }

    /* Study mode */
    .study-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 14px; padding-bottom: 18px; border-bottom: 1px solid var(--ds-surface-muted); }
    .study-header > div { flex: 1 1 320px; }

    .study-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin: 16px 0 6px; }
    .study-toolbar .subtle { margin: 0; font-size: 12px; }
    .search-box { display: flex; flex: 1 1 260px; max-width: 380px; align-items: center; gap: 8px; padding: 0 12px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 999px; }
    .search-box:focus-within { border-color: var(--ds-primary); box-shadow: 0 0 0 3px rgba(239, 68, 68, .12); }
    .search-box i { color: var(--ds-text-subtle); }
    .search-box input { width: 100%; min-height: 38px; padding: 0; font-size: 13px; background: transparent; border: 0; outline: 0; }

    .question-list { margin: 0; padding: 0; list-style: none; }
    .question-item { padding: 20px 0; border-bottom: 1px solid var(--ds-surface-muted); }
    .question-item:last-child { border-bottom: 0; }
    .finish-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 8px; padding: 14px 16px; background: var(--ds-warning-soft); border: 1px solid var(--ds-warning-border); border-radius: 12px; }
    .finish-bar p { flex: 1 1 260px; margin: 0; color: #78350f; font-size: 13px; line-height: 1.5; }
    .question-text { display: flex; gap: 10px; margin: 0 0 12px; color: var(--ds-text); font-size: 15px; font-weight: 600; line-height: 1.5; }
    .question-number { flex: 0 0 auto; min-width: 30px; height: 24px; padding: 0 6px; color: var(--ds-primary-hover); font-size: 12px; font-weight: 700; line-height: 24px; text-align: center; background: var(--ds-danger-soft); border-radius: 6px; }

    .option-list { display: grid; gap: 6px; margin: 0; padding: 0 0 0 40px; list-style: none; }
    .option-row { display: flex; align-items: center; gap: 10px; padding: 9px 12px; color: var(--ds-text-secondary); font-size: 13.5px; line-height: 1.45; background: var(--ds-surface-subtle); border: 1px solid var(--ds-surface-muted); border-radius: 10px; }
    .option-row.correct { color: #064e3b; font-weight: 500; background: var(--ds-success-soft); border-color: var(--ds-success-border); }
    .option-letter { flex: 0 0 auto; width: 20px; color: var(--ds-text-subtle); font-size: 12px; font-weight: 700; }
    .correct .option-letter { color: var(--ds-success); }
    .option-text { flex: 1; min-width: 0; }

    .points-pill { flex: 0 0 auto; min-width: 52px; padding: 2px 9px; font-size: 11px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: center; border-radius: 999px; }
    .points-pill.top { color: #fff; background: var(--ds-success); }
    .points-pill.mid { color: var(--ds-success-text); background: #d1fae5; }
    .points-pill.low { color: var(--ds-warning-text); background: #fef3c7; }
    .points-pill.zero { color: var(--ds-text-muted); background: var(--ds-surface-muted); }
    .empty { padding: 20px 0; text-align: center; }

    /* Shared */
    .accent-button { display: inline-flex; min-height: 42px; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; color: #fff; font-size: 14px; font-weight: 700; background: linear-gradient(to right, var(--ds-primary), var(--ds-primary-hover)); border: 0; border-radius: 10px; }
    .accent-button:hover:not(:disabled) { background: linear-gradient(to right, var(--ds-primary-hover), var(--ds-danger-text)); }
    .accent-button:disabled, .ghost-button:disabled { cursor: not-allowed; opacity: .6; }
    .ghost-button { min-height: 42px; padding: 10px 16px; color: var(--ds-text-secondary); font-size: 14px; font-weight: 600; background: #fff; border: 1px solid var(--ds-border-strong); border-radius: 10px; }
    .button-spinner { width: 14px; height: 14px; border: 2px solid #ffffff70; border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }

    .message-state { text-align: center; }
    .message-state h2 { margin: 0; font-size: 20px; }
    .message-state p { margin: 9px 0 20px; color: var(--ds-text-muted); font-size: 13px; }
    .state-icon { display: grid; width: 48px; height: 48px; margin: 2px auto 16px; color: var(--ds-primary-hover); font-size: 21px; place-items: center; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 50%; }

    .skeleton-line, .skeleton-tile { background: linear-gradient(90deg, var(--ds-surface-muted), var(--ds-border), var(--ds-surface-muted)); background-size: 200% 100%; border-radius: 8px; animation: shimmer 1.4s ease-in-out infinite; }
    .skeleton-line.short { width: 130px; height: 12px; margin-bottom: 14px; }
    .skeleton-line.title { width: 60%; height: 26px; margin-bottom: 22px; }
    .skeleton-tile { height: 168px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    @keyframes shimmer { to { background-position: -200% 0; } }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 575px) {
        .learn-card { padding: 18px 16px; }
        .category-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; }
        .category-tile { min-height: 208px; }
        .option-list { padding-left: 0; }
        .confirm-actions { width: 100%; }
        .confirm-actions button { flex: 1; }
    }
    @media (min-width: 576px) and (max-width: 900px) {
        .category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
</style>
