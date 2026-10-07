<template>
  <InfoPageShell
    icon="bi-journal-text"
    eyebrow="Testament"
    title="Community Testament Notes"
    subtitle="Share useful resources, equipment, and knowledge with community members."
    :badges="['Community shared', 'Contact details are optional']"
  >
    <section class="community" aria-labelledby="community-title">
      <!-- Action header -->
      <header class="feed-header">
        <div class="feed-header-copy">
          <h2 id="community-title">Community Notes</h2>
          <p>Share &amp; browse resources shared by community members</p>
        </div>
        <button type="button" class="create-button" @click="openCreateForm">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> Create a Note
        </button>
      </header>

      <Transition name="fade">
        <p v-if="noteNotice" class="alert success" role="status">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i> {{ noteNotice }}
          <button type="button" class="alert-dismiss" aria-label="Dismiss" @click="noteNotice = ''">×</button>
        </p>
      </Transition>
      <p v-if="notesError" class="alert error" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ notesError }}
        <button type="button" class="link-button" @click="loadNotes">Try again</button>
      </p>

      <!-- Feed scope -->
      <div class="scope-tabs" role="tablist" aria-label="Which notes to show">
        <button
          v-for="tab in SCOPE_TABS"
          :key="tab.value"
          type="button"
          role="tab"
          class="scope-tab"
          :class="{ active: feedScope === tab.value }"
          :aria-selected="feedScope === tab.value"
          @click="setScope(tab.value)"
        >
          <i :class="['bi', tab.icon]" aria-hidden="true"></i> {{ tab.label }}
        </button>
      </div>

      <!-- Toolbar -->
      <div v-if="!notesLoading && notes.length > 0" class="feed-toolbar">
        <label class="search-field">
          <i class="bi bi-search" aria-hidden="true"></i>
          <span class="visually-hidden">Search notes</span>
          <input v-model="searchQuery" type="search" placeholder="Search titles, notes, places or people…" autocomplete="off" />
        </label>
        <div class="category-chips" role="group" aria-label="Filter by category">
          <button
            type="button"
            class="chip"
            :class="{ active: activeCategory === '' }"
            :aria-pressed="activeCategory === ''"
            @click="activeCategory = ''"
          >
            All <span class="chip-count">{{ notes.length }}</span>
          </button>
          <button
            v-for="category in categories"
            :key="category.name"
            type="button"
            class="chip"
            :class="{ active: activeCategory === category.name }"
            :aria-pressed="activeCategory === category.name"
            @click="activeCategory = activeCategory === category.name ? '' : category.name"
          >
            <i :class="['bi', categoryIcon(category.name)]" aria-hidden="true"></i>
            {{ category.name }} <span class="chip-count">{{ category.count }}</span>
          </button>
        </div>
      </div>

      <!-- Loading skeletons -->
      <div v-if="notesLoading" class="notes-grid" role="status" aria-label="Loading community notes">
        <div v-for="n in 4" :key="n" class="note-card skeleton" aria-hidden="true">
          <span class="sk sk-pill"></span>
          <span class="sk sk-title"></span>
          <span class="sk sk-line"></span>
          <span class="sk sk-line short"></span>
          <span class="sk sk-button"></span>
        </div>
        <span class="visually-hidden">Loading community notes…</span>
      </div>

      <!-- Empty -->
      <div v-else-if="!notesError && notes.length === 0" class="notes-empty">
        <div class="empty-icon" aria-hidden="true"><i class="bi bi-box2-heart"></i></div>
        <template v-if="feedScope === 'mine'">
          <h3>You haven't shared any notes yet</h3>
          <p>Notes you post appear here, where you can edit or remove them at any time.</p>
        </template>
        <template v-else>
          <h3>No notes have been shared yet</h3>
          <p>Be the first to post a resource. Unused equipment, a useful tool or hard-won knowledge could make someone's day.</p>
        </template>
        <button type="button" class="primary-button" @click="openCreateForm">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> {{ feedScope === 'mine' ? 'Create a note' : 'Share the first note' }}
        </button>
      </div>

      <!-- No filter matches -->
      <div v-else-if="notes.length > 0 && filteredNotes.length === 0" class="notes-empty compact">
        <div class="empty-icon" aria-hidden="true"><i class="bi bi-search-heart"></i></div>
        <h3>No matching notes</h3>
        <p>Try another search term or category.</p>
        <button type="button" class="ghost-button" @click="clearFilters">Clear filters</button>
      </div>

      <!-- Feed -->
      <TransitionGroup v-else tag="div" name="card" class="notes-grid">
        <article
          v-for="note in filteredNotes"
          :key="note.id"
          class="note-card"
        >
          <div class="note-card-head">
            <span class="note-category">
              <i :class="['bi', categoryIcon(note.category)]" aria-hidden="true"></i> {{ note.category }}
            </span>
            <div class="note-head-end">
              <span class="note-status"><span class="status-dot" aria-hidden="true"></span> Available</span>
              <div v-if="note.is_owner" class="owner-actions">
                <button type="button" class="icon-button" :aria-label="`Edit ${note.title}`" title="Edit note" @click="openEditForm(note)">
                  <i class="bi bi-pencil" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-button danger" :aria-label="`Delete ${note.title}`" title="Delete note" @click="askDelete(note)">
                  <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </div>

          <h3 class="note-title">{{ note.title }}</h3>
          <p class="note-description" :class="{ clamped: !expandedDescriptions[note.id] }">{{ note.description }}</p>
          <button
            v-if="note.description.length > 180"
            type="button"
            class="read-more"
            :aria-expanded="Boolean(expandedDescriptions[note.id])"
            @click="expandedDescriptions[note.id] = !expandedDescriptions[note.id]"
          >
            {{ expandedDescriptions[note.id] ? 'Show less' : 'Read more' }}
            <i :class="['bi', expandedDescriptions[note.id] ? 'bi-chevron-up' : 'bi-chevron-down']" aria-hidden="true"></i>
          </button>

          <ul class="note-meta">
            <li v-if="note.location"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> {{ note.location }}</li>
            <li v-if="note.created_at">
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              <time :datetime="note.created_at">{{ formatDate(note.created_at) }}</time>
            </li>
          </ul>

          <div class="note-footer">
            <div class="note-owner">
              <span class="owner-avatar" aria-hidden="true">{{ initials(note.owner_name) }}</span>
              <span class="owner-text">
                <span class="owner-label">Shared by</span>
                <span class="owner-name">{{ note.owner_name }}</span>
              </span>
            </div>
            <button
              type="button"
              class="contact-button"
              :class="{ open: expandedContacts[note.id] }"
              :aria-expanded="Boolean(expandedContacts[note.id])"
              :aria-controls="`note-contacts-${note.id}`"
              @click="expandedContacts[note.id] = !expandedContacts[note.id]"
            >
              <i class="bi bi-chat-heart-fill" aria-hidden="true"></i> Contact owner
              <i class="bi bi-chevron-down chevron" aria-hidden="true"></i>
            </button>
          </div>

          <Transition name="reveal">
            <div v-if="expandedContacts[note.id]" :id="`note-contacts-${note.id}`" class="note-contacts">
              <a v-if="note.email" :href="`mailto:${note.email}`" class="contact-row">
                <span class="contact-icon" aria-hidden="true"><i class="bi bi-envelope-fill"></i></span>
                <span class="contact-text"><small>Email</small>{{ note.email }}</span>
                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
              </a>
              <a v-if="note.phone" :href="`tel:${note.phone}`" class="contact-row">
                <span class="contact-icon" aria-hidden="true"><i class="bi bi-telephone-fill"></i></span>
                <span class="contact-text"><small>Phone</small>{{ note.phone }}</span>
                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
              </a>
              <p v-if="!note.email && !note.phone" class="contact-none">
                <i class="bi bi-incognito" aria-hidden="true"></i> The owner did not share contact details.
              </p>
            </div>
          </Transition>
        </article>
      </TransitionGroup>
    </section>

    <!-- Create / edit note modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showNoteForm" class="note-modal-backdrop" role="presentation" @click.self="closeNoteForm">
          <section class="note-modal" role="dialog" aria-modal="true" aria-labelledby="note-form-title">
            <div class="note-modal-heading">
              <div class="modal-icon" aria-hidden="true"><i :class="['bi', editingId === null ? 'bi-gift-fill' : 'bi-pencil-square']"></i></div>
              <div class="modal-heading-text">
                <p class="form-eyebrow">Community sharing</p>
                <h2 id="note-form-title">{{ editingId === null ? 'Create a testament note' : 'Edit your testament note' }}</h2>
                <p class="form-intro">Only the contact details you provide will be visible to other community members.</p>
              </div>
              <button type="button" class="modal-close" aria-label="Close form" :disabled="noteSaving" @click="closeNoteForm">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
              </button>
            </div>

            <form class="note-form" @submit.prevent="saveNote">
              <label class="field">
                <span class="field-label">Title</span>
                <input v-model="noteForm.title" required maxlength="150" autocomplete="off" placeholder="e.g. Unused wheelchair in good condition" />
              </label>

              <label class="field">
                <span class="field-label">
                  Description / note
                  <span class="counter">{{ noteForm.description.length.toLocaleString() }} / 10,000</span>
                </span>
                <textarea v-model="noteForm.description" required maxlength="10000" rows="4" placeholder="What is it, what condition is it in, and who might find it useful?"></textarea>
              </label>

              <div class="field">
                <label class="field-label" for="note-category">Category</label>
                <input id="note-category" v-model="noteForm.category" required maxlength="80" autocomplete="off" placeholder="e.g. Equipment, tools, knowledge" />
                <div class="suggestions" role="group" aria-label="Suggested categories">
                  <button
                    v-for="suggestion in categorySuggestions"
                    :key="suggestion"
                    type="button"
                    class="suggestion"
                    :class="{ active: noteForm.category.trim().toLowerCase() === suggestion.toLowerCase() }"
                    @click="noteForm.category = suggestion"
                  >
                    <i :class="['bi', categoryIcon(suggestion)]" aria-hidden="true"></i> {{ suggestion }}
                  </button>
                </div>
              </div>

              <fieldset class="contact-fieldset">
                <legend><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Contact &amp; location <span>(all optional)</span></legend>
                <div class="note-form-row">
                  <label class="field">
                    <span class="field-label">Phone</span>
                    <span class="input-icon">
                      <i class="bi bi-telephone" aria-hidden="true"></i>
                      <input v-model="noteForm.phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+1 555 0100" />
                    </span>
                  </label>
                  <label class="field">
                    <span class="field-label">Email</span>
                    <span class="input-icon">
                      <i class="bi bi-envelope" aria-hidden="true"></i>
                      <input v-model="noteForm.email" type="email" maxlength="255" autocomplete="email" placeholder="you@example.com" />
                    </span>
                  </label>
                </div>
                <label class="field">
                  <span class="field-label">Location</span>
                  <span class="input-icon">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <input v-model="noteForm.location" maxlength="160" autocomplete="address-level2" placeholder="City or neighbourhood" />
                  </span>
                </label>
              </fieldset>

              <p v-if="noteFormError" class="alert error" role="alert">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ noteFormError }}
              </p>

              <div class="note-form-actions">
                <button type="button" class="ghost-button" :disabled="noteSaving" @click="closeNoteForm">Cancel</button>
                <button type="submit" class="primary-button" :disabled="noteSaving">
                  <span v-if="noteSaving" class="spinner" aria-hidden="true"></span>
                  <i v-else :class="['bi', editingId === null ? 'bi-send-fill' : 'bi-check2']" aria-hidden="true"></i>
                  <template v-if="editingId === null">{{ noteSaving ? 'Sharing…' : 'Share note' }}</template>
                  <template v-else>{{ noteSaving ? 'Saving…' : 'Save changes' }}</template>
                </button>
              </div>
            </form>
          </section>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete confirmation -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="deleteTarget" class="note-modal-backdrop" role="presentation" @click.self="cancelDelete">
          <section class="note-modal confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="delete-title" aria-describedby="delete-desc">
            <div class="confirm-body">
              <div class="confirm-icon" aria-hidden="true"><i class="bi bi-trash3-fill"></i></div>
              <h2 id="delete-title">Delete this note?</h2>
              <p id="delete-desc">“{{ deleteTarget.title }}” will be removed from the community feed. This can't be undone.</p>
              <p v-if="deleteError" class="alert error" role="alert">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ deleteError }}
              </p>
            </div>
            <div class="note-form-actions confirm-actions">
              <button type="button" class="ghost-button" :disabled="deleteBusy" @click="cancelDelete">Cancel</button>
              <button type="button" class="primary-button danger-button" :disabled="deleteBusy" @click="confirmDelete">
                <span v-if="deleteBusy" class="spinner" aria-hidden="true"></span>
                <i v-else class="bi bi-trash3" aria-hidden="true"></i>
                {{ deleteBusy ? 'Deleting…' : 'Delete note' }}
              </button>
            </div>
          </section>
        </div>
      </Transition>
    </Teleport>
  </InfoPageShell>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import { errorMessage, testamentApi, type TestamentResourceNote, type TestamentResourceNoteInput } from '@/services/testament';

