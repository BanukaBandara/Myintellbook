<template>
    <section class="daily-card" aria-live="polite">
        <div v-if="loading" class="skeleton" aria-label="Loading today's question">
            <div class="skeleton-line short"></div>
            <div class="skeleton-line title"></div>
            <div v-for="item in 3" :key="item" class="skeleton-option"></div>
        </div>

        <div v-else-if="loadError" class="message-state">
            <i class="bi bi-exclamation-circle state-icon" aria-hidden="true"></i>
            <h2>Today's question is unavailable</h2>
            <p>{{ loadError }}</p>
            <button class="accent-button" type="button" @click="loadQuestion">Try again</button>
        </div>

        <template v-else-if="question">
            <header class="card-heading">
                <div>
                    <p class="eyebrow"><span class="live-dot"></span> DAILY HIP QUESTION</p>
                    <span class="category-badge">{{ question.category || 'Daily reflection' }}</span>
                </div>
                <time class="date-label">{{ todayLabel }}</time>
            </header>

            <!-- Points and correctness only exist once the midnight run has evaluated the answer. -->
            <div v-if="hasAnswered && isEvaluated" class="completion-state">
                <i class="bi bi-shield-check state-icon" aria-hidden="true"></i>
                <p class="eyebrow centered">DAILY PRACTICE COMPLETE</p>
                <h2>Your reflection is recorded.</h2>
                <p v-if="submitError" class="error-alert" role="alert">{{ submitError }}</p>
                <span v-if="isCorrect !== null" class="result-badge" :class="isCorrect ? 'correct' : 'incorrect'">
                    <i :class="['bi', isCorrect ? 'bi-check-circle-fill' : 'bi-x-circle-fill']" aria-hidden="true"></i>
                    {{ isCorrect ? 'Correct' : 'Incorrect' }}
                </span>
                <p class="completion-copy">You earned</p>
                <p class="score-value">+{{ scoreLabel }} <span>HIP points</span></p>
                <span class="ethics-badge"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Integrity in action</span>
                <div class="countdown">
                    <span>NEXT QUESTION IN</span>
                    <time>{{ countdownLabel }}</time>
                </div>
            </div>

            <!-- Until the midnight evaluation the question stays open; the saved choice shows as selected. -->
            <div v-else class="question-content">
                <h2>{{ question.question_text }}</h2>
                <p class="instruction">Choose the response that best reflects your judgment.</p>

                <fieldset class="options-list" :disabled="submitting || !canUpdate">
                    <legend class="sr-only">Choose one answer</legend>
                    <label
                        v-for="(option, index) in question.options"
                        :key="`${question.id}-${index}`"
                        class="option-row"
                        :class="{ selected: selectedIndex === index, disabled: submitting }"
                    >
                        <input
                            v-model="selectedIndex"
                            type="radio"
                            name="daily-question-option"
                            :value="index"
                            :aria-label="option"
                        >
                        <span class="radio-mark" aria-hidden="true"></span>
                        <span class="option-text">{{ option }}</span>
                    </label>
                </fieldset>

                <p v-if="submitError" class="error-alert" role="alert">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    {{ submitError }}
                </p>

                <div class="deadline-note">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <span>
                        You can change your answer until midnight, when it is evaluated and points are awarded.
                        <strong>{{ countdownLabel }} left</strong>
                    </span>
                </div>

                <footer class="submit-row">
                    <span class="privacy-note" aria-live="polite">
                        <template v-if="isSaved">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Answer saved
                        </template>
                        <template v-else>
                            <i class="bi bi-lock-fill" aria-hidden="true"></i> Your choice is private
                        </template>
                    </span>
                    <button
                        class="accent-button"
                        type="button"
                        :disabled="selectedIndex === null || isSaved || submitting || !canUpdate"
                        @click="submitAnswer"
                    >
                        <span v-if="submitting" class="button-spinner" aria-hidden="true"></span>
                        {{ submitting ? 'Saving' : hasAnswered ? 'Update answer' : 'Submit answer' }}
                    </button>
                </footer>
            </div>
        </template>
    </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { AxiosError } from 'axios';
