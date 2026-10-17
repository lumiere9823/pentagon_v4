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
| 2026-10-17 | D-018 | Responsive Styling Contract and Schema v1 Compatibility | Add optional `styles: { tablet?: {...}, mobile?: {...} }` with strict presentational allowlist | Preserves Schema v1 without version bump; deterministic cascade; backend validated | User / Architect |

## D-018 — Responsive Styling Contract and Document Schema v1 Compatibility
- **Date:** 2026-10-17
- **Status:** Proposed
- **Context:** Visual page builder requires viewport-specific styling overrides (Tablet 768px, Mobile 375px) while maintaining the 2GB server budget, preserving 100% backward compatibility with existing Schema v1 documents, and avoiding an unnecessary schema version bump.
- **Options considered:**
  1. *Schema Version Bump (v2):* Breaking change requiring DB migrations, revision transformations, and Dual-Schema validator support. Overkill for an optional presentational feature.
  2. *CSS String Injections (`customCss`):* Arbitrary CSS text area per component. High security risk, impossible to validate on server, unmaintainable.
  3. *Optional Structured Node Dictionary (`styles: { tablet?: {...}, mobile?: {...} }`):* Recommended. Maintains `schemaVersion: 1`, strictly typed against canonical component registry schemas, rejects structural and content overrides, enables deterministic cascade (Desktop -> Tablet -> Mobile).
- **Decision:** (Proposed for approval at Sprint 1 Gate A)
  1. Adopt optional `styles` dictionary on `DocumentNode`. Desktop values remain the root of the cascade in `props`.
  2. Permitted viewports in `styles` are strictly `tablet` and `mobile` (rejected if `desktop` or arbitrary keys are submitted).
  3. Mobile inherits Tablet when Mobile has no override (`mobile ?? tablet ?? desktop`). For published static CSS parity, the compiler evaluates the effective value at each breakpoint and emits standard mobile-first Tailwind utilities: base class for Mobile, `md:` for Tablet (if different from Mobile), and `lg:` for Desktop (if different from Tablet).
  4. Columns and CardGrid constraints: Retain canonical `[2, 3, 4]` constraint for both base `props` and responsive overrides; `1` is not permitted in the data contract (renderers already auto-collapse to 1 column at narrow screens).
  5. Component `display`: Not stored in base `props` (all 12 components are implicitly visible by default). Permitted in `styles` as `'visible' | 'hidden'`. Centralized handling in `NodeRenderer.vue` prevents disappearing unselectable elements in editor mode.
  6. PHP JSON Decoding: Safe handling of `{}` vs `[]` by pruning empty arrays without error; non-empty list arrays are rejected with `invalid_styles_format`.
  7. Node Duplication: `cloneNodeWithNewIds()` updated to preserve `styles` with fresh IDs for child nodes.
- **Consequences:** Existing documents without `styles` remain 100% valid. Zero data loss when normalising. Exact parity between editor canvas simulation and published CSS.
- **Evidence / links:** `PHASE_06_BA_TECHNICAL_PLAN.md` Section 3; `app/Domains/DocumentEngine/Validation/DocumentValidator.php`.

## Template
### D-XXX — Short title
- **Date:**
- **Status:** Proposed / Accepted / Rejected / Superseded
- **Context:**
- **Options considered:**
- **Decision:**
- **Consequences:**
- **Evidence / links:**