const notes = ref<TestamentResourceNote[]>([]);
const notesLoading = ref(true);
const notesError = ref('');
const showNoteForm = ref(false);
const noteSaving = ref(false);
const noteFormError = ref('');
const noteNotice = ref('');
const expandedContacts = ref<Record<number, boolean>>({});

const emptyNote = (): TestamentResourceNoteInput => ({
  title: '',
  description: '',
  category: '',
  phone: null,
  email: null,
  location: null,
});
const noteForm = ref<TestamentResourceNoteInput>(emptyNote());

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(iso));
}

type FeedScope = 'all' | 'mine';
const SCOPE_TABS: { value: FeedScope; label: string; icon: string }[] = [
  { value: 'all', label: 'All Community Notes', icon: 'bi-people-fill' },
  { value: 'mine', label: 'My Testament Notes', icon: 'bi-person-fill' },
];
const feedScope = ref<FeedScope>('all');
let loadRequest = 0;

async function loadNotes(): Promise<void> {
  const request = ++loadRequest;
  notesError.value = '';
  notesLoading.value = true;
  try {
    const loaded = feedScope.value === 'mine' ? await testamentApi.myResourceNotes() : await testamentApi.resourceNotes();
    // Ignore a slower response for a tab the user has already left.
    if (request === loadRequest) notes.value = loaded;
  } catch (error) {
    if (request === loadRequest) notesError.value = errorMessage(error, 'Community notes could not be loaded.');
  } finally {
    if (request === loadRequest) notesLoading.value = false;
  }
}

