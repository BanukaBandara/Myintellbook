<template>
    <main class="exam-page">
        <div v-if="loading" class="exam-card" aria-label="Loading exams">
            <div class="skeleton-line short"></div>
            <div class="skeleton-line title"></div>
            <div class="category-grid">
                <div v-for="item in 6" :key="item" class="skeleton-tile"></div>
            </div>
        </div>

        <div v-else-if="loadError" class="exam-card message-state">
            <i class="bi bi-exclamation-circle state-icon" aria-hidden="true"></i>
            <h2>Exams are unavailable</h2>
            <p>{{ loadError }}</p>
            <button class="accent-button" type="button" @click="loadCategories">Try again</button>
        </div>

        <!-- Live exam -->
        <template v-else-if="exam">
            <header class="timer-bar" :class="{ warning: remainingSeconds <= 120, critical: remainingSeconds <= 30 }">
                <div class="timer-meta">
                    <span class="timer-category">
                        <i :class="['bi', exam.category.icon]" aria-hidden="true"></i> {{ exam.category.name }}
                    </span>
                    <span class="timer-progress">{{ answeredCount }} of {{ exam.questions.length }} answered</span>
                </div>
                <div class="timer-clock" role="timer" :aria-label="`${timerLabel} remaining`">
                    <i class="bi bi-stopwatch" aria-hidden="true"></i>
                    <time>{{ timerLabel }}</time>
                </div>
            </header>

            <div v-if="poolResetMessage" class="pool-reset" role="status">
                <i class="bi bi-trophy-fill" aria-hidden="true"></i>
                <p>{{ poolResetMessage }}</p>
                <button type="button" class="dismiss" aria-label="Dismiss message" @click="poolResetMessage = ''">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <section class="exam-card" aria-live="polite">
                <p class="eyebrow">QUESTION {{ currentIndex + 1 }} OF {{ exam.questions.length }}</p>
                <h2 class="question-text">{{ currentQuestion.question_text }}</h2>

                <fieldset class="options-list" :disabled="submitting">
                    <legend class="sr-only">Choose one answer</legend>
                    <label
                        v-for="option in currentQuestion.options"
                        :key="`${currentQuestion.id}-${option.id}`"
                        class="option-row"
                        :class="{ selected: answers[currentQuestion.id] === option.id }"
                    >
                        <input
                            type="radio"
                            :name="`exam-question-${currentQuestion.id}`"
                            :value="option.id"
                            :checked="answers[currentQuestion.id] === option.id"
                            @change="selectAnswer(currentQuestion.id, option.id)"
                        >
                        <span class="radio-mark" aria-hidden="true"></span>
                        <span class="option-text">{{ option.text }}</span>
                    </label>
                </fieldset>

                <footer class="nav-row">
                    <button class="ghost-button" type="button" :disabled="currentIndex === 0 || submitting" @click="currentIndex--">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Previous
                    </button>
                    <button
                        v-if="currentIndex < exam.questions.length - 1"
                        class="accent-button"
                        type="button"
                        :disabled="submitting"
                        @click="currentIndex++"
                    >
                        Next <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                    <button v-else class="accent-button" type="button" :disabled="submitting" @click="requestSubmit">
                        <span v-if="submitting" class="button-spinner" aria-hidden="true"></span>
                        {{ submitting ? 'Submitting' : 'Submit exam' }}
                    </button>
                </footer>

                <div v-if="confirmingSubmit" class="confirm-bar" role="alertdialog" aria-labelledby="confirm-submit-text">
                    <p id="confirm-submit-text">
                        You have <strong>{{ exam.questions.length - answeredCount }}</strong> unanswered
                        {{ exam.questions.length - answeredCount === 1 ? 'question' : 'questions' }}. Submit anyway?
                    </p>
                    <div class="confirm-actions">
                        <button class="ghost-button" type="button" @click="confirmingSubmit = false">Keep answering</button>
                        <button class="accent-button" type="button" @click="submitExam()">Submit now</button>
                    </div>
                </div>

                <p v-if="submitError" class="error-alert" role="alert">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ submitError }}
                    <button v-if="canRetrySubmit" class="link-button" type="button" @click="submitExam()">Retry</button>
                </p>

                <nav class="question-dots" aria-label="Jump to question">
                    <button
                        v-for="(question, index) in exam.questions"
                        :key="question.id"
                        type="button"
                        class="dot"
                        :class="{ current: index === currentIndex, answered: answers[question.id] !== undefined }"
                        :aria-label="`Question ${index + 1}${answers[question.id] !== undefined ? ', answered' : ''}`"
                        :aria-current="index === currentIndex ? 'step' : undefined"
                        :disabled="submitting"
                        @click="currentIndex = index"
                    >{{ index + 1 }}</button>
                </nav>
            </section>
        </template>

        <!-- Exam config -->
        <section v-else class="exam-card" aria-labelledby="exam-title">
            <header class="select-header">
                <p class="eyebrow">EXAM MODULE</p>
                <h1 id="exam-title">Choose an exam category</h1>
                <p class="subtle">Each exam is timed and adds the points you earn to your HIP score. One attempt per category per day.</p>
            </header>

            <p v-if="notice" class="notice" role="status">
                <i class="bi bi-info-circle" aria-hidden="true"></i> {{ notice }}
            </p>

            <div class="category-grid">
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="category-tile"
                    :class="{ selected: pendingCategory?.id === category.id }"
                    :disabled="category.attempted_today || category.question_count === 0 || starting"
                    :aria-pressed="pendingCategory?.id === category.id"
                    @click="pendingCategory = category; startError = ''"
                >
                    <span class="tile-top">
                        <i :class="['bi', category.icon, 'tile-icon']" aria-hidden="true"></i>
                        <span v-if="category.attempted_today" class="done-badge"><i class="bi bi-check2" aria-hidden="true"></i> Done today</span>
                    </span>
                    <span class="tile-name">{{ category.name }}</span>
                    <span class="tile-description">{{ category.description }}</span>
                    <span class="exam-format">
                        <span><i class="bi bi-list-ol" aria-hidden="true"></i> {{ questionCount }} Questions</span>
                        <span class="divider" aria-hidden="true">|</span>
                        <span><i class="bi bi-clock" aria-hidden="true"></i> {{ durationMinutes }} Minutes</span>
                    </span>
                </button>
            </div>

            <div v-if="pendingCategory" class="confirm-bar">
                <p>
                    Start the <strong>{{ pendingCategory.name }}</strong> exam? The {{ durationMinutes }}-minute timer starts
                    immediately and your answers are submitted automatically at 00:00.
                </p>
                <div class="confirm-actions">
                    <button class="ghost-button" type="button" :disabled="starting" @click="pendingCategory = null">Cancel</button>
                    <button class="accent-button" type="button" :disabled="starting" @click="startExam(pendingCategory.id)">
                        <span v-if="starting" class="button-spinner" aria-hidden="true"></span>
                        {{ starting ? 'Starting' : 'Start exam' }}
                    </button>
                </div>
            </div>
            <p v-if="startError" class="error-alert" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ startError }}
            </p>
        </section>

        <!-- Results summary -->
        <div v-if="result" class="modal-backdrop" @click.self="closeResult">
            <div class="result-modal" role="dialog" aria-modal="true" aria-labelledby="result-title">
                <i class="bi bi-award state-icon success" aria-hidden="true"></i>
                <p class="eyebrow centered">{{ autoSubmitted ? "TIME'S UP — EXAM SUBMITTED" : 'EXAM SUBMITTED' }}</p>
                <h2 id="result-title">{{ result.category?.name }}</h2>

                <p class="score-value">{{ formatNumber(result.total_score) }}<span> / {{ formatNumber(result.max_score) }}</span></p>
                <div class="score-meter" aria-hidden="true">
                    <span :style="{ width: `${Math.min(100, result.percentage)}%` }"></span>
                </div>
                <p class="subtle">{{ result.percentage }}% score</p>

                <dl class="stats">
                    <div>
                        <dt>Correct answers</dt>
                        <dd>{{ result.correct_count }} / {{ result.total_questions }}</dd>
                    </div>
                    <div>
                        <dt>Answered</dt>
                        <dd>{{ result.answered_count }} / {{ result.total_questions }}</dd>
                    </div>
                    <div>
                        <dt>Points gained</dt>
                        <dd class="gain">+{{ formatNumber(result.points_gained) }}</dd>
                    </div>
                </dl>

                <div v-if="result.hip_score !== null" class="hip-banner">
                    <span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Updated HIP Score</span>
                    <strong>{{ formatHip(result.hip_score) }}</strong>
                </div>

                <button
                    v-if="result.breakdown.length"
                    class="review-toggle"
                    type="button"
                    :aria-expanded="showReview"
                    aria-controls="answer-review"
                    @click="showReview = !showReview"
                >
                    {{ showReview ? 'Hide' : 'Review' }} answers
                    <i :class="['bi', showReview ? 'bi-chevron-up' : 'bi-chevron-down']" aria-hidden="true"></i>
                </button>

                <ol v-if="showReview" id="answer-review" class="review-list">
                    <li v-for="(item, index) in result.breakdown" :key="item.question_id" class="review-item">
                        <div class="review-head">
                            <span class="review-number">{{ index + 1 }}</span>
                            <span class="review-question">{{ item.question_text }}</span>
                            <span class="review-points" :class="{ full: item.is_correct, none: !item.points_earned }">
                                {{ formatNumber(item.points_earned) }}/{{ formatNumber(item.max_points) }}
                            </span>
                        </div>
                        <p class="review-line" :class="item.is_correct ? 'right' : 'wrong'">
                            <i :class="['bi', item.is_correct ? 'bi-check-circle-fill' : 'bi-x-circle-fill']" aria-hidden="true"></i>
                            <span>Your answer: {{ item.selected_answer?.text ?? 'Not answered' }}</span>
                        </p>
                        <p v-if="!item.is_correct" class="review-line right">
                            <i class="bi bi-lightbulb-fill" aria-hidden="true"></i>
                            <span>Best answer: {{ item.correct_answers.join(' / ') }}</span>
                        </p>
                    </li>
                </ol>

                <button ref="closeButton" class="accent-button wide" type="button" @click="closeResult">Done</button>
            </div>
        </div>
    </main>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { AxiosError } from 'axios';
