/**
 * Client-Side Safe HTML Sanitizer
 * Uses W3C DOMParser instead of fragile regexes.
 * Strips script tags, unsafe elements, event handlers (onclick/onerror),
 * disallowed URL schemes (javascript:, data:), and DOM-clobbering id attributes.
 */

const ALLOWED_TAGS = new Set([
    'p',
    'strong',
    'b',
    'em',
    'i',
    'u',
    's',
    'strike',
    'del',
    'a',
    'ul',
    'ol',
    'li',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'blockquote',
    'code',
    'pre',
    'br',
    'hr',
    'span',
    'div',
]);

const ALLOWED_SCHEMES = ['http:', 'https:', 'mailto:', 'tel:'];

export function isSafeUrl(url: string | null | undefined): boolean {
    if (!url || typeof url !== 'string') {
        return true;
    }

    const trimmed = url.trim();
    if (trimmed === '') {
        return true;
    }

    // Check for invisible or control characters (CVE prevention)
    for (let i = 0; i < trimmed.length; i++) {
        const code = trimmed.charCodeAt(i);
        if ((code >= 0 && code <= 31) || code === 127) {
            return false;
        }
    }

    // Reject protocol-relative URLs (e.g. //evil.com)
    if (trimmed.startsWith('//')) {
        return false;
    }

    // Relative URLs and hash anchors are safe
    if (trimmed.startsWith('/')) {
        return true;
    }
    if (trimmed.startsWith('#')) {
        return true;
    }

    try {
        const parsed = new URL(trimmed, 'https://example.com');
        return ALLOWED_SCHEMES.includes(parsed.protocol);
    } catch {
        // If not a valid URL format, check if it's a relative path without leading slash
        return !/^[a-zA-Z0-9+.-]+:/i.test(trimmed);
    }
}

export interface DOMParserLike {
    parseFromString(str: string, type: string): { body: Node };
}

export function sanitizeHtml(
    rawHtml: string,
    customParser?: DOMParserLike,
): string {
    if (!rawHtml || typeof rawHtml !== 'string') {
        return '';
    }

    let parser: DOMParserLike | null = customParser ?? null;

    if (!parser) {
        if (typeof DOMParser !== 'undefined') {
            parser = new DOMParser();
        } else if (typeof window !== 'undefined' && window.DOMParser) {
            parser = new window.DOMParser();
        }
    }

    // If non-browser environment lacks trusted DOMParser, fail safely rather than using regex
    if (!parser) {
        return '';
    }

    const doc = parser.parseFromString(rawHtml, 'text/html');
    if (!doc || !doc.body) {
        return '';
    }

    function cleanNode(node: Node): void {
        const children = Array.from(node.childNodes);

        for (const child of children) {
            // Node.ELEMENT_NODE === 1
            if (child.nodeType === 1) {
                const element = child as HTMLElement;
                const tagName = element.tagName.toLowerCase();

                // If tag is not in allowlist, remove it completely
                if (!ALLOWED_TAGS.has(tagName)) {
                    element.remove();
                    continue;
                }

                // Clean attributes
                const attributes = Array.from(element.attributes);
                for (const attr of attributes) {
                    const attrName = attr.name.toLowerCase();

                    // Strip any inline event handlers (onclick, onerror, onload, etc.)
                    if (attrName.startsWith('on')) {
                        element.removeAttribute(attr.name);
                        continue;
                    }

                    // Links: validate href scheme and set rel
                    if (tagName === 'a' && attrName === 'href') {
                        if (!isSafeUrl(attr.value)) {
                            element.setAttribute('href', '#');
                        } else if (
                            element.getAttribute('target') === '_blank'
                        ) {
                            element.setAttribute('rel', 'noopener noreferrer');
                        }
                    } else if (
                        attrName !== 'class' &&
                        attrName !== 'target' &&
                        attrName !== 'rel'
                    ) {
                        // Strip arbitrary dangerous or unknown attributes
                        // Note: 'id' is intentionally stripped to prevent DOM clobbering (F-02)
                        element.removeAttribute(attr.name);
                    }
                }

                // Recursively clean children
                cleanNode(element);
            } else if (child.nodeType !== 3 && child.nodeType !== 8) {
                // Node.TEXT_NODE === 3, Node.COMMENT_NODE === 8
                child.remove();
            }
        }
    }

    cleanNode(doc.body);
    return (doc.body as HTMLElement).innerHTML || '';
}