function setScope(scope: FeedScope): void {
  if (feedScope.value === scope) return;
  feedScope.value = scope;
  void loadNotes();
}

/** Id of the note being edited, or null when the form creates a new note. */
const editingId = ref<number | null>(null);

function openCreateForm(): void {
  // Coming out of an edit, start from a blank form rather than the edited note.
  if (editingId.value !== null) {
    editingId.value = null;
    noteForm.value = emptyNote();
  }
  noteFormError.value = '';
  showNoteForm.value = true;
}

function openEditForm(note: TestamentResourceNote): void {
  editingId.value = note.id;
  noteForm.value = {
    title: note.title,
    description: note.description,
    category: note.category,
    phone: note.phone,
    email: note.email,
    location: note.location,
  };
  noteFormError.value = '';
  showNoteForm.value = true;
}

function closeNoteForm(): void {
  if (noteSaving.value) return;
  showNoteForm.value = false;
  noteFormError.value = '';
}

async function saveNote(): Promise<void> {
  noteSaving.value = true;
  noteFormError.value = '';
  noteNotice.value = '';

  const input: TestamentResourceNoteInput = {
    title: noteForm.value.title.trim(),
    description: noteForm.value.description.trim(),
    category: noteForm.value.category.trim(),
    phone: noteForm.value.phone?.trim() || null,
    email: noteForm.value.email?.trim() || null,
    location: noteForm.value.location?.trim() || null,
  };
  const id = editingId.value;

  try {
    if (id === null) {
      const created = await testamentApi.createResourceNote(input);
      notes.value = [created, ...notes.value];
      noteNotice.value = 'Your note is now available in the community feed.';
    } else {
      const updated = await testamentApi.updateResourceNote(id, input);
      notes.value = notes.value.map((note) => (note.id === id ? updated : note));
      editingId.value = null;
      noteNotice.value = 'Your note has been updated.';
    }
    noteForm.value = emptyNote();
    showNoteForm.value = false;
  } catch (error) {
    noteFormError.value = errorMessage(error, id === null ? 'Your note could not be shared.' : 'Your changes could not be saved.');
  } finally {
    noteSaving.value = false;
  }
}