import instance from '@/assets/axios';

type DailyQuestion = {
    id: number;
    category: string | null;
    question_text: string;
    options: string[];
};

const emit = defineEmits<{ (event: 'day-changed'): void }>();

const question = ref<DailyQuestion | null>(null);
const hasAnswered = ref(false);
const canUpdate = ref(true);
// 'pending_evaluation' until the midnight run, then 'evaluated'; null when not answered.
const answerStatus = ref<string | null>(null);
const userScoreToday = ref<number | null>(null);
const isCorrect = ref<boolean | null>(null);
const selectedIndex = ref<number | null>(null);
// Index last confirmed by the server; "Update answer" is only needed when the selection differs.
const savedIndex = ref<number | null>(null);
const loading = ref(true);
const submitting = ref(false);
const loadError = ref('');
const submitError = ref('');

function toPoints(value: unknown): number | null {
    return value !== null && value !== undefined && Number.isFinite(Number(value)) ? Number(value) : null;
}
const now = ref(Date.now());
let currentDayKey = new Date().toDateString();
let countdownTimer: ReturnType<typeof setInterval> | undefined;

const isEvaluated = computed(() => answerStatus.value === 'evaluated');
const scoreLabel = computed(() => userScoreToday.value === null ? '--' : `${userScoreToday.value}`);
const isSaved = computed(() => savedIndex.value !== null && selectedIndex.value === savedIndex.value);
const todayLabel = computed(() => new Intl.DateTimeFormat(undefined, {
    weekday: 'long', month: 'short', day: 'numeric',
}).format(new Date(now.value)));
const countdownLabel = computed(() => {
    const nextMidnight = new Date(now.value);
    nextMidnight.setHours(24, 0, 0, 0);
    const seconds = Math.max(0, Math.floor((nextMidnight.getTime() - now.value) / 1000));
    const hours = Math.floor(seconds / 3600).toString().padStart(2, '0');
    const minutes = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
    return `${hours}:${minutes}:${(seconds % 60).toString().padStart(2, '0')}`;
});

async function loadQuestion(): Promise<void> {
    loading.value = true;
    loadError.value = '';

    try {
        const { data } = await instance.get('/daily-question/today');
        const payload = data?.question;
        if (data?.status !== 'success' || !payload || !Array.isArray(payload.options)
            || !payload.options.every((option: unknown) => typeof option === 'string')) {
            throw new Error('Invalid daily question response');
        }

        question.value = {
            id: Number(payload.id),
            category: typeof payload.category === 'string' ? payload.category : null,
            question_text: typeof payload.question_text === 'string' ? payload.question_text : '',
            options: payload.options,
        };
        hasAnswered.value = data.has_answered === true;
        canUpdate.value = data.can_update !== false;
        answerStatus.value = hasAnswered.value && typeof data.answer_status === 'string' ? data.answer_status : null;
        userScoreToday.value = isEvaluated.value ? toPoints(data.user_score_today) : null;
        isCorrect.value = isEvaluated.value && typeof data.is_correct === 'boolean' ? data.is_correct : null;
        const saved = Number(data.selected_option_index);
        selectedIndex.value = hasAnswered.value && data.selected_option_index !== null
            && Number.isInteger(saved) && saved >= 0 && saved < payload.options.length
            ? saved
            : null;
        savedIndex.value = selectedIndex.value;
    } catch {
        question.value = null;
        loadError.value = 'Please try again in a moment.';
    } finally {
        loading.value = false;
    }
}