import instance from '@/assets/axios';
import { refreshLci } from '@/services/lci';

type ExamCategory = {
    id: number;
    name: string;
    icon: string;
    description: string;
    question_count: number;
    attempted_today: boolean;
};
type ExamQuestion = { id: number; question_text: string; options: { id: number; text: string }[] };
type ExamSession = {
    exam_session_id: number;
    session_token: string;
    category: ExamCategory;
    questions: ExamQuestion[];
};
type ExamResult = {
    category: ExamCategory | null;
    total_questions: number;
    answered_count: number;
    correct_count: number;
    total_score: number;
    max_score: number;
    percentage: number;
    points_gained: number;
    hip_score: number | null;
    breakdown: {
        question_id: number;
        question_text: string;
        selected_answer: { id: number; text: string } | null;
        correct_answers: string[];
        points_earned: number;
        max_points: number;
        is_correct: boolean;
    }[];
};

const loading = ref(true);
const loadError = ref('');
const notice = ref('');
const categories = ref<ExamCategory[]>([]);
const questionCount = ref(30);
const durationMinutes = ref(15);
const pendingCategory = ref<ExamCategory | null>(null);
const starting = ref(false);
const startError = ref('');

const exam = ref<ExamSession | null>(null);
const answers = ref<Record<number, number>>({});
const currentIndex = ref(0);
const deadline = ref(0);
const now = ref(Date.now());
const submitting = ref(false);
const submitError = ref('');
const canRetrySubmit = ref(false);
const confirmingSubmit = ref(false);
const autoSubmitted = ref(false);
const result = ref<ExamResult | null>(null);
const showReview = ref(false);
const poolResetMessage = ref('');
const closeButton = ref<HTMLButtonElement | null>(null);
let ticker: ReturnType<typeof setInterval> | undefined;