const deleteTarget = ref<TestamentResourceNote | null>(null);
const deleteBusy = ref(false);
const deleteError = ref('');

function askDelete(note: TestamentResourceNote): void {
  deleteError.value = '';
  deleteTarget.value = note;
}

function cancelDelete(): void {
  if (deleteBusy.value) return;
  deleteTarget.value = null;
}

async function confirmDelete(): Promise<void> {
  const target = deleteTarget.value;
  if (!target) return;
  deleteBusy.value = true;
  deleteError.value = '';
  noteNotice.value = '';
  try {
    await testamentApi.deleteResourceNote(target.id);
    notes.value = notes.value.filter((note) => note.id !== target.id);
    deleteTarget.value = null;
    noteNotice.value = 'Your note has been deleted.';
  } catch (error) {
    deleteError.value = errorMessage(error, 'Your note could not be deleted.');
  } finally {
    deleteBusy.value = false;
  }
}

// ----- View-only helpers (search, filters, presentation) -----

const searchQuery = ref('');
const activeCategory = ref('');
const expandedDescriptions = ref<Record<number, boolean>>({});
const categorySuggestions = ['Equipment', 'Tools', 'Knowledge', 'Books', 'Medical', 'Household'];

const categories = computed(() => {
  const counts = new Map<string, number>();
  for (const note of notes.value) counts.set(note.category, (counts.get(note.category) ?? 0) + 1);
  return [...counts].map(([name, count]) => ({ name, count })).sort((a, b) => b.count - a.count);
});

const filteredNotes = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  return notes.value.filter((note) => {
    if (activeCategory.value && note.category !== activeCategory.value) return false;
    if (!query) return true;
    return [note.title, note.description, note.category, note.location, note.owner_name]
      .some((field) => field?.toLowerCase().includes(query));
  });
});

function clearFilters(): void {
  searchQuery.value = '';
  activeCategory.value = '';
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase() || '?';
}

const CATEGORY_ICONS: [RegExp, string][] = [
  [/equip|device|gear|machine/i, 'bi-tools'],
  [/tool/i, 'bi-wrench-adjustable'],
  [/know|note|skill|guide|tip/i, 'bi-lightbulb-fill'],
  [/book|read|study|educat/i, 'bi-book-fill'],
  [/medic|health|care/i, 'bi-heart-pulse-fill'],
  [/house|home|furnit/i, 'bi-house-heart-fill'],
  [/cloth|wear/i, 'bi-bag-heart-fill'],
  [/tech|computer|electr|phone/i, 'bi-cpu-fill'],
];

function categoryIcon(category: string): string {
  return CATEGORY_ICONS.find(([pattern]) => pattern.test(category))?.[1] ?? 'bi-tag-fill';
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape') return;
  if (deleteTarget.value) cancelDelete();
  else if (showNoteForm.value) closeNoteForm();
}

