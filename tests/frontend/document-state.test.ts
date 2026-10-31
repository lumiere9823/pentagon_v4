import { describe, expect, it, vi } from 'vitest';
import {
    createDefaultDocument,
    createNodeId,
    useDocumentState,
} from '@/composables/useDocumentState';
import {
    DOCUMENT_LIMITS,
    type DocumentNode,
    type DocumentSchema,
} from '@/types/document';

function containerNode(
    id: string,
    children: DocumentNode[] = [],
): DocumentNode {
    return {
        id,
        type: 'Container',
        props: { maxWidth: 'lg', centered: true },
        children,
    };
}

function sectionNode(
    id: string,
    children: DocumentNode[] = [],
): DocumentNode {
    return {
        id,
        type: 'Section',
        props: { padding: 'medium', background: 'transparent' },
        children,
    };
}

function dividerNode(id: string): DocumentNode {
    return {
        id,
        type: 'Divider',
        props: { style: 'solid', color: 'muted' },
    };
}

function headingNode(id: string, text = 'Title'): DocumentNode {
    return {
        id,
        type: 'Heading',
        props: { text, level: 2 },
    };
}

function collectIds(node: DocumentNode): string[] {
    return [node.id, ...(node.children ?? []).flatMap(collectIds)];
}

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

    it('keeps inserted node IDs unique when random and time entropy repeat', () => {
        const randomSpy = vi.spyOn(Math, 'random').mockReturnValue(0.25);
        const timeSpy = vi.spyOn(Date, 'now').mockReturnValue(1_700_000_000_000);

        try {
            const { document, insertNode } = useDocumentState();
            const containerId = document.value.root.children![0].id;

            expect(insertNode('Heading', containerId, 'inside').success).toBe(
                true,
            );
            expect(insertNode('Heading', containerId, 'inside').success).toBe(
                true,
            );

            const insertedIds = document.value.root.children![0].children!.map(
                (node) => node.id,
            );
            expect(new Set(insertedIds).size).toBe(insertedIds.length);
        } finally {
            randomSpy.mockRestore();
            timeSpy.mockRestore();
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

    it('rejects insertion beyond maximum depth without partially mutating the target', () => {
        let branch: DocumentNode = {
            id: 'depth_10',
            type: 'Container',
            props: { maxWidth: 'lg', centered: true },
        };
        for (let depth = 9; depth >= 2; depth--) {
            branch = containerNode(`depth_${depth}`, [branch]);
        }
        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [branch],
            },
        };
        const state = useDocumentState(initialDocument);
        const before = JSON.stringify(state.document.value);

        const result = state.insertNode('Divider', 'depth_10', 'inside');

        expect(result.success).toBe(false);
        expect(JSON.stringify(state.document.value)).toBe(before);
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

    it('keeps every cloned subtree ID unique when ID entropy repeats', () => {
        const randomSpy = vi.spyOn(Math, 'random').mockReturnValue(0.25);
        const timeSpy = vi.spyOn(Date, 'now').mockReturnValue(1_700_000_000_000);

        try {
            const initialDocument: DocumentSchema = {
                schemaVersion: 1,
                root: {
                    id: 'root_section',
                    type: 'Section',
                    props: { padding: 'medium', background: 'transparent' },
                    children: [
                        containerNode('source_container', [
                            containerNode('nested_container', [
                                dividerNode('nested_divider'),
                            ]),
                        ]),
                    ],
                },
            };
            const { document, duplicateNode } =
                useDocumentState(initialDocument);

            expect(duplicateNode('source_container').success).toBe(true);

            const allIds = collectIds(document.value.root);
            expect(new Set(allIds).size).toBe(allIds.length);
        } finally {
            randomSpy.mockRestore();
            timeSpy.mockRestore();
        }
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

    it('rejects moving a multi-level subtree beyond the maximum depth atomically', () => {
        const movingSubtree = containerNode('moving_root', [
            containerNode('moving_middle', [containerNode('moving_leaf')]),
        ]);
        const destination = containerNode('destination_depth_8');
        let destinationBranch = destination;

        for (let depth = 7; depth >= 2; depth--) {
            destinationBranch = containerNode(
                `destination_depth_${depth}`,
                [destinationBranch],
            );
        }

        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [movingSubtree, destinationBranch],
            },
        };
        const { document, moveNode, selectNode, selectedNodeId } =
            useDocumentState(initialDocument);
        selectNode('moving_middle');
        const before = JSON.stringify(document.value);

        const result = moveNode('moving_root', 'destination_depth_8', 0);

        expect(result.success).toBe(false);
        expect(result.error).toContain('maximum tree depth');
        expect(JSON.stringify(document.value)).toBe(before);
        expect(selectedNodeId.value).toBe('moving_middle');
    });

    it('allows a multi-level subtree at exactly the maximum depth and preserves IDs', () => {
        const movingSubtree = containerNode('moving_root', [
            dividerNode('moving_leaf'),
        ]);
        const destination = containerNode('destination_depth_8');
        let destinationBranch = destination;

        for (let depth = 7; depth >= 2; depth--) {
            destinationBranch = containerNode(
                `destination_depth_${depth}`,
                [destinationBranch],
            );
        }

        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [movingSubtree, destinationBranch],
            },
        };
        const originalIds = collectIds(movingSubtree);
        const { getMaxDepth, moveNode, findNode } =
            useDocumentState(initialDocument);

        const result = moveNode('moving_root', 'destination_depth_8', 0);

        expect(result.success).toBe(true);
        expect(getMaxDepth()).toBe(DOCUMENT_LIMITS.MAX_TREE_DEPTH);
        expect(findNode('moving_root')?.parent?.id).toBe(
            'destination_depth_8',
        );
        expect(collectIds(findNode('moving_root')!.node)).toEqual(originalIds);
    });

    it('rejects moving into a destination already at the direct-child limit', () => {
        const destination = containerNode(
            'full_destination',
            Array.from(
                { length: DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE },
                (_, index): DocumentNode => ({
                    id: `existing_child_${index}`,
                    type: 'Divider',
                    props: { style: 'solid', color: 'muted' },
                }),
            ),
        );
        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [
                    {
                        id: 'moving_heading',
                        type: 'Heading',
                        props: { text: 'Move me', level: 2, align: 'left' },
                    },
                    destination,
                ],
            },
        };
        const { document, moveNode, selectNode, selectedNodeId } =
            useDocumentState(initialDocument);
        selectNode('moving_heading');
        const before = JSON.stringify(document.value);

        const result = moveNode('moving_heading', 'full_destination', 50);

        expect(result.success).toBe(false);
        expect(result.error).toContain('maximum of 50 children');
        expect(JSON.stringify(document.value)).toBe(before);
        expect(selectedNodeId.value).toBe('moving_heading');
    });

    it('allows a destination with 49 children to reach exactly 50', () => {
        const destination = containerNode(
            'destination',
            Array.from({ length: 49 }, (_, index) =>
                dividerNode(`existing_child_${index}`),
            ),
        );
        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [dividerNode('moving_divider'), destination],
            },
        };
        const { moveNode, findNode } = useDocumentState(initialDocument);

        const result = moveNode('moving_divider', 'destination', 49);

        expect(result.success).toBe(true);
        expect(findNode('destination')!.node.children).toHaveLength(50);
    });

    it('allows reordering within a parent that already has 50 children', () => {
        const fullParent = containerNode(
            'full_parent',
            Array.from({ length: 50 }, (_, index) =>
                dividerNode(`existing_child_${index}`),
            ),
        );
        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [fullParent],
            },
        };
        const { moveNode, findNode } = useDocumentState(initialDocument);

        const result = moveNode('existing_child_0', 'full_parent', 49);

        expect(result.success).toBe(true);
        expect(findNode('full_parent')!.node.children).toHaveLength(50);
        expect(findNode('full_parent')!.node.children![49].id).toBe(
            'existing_child_0',
        );
    });

    it('rejects root, self, missing-destination, and leaf-parent moves atomically', () => {
        const nested = containerNode('nested_container', [
            dividerNode('nested_divider'),
        ]);
        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [nested, dividerNode('leaf_target')],
            },
        };
        const state = useDocumentState(initialDocument);
        state.selectNode('nested_divider');
        const before = JSON.stringify(state.document.value);

        const results = [
            state.moveNode('root_section', 'nested_container', 0),
            state.moveNode('nested_container', 'nested_container', 0),
            state.moveNode('nested_divider', 'missing_parent', 0),
            state.moveNode('nested_divider', 'leaf_target', 0),
        ];

        expect(results.every((result) => !result.success)).toBe(true);
        expect(JSON.stringify(state.document.value)).toBe(before);
        expect(state.selectedNodeId.value).toBe('nested_divider');
    });

    it('moves safely while the document is at the total node limit', () => {
        const rootChildren = Array.from({ length: 5 }, (_, parentIndex) =>
            containerNode(
                `parent_${parentIndex}`,
                Array.from(
                    { length: parentIndex === 0 ? 48 : 49 },
                    (_, childIndex) =>
                        dividerNode(`child_${parentIndex}_${childIndex}`),
                ),
            ),
        );
        const initialDocument: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: rootChildren,
            },
        };
        const state = useDocumentState(initialDocument);
        expect(state.countNodes()).toBe(DOCUMENT_LIMITS.MAX_TOTAL_NODES);

        const result = state.moveNode('child_0_0', 'parent_1', 49);

        expect(result.success).toBe(true);
        expect(state.countNodes()).toBe(DOCUMENT_LIMITS.MAX_TOTAL_NODES);
        expect(state.findNode('parent_1')!.node.children).toHaveLength(50);
    });

    it('rejects an invalid replacement document without changing document or selection', () => {
        const state = useDocumentState();
        const selectedId = state.document.value.root.children![0].id;
        state.selectNode(selectedId);
        const before = JSON.stringify(state.document.value);
        const invalidReplacement: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'duplicate_id',
                type: 'Section',
                props: { padding: 'medium', background: 'transparent' },
                children: [
                    {
                        id: 'duplicate_id',
                        type: 'Heading',
                        props: { text: 'Duplicate', level: 2, align: 'left' },
                    },
                ],
            },
        };

        const result = state.setDocument(invalidReplacement);

        expect(result.success).toBe(false);
        expect(result.error).toContain('unique');
        expect(JSON.stringify(state.document.value)).toBe(before);
        expect(state.selectedNodeId.value).toBe(selectedId);
    });

    it('rejects replacement documents that violate each structural boundary', () => {
        const tooDeepRoot = containerNode('depth_10');
        let tooDeepBranch = tooDeepRoot;
        for (let depth = 9; depth >= 1; depth--) {
            tooDeepBranch = containerNode(`depth_${depth}`, [tooDeepBranch]);
        }

        const tooManyNodes = Array.from({ length: 5 }, (_, parentIndex) =>
            containerNode(
                `large_parent_${parentIndex}`,
                Array.from({ length: 49 }, (_, childIndex) =>
                    dividerNode(`large_child_${parentIndex}_${childIndex}`),
                ),
            ),
        );

        const invalidDocuments: DocumentSchema[] = [
            {
                schemaVersion: 1,
                root: {
                    id: 'leaf_root',
                    type: 'Heading',
                    props: { text: 'Invalid root', level: 2, align: 'left' },
                },
            },
            {
                schemaVersion: 1,
                root: {
                    id: 'root_section',
                    type: 'Section',
                    props: { padding: 'medium', background: 'transparent' },
                    children: [
                        {
                            id: 'leaf_with_child',
                            type: 'Heading',
                            props: { text: 'Leaf', level: 2, align: 'left' },
                            children: [dividerNode('illegal_child')],
                        },
                    ],
                },
            },
            {
                schemaVersion: 1,
                root: {
                    id: 'root_section',
                    type: 'Section',
                    props: { padding: 'medium', background: 'transparent' },
                    children: [tooDeepBranch],
                },
            },
            {
                schemaVersion: 1,
                root: {
                    id: 'root_section',
                    type: 'Section',
                    props: { padding: 'medium', background: 'transparent' },
                    children: [
                        containerNode(
                            'too_many_children',
                            Array.from({ length: 51 }, (_, index) =>
                                dividerNode(`overflow_child_${index}`),
                            ),
                        ),
                    ],
                },
            },
            {
                schemaVersion: 1,
                root: {
                    id: 'root_section',
                    type: 'Section',
                    props: { padding: 'medium', background: 'transparent' },
                    children: tooManyNodes,
                },
            },
            {
                schemaVersion: 1,
                root: {
                    id: 'bad id',
                    type: 'Section',
                    props: { padding: 'medium', background: 'transparent' },
                },
            },
        ];

        for (const invalidDocument of invalidDocuments) {
            const state = useDocumentState();
            const before = JSON.stringify(state.document.value);
            const result = state.setDocument(invalidDocument);

            expect(result.success).toBe(false);
            expect(JSON.stringify(state.document.value)).toBe(before);
        }
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

    describe('Package 3D.1 Move Node Hardening & Invariants', () => {
        it('returns changed: false when moveNodeRelative is called at sibling boundaries (no-op)', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', [
                        dividerNode('child_0'),
                        dividerNode('child_1'),
                    ]),
                ]),
            };
            const { moveNodeRelative, findNode } = useDocumentState(initialDoc);

            // Move top child up (no-op)
            const upRes = moveNodeRelative('child_0', 'up');
            expect(upRes.success).toBe(true);
            expect(upRes.changed).toBe(false);
            expect(findNode('parent')!.node.children![0].id).toBe('child_0');

            // Move bottom child down (no-op)
            const downRes = moveNodeRelative('child_1', 'down');
            expect(downRes.success).toBe(true);
            expect(downRes.changed).toBe(false);
            expect(findNode('parent')!.node.children![1].id).toBe('child_1');

            // Valid swap
            const swapRes = moveNodeRelative('child_0', 'down');
            expect(swapRes.success).toBe(true);
            expect(swapRes.changed).toBe(true);
            expect(findNode('parent')!.node.children![0].id).toBe('child_1');
            expect(findNode('parent')!.node.children![1].id).toBe('child_0');
        });

        it('returns changed: false when moveNode moves to its existing position (no-op)', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', [
                        dividerNode('child_0'),
                        dividerNode('child_1'),
                        dividerNode('child_2'),
                    ]),
                ]),
            };
            const { moveNode, document } = useDocumentState(initialDoc);
            const before = JSON.stringify(document.value);

            // In post-removal coordinates, inserting child_1 at index 1 is its original spot
            const res = moveNode('child_1', 'parent', 1);
            expect(res.success).toBe(true);
            expect(res.changed).toBe(false);
            expect(JSON.stringify(document.value)).toBe(before);
        });

        it('moves a middle child downward and upward correctly in the same parent', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', [
                        dividerNode('child_0'),
                        dividerNode('child_1'),
                        dividerNode('child_2'),
                        dividerNode('child_3'),
                    ]),
                ]),
            };
            const { moveNode, findNode } = useDocumentState(initialDoc);

            // Move child_1 (at index 1) downward after child_2: post-removal index 2
            const downRes = moveNode('child_1', 'parent', 2);
            expect(downRes.success).toBe(true);
            expect(downRes.changed).toBe(true);
            const childrenAfterDown = findNode('parent')!.node.children!.map((c) => c.id);
            expect(childrenAfterDown).toEqual(['child_0', 'child_2', 'child_1', 'child_3']);

            // Move child_1 (now at index 2) upward to start: post-removal index 0
            const upRes = moveNode('child_1', 'parent', 0);
            expect(upRes.success).toBe(true);
            expect(upRes.changed).toBe(true);
            const childrenAfterUp = findNode('parent')!.node.children!.map((c) => c.id);
            expect(childrenAfterUp).toEqual(['child_1', 'child_0', 'child_2', 'child_3']);
        });

        it('moves the first child to the end and last child upward by one position', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', [
                        dividerNode('child_0'),
                        dividerNode('child_1'),
                        dividerNode('child_2'),
                    ]),
                ]),
            };
            const { moveNode, findNode } = useDocumentState(initialDoc);

            // Move child_0 to end: post-removal index 2
            const endRes = moveNode('child_0', 'parent', 2);
            expect(endRes.success).toBe(true);
            expect(endRes.changed).toBe(true);
            expect(findNode('parent')!.node.children!.map((c) => c.id)).toEqual([
                'child_1',
                'child_2',
                'child_0',
            ]);

            // Move child_0 (last child) upward by one: post-removal index 1
            const upRes = moveNode('child_0', 'parent', 1);
            expect(upRes.success).toBe(true);
            expect(upRes.changed).toBe(true);
            expect(findNode('parent')!.node.children!.map((c) => c.id)).toEqual([
                'child_1',
                'child_0',
                'child_2',
            ]);
        });

        it('preserves node IDs, subtree content, and responsive overrides across moves', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent_a', [
                        {
                            id: 'complex_child',
                            type: 'Heading',
                            props: { text: 'Title', level: 2 },
                            styles: {
                                tablet: { align: 'center' },
                                mobile: { align: 'right' },
                            },
                        },
                    ]),
                    containerNode('parent_b', []),
                ]),
            };
            const { moveNode, findNode } = useDocumentState(initialDoc);

            const res = moveNode('complex_child', 'parent_b', 0);
            expect(res.success).toBe(true);
            expect(res.changed).toBe(true);

            const moved = findNode('complex_child');
            expect(moved).not.toBeNull();
            expect(moved!.parent!.id).toBe('parent_b');
            expect(moved!.node.props.text).toBe('Title');
            expect(moved!.node.styles?.tablet?.align).toBe('center');
            expect(moved!.node.styles?.mobile?.align).toBe('right');
        });

        it('returns success: false and changed: false on stale source or target IDs', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', [dividerNode('child_0')]),
                ]),
            };
            const { moveNode } = useDocumentState(initialDoc);

            const staleSource = moveNode('non_existent_source', 'parent', 0);
            expect(staleSource.success).toBe(false);
            expect(staleSource.changed).toBe(false);
            expect(staleSource.error).toContain('Source node');

            const staleTarget = moveNode('child_0', 'non_existent_target', 0);
            expect(staleTarget.success).toBe(false);
            expect(staleTarget.changed).toBe(false);
            expect(staleTarget.error).toContain('Target container');
        });

        it('rejects moving into a leaf component with descriptive error', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', [
                        dividerNode('leaf_target'),
                        dividerNode('moving_node'),
                    ]),
                ]),
            };
            const { moveNode } = useDocumentState(initialDoc);

            const res = moveNode('moving_node', 'leaf_target', 0);
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toContain('cannot accept children because it is a leaf component');
        });

        it('rejects moving the root node', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', []),
                ]),
            };
            const { moveNode } = useDocumentState(initialDoc);

            const res = moveNode('root', 'parent', 0);
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toBe('The root node cannot be moved.');
        });

        it('rejects circular hierarchy moves (moving container into itself or descendant)', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('ancestor_container', [
                        containerNode('descendant_container', []),
                    ]),
                ]),
            };
            const { moveNode } = useDocumentState(initialDoc);

            // Move into descendant
            const cycleRes = moveNode('ancestor_container', 'descendant_container', 0);
            expect(cycleRes.success).toBe(false);
            expect(cycleRes.changed).toBe(false);
            expect(cycleRes.error).toContain('Cannot move a container into itself or its own descendants');

            // Move into itself
            const selfRes = moveNode('ancestor_container', 'ancestor_container', 0);
            expect(selfRes.success).toBe(false);
            expect(selfRes.changed).toBe(false);
            expect(selfRes.error).toContain('Cannot move a container into itself or its own descendants');
        });

        it('rejects moves that would exceed container children capacity', () => {
            const fullChildren = Array.from({ length: 50 }, (_, i) => dividerNode(`child_${i}`));
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('full_container', fullChildren),
                    containerNode('source_container', [dividerNode('extra_node')]),
                ]),
            };
            const { moveNode } = useDocumentState(initialDoc);

            const res = moveNode('extra_node', 'full_container', 0);
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toContain('target container already has the maximum of 50 children');
        });

        it('rejects moves that would exceed maximum tree depth (10)', () => {
            // Build a tree of depth 9
            let current = containerNode('depth_9', []);
            for (let d = 8; d >= 1; d--) {
                current = containerNode(`depth_${d}`, [current]);
            }
            // A moving subtree of height 2
            const movingSubtree = containerNode('moving_parent', [dividerNode('moving_leaf')]);

            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    current,
                    movingSubtree,
                ]),
            };
            const { moveNode } = useDocumentState(initialDoc);

            // Target 'depth_9' is at depth 10 in the document (root is depth 1, depth_1 is depth 2, ..., depth_9 is depth 10)
            // Moving a subtree of height 2 into it would produce depth 10 + 2 = 12 > 10
            const res = moveNode('moving_parent', 'depth_9', 0);
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toContain('would exceed maximum tree depth of 10');
        });
    });

    describe('Package 3E.1 Node Locking: Schema, Validation & Mutation Engine', () => {
        it('reports lock status correctly via isNodeLocked and isNodeOrAncestorLocked', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('locked_parent', [
                            dividerNode('unlocked_child'),
                        ]),
                        locked: true,
                    },
                    containerNode('unlocked_parent', [
                        {
                            ...dividerNode('directly_locked_child'),
                            locked: true,
                        },
                        dividerNode('free_child'),
                    ]),
                ]),
            };
            const { isNodeLocked, isNodeOrAncestorLocked, getProtectingAncestor } = useDocumentState(initialDoc);

            // Unlocked node in unlocked parent
            expect(isNodeLocked('free_child')).toBe(false);
            expect(isNodeOrAncestorLocked('free_child')).toBe(false);
            expect(getProtectingAncestor('free_child')).toBeNull();

            // Directly locked node in unlocked parent
            expect(isNodeLocked('directly_locked_child')).toBe(true);
            expect(isNodeOrAncestorLocked('directly_locked_child')).toBe(true);
            expect(getProtectingAncestor('directly_locked_child')).toBeNull();

            // Unlocked child in locked parent (inherited locking)
            expect(isNodeLocked('unlocked_child')).toBe(false);
            expect(isNodeOrAncestorLocked('unlocked_child')).toBe(true);
            expect(getProtectingAncestor('unlocked_child')?.id).toBe('locked_parent');

            // Directly locked parent
            expect(isNodeLocked('locked_parent')).toBe(true);
            expect(isNodeOrAncestorLocked('locked_parent')).toBe(true);
            expect(getProtectingAncestor('locked_parent')).toBeNull();

            // Root node (unlocked)
            expect(isNodeLocked('root')).toBe(false);
            expect(isNodeOrAncestorLocked('root')).toBe(false);
            expect(getProtectingAncestor('root')).toBeNull();
        });

        it('toggles lock state via setNodeLocked with accurate changed flags', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    dividerNode('node_1'),
                ]),
            };
            const { setNodeLocked, isNodeLocked, findNode } = useDocumentState(initialDoc);

            // Lock node
            const lockRes = setNodeLocked('node_1', true);
            expect(lockRes.success).toBe(true);
            expect(lockRes.changed).toBe(true);
            expect(isNodeLocked('node_1')).toBe(true);
            expect(findNode('node_1')?.node.locked).toBe(true);

            // No-op lock (already locked)
            const noopLock = setNodeLocked('node_1', true);
            expect(noopLock.success).toBe(true);
            expect(noopLock.changed).toBe(false);

            // Unlock node
            const unlockRes = setNodeLocked('node_1', false);
            expect(unlockRes.success).toBe(true);
            expect(unlockRes.changed).toBe(true);
            expect(isNodeLocked('node_1')).toBe(false);
            expect(findNode('node_1')?.node.locked).toBeUndefined();

            // No-op unlock (already unlocked)
            const noopUnlock = setNodeLocked('node_1', false);
            expect(noopUnlock.success).toBe(true);
            expect(noopUnlock.changed).toBe(false);

            // Non-existent node
            const missingRes = setNodeLocked('missing_node', true);
            expect(missingRes.success).toBe(false);
            expect(missingRes.changed).toBe(false);
            expect(missingRes.error).toContain('not found');
        });

        it('supports locking the root container and propagates lock to all descendants', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent', [
                        dividerNode('child'),
                    ]),
                ]),
            };
            const { setNodeLocked, isNodeLocked, isNodeOrAncestorLocked } = useDocumentState(initialDoc);

            expect(isNodeOrAncestorLocked('child')).toBe(false);

            // Lock root
            const res = setNodeLocked('root', true);
            expect(res.success).toBe(true);
            expect(res.changed).toBe(true);
            expect(isNodeLocked('root')).toBe(true);

            // All descendants are now protected by root lock
            expect(isNodeOrAncestorLocked('parent')).toBe(true);
            expect(isNodeOrAncestorLocked('child')).toBe(true);

            // Unlock root
            setNodeLocked('root', false);
            expect(isNodeLocked('root')).toBe(false);
            expect(isNodeOrAncestorLocked('child')).toBe(false);
        });

        it('allows toggling child lock state inside locked parent, maintaining child lock when parent unlocks', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('parent', [
                            dividerNode('child'),
                        ]),
                        locked: true,
                    },
                ]),
            };
            const { setNodeLocked, isNodeLocked, isNodeOrAncestorLocked } = useDocumentState(initialDoc);

            expect(isNodeOrAncestorLocked('child')).toBe(true);
            expect(isNodeLocked('child')).toBe(false);

            // Explicitly lock child while parent is locked
            const lockChildRes = setNodeLocked('child', true);
            expect(lockChildRes.success).toBe(true);
            expect(lockChildRes.changed).toBe(true);
            expect(isNodeLocked('child')).toBe(true);

            // Now unlock parent: child must remain individually locked!
            setNodeLocked('parent', false);
            expect(isNodeLocked('parent')).toBe(false);
            expect(isNodeLocked('child')).toBe(true);
            expect(isNodeOrAncestorLocked('child')).toBe(true);
        });

        it('rejects updateNodeProps, updateNodeStyle, and resetNodeStyle on locked nodes and descendants', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('locked_parent', [
                            {
                                id: 'heading_child',
                                type: 'Heading',
                                props: { text: 'Initial Text', level: 2 },
                                styles: { tablet: { align: 'center' } },
                            },
                        ]),
                        locked: true,
                    },
                ]),
            };
            const { updateNodeProps, updateNodeStyle, resetNodeStyle, findNode } = useDocumentState(initialDoc);

            // 1. Update Props
            const propRes = updateNodeProps('heading_child', { text: 'New Text' });
            expect(propRes.success).toBe(false);
            expect(propRes.error).toContain('locked');
            expect(findNode('heading_child')?.node.props.text).toBe('Initial Text');

            // 2. Update Style
            const styleRes = updateNodeStyle('heading_child', 'tablet', 'align', 'right');
            expect(styleRes.success).toBe(false);
            expect(styleRes.error).toContain('locked');
            expect(findNode('heading_child')?.node.styles?.tablet?.align).toBe('center');

            // 3. Reset Style
            const resetRes = resetNodeStyle('heading_child', 'tablet', 'align');
            expect(resetRes.success).toBe(false);
            expect(resetRes.error).toContain('locked');
            expect(findNode('heading_child')?.node.styles?.tablet?.align).toBe('center');
        });

        it('rejects deleteNode and duplicateNode on locked nodes and descendants of locked ancestors', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('locked_parent', [
                            dividerNode('child_under_locked_parent'),
                        ]),
                        locked: true,
                    },
                    containerNode('unlocked_parent', [
                        {
                            ...dividerNode('directly_locked_node'),
                            locked: true,
                        },
                    ]),
                ]),
            };
            const { deleteNode, duplicateNode, findNode } = useDocumentState(initialDoc);

            // Delete directly locked node
            const delDirect = deleteNode('directly_locked_node');
            expect(delDirect.success).toBe(false);
            expect(delDirect.error).toContain('locked');
            expect(findNode('directly_locked_node')).not.toBeNull();

            // Delete child under locked parent
            const delInherited = deleteNode('child_under_locked_parent');
            expect(delInherited.success).toBe(false);
            expect(delInherited.error).toContain('locked');
            expect(findNode('child_under_locked_parent')).not.toBeNull();

            // Delete unlocked container that contains a locked child
            const delContainerWithLockedChild = deleteNode('unlocked_parent');
            expect(delContainerWithLockedChild.success).toBe(false);
            expect(delContainerWithLockedChild.error).toContain('contains locked elements');
            expect(findNode('unlocked_parent')).not.toBeNull();

            // Duplicate directly locked node
            const dupDirect = duplicateNode('directly_locked_node');
            expect(dupDirect.success).toBe(false);
            expect(dupDirect.error).toContain('locked');

            // Duplicate child under locked parent
            const dupInherited = duplicateNode('child_under_locked_parent');
            expect(dupInherited.success).toBe(false);
            expect(dupInherited.error).toContain('locked');
        });

        it('rejects moveNodeRelative on locked nodes and descendants of locked ancestors', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('locked_parent', [
                            dividerNode('c_0'),
                            dividerNode('c_1'),
                        ]),
                        locked: true,
                    },
                ]),
            };
            const { moveNodeRelative, findNode } = useDocumentState(initialDoc);

            const res = moveNodeRelative('c_0', 'down');
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toContain('locked');
            expect(findNode('locked_parent')?.node.children?.[0].id).toBe('c_0');
        });

        it('rejects moveNode when source ancestry or destination ancestry is locked', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('locked_source_parent', [
                            dividerNode('moving_from_locked'),
                        ]),
                        locked: true,
                    },
                    {
                        ...containerNode('locked_dest_parent', []),
                        locked: true,
                    },
                    containerNode('unlocked_parent', [
                        dividerNode('free_moving_node'),
                    ]),
                ]),
            };
            const { moveNode, findNode } = useDocumentState(initialDoc);

            // Moving out of locked parent
            const moveOutRes = moveNode('moving_from_locked', 'unlocked_parent', 0);
            expect(moveOutRes.success).toBe(false);
            expect(moveOutRes.changed).toBe(false);
            expect(moveOutRes.error).toContain('locked');
            expect(findNode('moving_from_locked')?.parent?.id).toBe('locked_source_parent');

            // Moving into locked container
            const moveInRes = moveNode('free_moving_node', 'locked_dest_parent', 0);
            expect(moveInRes.success).toBe(false);
            expect(moveInRes.changed).toBe(false);
            expect(moveInRes.error).toContain('locked');
            expect(findNode('free_moving_node')?.parent?.id).toBe('unlocked_parent');
        });

        it('rejects insertNode into locked containers or locked root', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('locked_parent', [
                            dividerNode('child'),
                        ]),
                        locked: true,
                    },
                ]),
            };
            const { insertNode, setNodeLocked, countNodes } = useDocumentState(initialDoc);
            const initialCount = countNodes();

            // Insert inside locked container
            const insideRes = insertNode('Divider', 'locked_parent', 'inside');
            expect(insideRes.success).toBe(false);
            expect(insideRes.error).toContain('locked');
            expect(countNodes()).toBe(initialCount);

            // Insert sibling next to child in locked container
            const siblingRes = insertNode('Divider', 'child', 'after');
            expect(siblingRes.success).toBe(false);
            expect(siblingRes.error).toContain('locked');
            expect(countNodes()).toBe(initialCount);

            // Lock root and attempt default insert
            setNodeLocked('root', true);
            const rootRes = insertNode('Divider', null, 'inside');
            expect(rootRes.success).toBe(false);
            expect(rootRes.error).toContain('locked');
            expect(countNodes()).toBe(initialCount);
        });

        it('rejects replacement document with invalid locked type in setDocument', () => {
            const { setDocument } = useDocumentState();

            const invalidDoc: any = {
                schemaVersion: 1,
                root: {
                    id: 'root_sec',
                    type: 'Section',
                    props: {},
                    locked: 'true', // Invalid: string instead of boolean
                    children: [],
                },
            };

            const res = setDocument(invalidDoc);
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toContain("'locked' must be a boolean");
        });
    });

    describe('Package 3E.3 Layer Visibility & Inherited Visibility', () => {
        it('toggles visibility state via setNodeHidden with accurate changed flags', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [headingNode('node_1', 'Hello')]),
            };

            const { setNodeHidden, isNodeHidden, findNode, selectNode, selectedNodeId } =
                useDocumentState(initialDoc);

            selectNode('node_1');
            expect(selectedNodeId.value).toBe('node_1');

            // Hide node_1
            const hideRes = setNodeHidden('node_1', true);
            expect(hideRes.success).toBe(true);
            expect(hideRes.changed).toBe(true);
            expect(isNodeHidden('node_1')).toBe(true);
            expect(findNode('node_1')?.node.hidden).toBe(true);
            // Selection is preserved
            expect(selectedNodeId.value).toBe('node_1');

            // No-op hide
            const noopHide = setNodeHidden('node_1', true);
            expect(noopHide.success).toBe(true);
            expect(noopHide.changed).toBe(false);

            // Unhide node_1
            const unhideRes = setNodeHidden('node_1', false);
            expect(unhideRes.success).toBe(true);
            expect(unhideRes.changed).toBe(true);
            expect(isNodeHidden('node_1')).toBe(false);
            expect(findNode('node_1')?.node.hidden).toBeUndefined();

            // No-op unhide
            const noopUnhide = setNodeHidden('node_1', false);
            expect(noopUnhide.success).toBe(true);
            expect(noopUnhide.changed).toBe(false);

            // Missing node
            const missingRes = setNodeHidden('missing_node', true);
            expect(missingRes.success).toBe(false);
            expect(missingRes.changed).toBe(false);
            expect(missingRes.error).toContain("Node 'missing_node' not found");
        });

        it('strictly prevents root node from being hidden', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', []),
            };

            const { setNodeHidden, isNodeHidden } = useDocumentState(initialDoc);

            const res = setNodeHidden('root', true);
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toBe('The root node cannot be hidden.');
            expect(isNodeHidden('root')).toBe(false);
        });

        it('rejects setNodeHidden if target node or ancestor is locked', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('locked_parent', [
                            headingNode('child_heading', 'Inside Locked Parent'),
                        ]),
                        locked: true,
                    },
                    {
                        ...headingNode('locked_direct', 'Direct Locked'),
                        locked: true,
                    },
                ]),
            };

            const { setNodeHidden, isNodeHidden } = useDocumentState(initialDoc);

            // Direct locked node cannot be hidden
            const directRes = setNodeHidden('locked_direct', true);
            expect(directRes.success).toBe(false);
            expect(directRes.changed).toBe(false);
            expect(directRes.error).toContain(
                'Cannot change visibility of locked node or node inside a locked container.',
            );
            expect(isNodeHidden('locked_direct')).toBe(false);

            // Child of locked parent cannot be hidden
            const childRes = setNodeHidden('child_heading', true);
            expect(childRes.success).toBe(false);
            expect(childRes.changed).toBe(false);
            expect(childRes.error).toContain(
                'Cannot change visibility of locked node or node inside a locked container.',
            );
            expect(isNodeHidden('child_heading')).toBe(false);
        });

        it('computes inherited visibility and hiding ancestors without mutating descendants', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    containerNode('parent_container', [
                        headingNode('child_heading', 'Deep Heading'),
                    ]),
                ]),
            };

            const {
                setNodeHidden,
                isNodeHidden,
                isNodeOrAncestorHidden,
                getHidingAncestor,
                findNode,
            } = useDocumentState(initialDoc);

            expect(isNodeHidden('parent_container')).toBe(false);
            expect(isNodeHidden('child_heading')).toBe(false);
            expect(isNodeOrAncestorHidden('child_heading')).toBe(false);
            expect(getHidingAncestor('child_heading')).toBeNull();

            // Hide parent container
            setNodeHidden('parent_container', true);

            expect(isNodeHidden('parent_container')).toBe(true);
            // Child heading is NOT directly hidden:
            expect(isNodeHidden('child_heading')).toBe(false);
            expect(findNode('child_heading')?.node.hidden).toBeUndefined();
            // But child heading IS inherited hidden:
            expect(isNodeOrAncestorHidden('child_heading')).toBe(true);
            expect(getHidingAncestor('child_heading')?.id).toBe('parent_container');

            // Unhide parent container restores child without changing its state
            setNodeHidden('parent_container', false);
            expect(isNodeHidden('parent_container')).toBe(false);
            expect(isNodeOrAncestorHidden('child_heading')).toBe(false);
            expect(getHidingAncestor('child_heading')).toBeNull();
        });

        it('preserves hidden flag when duplicating a hidden node', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...headingNode('heading_hidden', 'Hidden Original'),
                        hidden: true,
                    },
                ]),
            };

            const { duplicateNode, findNode, selectedNodeId } =
                useDocumentState(initialDoc);

            const res = duplicateNode('heading_hidden');
            expect(res.success).toBe(true);
            expect(selectedNodeId.value).toBeTruthy();

            const duplicated = findNode(selectedNodeId.value!);
            expect(duplicated?.node.hidden).toBe(true);
        });

        it('rejects setDocument with invalid hidden type or hidden root', () => {
            const { setDocument } = useDocumentState();

            // Invalid hidden type
            const invalidTypeDoc: any = {
                schemaVersion: 1,
                root: {
                    id: 'root_sec',
                    type: 'Section',
                    props: {},
                    children: [
                        {
                            id: 'child_bad',
                            type: 'Heading',
                            props: {},
                            hidden: 'not_a_boolean',
                        },
                    ],
                },
            };

            const typeRes = setDocument(invalidTypeDoc);
            expect(typeRes.success).toBe(false);
            expect(typeRes.error).toContain("'hidden' must be a boolean");

            // Hidden root
            const hiddenRootDoc: any = {
                schemaVersion: 1,
                root: {
                    id: 'root_sec',
                    type: 'Section',
                    props: {},
                    hidden: true,
                    children: [],
                },
            };

            const rootRes = setDocument(hiddenRootDoc);
            expect(rootRes.success).toBe(false);
            expect(rootRes.error).toContain('the root node cannot be hidden');
        });

        it('unlocking a container does not mutate descendants own lock or visibility values', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('container_parent', [
                            {
                                ...headingNode(
                                    'child_locked_and_hidden',
                                    'Locked and Hidden',
                                ),
                                locked: true,
                                hidden: true,
                            },
                            {
                                ...headingNode(
                                    'child_hidden_only',
                                    'Hidden Only',
                                ),
                                hidden: true,
                            },
                            headingNode('child_default', 'Default Child'),
                        ]),
                    },
                ]),
            };

            const {
                setNodeLocked,
                findNode,
                isNodeLocked,
                isNodeOrAncestorLocked,
                isNodeHidden,
            } = useDocumentState(initialDoc);

            // 1. Lock the parent container
            const lockParentRes = setNodeLocked('container_parent', true);
            expect(lockParentRes.success).toBe(true);
            expect(lockParentRes.changed).toBe(true);

            expect(isNodeOrAncestorLocked('child_locked_and_hidden')).toBe(true);
            expect(isNodeOrAncestorLocked('child_hidden_only')).toBe(true);
            expect(isNodeOrAncestorLocked('child_default')).toBe(true);

            // 2. Unlock the parent container
            const unlockParentRes = setNodeLocked('container_parent', false);
            expect(unlockParentRes.success).toBe(true);
            expect(unlockParentRes.changed).toBe(true);

            // Verify child_locked_and_hidden retained its own individual locked: true and hidden: true
            const child1 = findNode('child_locked_and_hidden')?.node;
            expect(child1?.locked).toBe(true);
            expect(child1?.hidden).toBe(true);
            expect(isNodeLocked('child_locked_and_hidden')).toBe(true);
            expect(isNodeHidden('child_locked_and_hidden')).toBe(true);

            // Verify child_hidden_only retained hidden: true and did NOT gain locked: true
            const child2 = findNode('child_hidden_only')?.node;
            expect(child2?.locked).toBeUndefined();
            expect(child2?.hidden).toBe(true);
            expect(isNodeLocked('child_hidden_only')).toBe(false);
            expect(isNodeHidden('child_hidden_only')).toBe(true);

            // Verify child_default retained default unlocked and visible state
            const child3 = findNode('child_default')?.node;
            expect(child3?.locked).toBeUndefined();
            expect(child3?.hidden).toBeUndefined();
            expect(isNodeLocked('child_default')).toBe(false);
            expect(isNodeHidden('child_default')).toBe(false);
        });

        it('visibility changes do not change lock state, and lock changes do not change visibility state', () => {
            const initialDoc: DocumentSchema = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    headingNode('toggle_target', 'Toggle Target'),
                ]),
            };

            const { setNodeHidden, setNodeLocked, findNode } =
                useDocumentState(initialDoc);

            // Initially both undefined
            expect(findNode('toggle_target')?.node.locked).toBeUndefined();
            expect(findNode('toggle_target')?.node.hidden).toBeUndefined();

            // Set hidden to true
            setNodeHidden('toggle_target', true);
            expect(findNode('toggle_target')?.node.hidden).toBe(true);
            expect(findNode('toggle_target')?.node.locked).toBeUndefined();

            // Directly lock the node while hidden
            setNodeLocked('toggle_target', true);
            expect(findNode('toggle_target')?.node.locked).toBe(true);
            expect(findNode('toggle_target')?.node.hidden).toBe(true);

            // Unlock the node
            setNodeLocked('toggle_target', false);
            expect(findNode('toggle_target')?.node.locked).toBeUndefined();
            expect(findNode('toggle_target')?.node.hidden).toBe(true);

            // Set hidden back to false (unhide)
            setNodeHidden('toggle_target', false);
            expect(findNode('toggle_target')?.node.hidden).toBeUndefined();
            expect(findNode('toggle_target')?.node.locked).toBeUndefined();
        });
    });

    describe('Package 3F: Node Custom Labeling', () => {
        it('sets trimmed label and unsets on empty string or null', () => {
            const doc: DocumentTree = {
                schemaVersion: 1,
                root: {
                    id: 'root',
                    type: 'container',
                    children: [
                        {
                            id: 'child_1',
                            type: 'heading',
                            props: { text: 'Title' },
                        },
                    ],
                },
            };

            const { setNodeLabel, findNode } = useDocumentState(doc);

            // Set label with whitespace padding
            const res1 = setNodeLabel('child_1', '  Hero Headline  ');
            expect(res1).toEqual({ success: true, changed: true });
            expect(findNode('child_1')?.node.label).toBe('Hero Headline');

            // No-op with same trimmed content
            const resNoop = setNodeLabel('child_1', 'Hero Headline');
            expect(resNoop).toEqual({ success: true, changed: false });

            // Clear label with empty string
            const resClear = setNodeLabel('child_1', '   ');
            expect(resClear).toEqual({ success: true, changed: true });
            expect(findNode('child_1')?.node.label).toBeUndefined();

            // Clear again is no-op
            const resClearNoop = setNodeLabel('child_1', null);
            expect(resClearNoop).toEqual({ success: true, changed: false });
        });

        it('truncates labels to DOCUMENT_LIMITS.MAX_LABEL_LENGTH (64 chars)', () => {
            const doc: DocumentTree = {
                schemaVersion: 1,
                root: {
                    id: 'root',
                    type: 'container',
                    children: [
                        {
                            id: 'child_1',
                            type: 'paragraph',
                            props: {},
                        },
                    ],
                },
            };

            const { setNodeLabel, findNode } = useDocumentState(doc);
            const longLabel = 'A'.repeat(80);
            const res = setNodeLabel('child_1', longLabel);

            expect(res).toEqual({ success: true, changed: true });
            expect(findNode('child_1')?.node.label).toBe('A'.repeat(64));
            expect(findNode('child_1')?.node.label?.length).toBe(64);
        });

        it('fails closed when attempting to label directly locked nodes or descendants of locked containers', () => {
            const doc: DocumentTree = {
                schemaVersion: 1,
                root: {
                    id: 'root',
                    type: 'container',
                    props: {},
                    children: [
                        {
                            id: 'locked_parent',
                            type: 'container',
                            props: {},
                            locked: true,
                            children: [
                                {
                                    id: 'child_in_locked_parent',
                                    type: 'heading',
                                    props: {},
                                },
                            ],
                        },
                        {
                            id: 'directly_locked_child',
                            type: 'heading',
                            locked: true,
                            props: {},
                        },
                    ],
                },
            };

            const { setNodeLabel, findNode } = useDocumentState(doc);

            // Attempt on directly locked child
            const resDirect = setNodeLabel(
                'directly_locked_child',
                'New Label',
            );
            expect(resDirect.success).toBe(false);
            expect(resDirect.changed).toBe(false);
            expect(resDirect.error).toBe(
                'Cannot rename locked node or node inside a locked container.',
            );
            expect(findNode('directly_locked_child')?.node.label).toBeUndefined();

            // Attempt on descendant of locked parent
            const resDescendant = setNodeLabel(
                'child_in_locked_parent',
                'New Label',
            );
            expect(resDescendant.success).toBe(false);
            expect(resDescendant.changed).toBe(false);
            expect(
                findNode('child_in_locked_parent')?.node.label,
            ).toBeUndefined();

            // Attempt on locked parent itself
            const resParent = setNodeLabel('locked_parent', 'New Label');
            expect(resParent.success).toBe(false);
            expect(resParent.changed).toBe(false);
            expect(findNode('locked_parent')?.node.label).toBeUndefined();
        });

        it('preserves custom label when cloning / duplicating a node', () => {
            const doc: DocumentTree = {
                schemaVersion: 1,
                root: sectionNode('root', [
                    {
                        ...containerNode('card_1', [
                            {
                                ...headingNode('btn_1', 'Buy'),
                                label: 'Buy Now Button',
                            },
                        ]),
                        label: 'Featured Product Card',
                    },
                ]),
            };

            const { duplicateNode, findNode, selectedNodeId } =
                useDocumentState(doc);
            const dupRes = duplicateNode('card_1');
            expect(dupRes.success).toBe(true);
            expect(selectedNodeId.value).toBeTruthy();

            const dupNode = findNode(selectedNodeId.value!)?.node;
            expect(dupNode).toBeDefined();
            expect(dupNode?.label).toBe('Featured Product Card');
            expect(dupNode?.children?.[0].label).toBe('Buy Now Button');
        });

        it('rejects replacement document in setDocument with non-string or overlong labels', () => {
            const { setDocument } = useDocumentState();

            const invalidTypeDoc = {
                schemaVersion: 1,
                root: sectionNode('root_target', [
                    {
                        ...headingNode('node_target_1', 'Heading'),
                        label: 12345, // invalid type
                    },
                ]),
            };
            const resType = setDocument(invalidTypeDoc as any);
            expect(resType.success).toBe(false);
            expect(resType.error).toContain('label');

            const overlongDoc = {
                schemaVersion: 1,
                root: sectionNode('root_target', [
                    {
                        ...headingNode('node_target_1', 'Heading'),
                        label: 'x'.repeat(65), // 65 chars > 64
                    },
                ]),
            };
            const resOverlong = setDocument(overlongDoc as any);
            expect(resOverlong.success).toBe(false);
            expect(resOverlong.error).toContain('label');

            const validDoc = {
                schemaVersion: 1,
                root: sectionNode('root_target', [
                    {
                        ...headingNode('node_target_1', 'Heading'),
                        label: 'Valid Custom Heading',
                    },
                ]),
            };
            const resValid = setDocument(validDoc as any);
            expect(resValid.success).toBe(true);
        });
    });
});

