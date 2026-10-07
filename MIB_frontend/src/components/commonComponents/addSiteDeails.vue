<template>
  <nav class="side-nav dashboard-card" aria-label="MyIntellibook information">
    <p class="nav-heading">Resources</p>
    <ul class="nav-list">
      <li v-for="link in INFO_LINKS" :key="link.key">
        <RouterLink
          :to="link.to"
          class="nav-link-item"
          :class="{ active: link.isActive(route) }"
          :aria-current="link.isActive(route) ? 'page' : undefined"
        >
          <span class="nav-icon" aria-hidden="true"><i :class="['bi', link.icon]"></i></span>
          <span class="nav-label">{{ link.label }}</span>
          <i class="bi bi-chevron-right nav-chevron" aria-hidden="true"></i>
        </RouterLink>
      </li>
    </ul>

    <div class="nav-divider" role="presentation"></div>

    <p class="nav-heading">Trust &amp; Tribunal</p>
    <div class="nav-actions">
      <RouterLink
        v-for="action in INFO_ACTIONS"
        :key="action.key"
        :to="action.to"
        class="nav-cta"
        :class="[action.variant, { active: action.isActive(route) }]"
        :aria-current="action.isActive(route) ? 'page' : undefined"
      >
        <i :class="['bi', action.icon]" aria-hidden="true"></i>
        <span>{{ action.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import { INFO_ACTIONS, INFO_LINKS } from '@/components/infoPages/infoLinks';

const route = useRoute();
</script>

<style scoped>
.side-nav {
  padding: 14px 10px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.nav-heading {
  margin: 4px 10px 8px;
  color: var(--ds-text-subtle);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.nav-list { display: grid; gap: 2px; margin: 0; padding: 0; list-style: none; }

.nav-link-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  color: var(--ds-text-secondary);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  border-radius: 12px;
  transition: background-color .2s ease, color .2s ease;
}

.nav-link-item:hover { color: var(--ds-primary); background: var(--ds-primary-soft); }
.nav-link-item:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 1px; }

.nav-icon {
  display: grid;
  flex: 0 0 30px;
  width: 30px;
  height: 30px;
  color: var(--ds-text-muted);
  font-size: 14px;
  place-items: center;
  background: var(--ds-surface-muted);
  border-radius: 9px;
  transition: background-color .2s ease, color .2s ease;
}

.nav-link-item:hover .nav-icon,
.nav-link-item.active .nav-icon { color: var(--ds-primary); background: var(--ds-primary-100); }

.nav-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.nav-chevron { color: var(--ds-primary-300); font-size: 11px; opacity: 0; transform: translateX(-4px); transition: opacity .2s ease, transform .2s ease; }
.nav-link-item:hover .nav-chevron, .nav-link-item.active .nav-chevron { opacity: 1; transform: none; }

/* Active route: soft pill plus a left accent bar. */
.nav-link-item.active { color: var(--ds-primary-hover); font-weight: 600; background: var(--ds-primary-soft); }
.nav-link-item.active::before {
  position: absolute;
  top: 8px;
  bottom: 8px;
  left: -10px;
  width: 3px;
  content: '';
  background: var(--ds-primary);
  border-radius: 0 3px 3px 0;
}

.nav-divider { height: 1px; margin: 12px 10px; background: linear-gradient(90deg, transparent, var(--ds-border) 15%, var(--ds-border) 85%, transparent); }

.nav-actions { display: grid; gap: 8px; padding: 0 2px 2px; }

.nav-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 10px 14px;
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 12px;
  transition: background .2s ease, color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.nav-cta:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

.nav-cta.primary {
  color: #fff;
  background: var(--ds-primary);
  box-shadow: 0 4px 12px rgba(225, 29, 72, .25);
}

.nav-cta.primary:hover { color: #fff; background: var(--ds-primary-hover); box-shadow: 0 6px 16px rgba(225, 29, 72, .32); transform: translateY(-1px); }
.nav-cta.primary.active { box-shadow: 0 0 0 3px var(--ds-primary-100), 0 4px 12px rgba(225, 29, 72, .25); }

.nav-cta.secondary { color: var(--ds-primary); background: var(--ds-primary-soft); border: 1px solid transparent; }
.nav-cta.secondary:hover { color: var(--ds-primary); background: var(--ds-primary-100); }
.nav-cta.secondary.active { background: var(--ds-primary-soft); border-color: var(--ds-primary); }

@media (prefers-reduced-motion: reduce) {
  .nav-link-item, .nav-icon, .nav-chevron, .nav-cta { transition: none; }
}
</style>
