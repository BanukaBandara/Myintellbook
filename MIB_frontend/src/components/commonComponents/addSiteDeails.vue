<template>
  <Card :pt="{ body: 'p-0 m-0' }" class="shadow-sm rounded-3">
    <template #content>
      <nav class="menu-container">
        <!-- Main menu items -->
        <Button label="About" icon="pi pi-info-circle" class="menu-item" @click="() => router.push('/about_site')" />
        <Button label="Glossary" icon="pi pi-book" class="menu-item" @click="() => router.push('/glossary')" />
        <Button label="Terms & Conditions" icon="pi pi-file" class="menu-item" @click="() => router.push('/terms_conditions')" />
        <Button label="See How It Works" icon="pi pi-question-circle" class="menu-item" @click="() => router.push('/how_it_works')" />
        <Button label="Privacy Policy" icon="pi pi-lock" class="menu-item" @click="() => router.push('/privacy_policy')" />
        <Button label="Encryption Details" icon="pi pi-shield" class="menu-item" @click="() => router.push('/encription_details')" />
        <Button label="Data Retention Rule" icon="pi pi-database" class="menu-item" @click="() => router.push('/data_retention_rules')" />
        <Button label="Scoring Breakdown" icon="pi pi-chart-line" class="menu-item" @click="() => router.push('/scoring_breakdown')" />

        <!-- Divider -->
        <hr class="menu-divider" />

        <!-- Special actions -->
        <Button label="Verify Identity" icon="pi pi-id-card" class="menu-item" @click="() => router.push('/submit_case')" />
        <Button label="Submit a Case" icon="pi pi-send" class="menu-item" @click="() => router.push('/submit_case/external')" />

        <!-- Divider -->
      </nav>
    </template>
  </Card>
</template>

<script setup lang="ts">
import Button from 'primevue/button'
import Card from 'primevue/card'
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<style scoped>
.menu-container {
  display: flex;
  flex-direction: column;
  padding: 0.75rem 0.5rem;
  background-color: #fff;
}

.menu-item {
  justify-content: flex-start;
  width: 100%;
  text-align: left;
  font-weight: 500;
  color: #2c3e50;
  border: none;
  background: transparent;
  padding: 0.6rem 0.8rem;
  border-radius: 6px;
  transition: background-color 0.2s, color 0.2s;
}

.menu-item:hover {
  background-color: #f0f4f8;
  color: #A03829;
}

.menu-item .pi {
  margin-right: 0.5rem;
  font-size: 1rem;
  color: #6c757d;
}

/* Divider styling */
.menu-divider {
  border: none;
  border-top: 1px solid #e0e0e0;
  margin: 0.75re
  m 0;
}

.p-button-link-color,
.p-button-link-hover,
.p-button-link-active {
  color: #A03829 !important;
}


</style>
