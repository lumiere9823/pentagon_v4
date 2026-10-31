import { describe, expect, it } from 'vitest';
import {
    calculateDropIndex,
    isMoveNoop,
    resolvePointerDropTarget,
} from '@/lib/dropIndex';

describe('calculateDropIndex', () => {
    describe('Cross-parent reparenting', () => {
        it('uses direct pre-removal target index when moving between different parents', () => {
            // Source is in parent A, dropping into parent B
            expect(calculateDropIndex(-1, 0, false)).toBe(0);
            expect(calculateDropIndex(-1, 2, false)).toBe(2);
            expect(calculateDropIndex(3, 0, false)).toBe(0);
            expect(calculateDropIndex(3, 5, false)).toBe(5);
        });

        it('clamps negative target indices to 0', () => {
            expect(calculateDropIndex(-1, -3, false)).toBe(0);
        });
    });

    describe('Same-parent reordering index conversion', () => {
        // Assume children [A, B, C, D] (indices 0, 1, 2, 3)

        it('handles moving first child downward', () => {
            // Move A (0) to after B (pre-removal target index 2)
            // Post-removal: [B, C, D] -> insert at 1 -> [B, A, C, D]
            expect(calculateDropIndex(0, 2, true)).toBe(1);

            // Move A (0) to end (after D, pre-removal target index 4)
            // Post-removal: [B, C, D] -> insert at 3 -> [B, C, D, A]
            expect(calculateDropIndex(0, 4, true)).toBe(3);
        });

        it('handles moving last child upward', () => {
            // Move D (3) to before C (pre-removal target index 2)
            // Post-removal: [A, B, C] -> insert at 2 -> [A, B, D, C]
            expect(calculateDropIndex(3, 2, true)).toBe(2);

            // Move D (3) to start (before A, pre-removal target index 0)
            // Post-removal: [A, B, C] -> insert at 0 -> [D, A, B, C]
            expect(calculateDropIndex(3, 0, true)).toBe(0);
        });

        it('handles moving middle child upward and downward', () => {
            // Move B (1) upward before A (0)
            expect(calculateDropIndex(1, 0, true)).toBe(0);

            // Move B (1) downward after C (pre-removal target 3)
            // 3 > 1 -> 3 - 1 = 2
            expect(calculateDropIndex(1, 3, true)).toBe(2);
        });

        it('converts drops before and after self to the existing position (no-op)', () => {
            // Drop B (1) before B (pre-removal target 1)
            expect(calculateDropIndex(1, 1, true)).toBe(1);

            // Drop B (1) after B (pre-removal target 2)
            // 2 > 1 -> 2 - 1 = 1
            expect(calculateDropIndex(1, 2, true)).toBe(1);
        });
    });
});

describe('isMoveNoop', () => {
    it('returns true when same-parent move leaves node at its current index', () => {
        expect(isMoveNoop(1, 1, true)).toBe(true);
        expect(isMoveNoop(0, 0, true)).toBe(true);
        expect(isMoveNoop(4, 4, true)).toBe(true);
    });

    it('returns false when same-parent move changes index', () => {
        expect(isMoveNoop(1, 0, true)).toBe(false);
        expect(isMoveNoop(1, 2, true)).toBe(false);
    });

    it('returns false for cross-parent moves even if indices match', () => {
        expect(isMoveNoop(1, 1, false)).toBe(false);
        expect(isMoveNoop(0, 0, false)).toBe(false);
    });
});

