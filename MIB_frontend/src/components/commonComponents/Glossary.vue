<template>
  <InfoPageShell
    icon="bi-book"
    eyebrow="Glossary"
    title="The language of MyIntellibook"
    subtitle="Definitions for LCI terms, HIP concepts and the scoring metrics used across the platform."
  >
    <section class="glossary-panel" aria-label="Glossary terms">
      <div class="glossary-tools">
        <label class="search-field">
          <i class="bi bi-search" aria-hidden="true"></i>
          <span class="sr-only">Search glossary terms</span>
          <input v-model.trim="search" type="search" placeholder="Search glossary terms…" autocomplete="off">
          <button v-if="search" type="button" class="clear-button" aria-label="Clear search" @click="search = ''">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </label>

        <div class="filter-chips" role="group" aria-label="Filter by topic">
          <button
            v-for="topic in topics"
            :key="topic.key"
            type="button"
            class="filter-chip"
            :class="{ active: activeTopic === topic.key }"
            :aria-pressed="activeTopic === topic.key"
            @click="activeTopic = topic.key"
          >
            {{ topic.label }} <span class="chip-count">{{ countFor(topic.key) }}</span>
          </button>
        </div>
      </div>

      <p class="result-count" aria-live="polite">
        {{ filtered.length }} {{ filtered.length === 1 ? 'term' : 'terms' }}{{ search ? ` matching “${search}”` : '' }}
      </p>

      <div v-if="filtered.length" class="term-grid">
        <article v-for="entry in filtered" :key="entry.term" class="term-card">
          <div class="term-head">
            <span class="term-icon" aria-hidden="true"><i :class="['bi', TOPIC_ICONS[entry.topic]]"></i></span>
            <span class="term-topic">{{ topicLabel(entry.topic) }}</span>
            <span v-if="entry.isNew" class="term-new">New</span>
          </div>
          <h2 class="term-title">
            <template v-for="(part, index) in highlight(entry.term)" :key="index">
              <mark v-if="part.match">{{ part.text }}</mark><template v-else>{{ part.text }}</template>
            </template>
          </h2>
          <p class="term-definition">
            <template v-for="(part, index) in highlight(entry.definition)" :key="index">
              <mark v-if="part.match">{{ part.text }}</mark><template v-else>{{ part.text }}</template>
            </template>
          </p>
        </article>
      </div>

      <div v-else class="empty-state">
        <i class="bi bi-search" aria-hidden="true"></i>
        <p>No terms found matching your search.</p>
        <button type="button" class="reset-button" @click="search = ''; activeTopic = 'all'">Clear filters</button>
      </div>
    </section>
  </InfoPageShell>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';

type Topic = 'platform' | 'hip' | 'scoring';
type Entry = { term: string; definition: string; topic: Topic; isNew?: boolean };

const TOPIC_ICONS: Record<Topic, string> = {
  platform: 'bi-grid',
  hip: 'bi-person-badge',
  scoring: 'bi-graph-up-arrow',
};

const topics: { key: Topic | 'all'; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'hip', label: 'HIP & LCI' },
  { key: 'scoring', label: 'Scoring metrics' },
  { key: 'platform', label: 'Platform' },
];