const remainingSeconds = computed(() => Math.max(0, Math.ceil((deadline.value - now.value) / 1000)));
const timerLabel = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60).toString().padStart(2, '0');
    const seconds = (remainingSeconds.value % 60).toString().padStart(2, '0');
    return `${minutes}:${seconds}`;
});
const currentQuestion = computed(() => exam.value!.questions[currentIndex.value]);
const answeredCount = computed(() => Object.keys(answers.value).length);

const storageKey = (sessionId: number) => `exam-answers-${sessionId}`;

function saveAnswers(): void {
    if (!exam.value) return;
    try {
        localStorage.setItem(storageKey(exam.value.exam_session_id), JSON.stringify(answers.value));
    } catch { /* storage unavailable: answers stay in memory only */ }
}

function restoreAnswers(sessionId: number): Record<number, number> {
    try {
        const raw = localStorage.getItem(storageKey(sessionId));
        const parsed = raw ? JSON.parse(raw) : {};
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

function clearStoredAnswers(sessionId: number): void {
    try { localStorage.removeItem(storageKey(sessionId)); } catch { /* ignore */ }
}

function formatNumber(value: number): string {
    return Number.isInteger(value) ? `${value}` : value.toFixed(1);
}

function formatHip(value: number): string {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value);
}

function beginExam(data: any): void {
    exam.value = {
        exam_session_id: data.exam_session_id,
        session_token: data.session_token,
        category: data.category,
        questions: data.questions,
    };
    // Time from the server so a wrong device clock can't stretch or shorten the exam.
    deadline.value = Date.now() + Number(data.remaining_seconds ?? 0) * 1000;
    now.value = Date.now();
    answers.value = restoreAnswers(data.exam_session_id);
    currentIndex.value = 0;
    submitError.value = '';
    confirmingSubmit.value = false;
    autoSubmitted.value = false;
    pendingCategory.value = null;
    window.scrollTo({ top: 0 });
}

async function loadCategories(): Promise<void> {
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await instance.get('/exam/categories');
        if (data?.success !== true || !Array.isArray(data.categories)) throw new Error('Invalid exam response');
        categories.value = data.categories;
        questionCount.value = Number(data.question_count) || 30;
        durationMinutes.value = Number(data.duration_minutes) || 15;
        if (data.in_progress) {
            beginExam(data.in_progress);
            notice.value = '';
        }
    } catch {
        loadError.value = 'Please try again in a moment.';
    } finally {
        loading.value = false;
    }
}

