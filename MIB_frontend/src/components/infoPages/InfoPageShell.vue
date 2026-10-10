<template>
  <!-- Middle-column content only: the sidebars come from layouts/DashboardLayout.vue. -->
  <div class="info-page">
        <!-- On phones the sidebar is hidden, so offer the same links as a scrollable chip row. -->
        <nav class="mobile-nav d-md-none no-print" aria-label="MyIntellibook information">
          <RouterLink
            v-for="link in mobileLinks"
            :key="link.key"
            :to="link.to"
            class="mobile-chip"
            :class="{ active: link.isActive(route) }"
            :aria-current="link.isActive(route) ? 'page' : undefined"
          >
            <i :class="['bi', link.icon]" aria-hidden="true"></i>{{ link.label }}
          </RouterLink>
        </nav>

        <article class="info-print-area">
          <header class="info-hero">
            <div class="hero-icon" aria-hidden="true"><i :class="['bi', icon]"></i></div>
            <div class="hero-text">
              <p class="hero-eyebrow">{{ eyebrow }}</p>
              <h1 class="hero-title">{{ title }}</h1>
              <p v-if="subtitle" class="hero-subtitle">{{ subtitle }}</p>
              <ul v-if="badges.length" class="hero-badges" aria-label="Highlights">
                <li v-for="badge in badges" :key="badge"><i class="bi bi-shield-fill-check" aria-hidden="true"></i>{{ badge }}</li>
              </ul>
              <slot name="hero-extra" />
            </div>
            <div v-if="printable" class="hero-actions no-print">
              <button type="button" class="pdf-button" @click="downloadPdf">
                <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Download PDF
              </button>
            </div>
          </header>

          <slot />
        </article>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { INFO_ACTIONS, INFO_LINKS } from './infoLinks';

withDefaults(defineProps<{
  icon: string;
  eyebrow: string;
  title: string;
  subtitle?: string;
  badges?: string[];
  printable?: boolean;
}>(), { subtitle: '', badges: () => [], printable: false });

const route = useRoute();
const mobileLinks = computed(() => [...INFO_LINKS, ...INFO_ACTIONS]);

/** The browser's print dialog offers "Save as PDF"; print styles below strip the app chrome. */
function downloadPdf(): void {
  window.print();
}
</script>

<style scoped>

.mobile-nav {
  display: flex;
  gap: 8px;
  margin: 0 -4px 12px;
  padding: 2px 4px 6px;
  overflow-x: auto;
  scrollbar-width: none;
}

.mobile-nav::-webkit-scrollbar { display: none; }

.mobile-chip {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  color: var(--ds-text-secondary);
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 999px;
}

.mobile-chip.active { color: var(--ds-primary-hover); background: var(--ds-primary-soft); border-color: var(--ds-primary-200); }
.mobile-chip:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

.info-hero {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 14px;
  padding: 22px;
  overflow: hidden;
  background: radial-gradient(circle at 100% 0%, var(--ds-primary-100) 0, transparent 45%), #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.hero-icon {
  display: grid;
  flex: 0 0 52px;
  width: 52px;
  height: 52px;
  color: var(--ds-primary);
  font-size: 24px;
  place-items: center;
  background: var(--ds-primary-soft);
  border: 1px solid var(--ds-primary-200);
  border-radius: 14px;
}

.hero-text { flex: 1 1 260px; min-width: 0; }
.hero-eyebrow { margin: 0 0 4px; color: var(--ds-primary); font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
.hero-title { margin: 0; color: var(--ds-text); font-size: clamp(20px, 3vw, 26px); font-weight: 800; line-height: 1.25; }
.hero-subtitle { margin: 8px 0 0; color: var(--ds-text-muted); font-size: 14px; line-height: 1.6; }

.hero-badges { display: flex; flex-wrap: wrap; gap: 6px; margin: 14px 0 0; padding: 0; list-style: none; }
.hero-badges li { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; color: var(--ds-success-text); font-size: 11.5px; font-weight: 700; background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); border-radius: 999px; }

.hero-actions { flex: 0 0 auto; }

.pdf-button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 40px;
  padding: 8px 14px;
  color: var(--ds-primary-hover);
  font-size: 13px;
  font-weight: 700;
  background: #fff;
  border: 1px solid var(--ds-primary-200);
  border-radius: 12px;
  transition: background-color .2s ease;
}

.pdf-button:hover { background: var(--ds-primary-soft); }
.pdf-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

@media (max-width: 575px) {
  .info-hero { padding: 18px 16px; }
  .hero-actions { width: 100%; }
  .pdf-button { justify-content: center; width: 100%; }
}
</style>

<style>
/* Print / "Save as PDF": only the article is printed, with every section expanded. */
@media print {
  body * { visibility: hidden; }
  .info-print-area, .info-print-area * { visibility: visible; }
  .info-print-area { position: absolute; top: 0; left: 0; width: 100%; }
  .info-print-area .no-print { display: none !important; }
  .info-print-area [data-print-expand] { display: block !important; }
  .info-print-area .info-hero { border: 0; box-shadow: none; background: none; }
}
</style>