const glossary: Entry[] = [
  {
    term: 'MyIntellibook',
    topic: 'platform',
    definition: 'An intelligent learning and assessment platform that enhances knowledge, skills, and ethical reasoning through interactive tools, intelligent feedback, and performance-based scoring.',
  },
  {
    term: 'HIP – Human Intelligence Portfolio',
    topic: 'hip',
    definition: 'A dynamic personal profile that represents an individual’s real-life achievements, knowledge, and skills. It groups users based on their Life Competency Index (LCI) and reflects growth in both professional and personal competencies.',
  },
  {
    term: 'LCI – Life Competency Index',
    topic: 'hip',
    definition: 'A measurable value that indicates a person’s overall life competency, determined by education, career achievements, social contributions, problem-solving ability, and ethical behavior.',
  },
  {
    term: 'Online Tribunal',
    topic: 'platform',
    definition: 'A virtual platform that allows MyIntellibook members to resolve personal or professional conflicts positively, fairly, and efficiently.',
  },
  {
    term: 'Fixed Arm for Testament Management',
    topic: 'platform',
    definition: 'A secure and transparent system for managing testamentary records, wills, and inheritances.',
  },
  {
    term: 'Learning Modules',
    topic: 'platform',
    definition: 'Interactive lessons and assessments that help users strengthen knowledge across multiple disciplines.',
  },
  {
    term: 'Performance-Based Scoring',
    topic: 'scoring',
    definition: 'A system that evaluates learners not only on correct answers but also on reasoning quality, time management, and improvement trends.',
  },
  {
    term: 'Ethical Reasoning',
    topic: 'platform',
    definition: 'A core principle of MyIntellibook that emphasizes learning through values, integrity, and social responsibility.',
  },
  {
    term: 'Intelligent Feedback',
    topic: 'platform',
    definition: 'AI-driven guidance provided after quizzes or assessments, helping learners understand mistakes and improve decision-making.',
  },
  {
    term: 'Competency Groups',
    topic: 'hip',
    definition: 'Clusters of individuals with similar LCI scores or skill profiles that foster peer learning and mentoring.',
  },
  {
    term: 'Life Achievement Record',
    topic: 'hip',
    definition: 'A record of user accomplishments — including education, career, innovation, and social impact — that contributes to their LCI.',
  },
  // Scoring metrics as calculated by the platform.
  {
    term: 'TQM – Daily Questions',
    topic: 'scoring',
    isNew: true,
    definition: 'The LCI component earned from the Daily HIP Question. Each answer option carries 0–5 points; you can change your answer until midnight, when it is evaluated and the points are added.',
  },
  {
    term: 'EM – Exams',
    topic: 'scoring',
    isNew: true,
    definition: 'The LCI component earned from timed category exams: 30 questions in 15 minutes, up to 150 points per exam, one attempt per category per day.',
  },
  {
    term: 'PCM – Profile Completion',
    topic: 'scoring',
    isNew: true,
    definition: 'The LCI component awarded for a verified complete profile and verified profile identity.',
  },
  {
    term: 'HIP Rank',
    topic: 'scoring',
    isNew: true,
    definition: 'Your tier — Platinum 1–3, Gold 1–4, Silver 1–8 or Bronze — based on how far your LCI sits below the highest LCI in the system, relative to the range between the highest and lowest scores.',
  },
  {
    term: 'Learn Pass',
    topic: 'scoring',
    isNew: true,
    definition: 'A 7-day study pass for one question category that shows up to 100 questions with the point value of every option.',
  },
];

const search = ref('');
const activeTopic = ref<Topic | 'all'>('all');

const matchesSearch = (entry: Entry) => {
  const term = search.value.toLowerCase();
  return !term || entry.term.toLowerCase().includes(term) || entry.definition.toLowerCase().includes(term);
};

const filtered = computed(() => glossary.filter((entry) => matchesSearch(entry)
  && (activeTopic.value === 'all' || entry.topic === activeTopic.value)));

const countFor = (topic: Topic | 'all') => glossary.filter((entry) => matchesSearch(entry)
  && (topic === 'all' || entry.topic === topic)).length;

const topicLabel = (topic: Topic) => topics.find((item) => item.key === topic)?.label ?? '';

/** Splits text into plain and matching parts so matches can be wrapped in <mark> without v-html. */
function highlight(text: string): { text: string; match: boolean }[] {
  const term = search.value;
  if (!term) return [{ text, match: false }];
  const parts: { text: string; match: boolean }[] = [];
  const lower = text.toLowerCase();
  const needle = term.toLowerCase();
  let index = 0;
  while (index < text.length) {
    const found = lower.indexOf(needle, index);
    if (found === -1) {
      parts.push({ text: text.slice(index), match: false });
      break;
    }
    if (found > index) parts.push({ text: text.slice(index, found), match: false });
    parts.push({ text: text.slice(found, found + needle.length), match: true });
    index = found + needle.length;
  }
  return parts;
}
</script>

