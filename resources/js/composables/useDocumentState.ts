import { computed, ref, type Ref } from 'vue';
import {
    CONTAINER_COMPONENT_TYPES,
    DEFAULT_COMPONENT_PROPS,
    DOCUMENT_LIMITS,
    type CoreComponentType,
    type DocumentNode,
    type DocumentSchema,
    type InsertPosition,
    type TreeMutationResult,
} from '@/types/document';

export function createNodeId(type: string): string {
    const cleanType = type
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '')
        .slice(0, 8);
    const rand = Math.random().toString(36).substring(2, 8);
    const time = Date.now().toString(36).slice(-4);
    return `node_${cleanType}_${rand}${time}`;
}

export function createDefaultDocument(): DocumentSchema {
    return {
        schemaVersion: 1,
        root: {
            id: 'root_section_default',
            type: 'Section',
            props: {
                padding: 'medium',
                background: 'transparent',
            },
            children: [
                {
                    id: 'node_container_default',
                    type: 'Container',
                    props: {
                        maxWidth: 'lg',
                        centered: true,
                    },
                    children: [],
                },
            ],
        },
        pageSettings: {
            title: 'New Page',
            description: '',
        },
    };
}

export interface NodeSearchResult {
    node: DocumentNode;
    parent: DocumentNode | null;
    index: number;
    depth: number;
}

