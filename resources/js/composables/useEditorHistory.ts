import {
    computed,
    getCurrentInstance,
    onMounted,
    onUnmounted,
    shallowRef,
    triggerRef,
    type ShallowRef,
} from 'vue';
import type { DocumentSchema } from '@/types/document';

export interface UseEditorHistoryOptions {
    maxSnapshots?: number;
    debounceMs?: number;
    onApplySnapshot?: (doc: DocumentSchema) => ApplyDocumentResult;
    onUndo?: () => void;
    onRedo?: () => void;
}

type ApplyDocumentResult = void | boolean | { success: boolean };

export function useEditorHistory(
    getCurrentDocument: () => DocumentSchema,
    applyDocument: (doc: DocumentSchema) => ApplyDocumentResult,
    options: UseEditorHistoryOptions = {},
) {
    const maxSnapshots = options.maxSnapshots ?? 30;
    const debounceMs = options.debounceMs ?? 300;

    const past: ShallowRef<DocumentSchema[]> = shallowRef([]);
    const future: ShallowRef<DocumentSchema[]> = shallowRef([]);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let pendingBaseSnapshot: DocumentSchema | null = null;

    function cloneDoc(doc: DocumentSchema): DocumentSchema {
        if (typeof structuredClone === 'function') {
            try {
                return structuredClone(doc);
            } catch {
                return JSON.parse(JSON.stringify(doc));
            }
        }
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
            triggerRef(past);
            triggerRef(future);
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
        triggerRef(past);
        triggerRef(future);
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
        const previousDoc = past.value[past.value.length - 1];
        const applyResult = applyDocument(previousDoc);
        if (
            applyResult === false ||
            (typeof applyResult === 'object' && !applyResult.success)
        ) {
            return false;
        }

        past.value.pop();
        future.value.push(currentDoc);
        triggerRef(past);
        triggerRef(future);
        options.onUndo?.();

        return true;
    }

    function redo(): boolean {
        flushPending();
        if (future.value.length === 0) return false;

        const currentDoc = cloneDoc(getCurrentDocument());
        const nextDoc = future.value[future.value.length - 1];
        const applyResult = applyDocument(nextDoc);
        if (
            applyResult === false ||
            (typeof applyResult === 'object' && !applyResult.success)
        ) {
            return false;
        }

        future.value.pop();
        past.value.push(currentDoc);
        triggerRef(past);
        triggerRef(future);
        options.onRedo?.();

        return true;
    }

    function clearHistory(): void {
        flushPending();
        past.value = [];
        future.value = [];
        triggerRef(past);
        triggerRef(future);
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