describe('resolvePointerDropTarget', () => {
    describe('Same-parent sibling before/after reordering', () => {
        // Parent has [node_0 (0), node_1 (1), node_2 (2)]
        const baseParams = {
            sourceNodeId: 'node_0',
            sourceParentId: 'parent_c',
            sourceIndex: 0,
            targetNodeId: 'node_2',
            targetParentId: 'parent_c',
            targetIndexInParent: 2,
            targetIsContainer: false,
            targetChildCount: 0,
            position: 'before' as const,
        };

        it('resolves dropping node_0 before node_2 (downward move)', () => {
            const res = resolvePointerDropTarget(baseParams);
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(false);
            expect(res.targetParentId).toBe('parent_c');
            expect(res.targetIndex).toBe(1); // Post-removal index 1: [node_1, node_0, node_2]
        });

        it('resolves dropping node_0 after node_2 (downward to end)', () => {
            const res = resolvePointerDropTarget({
                ...baseParams,
                position: 'after',
            });
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(false);
            expect(res.targetParentId).toBe('parent_c');
            expect(res.targetIndex).toBe(2); // Post-removal index 2: [node_1, node_2, node_0]
        });

        it('resolves dropping node_2 before node_0 (upward to start)', () => {
            const res = resolvePointerDropTarget({
                ...baseParams,
                sourceNodeId: 'node_2',
                sourceIndex: 2,
                targetNodeId: 'node_0',
                targetIndexInParent: 0,
                position: 'before',
            });
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(false);
            expect(res.targetParentId).toBe('parent_c');
            expect(res.targetIndex).toBe(0); // Post-removal index 0: [node_2, node_0, node_1]
        });

        it('identifies dropping before immediate next sibling as a no-op', () => {
            // node_0 is ALREADY immediately before node_1
            const res = resolvePointerDropTarget({
                ...baseParams,
                sourceNodeId: 'node_0',
                sourceIndex: 0,
                targetNodeId: 'node_1',
                targetIndexInParent: 1,
                position: 'before',
            });
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(true);
            expect(res.targetIndex).toBe(0);
        });

        it('identifies dropping after immediate prior sibling as a no-op', () => {
            // node_1 is ALREADY immediately after node_0
            const res = resolvePointerDropTarget({
                ...baseParams,
                sourceNodeId: 'node_1',
                sourceIndex: 1,
                targetNodeId: 'node_0',
                targetIndexInParent: 0,
                position: 'after',
            });
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(true);
            expect(res.targetIndex).toBe(1);
        });

        it('identifies dropping on self before/after as a no-op', () => {
            const beforeSelf = resolvePointerDropTarget({
                ...baseParams,
                sourceNodeId: 'node_1',
                sourceIndex: 1,
                targetNodeId: 'node_1',
                targetIndexInParent: 1,
                position: 'before',
            });
            expect(beforeSelf.isValid).toBe(true);
            expect(beforeSelf.isNoop).toBe(true);

            const afterSelf = resolvePointerDropTarget({
                ...baseParams,
                sourceNodeId: 'node_1',
                sourceIndex: 1,
                targetNodeId: 'node_1',
                targetIndexInParent: 1,
                position: 'after',
            });
            expect(afterSelf.isValid).toBe(true);
            expect(afterSelf.isNoop).toBe(true);
        });
    });

    describe('Cross-parent reparenting', () => {
        it('resolves before and after drops into a different parent', () => {
            // Drop source (in parent A) before child_2 in parent B (targetIndexInParent: 2)
            const beforeRes = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'parent_a',
                sourceIndex: 0,
                targetNodeId: 'target_node',
                targetParentId: 'parent_b',
                targetIndexInParent: 2,
                targetIsContainer: false,
                targetChildCount: 0,
                position: 'before',
            });
            expect(beforeRes.isValid).toBe(true);
            expect(beforeRes.isNoop).toBe(false);
            expect(beforeRes.targetParentId).toBe('parent_b');
            expect(beforeRes.targetIndex).toBe(2);

            // Drop source after child_2 in parent B
            const afterRes = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'parent_a',
                sourceIndex: 0,
                targetNodeId: 'target_node',
                targetParentId: 'parent_b',
                targetIndexInParent: 2,
                targetIsContainer: false,
                targetChildCount: 0,
                position: 'after',
            });
            expect(afterRes.isValid).toBe(true);
            expect(afterRes.isNoop).toBe(false);
            expect(afterRes.targetParentId).toBe('parent_b');
            expect(afterRes.targetIndex).toBe(3);
        });
    });

    describe('Inside container drops', () => {
        it('resolves dropping inside a container to append at the end', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'parent_a',
                sourceIndex: 0,
                targetNodeId: 'dest_container',
                targetParentId: 'root',
                targetIndexInParent: 0,
                targetIsContainer: true,
                targetChildCount: 4,
                position: 'inside',
            });
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(false);
            expect(res.targetParentId).toBe('dest_container');
            expect(res.targetIndex).toBe(4);
        });

        it('identifies dropping inside same parent when already last child as no-op', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'parent_a',
                sourceIndex: 3, // Already child index 3 of 4
                targetNodeId: 'parent_a',
                targetParentId: 'root',
                targetIndexInParent: 0,
                targetIsContainer: true,
                targetChildCount: 4,
                position: 'inside',
            });
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(true);
            expect(res.targetIndex).toBe(3);
        });

        it('resolves appending a non-final child to its own parent as a valid move to the end', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'parent_a',
                sourceIndex: 0, // First child of 4
                targetNodeId: 'parent_a',
                targetParentId: 'root',
                targetIndexInParent: 0,
                targetIsContainer: true,
                targetChildCount: 4,
                position: 'inside',
            });
            expect(res.isValid).toBe(true);
            expect(res.isNoop).toBe(false);
            expect(res.targetParentId).toBe('parent_a');
            expect(res.targetIndex).toBe(3); // Post-removal index = 3 (end of remaining 3 children)
        });

        it('rejects dropping inside a leaf component', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'parent_a',
                sourceIndex: 0,
                targetNodeId: 'heading_leaf',
                targetParentId: 'parent_a',
                targetIndexInParent: 1,
                targetIsContainer: false,
                targetChildCount: 0,
                position: 'inside',
            });
            expect(res.isValid).toBe(false);
            expect(res.isNoop).toBe(false);
            expect(res.reason).toContain('leaf component');
        });

        it('rejects dropping directly inside itself', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'container_a',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'container_a',
                targetParentId: 'root',
                targetIndexInParent: 0,
                targetIsContainer: true,
                targetChildCount: 2,
                position: 'inside',
            });
            expect(res.isValid).toBe(false);
            expect(res.isNoop).toBe(false);
            expect(res.reason).toContain('inside itself');
        });
    });

    describe('Invalid target and boundary protection', () => {
        it('rejects dropping into a descendant container (cycle prevention)', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'container_a',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'nested_container',
                targetParentId: 'container_a',
                targetIndexInParent: 0,
                targetIsContainer: true,
                targetChildCount: 0,
                position: 'inside',
                targetIsDescendantOfSource: true,
            });
            expect(res.isValid).toBe(false);
            expect(res.reason).toContain('Cannot move a container into itself or its own descendants');
        });

        it('rejects before/after drops on the root node (no root siblings)', () => {
            const beforeRoot = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'root',
                targetParentId: null, // Root has no parent
                targetIndexInParent: 0,
                targetIsContainer: true,
                targetChildCount: 3,
                position: 'before',
            });
            expect(beforeRoot.isValid).toBe(false);
            expect(beforeRoot.reason).toContain('Root node cannot have siblings');

            const afterRoot = resolvePointerDropTarget({
                sourceNodeId: 'source_node',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'root',
                targetParentId: null,
                targetIndexInParent: 0,
                targetIsContainer: true,
                targetChildCount: 3,
                position: 'after',
            });
            expect(afterRoot.isValid).toBe(false);
            expect(afterRoot.reason).toContain('Root node cannot have siblings');
        });

        it('rejects moves when the source node or its ancestry is locked', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'locked_child',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'target_container',
                targetParentId: 'root',
                targetIndexInParent: 1,
                targetIsContainer: true,
                targetChildCount: 0,
                position: 'inside',
                sourceIsLocked: true,
            });
            expect(res.isValid).toBe(false);
            expect(res.reason).toContain('Source component is locked');
        });

        it('rejects inside drops when the destination container is locked', () => {
            const res = resolvePointerDropTarget({
                sourceNodeId: 'free_child',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'locked_container',
                targetParentId: 'root',
                targetIndexInParent: 1,
                targetIsContainer: true,
                targetChildCount: 0,
                position: 'inside',
                targetIsLocked: true,
            });
            expect(res.isValid).toBe(false);
            expect(res.reason).toContain('Target container is locked');
        });

        it('rejects before/after drops when the target parent container is locked', () => {
            const resBefore = resolvePointerDropTarget({
                sourceNodeId: 'free_child',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'child_in_locked_parent',
                targetParentId: 'locked_parent',
                targetIndexInParent: 0,
                targetIsContainer: false,
                targetChildCount: 0,
                position: 'before',
                targetParentIsLocked: true,
            });
            expect(resBefore.isValid).toBe(false);
            expect(resBefore.reason).toContain('Target parent container is locked');

            const resAfter = resolvePointerDropTarget({
                sourceNodeId: 'free_child',
                sourceParentId: 'root',
                sourceIndex: 0,
                targetNodeId: 'child_in_locked_parent',
                targetParentId: 'locked_parent',
                targetIndexInParent: 0,
                targetIsContainer: false,
                targetChildCount: 0,
                position: 'after',
                targetParentIsLocked: true,
            });
            expect(resAfter.isValid).toBe(false);
            expect(resAfter.reason).toContain('Target parent container is locked');
        });
    });
});