export function useDocumentState(initialDoc?: DocumentSchema) {
    const document: Ref<DocumentSchema> = ref(
        initialDoc ?? createDefaultDocument(),
    );
    const selectedNodeId: Ref<string | null> = ref(null);
    const hoveredNodeId: Ref<string | null> = ref(null);
    const lastError: Ref<string | null> = ref(null);

    // Tree Search & Traversal
    function findNode(
        id: string,
        current: DocumentNode = document.value.root,
        parent: DocumentNode | null = null,
        index: number = 0,
        depth: number = 1,
    ): NodeSearchResult | null {
        if (current.id === id) {
            return { node: current, parent, index, depth };
        }

        if (current.children && current.children.length > 0) {
            for (let i = 0; i < current.children.length; i++) {
                const res = findNode(
                    id,
                    current.children[i],
                    current,
                    i,
                    depth + 1,
                );
                if (res) return res;
            }
        }

        return null;
    }

    function countNodes(current: DocumentNode = document.value.root): number {
        let count = 1;
        if (current.children && current.children.length > 0) {
            for (const child of current.children) {
                count += countNodes(child);
            }
        }
        return count;
    }

    function getMaxDepth(
        current: DocumentNode = document.value.root,
        depth: number = 1,
    ): number {
        if (!current.children || current.children.length === 0) {
            return depth;
        }
        let max = depth;
        for (const child of current.children) {
            max = Math.max(max, getMaxDepth(child, depth + 1));
        }
        return max;
    }

    // Cycle Prevention: check if targetId is inside searchNodeId's subtree
    function isDescendant(
        searchNodeId: string,
        potentialAncestorId: string,
    ): boolean {
        if (searchNodeId === potentialAncestorId) return true;
        const ancestorSearchResult = findNode(potentialAncestorId);
        if (!ancestorSearchResult) return false;

        function checkChildren(node: DocumentNode): boolean {
            if (node.id === searchNodeId) return true;
            if (node.children) {
                for (const child of node.children) {
                    if (checkChildren(child)) return true;
                }
            }
            return false;
        }

        return checkChildren(ancestorSearchResult.node);
    }

    // Recursively clone a node and assign new unique IDs
    function cloneNodeWithNewIds(node: DocumentNode): DocumentNode {
        const cloned: DocumentNode = {
            id: createNodeId(node.type),
            type: node.type,
            props: JSON.parse(JSON.stringify(node.props)),
            children: node.children
                ? node.children.map(cloneNodeWithNewIds)
                : undefined,
        };
        return cloned;
    }

    // Selection management
    function selectNode(id: string | null): void {
        selectedNodeId.value = id;
    }

    function clearSelection(): void {
        selectedNodeId.value = null;
    }

    function setHoveredNode(id: string | null): void {
        hoveredNodeId.value = id;
    }

    const selectedNode = computed(() => {
        if (!selectedNodeId.value) return null;
        return findNode(selectedNodeId.value)?.node ?? null;
    });

    // Mutations
    function insertNode(
        type: CoreComponentType,
        targetId?: string | null,
        position: InsertPosition = 'inside',
        customProps?: Record<string, unknown>,
        targetIndex?: number,
    ): TreeMutationResult {
        lastError.value = null;

        // Check total node limit
        const totalNodes = countNodes();
        if (totalNodes >= DOCUMENT_LIMITS.MAX_TOTAL_NODES) {
            const error = `Document limit reached: maximum ${DOCUMENT_LIMITS.MAX_TOTAL_NODES} nodes allowed.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Determine destination container and index
        let destContainer: DocumentNode;
        let insertIndex: number;
        let targetDepth: number;

        if (!targetId) {
            // Default: append into root
            destContainer = document.value.root;
            if (!destContainer.children) destContainer.children = [];
            insertIndex =
                typeof targetIndex === 'number' && !isNaN(targetIndex)
                    ? Math.max(
                          0,
                          Math.min(targetIndex, destContainer.children.length),
                      )
                    : destContainer.children.length;
            targetDepth = 1;
        } else {
            const search = findNode(targetId);
            if (!search) {
                const error = `Target node '${targetId}' not found.`;
                lastError.value = error;
                return { success: false, error };
            }

            if (position === 'inside') {
                if (!CONTAINER_COMPONENT_TYPES.has(search.node.type)) {
                    const error = `Cannot insert inside '${search.node.type}' because it is a leaf component.`;
                    lastError.value = error;
                    return { success: false, error };
                }
                destContainer = search.node;
                if (!destContainer.children) destContainer.children = [];
                insertIndex =
                    typeof targetIndex === 'number' && !isNaN(targetIndex)
                        ? Math.max(
                              0,
                              Math.min(
                                  targetIndex,
                                  destContainer.children.length,
                              ),
                          )
                        : destContainer.children.length;
                targetDepth = search.depth;
            } else {
                // 'before' or 'after'
                if (!search.parent) {
                    const error = `Cannot insert siblings relative to the Root node.`;
                    lastError.value = error;
                    return { success: false, error };
                }
                destContainer = search.parent;
                if (!destContainer.children) destContainer.children = [];
                insertIndex =
                    position === 'before' ? search.index : search.index + 1;
                targetDepth = search.depth - 1;
            }
        }

        // Check container children limit
        if (
            destContainer.children &&
            destContainer.children.length >=
                DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE
        ) {
            const error = `Container limit reached: maximum ${DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE} child nodes per container.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Check tree depth limit
        if (targetDepth + 1 > DOCUMENT_LIMITS.MAX_TREE_DEPTH) {
            const error = `Tree depth limit reached: maximum ${DOCUMENT_LIMITS.MAX_TREE_DEPTH} levels allowed.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Create new node with valid default props
        const defaultProps = DEFAULT_COMPONENT_PROPS[type] || {};
        const mergedProps = { ...defaultProps, ...customProps };

        const newNode: DocumentNode = {
            id: createNodeId(type),
            type,
            props: JSON.parse(JSON.stringify(mergedProps)),
            children: CONTAINER_COMPONENT_TYPES.has(type) ? [] : undefined,
        };

        if (!destContainer.children) destContainer.children = [];
        destContainer.children.splice(insertIndex, 0, newNode);

        // Auto-select newly inserted node
        selectedNodeId.value = newNode.id;

        return { success: true };
    }

    function updateNodeProps(
        nodeId: string,
        newProps: Record<string, unknown>,
    ): TreeMutationResult {
        lastError.value = null;
        const search = findNode(nodeId);
        if (!search) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        search.node.props = {
            ...search.node.props,
            ...JSON.parse(JSON.stringify(newProps)),
        };

        return { success: true };
    }

    function deleteNode(nodeId: string): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be deleted.';
            lastError.value = error;
            return { success: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Node '${nodeId}' or its parent container not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        const shouldClearSelection =
            selectedNodeId.value === nodeId ||
            (selectedNodeId.value !== null &&
                isDescendant(selectedNodeId.value, nodeId));

        search.parent.children.splice(search.index, 1);

        if (shouldClearSelection) {
            selectedNodeId.value = null;
        }

        return { success: true };
    }

    function duplicateNode(nodeId: string): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be duplicated.';
            lastError.value = error;
            return { success: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Count nodes in clone to ensure total nodes limit is respected
        const cloneSubtree = cloneNodeWithNewIds(search.node);
        const subtreeNodeCount = countNodes(cloneSubtree);
        const currentTotal = countNodes();

        if (currentTotal + subtreeNodeCount > DOCUMENT_LIMITS.MAX_TOTAL_NODES) {
            const error = `Cannot duplicate: would exceed total document limit of ${DOCUMENT_LIMITS.MAX_TOTAL_NODES} nodes.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (
            search.parent.children.length >=
            DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE
        ) {
            const error = `Cannot duplicate: container already at maximum of ${DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE} children.`;
            lastError.value = error;
            return { success: false, error };
        }

        search.parent.children.splice(search.index + 1, 0, cloneSubtree);
        selectedNodeId.value = cloneSubtree.id;

        return { success: true };
    }

    function moveNodeRelative(
        nodeId: string,
        direction: 'up' | 'down',
    ): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be moved.';
            lastError.value = error;
            return { success: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        const siblings = search.parent.children;
        const currentIndex = search.index;

        if (direction === 'up') {
            if (currentIndex === 0) {
                return { success: true }; // Already at top
            }
            const temp = siblings[currentIndex];
            siblings[currentIndex] = siblings[currentIndex - 1];
            siblings[currentIndex - 1] = temp;
        } else {
            if (currentIndex === siblings.length - 1) {
                return { success: true }; // Already at bottom
            }
            const temp = siblings[currentIndex];
            siblings[currentIndex] = siblings[currentIndex + 1];
            siblings[currentIndex + 1] = temp;
        }

        return { success: true };
    }

    function moveNode(
        nodeId: string,
        targetParentId: string,
        targetIndex: number,
    ): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be moved.';
            lastError.value = error;
            return { success: false, error };
        }

        // Cycle prevention: target parent cannot be the node itself or inside the node
        if (isDescendant(targetParentId, nodeId)) {
            const error =
                'Cannot move a container into itself or its own descendants.';
            lastError.value = error;
            return { success: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Source node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        const targetSearch = findNode(targetParentId);
        if (!targetSearch) {
            const error = `Target container '${targetParentId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (!CONTAINER_COMPONENT_TYPES.has(targetSearch.node.type)) {
            const error = `Target '${targetSearch.node.type}' cannot accept children because it is a leaf component.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Check depth limit
        const movingSubtreeMaxDepth = getMaxDepth(search.node);
        const movingRelativeDepth = movingSubtreeMaxDepth - search.depth;
        if (
            targetSearch.depth + 1 + movingRelativeDepth >
            DOCUMENT_LIMITS.MAX_TREE_DEPTH
        ) {
            const error = `Move rejected: would exceed maximum tree depth of ${DOCUMENT_LIMITS.MAX_TREE_DEPTH}.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Remove from old parent
        search.parent.children.splice(search.index, 1);

        // Add to new parent at target index
        if (!targetSearch.node.children) targetSearch.node.children = [];
        const clampedIndex = Math.max(
            0,
            Math.min(targetIndex, targetSearch.node.children.length),
        );
        targetSearch.node.children.splice(clampedIndex, 0, search.node);

        return { success: true };
    }

    // Replace entire document (used by undo/redo)
    function setDocument(newDoc: DocumentSchema): void {
        document.value = JSON.parse(JSON.stringify(newDoc));
        if (selectedNodeId.value && !findNode(selectedNodeId.value)) {
            selectedNodeId.value = null;
        }
    }

    return {
        document,
        selectedNodeId,
        hoveredNodeId,
        selectedNode,
        lastError,
        findNode,
        countNodes,
        getMaxDepth,
        isDescendant,
        selectNode,
        clearSelection,
        setHoveredNode,
        insertNode,
        updateNodeProps,
        deleteNode,
        duplicateNode,
        moveNodeRelative,
        moveNode,
        setDocument,
    };
}
