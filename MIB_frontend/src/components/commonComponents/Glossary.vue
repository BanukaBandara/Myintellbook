<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>

    <!-- Left Sidebar -->
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>

    <!-- Main Content – Now matches Terms & Scoring pages exactly -->
    <div class="col-md-4 mt-3">
      <section class="about-container">
        <h1>Glossary</h1>

        <!-- Search Box – styled to match the platform's clean aesthetic -->
        <input
          v-model="search"
          type="text"
          placeholder="Search glossary terms..."
          class="search-box mb-4"
        />

        <!-- Glossary Entries -->
        <div
          v-for="entry in filteredGlossary"
          :key="entry.term"
          class="glossary-entry"
        >
          <h2>{{ entry.term }}</h2>
          <p>{{ entry.definition }}</p>
        </div>

        <!-- Empty state when no results -->
        <div v-if="filteredGlossary.length === 0" class="text-muted text-center py-4">
          No terms found matching your search.
        </div>
      </section>
    </div>

    <!-- Right Sidebar -->
    <div class="col-md-3 mt-3 d-none d-md-block">
      <latestUpdates />
      <Divider class="w-75" />
      <Divider class="w-75" />
      <Divider class="w-75" />
      <ProfileList />
    </div>

    <div class="col-md-2"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { defineAsyncComponent } from 'vue'
import Divider from 'primevue/divider'

// Async components – adjust paths only if needed
const ProfileDetails = defineAsyncComponent(() => 
  import('../../stores/User/userProfile')
)
const addSiteDeails = defineAsyncComponent(() => 
  import('../../components/commonComponents/addSiteDeails.vue')
)
const latestUpdates = defineAsyncComponent(() => 
  import('../../components/commonComponents/latestUpdates.vue')
)

// Search functionality
const search = ref('')

const glossary = [
  {
    term: 'MyIntellibook',
    definition:
      'An intelligent learning and assessment platform that enhances knowledge, skills, and ethical reasoning through interactive tools, intelligent feedback, and performance-based scoring.',
  },
  {
    term: 'HIP – Human Intelligence Portfolio',
    definition:
      'A dynamic personal profile that represents an individual’s real-life achievements, knowledge, and skills. It groups users based on their Life Competency Index (LCI) and reflects growth in both professional and personal competencies.',
  },
  {
    term: 'LCI – Life Competency Index',
    definition:
      'A measurable value that indicates a person’s overall life competency, determined by education, career achievements, social contributions, problem-solving ability, and ethical behavior.',
  },
  {
    term: 'Online Tribunal',
    definition:
      'A virtual platform that allows MyIntellibook members to resolve personal or professional conflicts positively, fairly, and efficiently.',
  },
  {
    term: 'Fixed Arm for Testament Management',
    definition:
      'A secure and transparent system for managing testamentary records, wills, and inheritances.',
  },
  {
    term: 'Learning Modules',
    definition:
      'Interactive lessons and assessments that help users strengthen knowledge across multiple disciplines.',
  },
  {
    term: 'Performance-Based Scoring',
    definition:
      'A system that evaluates learners not only on correct answers but also on reasoning quality, time management, and improvement trends.',
  },
  {
    term: 'Ethical Reasoning',
    definition:
      'A core principle of MyIntellibook that emphasizes learning through values, integrity, and social responsibility.',
  },
  {
    term: 'Intelligent Feedback',
    definition:
      'AI-driven guidance provided after quizzes or assessments, helping learners understand mistakes and improve decision-making.',
  },
  {
    term: 'Competency Groups',
    definition:
      'Clusters of individuals with similar LCI scores or skill profiles that foster peer learning and mentoring.',
  },
  {
    term: 'Life Achievement Record',
    definition:
      'A record of user accomplishments — including education, career, innovation, and social impact — that contributes to their LCI.',
  },
]

const filteredGlossary = computed(() =>
  glossary.filter((entry) =>
    entry.term.toLowerCase().includes(search.value.toLowerCase()) ||
    entry.definition.toLowerCase().includes(search.value.toLowerCase())
  )
)
</script>

<style scoped>
.about-container {
  max-width: 800px;
  padding: 1rem 2rem;
  font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.6;
  background-color: #f9f9f9;
  border-radius: 8px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
}

.about-container h1 {
  font-size: 1.3rem;
  margin-bottom: 1.2rem;
  color: #2c3e50;
  font-weight: 600;
}

/* Search box – clean and consistent with other pages */
.search-box {
  width: 100%;
  padding: 0.75rem 1rem;
  font-size: 1rem;
  border: 1px solid #ccc;
  border-radius: 6px;
  background-color: #f8f8f8;
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
  transition: border-color 0.3s;
}

.search-box:focus {
  outline: none;
  border-color: #A03829;
  box-shadow: 0 0 0 2px rgba(160, 56, 41, 0.2);
}

/* Glossary entries – same spacing and color scheme as Terms page */
.glossary-entry {
  margin-bottom: 1.6rem;
  padding-bottom: 1.2rem;
  border-bottom: 1px solid #eee;
}

.glossary-entry:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.glossary-entry h2 {
  font-size: 1.25rem;
  margin: 0 0 0.5rem 0;
  color: #A03829;
  font-weight: 600;
}

.glossary-entry p {
  margin: 0;
  color: #333;
  line-height: 1.65;
}
</style>