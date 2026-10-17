import { describe, expect, it } from 'vitest';
import { isSafeUrl, sanitizeHtml, type DOMParserLike } from '@/lib/domSanitizer';

/**
 * Lightweight in-memory DOM mock for testing sanitizeHtml in a Node environment
 */
class MockAttribute {
    constructor(public name: string, public value: string) {}
}

class MockNode {
    childNodes: MockNode[] = [];
    parentNode: MockNode | null = null;

    constructor(public nodeType: number) {}

    appendChild(child: MockNode) {
        child.parentNode = this;
        this.childNodes.push(child);
    }

    remove() {
        if (this.parentNode) {
            const idx = this.parentNode.childNodes.indexOf(this);
            if (idx !== -1) {
                this.parentNode.childNodes.splice(idx, 1);
            }
            this.parentNode = null;
        }
    }
}

class MockTextNode extends MockNode {
    constructor(public textContent: string) {
        super(3); // Node.TEXT_NODE
    }
}

class MockElement extends MockNode {
    attributes: MockAttribute[] = [];

    constructor(public tagName: string) {
        super(1); // Node.ELEMENT_NODE
    }

    getAttribute(name: string): string | null {
        const found = this.attributes.find((a) => a.name.toLowerCase() === name.toLowerCase());
        return found ? found.value : null;
    }

    setAttribute(name: string, value: string) {
        const found = this.attributes.find((a) => a.name.toLowerCase() === name.toLowerCase());
        if (found) {
            found.value = value;
        } else {
            this.attributes.push(new MockAttribute(name, value));
        }
    }

    removeAttribute(name: string) {
        const idx = this.attributes.findIndex((a) => a.name.toLowerCase() === name.toLowerCase());
        if (idx !== -1) {
            this.attributes.splice(idx, 1);
        }
    }

    get innerHTML(): string {
        return this.childNodes
            .map((c) => {
                if (c.nodeType === 3) {
                    return (c as MockTextNode).textContent;
                }
                const el = c as MockElement;
                const tag = el.tagName.toLowerCase();
                const attrs = el.attributes
                    .map((a) => ` ${a.name}="${a.value}"`)
                    .join('');
                if (tag === 'br' || tag === 'hr') {
                    return `<${tag}${attrs}>`;
                }
                return `<${tag}${attrs}>${el.innerHTML}</${tag}>`;
            })
            .join('');
    }
}

/**
 * Simple test parser that converts simple HTML strings into MockElement trees
 */