<style scoped>
.glossary-panel { padding: 16px; background: #fff; border: 1px solid var(--ds-border); border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }

.glossary-tools { display: grid; gap: 12px; }

.search-field { display: flex; align-items: center; gap: 10px; padding: 0 14px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 14px; transition: border-color .2s ease, box-shadow .2s ease; }
.search-field:focus-within { background: #fff; border-color: var(--ds-primary-300); box-shadow: 0 0 0 4px rgba(225, 29, 72, .1); }
.search-field > i { color: var(--ds-text-subtle); }
.search-field input { width: 100%; min-height: 46px; padding: 0; font-size: 14.5px; background: transparent; border: 0; outline: 0; }
.clear-button { display: grid; width: 28px; height: 28px; padding: 0; color: var(--ds-text-muted); place-items: center; background: none; border: 0; border-radius: 8px; }
.clear-button:hover { color: var(--ds-primary-hover); background: var(--ds-primary-soft); }

.filter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.filter-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; color: var(--ds-text-secondary); font-size: 12.5px; font-weight: 600; background: #fff; border: 1px solid var(--ds-border); border-radius: 999px; transition: background-color .2s ease, color .2s ease, border-color .2s ease; }
.filter-chip:hover { color: var(--ds-primary-hover); border-color: var(--ds-primary-200); }
.filter-chip.active { color: #fff; background: var(--ds-primary); border-color: var(--ds-primary); }
.filter-chip:focus-visible, .reset-button:focus-visible, .clear-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.chip-count { padding: 0 6px; font-size: 11px; background: rgba(0, 0, 0, .06); border-radius: 999px; }
.filter-chip.active .chip-count { background: rgba(255, 255, 255, .25); }

.result-count { margin: 14px 2px 10px; color: var(--ds-text-muted); font-size: 12.5px; }

.term-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }

.term-card { display: flex; flex-direction: column; gap: 6px; padding: 16px; background: #fff; border: 1px solid var(--ds-border); border-radius: 16px; transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
.term-card:hover { border-color: var(--ds-primary-200); box-shadow: 0 6px 18px rgba(15, 23, 42, .06); transform: translateY(-1px); }

.term-head { display: flex; align-items: center; gap: 8px; }
.term-icon { display: grid; width: 28px; height: 28px; color: var(--ds-primary); font-size: 13px; place-items: center; background: var(--ds-primary-soft); border-radius: 8px; }
.term-topic { flex: 1; color: var(--ds-text-subtle); font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.term-new { padding: 2px 8px; color: var(--ds-success-text); font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; background: #d1fae5; border-radius: 999px; }
.term-title { margin: 4px 0 0; color: var(--ds-text); font-size: 15.5px; font-weight: 750; line-height: 1.35; }
.term-definition { margin: 0; color: var(--ds-text-secondary); font-size: 13.5px; line-height: 1.65; }

mark { padding: 0 2px; color: inherit; background: var(--ds-primary-100); border-radius: 3px; }

.empty-state { padding: 36px 12px; color: var(--ds-text-muted); text-align: center; }
.empty-state > i { display: block; margin-bottom: 8px; color: var(--ds-primary-300); font-size: 28px; }
.empty-state p { margin: 0 0 12px; }
.reset-button { padding: 7px 14px; color: var(--ds-primary-hover); font-size: 13px; font-weight: 700; background: var(--ds-primary-soft); border: 0; border-radius: 10px; }

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

@media (prefers-reduced-motion: reduce) {
  .term-card, .filter-chip, .search-field { transition: none; }
}
</style>