async function startExam(categoryId: number): Promise<void> {
    if (starting.value) return;
    starting.value = true;
    startError.value = '';
    try {
        const { data } = await instance.post('/exam/start', { category_id: categoryId });
        notice.value = '';
        beginExam(data);
        poolResetMessage.value = data.pool_reset === true && typeof data.message === 'string' ? data.message : '';
    } catch (error) {
        const response = (error as AxiosError<any>).response;
        startError.value = response?.data?.message || 'The exam could not be started. Please try again.';
        if (response?.status === 409) void loadCategories();
    } finally {
        starting.value = false;
    }
}

function selectAnswer(questionId: number, optionId: number): void {
    answers.value = { ...answers.value, [questionId]: optionId };
    saveAnswers();
}

function requestSubmit(): void {
    if (!exam.value) return;
    if (answeredCount.value < exam.value.questions.length) {
        confirmingSubmit.value = true;
    } else {
        void submitExam();
    }
}

async function submitExam(isAuto = false): Promise<void> {
    if (!exam.value || submitting.value) return;
    submitting.value = true;
    submitError.value = '';
    canRetrySubmit.value = false;
    confirmingSubmit.value = false;
    const session = exam.value;

    try {
        const { data } = await instance.post('/exam/submit', {
            exam_session_id: session.exam_session_id,
            session_token: session.session_token,
            answers: session.questions.map((question) => ({
                question_id: question.id,
                answer_id: answers.value[question.id] ?? null,
            })),
        });
        if (data?.success !== true || !data.result) throw new Error('Submission was not confirmed');
        clearStoredAnswers(session.exam_session_id);
        autoSubmitted.value = isAuto;
        showReview.value = false;
        result.value = {
            ...data.result,
            hip_score: Number.isFinite(Number(data.result.hip_score)) ? Number(data.result.hip_score) : null,
            breakdown: Array.isArray(data.result.breakdown) ? data.result.breakdown : [],
        };
        exam.value = null;
        // Update the sidebar's LCI and rank badge; failure here shouldn't affect the result modal.
        refreshLci().catch(() => {});
        await nextTick();
        closeButton.value?.focus();
    } catch (error) {
        const response = (error as AxiosError<any>).response;
        if (response?.status === 422 || response?.status === 404) {
            // The server closed this session (time ran out); nothing left to retry.
            clearStoredAnswers(session.exam_session_id);
            exam.value = null;
            notice.value = response.data?.message || 'This exam could not be submitted.';
            void loadCategories();
        } else {
            submitError.value = 'Your answers could not be submitted. Check your connection and retry.';
            canRetrySubmit.value = true;
        }
    } finally {
        submitting.value = false;
    }
}

