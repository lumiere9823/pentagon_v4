import { describe, expect, it } from 'vitest';
import { useDocumentState } from '@/composables/useDocumentState';
import { useEditorHistory } from '@/composables/useEditorHistory';

describe('useEditorHistory', () => {
    it('pushes snapshots, undos, and redos correctly', () => {
        const { document, insertNode, setDocument } = useDocumentState();

        const history = useEditorHistory(
            () => document.value,
            (restored) => setDocument(restored),
            { maxSnapshots: 30, debounceMs: 50 }
        );

        expect(history.canUndo.value).toBe(false);
        expect(history.canRedo.value).toBe(false);

        // Snapshot 1
        const doc0 = JSON.parse(JSON.stringify(document.value));
        const containerId = document.value.root.children![0].id;
        insertNode('Heading', containerId, 'inside');
        history.pushSnapshot(doc0);

        expect(history.canUndo.value).toBe(true);
        expect(history.canRedo.value).toBe(false);

        // Snapshot 2
        const doc1 = JSON.parse(JSON.stringify(document.value));
        insertNode('Button', containerId, 'inside');
        history.pushSnapshot(doc1);

        expect(history.past.value.length).toBe(2);

        // Undo Snapshot 2 -> returns to Heading only
        const undo1 = history.undo();
        expect(undo1).toBe(true);
        expect(history.canRedo.value).toBe(true);
        expect(document.value.root.children![0].children!.length).toBe(1);
        expect(document.value.root.children![0].children![0].type).toBe('Heading');

        // Undo Snapshot 1 -> returns to empty container
        const undo2 = history.undo();
        expect(undo2).toBe(true);
        expect(document.value.root.children![0].children!.length).toBe(0);

        // Redo -> Heading appears
        const redo1 = history.redo();
        expect(redo1).toBe(true);
        expect(document.value.root.children![0].children!.length).toBe(1);

        // Redo -> Button appears
        const redo2 = history.redo();
        expect(redo2).toBe(true);
        expect(document.value.root.children![0].children!.length).toBe(2);
    });

    it('clears redo stack when a new mutation is pushed after undo', () => {
        const { document, insertNode, setDocument } = useDocumentState();

        const history = useEditorHistory(
            () => document.value,
            (restored) => setDocument(restored),
            { maxSnapshots: 30, debounceMs: 50 }
        );

        const doc0 = JSON.parse(JSON.stringify(document.value));
        const containerId = document.value.root.children![0].id;
        insertNode('Heading', containerId, 'inside');
        history.pushSnapshot(doc0);

        expect(history.canUndo.value).toBe(true);

        history.undo();
        expect(history.canRedo.value).toBe(true);

        // Now push a new snapshot
        const docCurrent = JSON.parse(JSON.stringify(document.value));
        insertNode('Spacer', containerId, 'inside');
        history.pushSnapshot(docCurrent);

        // Redo must be cleared
        expect(history.canRedo.value).toBe(false);
    });

    it('caps maximum snapshot history at maxSnapshots', () => {
        const { document, insertNode, setDocument } = useDocumentState();

        const history = useEditorHistory(
            () => document.value,
            (restored) => setDocument(restored),
            { maxSnapshots: 5, debounceMs: 50 }
        );

        const containerId = document.value.root.children![0].id;

        for (let i = 0; i < 10; i++) {
            const snap = JSON.parse(JSON.stringify(document.value));
            insertNode('Divider', containerId, 'inside');
            history.pushSnapshot(snap);
        }

        expect(history.past.value.length).toBeLessThanOrEqual(5);
    });
});