onMounted(() => {
  void loadNotes();
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<style scoped>
/* ---------- Shell ---------- */
.community { padding: 20px; background: #fff; border: 1px solid var(--ds-border); border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }

/* ---------- Action header ---------- */
.feed-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 16px; margin-bottom: 16px; }
.feed-header-copy { min-width: 0; }
.feed-header-copy h2 { margin: 0; color: var(--ds-text); font-size: 18px; font-weight: 700; line-height: 1.3; }
.feed-header-copy p { margin: 2px 0 0; color: var(--ds-text-muted); font-size: 13px; line-height: 1.5; }
/* bg-rose-600 hover:bg-rose-700 text-white font-medium px-4 py-2.5 rounded-xl shadow-sm transition-all active:scale-95 flex items-center gap-2 text-sm */
.create-button { display: flex; flex: 0 0 auto; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; color: #fff; font-size: 14px; font-weight: 500; line-height: 20px; white-space: nowrap; background: #e11d48; border: 0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, .1), 0 1px 2px -1px rgba(0, 0, 0, .1); transition: all .15s cubic-bezier(.4, 0, .2, 1); }
.create-button:hover { background: #be123c; }
.create-button:active { transform: scale(.95); }
.create-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

/* ---------- Scope tabs ---------- */
.scope-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px; margin-bottom: 12px; padding: 4px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 14px; }
.scope-tab { display: inline-flex; min-width: 0; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 8px 12px; color: var(--ds-text-secondary); font-size: 13px; font-weight: 700; background: transparent; border: 0; border-radius: 10px; transition: background-color .2s ease, color .2s ease, box-shadow .2s ease; }
.scope-tab:hover:not(.active) { color: var(--ds-primary-hover); }
.scope-tab.active { color: var(--ds-primary-hover); background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }

/* ---------- Toolbar ---------- */
.feed-toolbar { display: grid; gap: 10px; margin-bottom: 24px; }
.search-field { position: relative; display: block; margin: 0; }
.search-field > i { position: absolute; top: 50%; left: 13px; color: var(--ds-text-subtle); font-size: 14px; transform: translateY(-50%); pointer-events: none; }
.search-field input { width: 100%; min-height: 42px; padding: 10px 12px 10px 38px; color: var(--ds-text); font-size: 13px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 12px; outline: none; transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease; }
.search-field input:focus { background: #fff; border-color: var(--ds-primary-300); box-shadow: 0 0 0 3px var(--ds-primary-100); }
.category-chips { display: flex; gap: 6px; margin: 0 -2px; padding: 2px 2px 4px; overflow-x: auto; scrollbar-width: none; }
.category-chips::-webkit-scrollbar { display: none; }
.chip { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 6px; padding: 6px 11px; color: var(--ds-text-secondary); font-size: 12px; font-weight: 650; white-space: nowrap; background: #fff; border: 1px solid var(--ds-border); border-radius: 999px; transition: all .18s ease; }
.chip:hover { color: var(--ds-primary-hover); border-color: var(--ds-primary-200); }
.chip.active { color: #fff; background: var(--ds-primary); border-color: transparent; box-shadow: 0 4px 10px rgba(225, 29, 72, .25); }
.chip-count { padding: 1px 6px; font-size: 10.5px; font-weight: 700; background: rgba(15, 23, 42, .06); border-radius: 999px; }
.chip.active .chip-count { background: rgba(255, 255, 255, .22); }

/* ---------- Grid & cards ---------- */
.notes-grid { position: relative; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 14px; }
/* bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all p-5 */
.note-card {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  padding: 20px;
  background: #fff;
  border: 1px solid rgba(226, 232, 240, .8);
  border-radius: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
  transition: all .15s cubic-bezier(.4, 0, .2, 1);
}
.note-card:hover { box-shadow: 0 4px 6px -1px rgba(15, 23, 42, .1), 0 2px 4px -2px rgba(15, 23, 42, .1); }

.note-card-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
/* bg-slate-100 text-slate-700 text-xs font-medium px-2.5 py-1 rounded-lg border border-slate-200/50 */
.note-category { display: inline-flex; min-width: 0; align-items: center; gap: 6px; padding: 4px 10px; color: #334155; font-size: 12px; font-weight: 500; line-height: 16px; background: #f1f5f9; border: 1px solid rgba(226, 232, 240, .5); border-radius: 8px; overflow-wrap: anywhere; }
.note-category i { color: #64748b; }
/* text-xs font-medium text-slate-600 with a w-2 h-2 bg-emerald-500 dot */
.note-status { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 6px; color: #475569; font-size: 12px; font-weight: 500; }
.status-dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; }
.note-head-end { display: flex; flex: 0 0 auto; align-items: center; gap: 8px; }
.owner-actions { display: flex; gap: 2px; padding-left: 8px; border-left: 1px solid var(--ds-surface-muted); }
.icon-button { display: grid; width: 28px; height: 28px; color: var(--ds-text-subtle); font-size: 13px; place-items: center; background: transparent; border: 0; border-radius: 8px; transition: color .18s ease, background-color .18s ease; }
.icon-button:hover { color: #334155; background: #f1f5f9; }
.icon-button.danger:hover { color: var(--ds-danger); background: var(--ds-danger-soft); }

.note-title { margin: 12px 0 6px; color: var(--ds-text); font-size: 16px; font-weight: 800; line-height: 1.35; overflow-wrap: anywhere; }
.note-description { margin: 0; color: var(--ds-text-secondary); font-size: 13px; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.note-description.clamped { display: -webkit-box; overflow: hidden; -webkit-line-clamp: 4; -webkit-box-orient: vertical; }
.read-more { display: inline-flex; align-self: flex-start; align-items: center; gap: 4px; margin-top: 6px; padding: 0; color: var(--ds-primary); font-size: 12px; font-weight: 600; background: none; border: 0; }
.read-more:hover { color: var(--ds-primary-hover); }

.note-meta { display: flex; flex: 1; flex-wrap: wrap; align-content: flex-start; gap: 6px; margin: 12px 0 14px; padding: 0; list-style: none; }
.note-meta li { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; color: var(--ds-text-muted); font-size: 11.5px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-surface-muted); border-radius: 8px; overflow-wrap: anywhere; }
.note-meta i { color: var(--ds-text-subtle); }

.note-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-top: 12px; border-top: 1px dashed var(--ds-border); }
.note-owner { display: flex; min-width: 0; align-items: center; gap: 9px; }
/* bg-slate-100 text-slate-700 font-semibold border border-slate-200 rounded-full w-9 h-9 flex items-center justify-center */
.owner-avatar { display: flex; flex: 0 0 36px; width: 36px; height: 36px; align-items: center; justify-content: center; color: #334155; font-size: 12px; font-weight: 600; letter-spacing: .02em; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 50%; }
.owner-text { display: grid; min-width: 0; line-height: 1.25; }
.owner-label { color: var(--ds-text-subtle); font-size: 10.5px; font-weight: 600; }
.owner-name { overflow: hidden; color: var(--ds-text); font-size: 12.5px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }

.contact-button { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 6px; min-height: 36px; padding: 7px 12px; color: #fff; font-size: 12px; font-weight: 700; background: var(--ds-primary); border: 0; border-radius: 10px; box-shadow: 0 4px 12px rgba(225, 29, 72, .25); transition: transform .2s ease, box-shadow .2s ease; }
.contact-button:hover { box-shadow: 0 8px 18px rgba(225, 29, 72, .32); transform: translateY(-1px); }
.contact-button .chevron { font-size: 10px; transition: transform .25s ease; }
.contact-button.open .chevron { transform: rotate(180deg); }

.note-contacts { display: grid; gap: 8px; margin-top: 12px; padding: 10px; background: linear-gradient(180deg, var(--ds-primary-soft), #fff); border: 1px solid var(--ds-primary-100); border-radius: 12px; }
.contact-row { display: flex; min-width: 0; align-items: center; gap: 10px; padding: 8px 10px; color: var(--ds-text); text-decoration: none; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 10px; transition: border-color .2s ease, transform .2s ease; }
.contact-row:hover { border-color: var(--ds-primary-200); transform: translateX(2px); }
.contact-row > .bi-arrow-up-right { margin-left: auto; color: var(--ds-text-subtle); font-size: 12px; }
.contact-icon { display: grid; flex: 0 0 30px; width: 30px; height: 30px; color: var(--ds-primary); place-items: center; background: var(--ds-primary-soft); border-radius: 8px; }
.contact-text { display: grid; min-width: 0; font-size: 12.5px; font-weight: 650; overflow-wrap: anywhere; }
.contact-text small { color: var(--ds-text-subtle); font-size: 10.5px; font-weight: 600; }
.contact-none { display: flex; align-items: center; gap: 7px; margin: 0; padding: 4px 2px; color: var(--ds-text-muted); font-size: 12px; }

/* ---------- Skeleton / empty ---------- */
.skeleton { gap: 10px; pointer-events: none; }
.skeleton::before { background: var(--ds-surface-muted); }
.sk { display: block; background: linear-gradient(90deg, var(--ds-surface-muted) 25%, var(--ds-surface-subtle) 50%, var(--ds-surface-muted) 75%); background-size: 200% 100%; border-radius: 8px; animation: shimmer 1.4s linear infinite; }
.sk-pill { width: 90px; height: 20px; border-radius: 999px; }
.sk-title { width: 70%; height: 18px; margin-top: 4px; }
.sk-line { width: 100%; height: 11px; }
.sk-line.short { width: 60%; }
.sk-button { width: 100%; height: 36px; margin-top: 10px; border-radius: 10px; }
@keyframes shimmer { to { background-position: -200% 0; } }

.notes-empty { display: grid; justify-items: center; gap: 8px; padding: 34px 20px; text-align: center; background: radial-gradient(circle at 50% 0%, var(--ds-primary-soft) 0, transparent 60%), var(--ds-surface-subtle); border: 1px dashed var(--ds-primary-200); border-radius: 16px; }
.notes-empty.compact { padding: 26px 20px; }
.notes-empty h3 { margin: 4px 0 0; color: var(--ds-text); font-size: 16px; font-weight: 800; }
.notes-empty p { max-width: 380px; margin: 0 0 6px; color: var(--ds-text-muted); font-size: 13px; line-height: 1.55; }
.empty-icon { display: grid; width: 60px; height: 60px; color: var(--ds-primary); font-size: 28px; place-items: center; background: #fff; border: 1px solid var(--ds-primary-200); border-radius: 18px; box-shadow: 0 10px 24px -10px rgba(225, 29, 72, .4); animation: float 3.5s ease-in-out infinite; }
@keyframes float { 50% { transform: translateY(-5px); } }

/* ---------- Buttons & alerts ---------- */
.primary-button, .ghost-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 42px; padding: 9px 16px; font-size: 13.5px; font-weight: 500; border-radius: 12px; transition: transform .2s ease, box-shadow .2s ease, background-color .2s ease; }
.primary-button { color: #fff; background: var(--ds-primary); border: 0; box-shadow: var(--ds-shadow-sm); }
.primary-button:hover:not(:disabled) { background: var(--ds-primary-hover); }
.ghost-button { color: var(--ds-text-secondary); background: #fff; border: 1px solid var(--ds-border); }
.ghost-button:hover:not(:disabled) { color: var(--ds-primary-hover); background: var(--ds-primary-soft); }
.primary-button:disabled, .ghost-button:disabled { cursor: not-allowed; opacity: .55; transform: none; }
.primary-button:focus-visible, .ghost-button:focus-visible, .contact-button:focus-visible, .link-button:focus-visible,
.modal-close:focus-visible, .chip:focus-visible, .read-more:focus-visible, .contact-row:focus-visible, .suggestion:focus-visible,
.alert-dismiss:focus-visible, .scope-tab:focus-visible, .icon-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.danger-button.primary-button { background: var(--ds-danger); box-shadow: 0 4px 12px rgba(220, 38, 38, .25); }
.danger-button.primary-button:hover:not(:disabled) { box-shadow: 0 8px 20px rgba(220, 38, 38, .32); }

.alert { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0 0 12px; padding: 10px 12px; font-size: 13px; border-radius: 12px; }
.alert.error { color: var(--ds-danger-text); background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); }
.alert.success { color: var(--ds-success-text); background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); }
.alert-dismiss { margin-left: auto; padding: 0 4px; color: inherit; font-size: 18px; line-height: 1; background: none; border: 0; opacity: .6; }
.alert-dismiss:hover { opacity: 1; }
.link-button { padding: 0; color: var(--ds-primary-hover); font-weight: 700; text-decoration: underline; background: none; border: 0; }
.spinner { width: 14px; height: 14px; border: 2px solid rgba(255, 255, 255, .4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

/* ---------- Modal ---------- */
.note-modal-backdrop { position: fixed; inset: 0; z-index: 1050; display: grid; overflow-y: auto; padding: 20px; place-items: center; background: rgba(17, 24, 39, .55); backdrop-filter: blur(4px); }
.note-modal { position: relative; width: min(100%, 580px); max-height: min(92vh, 860px); overflow-y: auto; padding: 0; background: #fff; border: 1px solid var(--ds-border); border-radius: 20px; box-shadow: 0 30px 80px -20px rgba(15, 23, 42, .45); }
.note-modal-heading { position: relative; display: flex; align-items: flex-start; gap: 14px; padding: 22px 22px 18px; background: radial-gradient(circle at 100% 0%, var(--ds-primary-100) 0, transparent 55%), #fff; border-bottom: 1px solid var(--ds-surface-muted); }
.modal-icon { display: grid; flex: 0 0 46px; width: 46px; height: 46px; color: #fff; font-size: 20px; place-items: center; background: var(--ds-primary); border-radius: 14px; box-shadow: 0 8px 18px -6px rgba(225, 29, 72, .55); }
.modal-heading-text { flex: 1; min-width: 0; }
.modal-heading-text h2 { margin: 2px 0 4px; color: var(--ds-text); font-size: 19px; font-weight: 800; }
.form-eyebrow { margin: 0; color: var(--ds-primary); font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
.form-intro { margin: 0; color: var(--ds-text-muted); font-size: 12.5px; line-height: 1.5; }
.modal-close { display: grid; flex: 0 0 34px; width: 34px; height: 34px; color: var(--ds-text-muted); font-size: 14px; place-items: center; background: #fff; border: 1px solid var(--ds-border); border-radius: 10px; transition: all .2s ease; }
.modal-close:hover:not(:disabled) { color: var(--ds-primary-hover); background: var(--ds-primary-soft); transform: rotate(90deg); }

.note-form { display: grid; gap: 14px; padding: 18px 22px 22px; }
.field { display: grid; gap: 6px; margin: 0; }
.field-label { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; color: var(--ds-text-secondary); font-size: 12px; font-weight: 700; }
.counter { color: var(--ds-text-subtle); font-size: 11px; font-weight: 500; font-variant-numeric: tabular-nums; }
.note-form input, .note-form textarea { width: 100%; padding: 10px 12px; color: var(--ds-text); font: inherit; font-size: 13px; font-weight: 400; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 11px; outline: none; transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease; }
.note-form input:focus, .note-form textarea:focus { background: #fff; border-color: var(--ds-primary-300); box-shadow: 0 0 0 3px var(--ds-primary-100); }
.note-form textarea { min-height: 110px; resize: vertical; }
.input-icon { position: relative; display: block; }
.input-icon > i { position: absolute; top: 50%; left: 12px; color: var(--ds-text-subtle); font-size: 13px; transform: translateY(-50%); pointer-events: none; }
.input-icon input { padding-left: 34px; }

.suggestions { display: flex; flex-wrap: wrap; gap: 6px; }
.suggestion { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; color: var(--ds-text-secondary); font-size: 11.5px; font-weight: 600; background: #fff; border: 1px solid var(--ds-border); border-radius: 999px; transition: all .18s ease; }
.suggestion:hover { color: var(--ds-primary-hover); border-color: var(--ds-primary-200); }
.suggestion.active { color: var(--ds-primary-hover); background: var(--ds-primary-soft); border-color: var(--ds-primary-300); }

.contact-fieldset { display: grid; gap: 12px; min-width: 0; margin: 0; padding: 14px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.contact-fieldset legend { float: left; display: flex; align-items: center; gap: 6px; width: 100%; margin: 0; padding: 0; color: var(--ds-text); font-size: 12.5px; font-weight: 800; }
.contact-fieldset legend i { color: var(--ds-success); }
.contact-fieldset legend span { color: var(--ds-text-subtle); font-weight: 500; }
.contact-fieldset input { background: #fff; }
.note-form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.note-form-actions { position: sticky; bottom: -22px; display: flex; justify-content: flex-end; gap: 8px; margin: 0 -22px -22px; padding: 14px 22px; background: rgba(255, 255, 255, .94); border-top: 1px solid var(--ds-surface-muted); backdrop-filter: blur(6px); }

.confirm-modal { width: min(100%, 400px); }
.confirm-body { display: grid; justify-items: center; gap: 6px; padding: 24px 22px 18px; text-align: center; }
.confirm-body h2 { margin: 6px 0 0; color: var(--ds-text); font-size: 18px; font-weight: 800; }
.confirm-body p { margin: 0; color: var(--ds-text-muted); font-size: 13px; line-height: 1.55; overflow-wrap: anywhere; }
.confirm-body .alert { margin-top: 8px; text-align: left; }
.confirm-icon { display: grid; width: 48px; height: 48px; color: var(--ds-danger); font-size: 20px; place-items: center; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 14px; }
.confirm-actions { position: static; margin: 0; }

/* ---------- Transitions ---------- */
.fade-enter-active, .fade-leave-active { transition: opacity .25s ease, transform .25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }
.reveal-enter-active, .reveal-leave-active { transition: opacity .22s ease, transform .22s ease; }
.reveal-enter-from, .reveal-leave-to { opacity: 0; transform: translateY(-6px); }
.card-enter-active { transition: opacity .35s ease, transform .35s ease; }
.card-leave-active { position: absolute; transition: opacity .2s ease; }
.card-enter-from { opacity: 0; transform: translateY(10px) scale(.98); }
.card-leave-to { opacity: 0; }
.card-move { transition: transform .35s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity .25s ease; }
.modal-enter-active .note-modal, .modal-leave-active .note-modal { transition: transform .3s cubic-bezier(.2, .9, .3, 1.2), opacity .25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .note-modal, .modal-leave-to .note-modal { opacity: 0; transform: translateY(16px) scale(.97); }

/* ---------- Responsive & motion ---------- */
@media (max-width: 575px) {
  .community { padding: 14px 12px; }
  .note-form-row { grid-template-columns: 1fr; }
  .note-modal-backdrop { align-items: end; padding: 0; }
  .note-modal { width: 100%; max-height: 94vh; border-radius: 20px 20px 0 0; }
  .note-modal-heading { padding: 18px 16px 14px; }
  .note-form { padding: 16px 16px 18px; }
  .note-form-actions { bottom: -18px; margin: 0 -16px -18px; padding: 12px 16px; }
  .note-form-actions > * { flex: 1; }
  .confirm-actions { margin: 0; }
  .scope-tab { padding: 8px 6px; font-size: 12px; }
  .note-footer { flex-wrap: wrap; }
  .contact-button { justify-content: center; width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation: none !important; transition: none !important; }
}
</style>
