import { describe, expect, it } from 'vitest';
import {
    createDefaultDocument,
    createNodeId,
    useDocumentState,
} from '@/composables/useDocumentState';
import { DOCUMENT_LIMITS, type DocumentSchema } from '@/types/document';

describe('useDocumentState', () => {
    it('creates a valid default document conforming to Schema v1', () => {
        const doc = createDefaultDocument();

        expect(doc.schemaVersion).toBe(1);
        expect(doc.root).toBeDefined();
        expect(doc.root.id).toBe('root_section_default');
        expect(doc.root.type).toBe('Section');
        expect(doc.root.children).toBeDefined();
        expect(doc.root.children!.length).toBeGreaterThanOrEqual(1);
        expect(doc.root.children![0].type).toBe('Container');
    });

    it('generates valid node IDs conforming to NODE_ID_REGEX', () => {
        for (let i = 0; i < 20; i++) {
            const id = createNodeId('Heading');
            expect(id).toMatch(DOCUMENT_LIMITS.NODE_ID_REGEX);
            expect(id.length).toBeGreaterThanOrEqual(3);
            expect(id.length).toBeLessThanOrEqual(64);
        }
    });

    it('inserts a component inside a container and selects it', () => {
        const { document, selectedNodeId, insertNode, findNode } = useDocumentState();

        const containerId = document.value.root.children![0].id;
        const res = insertNode('Heading', containerId, 'inside');

        expect(res.success).toBe(true);
        expect(selectedNodeId.value).toBeDefined();

        const inserted = findNode(selectedNodeId.value!);
        expect(inserted).not.toBeNull();
        expect(inserted?.node.type).toBe('Heading');
        expect(inserted?.node.props.text).toBe('New Heading');
        expect(inserted?.parent?.id).toBe(containerId);
    });

    it('rejects inserting inside a leaf component', () => {
        const { document, insertNode } = useDocumentState();

        const containerId = document.value.root.children![0].id;
        const res1 = insertNode('Button', containerId, 'inside');
        expect(res1.success).toBe(true);

        const buttonId = document.value.root.children![0].children![0].id;
        const res2 = insertNode('Heading', buttonId, 'inside');

        expect(res2.success).toBe(false);
        expect(res2.error).toContain('leaf component');
    });

    it('cannot delete or move the root node', () => {
        const { document, deleteNode, moveNodeRelative } = useDocumentState();

        const delRes = deleteNode(document.value.root.id);
        expect(delRes.success).toBe(false);
        expect(delRes.error).toContain('root node cannot be deleted');

        const moveRes = moveNodeRelative(document.value.root.id, 'up');
        expect(moveRes.success).toBe(false);
        expect(moveRes.error).toContain('root node cannot be moved');
    });

    it('deletes a non-root node correctly', () => {
        const { document, insertNode, deleteNode, findNode } = useDocumentState();

        const containerId = document.value.root.children![0].id;
        insertNode('Button', containerId, 'inside');
        const buttonNode = document.value.root.children![0].children![0];

        expect(findNode(buttonNode.id)).not.toBeNull();

        const delRes = deleteNode(buttonNode.id);
        expect(delRes.success).toBe(true);
        expect(findNode(buttonNode.id)).toBeNull();
    });

    it('duplicates a node and all subnodes with unique new IDs', () => {
        const { document, insertNode, duplicateNode, findNode } = useDocumentState();

        const containerId = document.value.root.children![0].id;
        insertNode('Hero', containerId, 'inside');
        const heroNode = document.value.root.children![0].children![0];

        insertNode('Heading', heroNode.id, 'inside');
        const headingNode = heroNode.children![0];

        const dupRes = duplicateNode(heroNode.id);
        expect(dupRes.success).toBe(true);

        const container = findNode(containerId)!.node;
        expect(container.children!.length).toBe(2);

        const clonedHero = container.children![1];
        expect(clonedHero.id).not.toBe(heroNode.id);
        expect(clonedHero.type).toBe('Hero');
        expect(clonedHero.children!.length).toBe(1);

        const clonedHeading = clonedHero.children![0];
        expect(clonedHeading.id).not.toBe(headingNode.id);
        expect(clonedHeading.type).toBe('Heading');
    });

    it('prevents cycle when moving a container into itself or its descendants', () => {
        const { document, insertNode, moveNode } = useDocumentState();

        const containerId = document.value.root.children![0].id;
        insertNode('Columns', containerId, 'inside');
        const columnsNode = document.value.root.children![0].children![0];

        insertNode('CardGrid', columnsNode.id, 'inside');
        const cardGridNode = columnsNode.children![0];

        // Attempt to move columns into cardGrid (its own descendant)
        const moveRes = moveNode(columnsNode.id, cardGridNode.id, 0);
        expect(moveRes.success).toBe(false);
        expect(moveRes.error).toContain('descendants');
    });

    it('updates node props cleanly', () => {
        const { document, insertNode, updateNodeProps, findNode } = useDocumentState();

        const containerId = document.value.root.children![0].id;
        insertNode('Heading', containerId, 'inside');
        const headingNode = document.value.root.children![0].children![0];

        const updateRes = updateNodeProps(headingNode.id, {
            text: 'Updated Custom Title',
            level: 1,
            align: 'center',
        });

        expect(updateRes.success).toBe(true);
        const updated = findNode(headingNode.id)!.node;
        expect(updated.props.text).toBe('Updated Custom Title');
        expect(updated.props.level).toBe(1);
        expect(updated.props.align).toBe('center');
    });

    it('enforces maximum tree depth and total node budget', () => {
        const { document, insertNode, getMaxDepth, countNodes } = useDocumentState();

        expect(getMaxDepth()).toBe(2);
        expect(countNodes()).toBe(2);
    });

    it('F-05: inserts a component at specified targetIndex (beginning, middle, end)', () => {
        const { document, insertNode, findNode } = useDocumentState();
        const containerId = document.value.root.children![0].id;

        // Insert first node: Button A
        insertNode('Button', containerId, 'inside', { label: 'Button A' });
        // Insert second node: Button B (appended)
        insertNode('Button', containerId, 'inside', { label: 'Button B' });

        const container = findNode(containerId)!.node;
        expect(container.children!.length).toBe(2);
        expect(container.children![0].props.label).toBe('Button A');
        expect(container.children![1].props.label).toBe('Button B');

        // Insert at beginning (index 0): Heading
        const resStart = insertNode('Heading', containerId, 'inside', { text: 'Start' }, 0);
        expect(resStart.success).toBe(true);
        expect(container.children![0].props.text).toBe('Start');
        expect(container.children![1].props.label).toBe('Button A');
        expect(container.children![2].props.label).toBe('Button B');

        // Insert in middle (index 2): Divider
        const resMid = insertNode('Divider', containerId, 'inside', {}, 2);
        expect(resMid.success).toBe(true);
        expect(container.children![1].props.label).toBe('Button A');
        expect(container.children![2].type).toBe('Divider');
        expect(container.children![3].props.label).toBe('Button B');

        // Insert at end (index 4 or greater): Spacer
        const resEnd = insertNode('Spacer', containerId, 'inside', {}, 99);
        expect(resEnd.success).toBe(true);
        expect(container.children![4].type).toBe('Spacer');
    });

    it('F-06: clears selection when deleting a container whose descendant is currently selected', () => {
        const { document, insertNode, deleteNode, selectNode, selectedNodeId } = useDocumentState();
        const containerId = document.value.root.children![0].id;

        // Insert Columns container inside default container
        insertNode('Columns', containerId, 'inside');
        const columnsNode = document.value.root.children![0].children![0];

        // Insert Button inside Columns
        insertNode('Button', columnsNode.id, 'inside');
        const buttonNode = columnsNode.children![0];

        // Select the deeply nested Button
        selectNode(buttonNode.id);
        expect(selectedNodeId.value).toBe(buttonNode.id);

        // Delete the parent Columns container
        const delRes = deleteNode(columnsNode.id);
        expect(delRes.success).toBe(true);

        // Selection MUST be cleared because selected node was inside the deleted subtree
        expect(selectedNodeId.value).toBeNull();
    });

    it('updates and resets responsive styles with pruning', () => {
        const { document, insertNode, updateNodeStyle, resetNodeStyle, findNode } = useDocumentState();
        const containerId = document.value.root.children![0].id;

        insertNode('Heading', containerId, 'inside', { text: 'Title', align: 'left' });
        const headingNode = document.value.root.children![0].children![0];

        // 1. Update style on tablet
        updateNodeStyle(headingNode.id, 'tablet', 'align', 'center');
        expect(headingNode.styles?.tablet?.align).toBe('center');
        expect(headingNode.props.align).toBe('left'); // Base props untouched

        // 2. Update style on mobile
        updateNodeStyle(headingNode.id, 'mobile', 'align', 'right');
        expect(headingNode.styles?.mobile?.align).toBe('right');

        // 3. Reset mobile override
        resetNodeStyle(headingNode.id, 'mobile', 'align');
        expect(headingNode.styles?.mobile).toBeUndefined(); // Pruned mobile dictionary
        expect(headingNode.styles?.tablet?.align).toBe('center');

        // 4. Reset tablet override
        resetNodeStyle(headingNode.id, 'tablet', 'align');
        expect(headingNode.styles).toBeUndefined(); // Entire styles object pruned
    });

    it('preserves styles when duplicating a node subtree', () => {
        const { document, insertNode, updateNodeStyle, duplicateNode } = useDocumentState();
        const containerId = document.value.root.children![0].id;

        insertNode('Button', containerId, 'inside', { label: 'Click me' });
        const buttonNode = document.value.root.children![0].children![0];
        updateNodeStyle(buttonNode.id, 'mobile', 'size', 'lg');

        const dupRes = duplicateNode(buttonNode.id);
        expect(dupRes.success).toBe(true);

        const container = document.value.root.children![0];
        expect(container.children!.length).toBe(2);

        const clonedButton = container.children![1];
        expect(clonedButton.id).not.toBe(buttonNode.id);
        expect(clonedButton.styles?.mobile?.size).toBe('lg');
    });
});

