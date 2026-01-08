<template>
  <div class="parts-request-notes">
    <!-- Add Note Input -->
    <div class="q-mb-sm add-note-row">
      <q-input
        v-model="newNoteContent"
        type="textarea"
        outlined
        dense
        placeholder="Add a note..."
        :rows="2"
        :disable="savingNote"
        class="add-note-input"
      />
      <q-btn
        flat
        dense
        icon="send"
        color="primary"
        :disable="!newNoteContent.trim() || savingNote"
        :loading="savingNote"
        class="q-ml-sm"
        @click="addNote"
      />
    </div>

    <!-- Notes List -->
    <div v-if="loading" class="text-center q-py-md">
      <q-spinner color="primary" size="2em" />
    </div>

    <div v-else-if="notes.length === 0" class="text-center text-grey q-py-sm text-caption">
      No notes yet
    </div>

    <q-list v-else separator class="rounded-borders bg-grey-1">
      <q-item v-for="note in notes" :key="note.id" class="q-py-sm q-px-sm">
        <q-item-section>
          <!-- Edit Mode -->
          <div v-if="editingNoteId === note.id" class="edit-note-container">
            <q-input
              v-model="editNoteContent"
              type="textarea"
              outlined
              dense
              :rows="2"
              :disable="savingNote"
              class="edit-note-input"
            />
            <div class="edit-actions q-ml-sm">
              <q-btn
                flat
                dense
                round
                icon="close"
                color="grey"
                size="sm"
                :disable="savingNote"
                @click="cancelEdit"
              />
              <q-btn
                flat
                dense
                round
                icon="check"
                color="positive"
                size="sm"
                :disable="!editNoteContent.trim() || savingNote"
                :loading="savingNote"
                @click="saveEdit(note.id)"
              />
            </div>
          </div>

          <!-- View Mode -->
          <template v-else>
            <q-item-label class="note-content text-body2">{{ note.content }}</q-item-label>
            <q-item-label caption class="q-mt-xs note-meta">
              <span class="text-weight-medium">{{ note.user?.name || 'Unknown' }}</span>
              <span class="q-mx-xs">-</span>
              <span>{{ formatDate(note.created_at) }}</span>
              <span v-if="note.is_edited" class="q-ml-xs text-italic">
                (edited)
              </span>
            </q-item-label>
          </template>
        </q-item-section>

        <!-- Actions (only show if not editing this note) -->
        <q-item-section v-if="editingNoteId !== note.id" side top>
          <div class="row no-wrap">
            <q-btn
              v-if="note.can_edit"
              flat
              dense
              round
              icon="edit"
              size="xs"
              color="grey-7"
              @click="startEdit(note)"
            >
              <q-tooltip>Edit</q-tooltip>
            </q-btn>
            <q-btn
              v-if="note.can_delete"
              flat
              dense
              round
              icon="delete"
              size="xs"
              color="negative"
              @click="confirmDelete(note)"
            >
              <q-tooltip>Delete</q-tooltip>
            </q-btn>
          </div>
        </q-item-section>
      </q-item>
    </q-list>

    <!-- Delete Confirmation Dialog -->
    <q-dialog v-model="showDeleteDialog" persistent>
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">Delete Note</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          Are you sure you want to delete this note?
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" color="grey" v-close-popup :disable="deletingNote" />
          <q-btn
            flat
            label="Delete"
            color="negative"
            :loading="deletingNote"
            @click="deleteNote"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { usePartsRequestsStore, type PartsRequestNote } from 'stores/partsRequests';
import { date } from 'quasar';

const props = defineProps<{
  partsRequestId: number;
}>();

const emit = defineEmits<{
  (e: 'count-changed', count: number): void;
}>();

const store = usePartsRequestsStore();

const notes = ref<PartsRequestNote[]>([]);
const loading = ref(false);
const savingNote = ref(false);
const deletingNote = ref(false);

// New note
const newNoteContent = ref('');

// Edit state
const editingNoteId = ref<number | null>(null);
const editNoteContent = ref('');

// Delete state
const showDeleteDialog = ref(false);
const noteToDelete = ref<PartsRequestNote | null>(null);

async function loadNotes() {
  if (!props.partsRequestId) return;
  loading.value = true;
  try {
    notes.value = await store.fetchNotes(props.partsRequestId);
    emit('count-changed', notes.value.length);
  } catch {
    // Error handled in store
  } finally {
    loading.value = false;
  }
}

async function addNote() {
  if (!newNoteContent.value.trim()) return;
  savingNote.value = true;
  try {
    const note = await store.createNote(props.partsRequestId, newNoteContent.value.trim());
    notes.value.unshift(note);
    newNoteContent.value = '';
    emit('count-changed', notes.value.length);
  } catch {
    // Error handled in store
  } finally {
    savingNote.value = false;
  }
}

function startEdit(note: PartsRequestNote) {
  editingNoteId.value = note.id;
  editNoteContent.value = note.content;
}

function cancelEdit() {
  editingNoteId.value = null;
  editNoteContent.value = '';
}

async function saveEdit(noteId: number) {
  if (!editNoteContent.value.trim()) return;
  savingNote.value = true;
  try {
    const updated = await store.updateNote(props.partsRequestId, noteId, editNoteContent.value.trim());
    const index = notes.value.findIndex(n => n.id === noteId);
    if (index !== -1) {
      notes.value[index] = updated;
    }
    cancelEdit();
  } catch {
    // Error handled in store
  } finally {
    savingNote.value = false;
  }
}

function confirmDelete(note: PartsRequestNote) {
  noteToDelete.value = note;
  showDeleteDialog.value = true;
}

async function deleteNote() {
  if (!noteToDelete.value) return;
  deletingNote.value = true;
  try {
    await store.deleteNote(props.partsRequestId, noteToDelete.value.id);
    notes.value = notes.value.filter(n => n.id !== noteToDelete.value!.id);
    showDeleteDialog.value = false;
    noteToDelete.value = null;
    emit('count-changed', notes.value.length);
  } catch {
    // Error handled in store
  } finally {
    deletingNote.value = false;
  }
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '';
  return date.formatDate(dateStr, 'MMM D, YYYY h:mm A');
}

// Watch for partsRequestId changes
watch(() => props.partsRequestId, (newId) => {
  if (newId) {
    void loadNotes();
  }
}, { immediate: true });

onMounted(() => {
  if (props.partsRequestId) {
    void loadNotes();
  }
});
</script>

<style scoped>
.parts-request-notes {
  width: 100%;
}

.add-note-row {
  display: flex;
  align-items: flex-start;
}

.add-note-input {
  flex: 1;
  min-width: 0;
}

.edit-note-container {
  display: flex;
  align-items: flex-start;
}

.edit-note-input {
  flex: 1;
  min-width: 0;
}

.edit-actions {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.note-content {
  white-space: pre-wrap;
  word-break: break-word;
}

.note-meta {
  font-size: 11px;
}
</style>