function closeResult(): void {
    result.value = null;
    void loadCategories();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && result.value) closeResult();
}

function warnBeforeLeaving(event: BeforeUnloadEvent): void {
    if (exam.value && !result.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}

onMounted(() => {
    void loadCategories();
    ticker = setInterval(() => {
        now.value = Date.now();
        if (exam.value && remainingSeconds.value === 0 && !submitting.value && !canRetrySubmit.value) {
            void submitExam(true);
        }
    }, 250);
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', warnBeforeLeaving);
});

onBeforeUnmount(() => {
    if (ticker) clearInterval(ticker);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', warnBeforeLeaving);
});
</script>

<style scoped>
    .exam-page { max-width: 820px; margin: 0 auto; padding: 16px 16px 40px; }

    .exam-card {
        position: relative;
        padding: 24px;
        color: var(--ds-text);
        background: #fff;
        border: 1px solid var(--ds-border);
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
    }

    .exam-card::before {
        position: absolute;
        top: 0;
        left: 24px;
        width: 72px;
        height: 2px;
        content: '';
        background: linear-gradient(90deg, var(--ds-primary-hover), var(--ds-primary-300));
    }

    h1, h2 { margin: 0; color: var(--ds-text); font-weight: 700; line-height: 1.4; }
    h1 { font-size: 22px; }
    h2 { font-size: 20px; }
    .eyebrow { margin: 0 0 10px; color: var(--ds-text-muted); font-size: 10px; font-weight: 700; letter-spacing: .12em; }
    .eyebrow.centered { text-align: center; }
    .subtle { margin: 8px 0 0; color: var(--ds-text-muted); font-size: 13px; line-height: 1.55; }

    /* Config */
    .select-header { margin-bottom: 20px; }
    .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }

    .category-tile {
        display: flex;
        min-height: 184px;
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
        padding: 18px;
        color: var(--ds-text-secondary);
        text-align: left;
        background: var(--ds-surface-subtle);
        border: 1px solid var(--ds-border);
        border-radius: 12px;
        transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .category-tile:hover:not(:disabled) { background: #fff; border-color: var(--ds-primary-300); box-shadow: 0 4px 12px rgba(15, 23, 42, .06); transform: translateY(-1px); }
    .category-tile:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
    .category-tile.selected { background: rgba(254, 242, 242, .7); border-color: var(--ds-primary); }
    .category-tile:disabled { cursor: not-allowed; opacity: .6; }
    .tile-top { display: flex; width: 100%; align-items: flex-start; justify-content: space-between; }
    .tile-icon { display: grid; width: 38px; height: 38px; margin-bottom: 4px; color: var(--ds-primary-hover); font-size: 18px; place-items: center; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 10px; }
    .done-badge { padding: 3px 9px; color: var(--ds-success-text); font-size: 11px; font-weight: 700; background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); border-radius: 999px; }
    .tile-name { color: var(--ds-text); font-size: 15px; font-weight: 700; line-height: 1.35; }
    .tile-description { flex: 1; color: var(--ds-text-muted); font-size: 12.5px; line-height: 1.5; }
    .exam-format { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 5px 10px; color: var(--ds-danger-text); font-size: 12px; font-weight: 700; background: var(--ds-danger-soft); border-radius: 8px; }
    .exam-format .divider { color: var(--ds-primary-300); }

    .confirm-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding: 14px 16px; background: var(--ds-warning-soft); border: 1px solid var(--ds-warning-border); border-radius: 12px; }
    .confirm-bar p { flex: 1 1 280px; margin: 0; color: #78350f; font-size: 13px; line-height: 1.5; }
    .confirm-actions { display: flex; gap: 8px; }

    .notice { display: flex; gap: 8px; margin: 0 0 16px; padding: 11px 12px; color: #1e3a8a; font-size: 13px; background: var(--ds-info-soft); border: 1px solid var(--ds-info-border); border-radius: 8px; }
    .error-alert { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 14px 0 0; padding: 11px 12px; color: var(--ds-danger-text); font-size: 12px; line-height: 1.4; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 8px; }
    .link-button { padding: 0; color: var(--ds-primary-hover); font-weight: 700; text-decoration: underline; background: none; border: 0; }

    /* Live exam */
    .timer-bar {
        position: sticky;
        z-index: 5;
        top: env(safe-area-inset-top, 0px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        padding: 12px 18px;
        color: #fff;
        background: var(--ds-text);
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .18);
        transition: background-color .3s ease;
    }

    .timer-bar.warning { background: var(--ds-warning-text); }
    .timer-bar.critical { background: var(--ds-primary-hover); }
    .timer-meta { display: flex; min-width: 0; flex-direction: column; gap: 2px; }
    .timer-category { overflow: hidden; font-size: 13px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .timer-progress { color: rgba(255, 255, 255, .7); font-size: 12px; }
    .timer-clock { display: flex; align-items: center; gap: 8px; font-size: 30px; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: .02em; }
    .timer-clock i { font-size: 20px; opacity: .8; }

    .pool-reset { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; padding: 12px 14px; color: #78350f; background: var(--ds-warning-soft); border: 1px solid var(--ds-warning-border); border-radius: 12px; }
    .pool-reset > i { margin-top: 1px; color: var(--ds-warning); font-size: 18px; }
    .pool-reset p { flex: 1; margin: 0; font-size: 13px; line-height: 1.5; }
    .pool-reset .dismiss { padding: 2px 4px; color: var(--ds-warning-text); background: none; border: 0; border-radius: 6px; }
    .pool-reset .dismiss:focus-visible { outline: 2px solid var(--ds-warning); }

    .question-text { margin-bottom: 18px; font-size: 19px; }
    .options-list { display: grid; gap: 10px; min-width: 0; margin: 0; padding: 0; border: 0; }

    .option-row {
        position: relative;
        display: flex;
        min-height: 54px;
        align-items: center;
        gap: 13px;
        padding: 14px 16px;
        color: var(--ds-text-secondary);
        cursor: pointer;
        background: var(--ds-surface-subtle);
        border: 1px solid var(--ds-border);
        border-radius: 12px;
        transition: background-color .18s ease, border-color .18s ease;
    }

    .option-row:hover { background: var(--ds-surface-muted); }
    .option-row.selected { color: var(--ds-danger-text); font-weight: 500; background: rgba(254, 242, 242, .6); border-color: var(--ds-primary); }
    .option-row input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .option-row:focus-within { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
    .radio-mark { display: grid; flex: 0 0 18px; width: 18px; height: 18px; place-items: center; border: 1px solid var(--ds-text-subtle); border-radius: 50%; }
    .selected .radio-mark { border-color: var(--ds-primary); }
    .selected .radio-mark::after { width: 8px; height: 8px; content: ''; background: var(--ds-primary); border-radius: 50%; }
    .option-text { font-size: 14px; line-height: 1.45; }

    .nav-row { display: flex; justify-content: space-between; gap: 10px; margin-top: 20px; }
    .question-dots { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--ds-surface-muted); }
    .dot { width: 32px; height: 32px; padding: 0; color: var(--ds-text-muted); font-size: 12px; font-weight: 600; background: #fff; border: 1px solid var(--ds-border); border-radius: 8px; }
    .dot.answered { color: var(--ds-success-text); background: var(--ds-success-soft); border-color: var(--ds-success-border); }
    .dot.current { color: #fff; background: var(--ds-primary-hover); border-color: var(--ds-primary-hover); }
    .dot:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

    /* Result modal */
    .modal-backdrop { position: fixed; z-index: 1050; inset: 0; display: grid; padding: 16px; place-items: center; background: rgba(17, 24, 39, .55); }
    .result-modal { width: 100%; max-width: 420px; max-height: calc(100vh - 32px); overflow: auto; padding: 28px 24px 24px; text-align: center; background: #fff; border-radius: 18px; box-shadow: 0 24px 48px rgba(15, 23, 42, .25); }
    .score-value { margin: 14px 0 10px; color: var(--ds-danger-text); font-family: Georgia, 'Times New Roman', serif; font-size: 46px; line-height: 1; }
    .score-value span { color: var(--ds-text-subtle); font-size: 20px; }
    .score-meter { height: 8px; overflow: hidden; background: var(--ds-surface-muted); border-radius: 999px; }
    .score-meter span { display: block; height: 100%; background: linear-gradient(90deg, var(--ds-primary), var(--ds-primary-300)); border-radius: 999px; }
    .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 20px 0; }
    .stats div { padding: 12px 6px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-surface-muted); border-radius: 12px; }
    .stats dt { color: var(--ds-text-muted); font-size: 11px; font-weight: 600; }
    .stats dd { margin: 4px 0 0; color: var(--ds-text); font-size: 18px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .stats dd.gain { color: var(--ds-success-text); }

    .hip-banner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 14px; padding: 12px 16px; color: #fff; text-align: left; background: linear-gradient(120deg, var(--ds-text), var(--ds-danger-text)); border-radius: 12px; }
    .hip-banner span { font-size: 12px; font-weight: 600; opacity: .85; }
    .hip-banner strong { font-size: 22px; font-variant-numeric: tabular-nums; }

    .review-toggle { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; padding: 4px 2px; color: var(--ds-primary-hover); font-size: 13px; font-weight: 700; background: none; border: 0; }
    .review-toggle:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; border-radius: 4px; }
    .review-list { max-height: 320px; overflow-y: auto; margin: 0 0 16px; padding: 0 4px 0 0; text-align: left; list-style: none; }
    .review-item { padding: 12px 0; border-top: 1px solid var(--ds-surface-muted); }
    .review-head { display: flex; align-items: flex-start; gap: 8px; }
    .review-number { flex: 0 0 auto; min-width: 24px; color: var(--ds-text-subtle); font-size: 12px; font-weight: 700; line-height: 20px; }
    .review-question { flex: 1; min-width: 0; color: var(--ds-text); font-size: 13px; font-weight: 600; line-height: 1.45; }
    .review-points { flex: 0 0 auto; padding: 1px 8px; color: var(--ds-warning-text); font-size: 11px; font-weight: 700; font-variant-numeric: tabular-nums; background: #fef3c7; border-radius: 999px; }
    .review-points.full { color: #fff; background: var(--ds-success); }
    .review-points.none { color: var(--ds-text-muted); background: var(--ds-surface-muted); }
    .review-line { display: flex; gap: 6px; margin: 6px 0 0 32px; font-size: 12.5px; line-height: 1.45; }
    .review-line.right { color: var(--ds-success-text); }
    .review-line.wrong { color: var(--ds-danger-text); }

    /* Shared */
    .accent-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; color: #fff; font-size: 14px; font-weight: 700; background: linear-gradient(to right, var(--ds-primary), var(--ds-primary-hover)); border: 0; border-radius: 10px; }
    .accent-button:hover:not(:disabled) { background: linear-gradient(to right, var(--ds-primary-hover), var(--ds-danger-text)); }
    .accent-button.wide { width: 100%; }
    .accent-button:disabled, .ghost-button:disabled { cursor: not-allowed; opacity: .55; }
    .ghost-button { display: inline-flex; min-height: 44px; align-items: center; gap: 8px; padding: 10px 16px; color: var(--ds-text-secondary); font-size: 14px; font-weight: 600; background: #fff; border: 1px solid var(--ds-border-strong); border-radius: 10px; }
    .button-spinner { width: 14px; height: 14px; border: 2px solid #ffffff70; border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }

    .message-state { text-align: center; }
    .message-state p { margin: 9px 0 20px; color: var(--ds-text-muted); font-size: 13px; }
    .state-icon { display: grid; width: 52px; height: 52px; margin: 2px auto 14px; color: var(--ds-primary-hover); font-size: 22px; place-items: center; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 50%; }
    .state-icon.success { color: var(--ds-success-text); background: var(--ds-success-soft); border-color: var(--ds-success-border); }

    .skeleton-line, .skeleton-tile { background: linear-gradient(90deg, var(--ds-surface-muted), var(--ds-border), var(--ds-surface-muted)); background-size: 200% 100%; border-radius: 8px; animation: shimmer 1.4s ease-in-out infinite; }
    .skeleton-line.short { width: 130px; height: 12px; margin-bottom: 14px; }
    .skeleton-line.title { width: 60%; height: 26px; margin-bottom: 22px; }
    .skeleton-tile { height: 184px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    @keyframes shimmer { to { background-position: -200% 0; } }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 575px) {
        .exam-card { padding: 18px 16px; }
        .timer-clock { font-size: 24px; }
        .question-text { font-size: 17px; }
        .confirm-actions { width: 100%; }
        .confirm-actions button { flex: 1; justify-content: center; }
        .stats dd { font-size: 16px; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
</style>
