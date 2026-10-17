import {
    computed,
    getCurrentInstance,
    onMounted,
    onUnmounted,
    ref,
    type Ref,
} from 'vue';
import type { DocumentSchema } from '@/types/document';

export interface UseEditorHistoryOptions {
    maxSnapshots?: number;
    debounceMs?: number;
    onApplySnapshot?: (doc: DocumentSchema) => void;
}

export function useEditorHistory(
    getCurrentDocument: () => DocumentSchema,
    applyDocument: (doc: DocumentSchema) => void,
    options: UseEditorHistoryOptions = {},
) {
    const maxSnapshots = options.maxSnapshots ?? 30;
    const debounceMs = options.debounceMs ?? 300;

    const past: Ref<DocumentSchema[]> = ref([]);
    const future: Ref<DocumentSchema[]> = ref([]);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let pendingBaseSnapshot: DocumentSchema | null = null;

    function cloneDoc(doc: DocumentSchema): DocumentSchema {
        return JSON.parse(JSON.stringify(doc));
    }

    // Flush any pending debounced prop edit
    function flushPending(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        if (pendingBaseSnapshot) {
            past.value.push(pendingBaseSnapshot);
            if (past.value.length > maxSnapshots) {
                past.value.shift();
            }
            pendingBaseSnapshot = null;
            future.value = [];
        }
    }

    // Push a discrete snapshot immediately (used for insert, delete, move, duplicate)
    function pushSnapshot(snapshotToSave?: DocumentSchema): void {
        flushPending();

        const snap = snapshotToSave
            ? cloneDoc(snapshotToSave)
            : cloneDoc(getCurrentDocument());
        past.value.push(snap);

        if (past.value.length > maxSnapshots) {
            past.value.shift();
        }

        future.value = [];
    }

    // Record debounced edit (used for continuous typing or slider dragging)
    function recordDebouncedEdit(beforeEditDoc: DocumentSchema): void {
        if (!pendingBaseSnapshot) {
            pendingBaseSnapshot = cloneDoc(beforeEditDoc);
        }

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            flushPending();
        }, debounceMs);
    }

    const canUndo = computed(() => past.value.length > 0);
    const canRedo = computed(() => future.value.length > 0);

    function undo(): boolean {
        flushPending();
        if (past.value.length === 0) return false;

        const currentDoc = cloneDoc(getCurrentDocument());
        const previousDoc = past.value.pop()!;

        future.value.push(currentDoc);
        applyDocument(previousDoc);

        return true;
    }

    function redo(): boolean {
        flushPending();
        if (future.value.length === 0) return false;

        const currentDoc = cloneDoc(getCurrentDocument());
        const nextDoc = future.value.pop()!;

        past.value.push(currentDoc);
        applyDocument(nextDoc);

        return true;
    }

    function clearHistory(): void {
        flushPending();
        past.value = [];
        future.value = [];
    }

    // Keyboard shortcuts handler
    function handleKeyDown(e: KeyboardEvent): void {
        // Skip if user is actively typing in input, textarea, or contenteditable
        const activeEl = document.activeElement;
        if (activeEl) {
            const tagName = activeEl.tagName.toLowerCase();
            const isContentEditable = (activeEl as HTMLElement)
                .isContentEditable;
            if (
                tagName === 'input' ||
                tagName === 'textarea' ||
                isContentEditable
            ) {
                return;
            }
        }

        const isMac =
            typeof navigator !== 'undefined' &&
            /Mac|iPod|iPhone|iPad/.test(navigator.platform);
        const modifier = isMac ? e.metaKey : e.ctrlKey;

        if (!modifier) return;

        // Ctrl+Z or Cmd+Z
        if (e.key === 'z' || e.key === 'Z') {
            if (e.shiftKey) {
                // Ctrl+Shift+Z => Redo
                if (canRedo.value) {
                    e.preventDefault();
                    redo();
                }
            } else {
                // Ctrl+Z => Undo
                if (canUndo.value) {
                    e.preventDefault();
                    undo();
                }
            }
        } else if (e.key === 'y' || e.key === 'Y') {
            // Ctrl+Y => Redo
            if (canRedo.value) {
                e.preventDefault();
                redo();
            }
        }
    }

    if (getCurrentInstance()) {
        onMounted(() => {
            if (typeof window !== 'undefined') {
                window.addEventListener('keydown', handleKeyDown);
            }
        });

        onUnmounted(() => {
            flushPending();
            if (typeof window !== 'undefined') {
                window.removeEventListener('keydown', handleKeyDown);
            }
        });
    }

    return {
        past,
        future,
        canUndo,
        canRedo,
        pushSnapshot,
        recordDebouncedEdit,
        undo,
        redo,
        clearHistory,
        flushPending,
    };
}
