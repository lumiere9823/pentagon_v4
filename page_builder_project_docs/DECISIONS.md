# Decision Log

Ghi quyết định đã được xác nhận. Các ý trong ADR ở trạng thái Proposed chưa phải quyết định cuối cùng.

| Date | ID | Decision | Context / alternatives | Consequences | Owner |
|---|---|---|---|---|---|
| 2026-10-16 | D-001 | Modular Monolith Architecture | Deploy single package on resource-constrained servers | Clean domain isolation; low operational overhead | User / Architect |
| 2026-10-16 | D-002 | PostgreSQL 18 with JSONB for Documents | High performance relational integrity + JSONB indexing | Relational tables for meta/audit; validated JSONB trees | User / Architect |
| 2026-10-16 | D-003 | Laravel 13 + Vue 3 + Inertia + TypeScript + Tailwind v4 | Official supported starter kit, SPA feel with server routing | Fast DX, type safety, low bundle size | User / Architect |
| 2026-10-16 | D-004 | Database `page_builder_dev` on Local PostgreSQL | Isolated database for project development | Zero impact on other local databases; clean baseline | User / Architect |
| 2026-10-16 | D-005 | Safe Health Check Endpoint (`/api/health`) | Minimal status payload, no secrets or SQL leaks | Resilient monitoring without exposing internal attack surface | User / Architect |
| 2026-10-16 | D-006 | Owner Invariant & Transactional Membership Sync | Creating a website must atomically generate an owner membership | Guaranteed owner invariant; role escalation prevented via API rules | User / Architect |
| 2026-10-16 | D-007 | Composite Unique Slug for Pages `(website_id, slug)` | Allow identical page slugs across different websites | Matches real-world CMS requirements; enforced at DB constraint level | User / Architect |
| 2026-10-16 | D-008 | Strict 404 IDOR Abort on Route Scoping Mismatch | Return 404 instead of 403 on cross-website resource mismatches | Conceals resource existence across website boundaries; prevents enumeration | User / Architect |
| 2026-10-17 | D-009 | Document JSON Schema v1 Specification | Unified root node tree structure with strict schemaVersion | Versioned documents, backward compatibility, clear migration pathway | User / Architect |
| 2026-10-17 | D-010 | 12 Core Component Allowlist with Canonical Aliases | Restrict tree nodes to 12 allowed types with alias support (Rich Text, Card Grid) | Prevents arbitrary component injection; robust editor interop | User / Architect |
| 2026-10-17 | D-011 | Server-Side Recursive Validator & Hard Limits | Enforce 512KB payload, 10 tree depth, 250 nodes, 50 children per node | Prevents ReDoS, stack overflow, DB bloat on 2GB RAM instances | User / Architect |
| 2026-10-17 | D-012 | Symfony HTML Sanitizer for RichText XSS Prevention | Use standard W3C parser `symfony/html-sanitizer` over fragile regex | Bulletproof XSS mitigation (strips scripts, event handlers, javascript:/data: URIs) | User / Architect |
| 2026-10-17 | D-013 | Separation of Page Metadata Route (`/edit`) and Visual Editor (`/builder`) | Keep `/edit` for page metadata form and create dedicated full-screen `/builder` (alias `/editor`) | Preserves Phase 02 metadata flow; provides dedicated SaaS canvas layout | User / Architect |
| 2026-10-17 | D-014 | Viewport Switcher Architecture (Desktop 1280px, Tablet 768px, Mobile 375px) | Centralized presets config in `editor.ts` with ARIA radiogroup | Smooth frame transitions without modifying document schema or DB | User / Architect |
| 2026-10-17 | D-015 | Dynamic API Component Library Loading with Boundary Validation | Consume `GET /api/components/registry` dynamically with loading skeleton and error retry | Zero hardcoded list in frontend; single source of truth in backend registry | User / Architect |
| 2026-10-17 | D-016 | Native HTML5 Drag-and-Drop with Accessible Click-to-Insert | Avoid heavy DnD dependencies; use browser native DnD API + click-to-insert | Zero bundle bloat (<1KB); guaranteed accessibility for keyboard users | User / Architect |
| 2026-10-17 | D-017 | Bounded Immutable Snapshot Stack for Undo/Redo | Document size <=250 nodes (20-50KB); deep-clone stack capped at 30 entries | Zero mutation/inversion bugs, <0.1ms cloning time, <1MB RAM client memory | User / Architect |

## Template
### D-XXX — Short title
- **Date:**
- **Status:** Proposed / Accepted / Rejected / Superseded
- **Context:**
- **Options considered:**
- **Decision:**
- **Consequences:**
- **Evidence / links:**
