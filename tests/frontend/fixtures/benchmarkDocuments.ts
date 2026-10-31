import type { DocumentNode, DocumentSchema } from '@/types/document';

export function countNodesInTree(node: DocumentNode): number {
    let count = 1;
    if (node.children) {
        for (const child of node.children) {
            count += countNodesInTree(child);
        }
    }
    return count;
}

export function generateBenchmarkDocument(
    targetNodes: 10 | 100 | 250,
): DocumentSchema {
    let currentId = 1;
    const nextId = (prefix: string) => `bench_${prefix}_${currentId++}`;

    const root: DocumentNode = {
        id: nextId('root_section'),
        type: 'Section',
        props: { padding: 'medium', background: 'transparent' },
        children: [],
    };

    let remaining = targetNodes - 1;

    while (remaining > 0) {
        const containerChildrenCount = Math.min(remaining - 1, 40);
        if (containerChildrenCount >= 0) {
            const container: DocumentNode = {
                id: nextId('container'),
                type: 'Container',
                props: { maxWidth: 'lg', centered: true },
                children: [],
            };
            remaining -= 1;

            for (let i = 0; i < containerChildrenCount; i++) {
                const nodeType =
                    i % 4 === 0
                        ? 'Heading'
                        : i % 4 === 1
                          ? 'Button'
                          : i % 4 === 2
                            ? 'Divider'
                            : 'Image';
                const props =
                    nodeType === 'Heading'
                        ? { text: `Heading ${currentId}`, level: 2 }
                        : nodeType === 'Button'
                          ? {
                                label: `Button ${currentId}`,
                                url: 'https://example.com',
                            }
                          : nodeType === 'Divider'
                            ? { style: 'solid' }
                            : {
                                  src: '/storage/media/bench.png',
                                  alt: 'Benchmark Image',
                              };
                container.children!.push({
                    id: nextId(nodeType.toLowerCase()),
                    type: nodeType,
                    props,
                });
                remaining -= 1;
            }
            root.children!.push(container);
        } else {
            break;
        }
    }

    return {
        schemaVersion: 1,
        root,
        pageSettings: {
            title: `Benchmark Page (${targetNodes} nodes)`,
            description: 'Automated performance benchmark harness document',
        },
    };
}