async function submitAnswer(): Promise<void> {
    const index = selectedIndex.value;
    if (submitting.value || !canUpdate.value || !question.value || !Number.isInteger(index)
        || index === null || index < 0 || index > 4) return;

    submitting.value = true;
    submitError.value = '';
    try {
        const { data } = await instance.post('/daily-questions/answer', {
            question_id: question.value.id,
            selected_option_index: index,
        });
        if (data?.status !== 'success' || data.answer_status !== 'pending_evaluation') {
            throw new Error('Answer submission was not confirmed');
        }
        // Points and correctness are only revealed after the midnight evaluation.
        hasAnswered.value = true;
        canUpdate.value = true;
        answerStatus.value = data.answer_status;
        userScoreToday.value = null;
        isCorrect.value = null;
        savedIndex.value = index;
    } catch (error) {
        const status = (error as AxiosError).response?.status;
        if (status === 409) {
            // The answer was already evaluated or the day rolled over; reload shows which.
            await loadQuestion();
            if (!hasAnswered.value) submitError.value = 'Today’s question is closed. A new question is now available.';
        } else if (status === 422) {
            submitError.value = 'That selection could not be validated. Choose an option and try again.';
        } else {
            submitError.value = 'Your answer could not be submitted. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    void loadQuestion();
    countdownTimer = setInterval(() => {
        now.value = Date.now();
        const dayKey = new Date(now.value).toDateString();
        if (dayKey !== currentDayKey) {
            currentDayKey = dayKey;
            submitError.value = '';
            void loadQuestion();
            emit('day-changed');
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (countdownTimer) clearInterval(countdownTimer);
});
</script>

<style scoped>
    .daily-card {
        --ink: var(--ds-text);
        --muted: var(--ds-text-muted);
        --red: var(--ds-primary);
        position: relative;
        overflow: hidden;
        padding: 24px;
        color: var(--ink);
        background: #fff;
        border: 1px solid var(--ds-border);
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
    }

    .daily-card::before {
        position: absolute;
        top: 0;
        left: 24px;
        width: 72px;
        height: 2px;
        content: '';
        background: linear-gradient(90deg, var(--ds-primary-hover), var(--ds-primary-300));
    }

    .card-heading {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 4px 0 18px;
        border-bottom: 1px solid var(--ds-surface-muted);
    }

    .eyebrow, .countdown > span {
        margin: 0 0 12px;
        color: var(--ds-text-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .12em;
    }

    .eyebrow { display: flex; align-items: center; gap: 8px; }
    .centered { justify-content: center; color: var(--ds-success-text); }
    .live-dot { width: 7px; height: 7px; background: var(--ds-success); border-radius: 50%; }

    .category-badge {
        display: inline-flex;
        min-height: 26px;
        align-items: center;
        padding: 4px 12px;
        color: var(--ds-primary-hover);
        font-size: 12px;
        font-weight: 600;
        background: var(--ds-danger-soft);
        border: 1px solid var(--ds-danger-border);
        border-radius: 999px;
    }

    .date-label { padding-top: 1px; color: var(--muted); font-size: 12px; white-space: nowrap; }
    .question-content { padding-top: 22px; }

    .question-content h2, .message-state h2, .completion-state h2 {
        margin: 0;
        color: var(--ds-text);
        font-family: inherit;
        font-size: 20px;
        font-weight: 700;
        line-height: 1.4;
    }

    .instruction, .message-state p, .completion-copy {
        margin: 9px 0 20px;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.55;
    }

    .options-list { display: grid; gap: 10px; min-width: 0; margin: 0; padding: 0; border: 0; }

    .option-row {
        position: relative;
        display: flex;
        min-height: 56px;
        align-items: center;
        gap: 13px;
        padding: 16px;
        color: var(--ds-text-secondary);
        cursor: pointer;
        background: var(--ds-surface-subtle);
        border: 1px solid var(--ds-border);
        border-radius: 12px;
        transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
    }

    .option-row:hover:not(.disabled) { background: var(--ds-surface-muted); }
    .option-row.selected { color: var(--ds-danger-text); font-weight: 500; background: #fff1f2; /* rose-50 */ border-color: #f43f5e; /* rose-500 */ box-shadow: 0 0 0 1px #f43f5e; }
    .option-row input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .option-row:focus-within { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
    .option-row.disabled { cursor: not-allowed; opacity: .7; }
    .radio-mark { display: grid; flex: 0 0 18px; width: 18px; height: 18px; place-items: center; border: 1px solid var(--ds-text-subtle); border-radius: 50%; }
    .selected .radio-mark { border-color: var(--ds-primary); }
    .selected .radio-mark::after { width: 8px; height: 8px; content: ''; background: var(--ds-primary); border-radius: 50%; }
    .option-text { font-size: 14px; line-height: 1.45; }

    .submit-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 20px; }
    .privacy-note { color: var(--ds-text-muted); font-size: 11px; }
    .privacy-note i { margin-right: 5px; color: var(--ds-success); }

    .accent-button {
        display: inline-flex;
        min-height: 54px;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 14px 24px;
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        background: linear-gradient(to right, var(--ds-primary), var(--ds-primary-hover));
        border: 0;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .12);
        transition: background .18s ease, transform .18s ease, opacity .18s ease;
    }

    .accent-button:hover:not(:disabled) { background: linear-gradient(to right, var(--ds-primary-hover), var(--ds-danger-text)); transform: translateY(-1px); }
    .accent-button:disabled { cursor: not-allowed; opacity: .5; box-shadow: none; }
    .button-spinner { width: 14px; height: 14px; border: 2px solid #ffffff70; border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }

    .error-alert { display: flex; gap: 8px; margin: 15px 0 0; padding: 11px 12px; color: var(--ds-danger-text); font-size: 12px; line-height: 1.4; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 8px; }
    .deadline-note { display: flex; gap: 8px; margin-top: 14px; color: var(--ds-text-muted); font-size: 12px; line-height: 1.5; }
    .deadline-note i { color: var(--ds-text-subtle); }
    .deadline-note strong { margin-left: 4px; color: var(--ds-text-secondary); font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .result-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; padding: 4px 12px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; border-radius: 999px; }
    .result-badge.correct { color: var(--ds-success-text); background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); }
    .result-badge.incorrect { color: var(--ds-danger-text); background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); }
    .completion-state, .message-state { padding-top: 24px; text-align: center; }
    .state-icon { display: grid; width: 48px; height: 48px; margin: 2px auto 16px; color: var(--ds-success-text); font-size: 21px; place-items: center; background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); border-radius: 50%; }
    .completion-copy { margin: 20px 0 0; }
    .score-value { margin: 0 0 15px; color: var(--ds-danger-text); font-family: Georgia, 'Times New Roman', serif; font-size: 42px; }
    .score-value span { color: var(--ds-text-muted); font-family: inherit; font-size: 14px; }


    .ethics-badge { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; color: var(--ds-success-text); font-size: 12px; font-weight: 600; background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); border-radius: 999px; }
    .countdown { display: flex; max-width: 270px; align-items: center; justify-content: space-between; margin: 24px auto 0; padding-top: 15px; border-top: 1px solid var(--ds-border); }
    .countdown > span { margin: 0; font-size: 9px; }
    .countdown time { color: var(--ds-text-secondary); font-variant-numeric: tabular-nums; font-size: 15px; }
    .message-state .state-icon { color: var(--ds-primary-hover); background: var(--ds-danger-soft); border-color: var(--ds-danger-border); }
    .skeleton-line, .skeleton-option { background: linear-gradient(90deg, var(--ds-surface-muted), var(--ds-border), var(--ds-surface-muted)); background-size: 200% 100%; border-radius: 8px; animation: shimmer 1.4s ease-in-out infinite; }
    .skeleton-line.short { width: 130px; height: 12px; margin-bottom: 22px; }
    .skeleton-line.title { width: 72%; height: 27px; margin-bottom: 20px; }
    .skeleton-option { height: 56px; margin-top: 9px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    @keyframes shimmer { to { background-position: -200% 0; } }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 575px) {
        .daily-card { padding: 20px; }
        .submit-row { align-items: stretch; flex-direction: column; }
        .submit-row .accent-button { width: 100%; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
    </style>