function createTestParser(): DOMParserLike {
    return {
        parseFromString(html: string): { body: any } {
            const body = new MockElement('body');
            const tagRegex = /<(\/)?([a-zA-Z0-9:-]+)([^>]*)>|([^<]+)/g;
            const stack: MockElement[] = [body];

            let match: RegExpExecArray | null;
            while ((match = tagRegex.exec(html)) !== null) {
                const [_, isClose, tagName, attrString, text] = match;

                if (text) {
                    stack[stack.length - 1].appendChild(new MockTextNode(text));
                } else if (isClose) {
                    if (stack.length > 1) {
                        const current = stack[stack.length - 1];
                        if (current.tagName.toLowerCase() === tagName.toLowerCase()) {
                            stack.pop();
                        }
                    }
                } else {
                    const el = new MockElement(tagName);
                    if (attrString) {
                        const attrRegex = /([a-zA-Z0-9_-]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g;
                        let attrMatch: RegExpExecArray | null;
                        while ((attrMatch = attrRegex.exec(attrString)) !== null) {
                            const name = attrMatch[1];
                            const val = attrMatch[2] ?? attrMatch[3] ?? attrMatch[4] ?? '';
                            el.setAttribute(name, val);
                        }
                    }
                    stack[stack.length - 1].appendChild(el);
                    if (tagName.toLowerCase() !== 'br' && tagName.toLowerCase() !== 'hr') {
                        stack.push(el);
                    }
                }
            }

            return { body };
        },
    };
}

describe('isSafeUrl', () => {
    it('allows valid http and https URLs', () => {
        expect(isSafeUrl('https://example.com')).toBe(true);
        expect(isSafeUrl('http://example.com/path?foo=bar')).toBe(true);
    });

    it('allows safe mailto and tel protocols', () => {
        expect(isSafeUrl('mailto:user@example.com')).toBe(true);
        expect(isSafeUrl('tel:+1234567890')).toBe(true);
    });

    it('allows site-relative paths and hash anchors', () => {
        expect(isSafeUrl('/about')).toBe(true);
        expect(isSafeUrl('/storage/images/sample.jpg')).toBe(true);
        expect(isSafeUrl('#section-features')).toBe(true);
        expect(isSafeUrl('')).toBe(true);
        expect(isSafeUrl(null)).toBe(true);
    });

    it('rejects protocol-relative double-slash URLs', () => {
        expect(isSafeUrl('//evil.com/payload')).toBe(false);
    });

    it('rejects dangerous javascript: schemes', () => {
        expect(isSafeUrl('javascript:alert(1)')).toBe(false);
        expect(isSafeUrl('JAVASCRIPT:alert(document.cookie)')).toBe(false);
        expect(isSafeUrl('javascript:void(0)')).toBe(false);
    });

    it('rejects data: and vbscript: URLs', () => {
        expect(isSafeUrl('data:text/html,<script>alert(1)</script>')).toBe(false);
        expect(isSafeUrl('vbscript:msgbox(1)')).toBe(false);
    });

    it('rejects URLs containing invisible or control characters', () => {
        expect(isSafeUrl('java\x00script:alert(1)')).toBe(false);
        expect(isSafeUrl('https://example.com/\x08test')).toBe(false);
    });
});

describe('sanitizeHtml', () => {
    const parser = createTestParser();

    it('fails safely in non-browser environments when no parser is provided', () => {
        // Without window.DOMParser or custom parser, sanitizeHtml must return empty string
        const result = sanitizeHtml('<p>Some content</p>');
        expect(result).toBe('');
    });

    it('preserves safe allowlisted tags and content', () => {
        const input = '<p>Hello <strong>world</strong> and <em>welcome</em>!</p>';
        const result = sanitizeHtml(input, parser);
        expect(result).toContain('<strong>world</strong>');
        expect(result).toContain('<em>welcome</em>');
    });

    it('F-02: removes id attributes from user-controlled markup to prevent DOM clobbering', () => {
        const input = '<p id="clobber-body" class="intro">Text</p><a id="lastError" href="https://example.com">Link</a>';
        const result = sanitizeHtml(input, parser);
        expect(result).not.toContain('id="clobber-body"');
        expect(result).not.toContain('id="lastError"');
        expect(result).toContain('class="intro"');
        expect(result).toContain('href="https://example.com"');
    });

    it('strips inline event handlers (onclick, onerror, onload)', () => {
        const input = '<p onclick="alert(1)" ONERROR="evil()" class="safe">Click me</p>';
        const result = sanitizeHtml(input, parser);
        expect(result).not.toContain('onclick');
        expect(result).not.toContain('ONERROR');
        expect(result).not.toContain('alert(1)');
        expect(result).toContain('class="safe"');
    });

    it('strips script, iframe, and style elements completely', () => {
        const input = '<div>Safe</div><script>alert("pwned")</script><iframe></iframe><style>body{display:none}</style>';
        const result = sanitizeHtml(input, parser);
        expect(result).toBe('<div>Safe</div>');
        expect(result).not.toContain('script');
        expect(result).not.toContain('iframe');
        expect(result).not.toContain('style');
    });

    it('strips SVG and MathML markup to prevent namespace confusion XSS', () => {
        const input = '<p>Text</p><svg><script>alert(1)</script></svg><math><mi>x</mi></math>';
        const result = sanitizeHtml(input, parser);
        expect(result).toBe('<p>Text</p>');
        expect(result).not.toContain('svg');
        expect(result).not.toContain('math');
    });

    it('neutralizes javascript: in links to "#"', () => {
        const input = '<a href="javascript:alert(1)">Click here</a>';
        const result = sanitizeHtml(input, parser);
        expect(result).toContain('href="#"');
        expect(result).not.toContain('javascript:alert(1)');
    });

    it('adds noopener noreferrer to target="_blank" links', () => {
        const input = '<a href="https://external.com" target="_blank">External</a>';
        const result = sanitizeHtml(input, parser);
        expect(result).toContain('rel="noopener noreferrer"');
    });

    it('handles empty, null, or non-string inputs safely', () => {
        expect(sanitizeHtml('', parser)).toBe('');
        expect(sanitizeHtml(null as any, parser)).toBe('');
        expect(sanitizeHtml(undefined as any, parser)).toBe('');
    });
});
