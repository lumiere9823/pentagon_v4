/**
 * Helper utilities for drop index calculations and no-op detection in tree mutations.
 */

/**
 * Calculates the post-removal insertion index for moving a node within or across containers.
 *
 * In the core `moveNode(nodeId, targetParentId, targetIndex)` API, `targetIndex` is defined as
 * the insertion position in the destination container's children array *after* the source node
 * has been removed (or for cross-parent moves, the index in the destination children array).
 *
 * When a user interface calculates drop positions before the source node is removed (for example,
 * "drop before sibling at index K" or "drop after sibling at index K"), this helper converts that
 * pre-removal target position into the post-removal insertion index expected by `moveNode`.
 *
 * @param sourceIndex The 0-based index of the node in its current parent (-1 if cross-parent).
 * @param preRemovalTargetIndex The intended 0-based index in the parent's children array before removal.
 * @param isSameParent Whether the source and destination parent containers are the same.
 * @returns The clamped, post-removal insertion index to pass to `moveNode`.
 */
export function calculateDropIndex(
    sourceIndex: number,
    preRemovalTargetIndex: number,
    isSameParent: boolean,
): number {
    const target = Math.max(0, preRemovalTargetIndex);
    if (!isSameParent || sourceIndex < 0) {
        return target;
    }

    // In a same-parent move:
    // If the target position is after the source node, removing the source node shifts
    // all subsequent elements left by 1. Therefore, the post-removal index is target - 1.
    if (target > sourceIndex) {
        return Math.max(0, target - 1);
    }

    // If the target position is before or at the source node, removal does not shift
    // elements prior to sourceIndex, so target remains unchanged.
    return target;
}

/**
 * Determines whether a requested same-parent move leaves the node at its current index (a no-op).
 *
 * @param sourceIndex The 0-based index of the node in its current parent.
 * @param postRemovalTargetIndex The insertion index in the children array after source removal.
 * @param isSameParent Whether the source and destination parent containers are the same.
 * @returns True if the move would not change the position of the node.
 */
export function isMoveNoop(
    sourceIndex: number,
    postRemovalTargetIndex: number,
    isSameParent: boolean,
): boolean {
    if (!isSameParent || sourceIndex < 0) {
        return false;
    }
    return sourceIndex === postRemovalTargetIndex;
}

export type DropPosition = 'before' | 'after' | 'inside';

export interface ResolvePointerDropTargetParams {
    sourceNodeId: string;
    sourceParentId: string | null;
    sourceIndex: number;
    targetNodeId: string;
    targetParentId: string | null;
    targetIndexInParent: number;
    targetIsContainer: boolean;
    targetChildCount: number;
    position: DropPosition;
    targetIsDescendantOfSource?: boolean;
    sourceIsLocked?: boolean;
    targetIsLocked?: boolean;
    targetParentIsLocked?: boolean;
}

export interface ResolvedPointerDropTarget {
    isValid: boolean;
    isNoop: boolean;
    targetParentId: string;
    targetIndex: number;
    reason?: string;
}

/**
 * Resolves the destination parent and post-removal insertion index for a pointer drop event.
 * Handles before, after, and inside positions with same-parent and cross-parent coordinate mapping.
 */
export function resolvePointerDropTarget(
    params: ResolvePointerDropTargetParams,
): ResolvedPointerDropTarget {
    const {
        sourceNodeId,
        sourceParentId,
        sourceIndex,
        targetNodeId,
        targetParentId,
        targetIndexInParent,
        targetIsContainer,
        targetChildCount,
        position,
        targetIsDescendantOfSource = false,
        sourceIsLocked = false,
        targetIsLocked = false,
        targetParentIsLocked = false,
    } = params;

    // Node locking checks
    if (sourceIsLocked) {
        return {
            isValid: false,
            isNoop: false,
            targetParentId: '',
            targetIndex: -1,
            reason: 'Source component is locked and cannot be moved.',
        };
    }

    if (position === 'inside' && targetIsLocked) {
        return {
            isValid: false,
            isNoop: false,
            targetParentId: targetNodeId,
            targetIndex: -1,
            reason: 'Target container is locked.',
        };
    }

    if (
        (position === 'before' || position === 'after') &&
        targetParentIsLocked
    ) {
        return {
            isValid: false,
            isNoop: false,
            targetParentId: targetParentId ?? '',
            targetIndex: -1,
            reason: 'Target parent container is locked.',
        };
    }

    // Cycle check: cannot drop into self or descendants
    if (targetIsDescendantOfSource) {
        return {
            isValid: false,
            isNoop: false,
            targetParentId: '',
            targetIndex: -1,
            reason: 'Cannot move a container into itself or its own descendants.',
        };
    }

    // Dropping directly onto self
    if (sourceNodeId === targetNodeId) {
        if (position === 'inside') {
            return {
                isValid: false,
                isNoop: false,
                targetParentId: '',
                targetIndex: -1,
                reason: 'Cannot drop a node inside itself.',
            };
        }
        return {
            isValid: true,
            isNoop: true,
            targetParentId: sourceParentId ?? '',
            targetIndex: sourceIndex,
        };
    }

    if (position === 'inside') {
        if (!targetIsContainer) {
            return {
                isValid: false,
                isNoop: false,
                targetParentId: targetNodeId,
                targetIndex: -1,
                reason: `Target '${targetNodeId}' cannot accept children because it is a leaf component.`,
            };
        }

        const isSameParent = sourceParentId === targetNodeId;
        const targetIndex = calculateDropIndex(
            sourceIndex,
            targetChildCount,
            isSameParent,
        );
        const isNoop = isMoveNoop(sourceIndex, targetIndex, isSameParent);

        return {
            isValid: true,
            isNoop,
            targetParentId: targetNodeId,
            targetIndex,
        };
    }

    // position === 'before' or 'after'
    if (!targetParentId) {
        return {
            isValid: false,
            isNoop: false,
            targetParentId: '',
            targetIndex: -1,
            reason: 'Root node cannot have siblings.',
        };
    }

    const isSameParent = sourceParentId === targetParentId;
    const preRemovalTargetIndex =
        position === 'before' ? targetIndexInParent : targetIndexInParent + 1;

    const targetIndex = calculateDropIndex(
        sourceIndex,
        preRemovalTargetIndex,
        isSameParent,
    );
    const isNoop = isMoveNoop(sourceIndex, targetIndex, isSameParent);

    return {
        isValid: true,
        isNoop,
        targetParentId,
        targetIndex,
    };
}
