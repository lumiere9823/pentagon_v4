import { describe, expect, it } from 'vitest';
import { useDocumentState } from '@/composables/useDocumentState';
import { useEditorHistory } from '@/composables/useEditorHistory';
import type { DocumentSchema } from '@/types/document';

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

    it('atomically restores responsive styles during undo and redo', () => {
        const { document, insertNode, updateNodeStyle, setDocument } = useDocumentState();

        const history = useEditorHistory(
            () => document.value,
            (restored) => setDocument(restored),
            { maxSnapshots: 30, debounceMs: 50 },
        );

        const containerId = document.value.root.children![0].id;
        insertNode('Heading', containerId, 'inside', { text: 'Title', align: 'left' });
        const headingNode = document.value.root.children![0].children![0];

        // Snapshot before responsive style change
        const snapBefore = JSON.parse(JSON.stringify(document.value));
        updateNodeStyle(headingNode.id, 'mobile', 'align', 'center');
        history.pushSnapshot(snapBefore);

        expect(headingNode.styles?.mobile?.align).toBe('center');

        // Undo -> restores no styles
        history.undo();
        const restoredNode = document.value.root.children![0].children![0];
        expect(restoredNode.styles).toBeUndefined();

        // Redo -> restores mobile style override
        history.redo();
        const redoNode = document.value.root.children![0].children![0];
        expect(redoNode.styles?.mobile?.align).toBe('center');
    });

    it('leaves document and history stacks unchanged when a snapshot is rejected', () => {
        const { document, setDocument } = useDocumentState();
        const history = useEditorHistory(
            () => document.value,
            (restored) => setDocument(restored),
        );
        const invalidSnapshot: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'duplicate_id',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [
                    {
                        id: 'duplicate_id',
                        type: 'Divider',
                        props: { style: 'solid', color: 'muted' },
                    },
                ],
            },
        };
        history.past.value.push(invalidSnapshot);
        const before = JSON.stringify(document.value);

        expect(history.undo()).toBe(false);
        expect(JSON.stringify(document.value)).toBe(before);
        expect(history.past.value).toEqual([invalidSnapshot]);
        expect(history.future.value).toEqual([]);
    });

    it('undoes and redoes a valid reparent without changing the moved node ID', () => {
        const { document, insertNode, moveNode, findNode, setDocument } =
            useDocumentState();
        const history = useEditorHistory(
            () => document.value,
            (restored) => setDocument(restored),
        );
        const sourceParentId = document.value.root.children![0].id;
        insertNode('Container', document.value.root.id, 'inside');
        const targetParentId = document.value.root.children![1].id;
        insertNode('Heading', sourceParentId, 'inside');
        const movedNodeId = document.value.root.children![0].children![0].id;
        const beforeMove = JSON.parse(JSON.stringify(document.value));

        expect(moveNode(movedNodeId, targetParentId, 0).success).toBe(true);
        history.pushSnapshot(beforeMove);
        expect(findNode(movedNodeId)?.parent?.id).toBe(targetParentId);

        expect(history.undo()).toBe(true);
        expect(findNode(movedNodeId)?.parent?.id).toBe(sourceParentId);

        expect(history.redo()).toBe(true);
        expect(findNode(movedNodeId)?.parent?.id).toBe(targetParentId);
    });
});

