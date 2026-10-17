/**
 * Client-Side Safe HTML Sanitizer
 * Uses W3C DOMParser instead of fragile regexes.
 * Strips script tags, unsafe elements, event handlers (onclick/onerror),
 * and disallowed URL schemes (javascript:, data:).
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

    // Relative URLs and hash anchors are safe
    if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
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

export function sanitizeHtml(rawHtml: string): string {
    if (!rawHtml || typeof rawHtml !== 'string') {
        return '';
    }

    if (typeof window === 'undefined' || typeof DOMParser === 'undefined') {
        // Simple fallback when DOMParser is unavailable (e.g. basic node test environment without jsdom)
        return rawHtml
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
            .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '')
            .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
            .replace(/on\w+="[^"]*"/gi, '')
            .replace(/on\w+='[^']*'/gi, '')
            .replace(/href\s*=\s*["']javascript:[^"']*["']/gi, 'href="#"');
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(rawHtml, 'text/html');

    function cleanNode(node: Node): void {
        const children = Array.from(node.childNodes);

        for (const child of children) {
            if (child.nodeType === Node.ELEMENT_NODE) {
                const element = child as HTMLElement;
                const tagName = element.tagName.toLowerCase();

                // If tag is not in whitelist, remove or unwrap it
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
                        attrName !== 'rel' &&
                        attrName !== 'id'
                    ) {
                        // Strip arbitrary dangerous or unknown attributes
                        element.removeAttribute(attr.name);
                    }
                }

                // Recursively clean children
                cleanNode(element);
            } else if (
                child.nodeType !== Node.TEXT_NODE &&
                child.nodeType !== Node.COMMENT_NODE
            ) {
                child.remove();
            }
        }
    }

    cleanNode(doc.body);
    return doc.body.innerHTML;
}
