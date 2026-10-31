# PENTAGON Page Builder - Capability Audit and Architecture Blueprint

Date: 2026-10-24
Scope: current working tree at `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c`
Status: research and planning only; no application behavior changed

## 1. Executive conclusion

PENTAGON already has a coherent, secure MVP document engine rather than a free-form page designer. It supports a validated tree of 12 registered component types, stable node IDs, bounded nesting, insert/delete/duplicate/sibling movement, snapshot undo/redo, autosave with optimistic locking, whole-page templates, immutable revisions/releases, server-side static HTML publishing, safe rich HTML, media ownership checks, and responsive overrides for a small allowlist of enum-valued properties.

The current ceiling is not the document tree. The main bottleneck is that one logical property is described and mapped in several places: PHP registry schemas, TypeScript defaults, hard-coded Inspector grouping/control rules, Vue preview renderers, TypeScript responsive presentation mappings, PHP publish mappings, and tests. This is workable for 12 small components but becomes increasingly shallow and error-prone as layout, typography, states, tokens, attributes, and reusable definitions grow.

A full rewrite is not justified. The existing `ComponentRegistry`, `ComponentDefinition`, document schema, validator, responsive representation, persistence, and immutable release pipeline provide usable seams. The recommended direction is to deepen the existing registry into the canonical property/capability contract, keep semantic markup component-specific, centralize reusable presentation primitives, and add parity/coverage tests that make an incomplete property implementation fail visibly.

Before adding controls, the first implementation slice must characterize and fix current pipeline correctness gaps: a first editor mutation can evade dirty tracking, canonical aliases validate but fail renderer dispatch, and the latent reparent mutation can exceed depth and child limits. These are source-backed and locally reproduced; existing green suites do not cover them.

## 2. Repository safety and audit basis

### 2.1 Git state before the audit

Verified with `git branch --show-current`, `git rev-parse HEAD`, `git remote -v`, `git status --short --branch`, `git diff --cached --stat`, `git diff --stat`, `git ls-files --others --exclude-standard`, and upstream resolution.

| Item | Verified state |
|---|---|
| Branch | `master` |
| HEAD | `ec7e07430c98ab24b59602eda2dfc44e628cee2c` |
| Remotes | none |
| Upstream | none; Git reports `no upstream configured for branch 'master'` |
| Staged changes | 0 files |
| Modified tracked files | 49 |
| Untracked files | 28 |
| Existing work | Phase 2A-2C implementation and documentation, including line-ending warnings in seven frontend test files |

The reported state was accurate. All pre-existing changes were treated as user-owned. No reset, checkout, stash, clean, delete, commit, push, migration, dependency change, or application edit was performed. `skills-lock.json` was not modified.

### 2.2 Sources read

- `page_builder_project_docs/SESSION_HANDOFF.md`
- `page_builder_project_docs/optimization/05-optimization-roadmap.md`
- `page_builder_project_docs/PENTAGON_SYSTEM_REFERENCE.md`
- `page_builder_project_docs/optimization/00-baseline-audit.md`
- `page_builder_project_docs/optimization/01-architecture-review.md`
- `page_builder_project_docs/optimization/04-security-reliability-review.md`
- Current registry, schema, validator, sanitizer, editor, Inspector, state, responsive, persistence, publishing, template, model, migration, fixture, and test sources cited below

Documentation was used as navigation and historical context. Current working-tree code and executed tests/reproductions are the evidence of current behavior.

## 3. Current document and component model

There is no registered `Page` component. A page is `DocumentSchema { schemaVersion, root, pageSettings }`; the root must be `Section`, `Container`, or `Hero`. Nodes have `id`, `type`, `props`, optional `styles.tablet/mobile`, and optional `children` (`resources/js/types/document.ts:19`, `app/Domains/DocumentEngine/Validation/DocumentValidator.php:122`).

Structural limits are 512 KiB, depth 10, 250 nodes, and 50 direct children (`app/Domains/DocumentEngine/DocumentLimits.php`; `resources/js/types/document.ts:93`). IDs are unique, 3-64 characters, and constrained to alphanumeric/underscore/hyphen. The server rejects unknown node props, unknown responsive props, unknown types, children on leaf nodes, unsafe URLs, malformed HTML-bearing values, and invalid enum/type values.

Parent/child compatibility is intentionally coarse: a type either accepts arbitrary registered children or is a leaf. There is no parent-type/child-type matrix, slot model, required-child model, or relationship between `Columns.count` and child count (`ComponentDefinition::$allowChildren`; `DocumentValidator::validateNode`).

## 4. Complete registered component inventory

Inspector groups below reflect actual key partitioning in `InspectorPanel.vue:83`, not registry metadata. Every registered prop is exposed, but controls and grouping are inferred from prop names/schema flags and the FAQ `items` key is special-cased.

| Type | Structural/content role | Children/root | Defaults used for new nodes | Inspector groups | Responsive props | Published renderer and validation notes |
|---|---|---|---|---|---|---|
| `Section` | Section/layout | children; root | `padding=medium`, `background=transparent` | Style: padding, background | padding, background, display | `<section>`; enum mappings to fixed vertical padding/background classes |
| `Container` | Container/layout | children; root | `maxWidth=lg`, `centered=true` | Style: maxWidth, centered | maxWidth, centered, display | `<div>`; fixed horizontal padding plus width/centering presets |
| `Heading` | Leaf/text | no children; not root | text `New Heading`, level 2, align left | Content: text; Style: level, align | align, display | Escaped `h1`-`h6`; level sets fixed size/weight classes |
| `RichText` | Leaf/text | no children; not root | starter paragraph HTML | Content: content | display only | Sanitized HTML in `<div>`; raw stored input is sanitized again during render |
| `Image` | Leaf/media | no children; not root | sample external src, alt, empty caption, aspect auto | Content: src/alt/caption; Style: aspectRatio | aspectRatio, display | `<figure><img>`; safe URL validation, lazy loading, publish-only non-empty src check |
| `Button` | Leaf/navigation/interactive control | no children; not root | label, `#`, primary, md, `_self` | Content: label/url; Style: variant/size; Settings: target | variant, size, display | Wrapper plus `<a role=button>`; safe URL; `_blank` adds `noopener noreferrer` |
| `Hero` | Content/layout composite | children; root | title/subtitle/center/CTA | Content: title/subtitle/ctaText/ctaUrl; Style: align | align, display | Fixed flex hero, optional CTA link, then arbitrary children |
| `Columns` | Layout | children; not root | count 2, gap medium | Style: count, gap | count, gap, display | CSS grid presets; count does not constrain child count or create named columns |
| `CardGrid` | Layout | children; not root | columns 3, gap medium | Style: columns, gap | columns, gap, display | CSS grid presets; semantically indistinguishable from Columns at schema level |
| `FAQ` | Leaf/content/interactive disclosure | no children; not root | one question/HTML answer | Content: items repeater | display only | Static `<details>/<summary>` output; answers sanitized; max 20 items server-side |
| `Spacer` | Leaf/layout | no children; not root | size medium | Style: size | size, display | Fixed height presets; editor adds a hover affordance only |
| `Divider` | Leaf/layout/visual | no children; not root | solid/muted | Style: style, color | style, color, display | Wrapper plus `<hr>` with fixed border style/color tokens |

Primary evidence: `app/Domains/DocumentEngine/Registry/ComponentRegistry.php:29`, `ComponentRegistry::registerCoreComponents`, `resources/js/types/document.ts:112`, `resources/js/components/editor/InspectorPanel.vue:83`, `resources/js/components/editor/canvas/NodeRenderer.vue:85`, and `DocumentHtmlRenderer::renderNode`.

Content-role gaps are explicit: no navigation/menu component, form component, video/audio, icon, list, reusable component instance, data-bound element, or generic HTML element is registered. The repository has a form-submission domain, but the builder registry has no form-building component.

## 5. Property pipeline traces

### 5.1 Heading alignment - supported end to end

1. Registry: `Heading.align` is enum `left|center|right`; responsive allowlist includes `align` (`ComponentRegistry.php:184`, `:32`).
2. Inspector: key is classified as Style and rendered as enum select (`InspectorPanel.vue:99`; `InspectorStyleGroup.vue:168`).
3. Mutation/history: desktop updates `props.align`; tablet/mobile updates `styles[viewport].align`; both record a debounced undo snapshot (`EditorShell.vue:357`, `:368`; `useDocumentState.ts:290`, `:310`).
4. Persistence: autosave sends the full document; server validates it and stores the submitted document under optimistic locking (`useAutosave.ts:109`; `PagePersistenceService.php:59`, `:173`).
5. Validation: base and responsive values use the same component schema; disallowed responsive keys fail (`DocumentValidator.php:495`).
6. Editor: responsive resolution produces viewport-stable `textAlign` independent of the real browser width (`responsive.ts:368`; `NodeRenderer.vue:103`).
7. Published output: base class is emitted in heading markup and node-specific responsive CSS overrides `text-align` (`DocumentHtmlRenderer.php:344`; `responsiveDeclarations`).

Status: fully supported within the enum contract. Limitation: typography beyond alignment and heading level is unavailable.

### 5.2 Section padding - supported responsive override with fixed vocabulary

Base `props.padding` is the desktop value. Tablet/mobile edits become overrides, mobile inherits tablet when absent, and Reset prunes the property, empty viewport object, then empty `styles` object (`InspectorPanel.vue:165`; `useDocumentState.ts:338`; `responsive.ts:125`). The validator accepts only allowed viewports and enum values. The editor maps the resolved enum to inline presentation; publishing emits node-ID CSS at 768/1024 (`DocumentValidator.php:452`; `responsive.ts:330`; `DocumentHtmlRenderer::responsiveStyleBlock`).

Status: fully supported. Limitation: vertical preset only; no per-side padding, margin, units, clamp, or token reference.

### 5.3 Button target - supported but base-only

`target` is a registry enum and falls into Settings because it is not in the hard-coded Content/Style sets. It persists as a base prop, validates, and renders `_self`/`_blank`; `_blank` gains a safe `rel` attribute (`DocumentHtmlRenderer.php:445`). It is not in the responsive allowlist.

Status: fully supported. Limitation: no disabled state, action type, download attribute, ARIA label, tracking attribute, state styling, or CTA abstraction shared with Hero.

### 5.4 Image source - supported with semantic validation limitations

The Inspector accepts a URL or media-library selection and may fill empty alt text (`InspectorContentGroup.vue:166`; `InspectorPanel.vue:190`). The server applies the generic safe-URL rule and media ownership scanning for managed `/storage` paths. Publishing adds a non-empty `src` semantic check with a node path. Renderer emits escaped `src/alt/caption` and fails closed outside preview (`HtmlSanitizer::isSafeUrl`; `DocumentValidator::collectPublishErrors`; `DocumentHtmlRenderer.php:399`).

Status: supported/partial. The generic URL rule allows `mailto:` and `tel:` for every `isUrl` prop, including `Image.src`; it does not validate image media kind or remote reachability. Alt text may be empty, and there is no width/height/srcset/sizes/focal point/crop/object-position control.

### 5.5 RichText and FAQ HTML - rendered safely, stored non-canonically

The Inspector edits HTML as plain textarea text. `ComponentDefinition` produces a sanitized document during validation, but `PagePersistenceService` stores the original submitted `$document`, and `DocumentHtmlRenderer::renderFragment` validates then renders the original decoded tree instead of the sanitized tree (`ComponentDefinition.php:260`, `PagePersistenceService.php:174`, `DocumentHtmlRenderer.php:91`). RichText/FAQ are sanitized again in their render methods, which protects published output.

Status: published output supported and guarded; persistence/canonicalization partial. Raw unsafe or non-canonical content can remain in drafts, revisions, and templates, increasing cross-layer drift. The editor sanitizer and Symfony server sanitizer are separate implementations and need parity fixtures.

### 5.6 Type aliases - UI/persistence/renderer inconsistency

The registry accepts `Rich Text` and `Card Grid` aliases and validator sanitized output normalizes them. Persistence stores the raw accepted document. Renderer validation succeeds, but dispatch matches the raw type string against canonical names (`ComponentRegistry.php:18`; `DocumentHtmlRenderer.php:224`, `:249`).

Executed in-memory reproduction: a valid Section containing type `Rich Text` passes `DocumentValidator` but `DocumentHtmlRenderer::render` throws `No render implementation for registered component 'Rich Text'.`

Status: present but broken end to end. Canonicalization must happen before persistence and rendering, or aliases must be removed from accepted document input.

### 5.7 First edit and publish - persistence coordination risk

`EditorShell` creates a non-immediate deep watch but initializes `isInitialMount=true`; therefore the first actual document mutation enters the callback, flips the flag, and returns without `notifyChange()` (`EditorShell.vue:125`). `handlePublish` only requests a save when `saveStatus !== 'saved'` (`EditorShell.vue:201`).

Executed watcher-pattern reproduction: first real mutation produced zero notifications; second produced one. Existing tests exercise `useAutosave` and publish protocol in isolation, but no test mounts the real `EditorShell` and proves first-edit -> dirty -> save -> publish ordering.

Status: unsafe until characterized/fixed. A single first edit can remain locally visible while publish compiles the previously persisted draft.

## 6. Capability matrix

Legend: Supported = proven end to end; Partial = useful but materially constrained or inconsistent; Absent = checked and not implemented; Unsafe = current path can violate an invariant; Unverified = no adequate runtime/browser evidence.

| Capability | Status | Applies to | Evidence and limitation | Recommendation |
|---|---|---|---|---|
| Stable unique node identity | Supported | all nodes | validator uniqueness/regex; duplication regenerates subtree IDs; tests in `DocumentValidatorTest`, `document-state.test.ts` | Keep/generalize as core invariant |
| Selection/hover | Supported | all nodes | `NodeRenderer` wrappers and `useDocumentState` refs | Keep |
| Insert/delete/duplicate | Supported | all except protected root actions | limits and subtree cloning tested | Keep; add integration coverage through mounted editor |
| Sibling movement | Supported | non-root | toolbar up/down only | Keep |
| Existing-node reparent | Unsafe/hidden | non-root | `moveNode` exists but is not wired to EditorShell; reproduced depth 11 and 51-child destination because depth math/capacity guard are wrong | Fix before exposing; then Layers/DnD |
| Parent/child rules | Partial | five container types | container-vs-leaf only; no slots/type matrix/count relationship | Generalize only where product semantics require it |
| Locking | Partial | root only | root cannot move/delete/duplicate; no node `locked` capability | Add with Layers if use cases justify |
| Visibility | Partial | all nodes | tablet/mobile `display=visible|hidden`; desktop base cannot be hidden; editor keeps hidden nodes selectable | Generalize responsive visibility; decide desktop semantics |
| Display mode | Absent | all | intrinsic block/flex/grid fixed in renderer maps | Implement selectively for structural types, not arbitrary on every semantic leaf |
| Flex controls | Absent | containers/Hero | Hero uses fixed flex only | Implement shared layout primitive after schema prerequisite |
| Grid controls | Partial | Columns/CardGrid | only 2/3/4 columns and three gaps; no rows, spans, auto-fit, alignment | Generalize a bounded grid primitive |
| Width/height/min/max | Partial | Container/Image/Spacer | preset maxWidth/aspect/height only | Implement bounded dimension values with units/tokens |
| Margin/padding/gap | Partial | Section/Columns/CardGrid/Spacer | fixed enums; no per-side controls | Generalize spacing primitive |
| Alignment | Partial | Heading/Hero | text alignment only; no flex/grid self/items/content controls | Add layout-specific alignment capabilities |
| Position/overflow/z-index | Absent | all | checked registry/schema/renderers | Defer until Layers and safe value design |
| Typography | Partial | Heading/RichText/Hero/Button | heading level/alignment and fixed component typography; no font family/weight/size/line-height/letter-spacing/color | High-value shared primitive; preserve semantic heading level separately |
| Rich text presentation | Partial | RichText/FAQ | safe HTML; `prose` class exists in markup but current built CSS contains no `.prose` rule and no typography dependency | Decide supported rich-text stylesheet and test output |
| Colors/backgrounds | Partial | Section/Divider/Button | small hard-coded semantic enums | Generalize through design tokens, not arbitrary raw colors first |
| Background image/gradient | Absent | structural nodes | no schema/Inspector/renderer mapping | Defer until media/token model exists |
| Borders/radii/shadows/opacity | Partial/absent | Divider/Button/Image fixed styles | baked classes, no user controls | Add bounded shared primitives selectively |
| Breakpoints | Supported/fixed | responsive-capable props | runtime contract 768/1024; canvas presets 375/768/1280 are separate | Keep fixed initially; avoid user-defined breakpoints |
| Responsive inheritance/reset | Supported | allowlisted props | base -> tablet -> mobile cascade, explicit reset/pruning; TS/PHP tests | Reuse model; expand capability metadata |
| Responsive order | Absent | layout children | no property or renderer mapping | Add only with structural controls |
| Hover/focus/active | Partial/absent | Button/FAQ and baked classes | fixed CSS behavior, not configurable | Add state layer after base property system |
| Disabled state/actions | Absent | Button | link-only model | Component-specific behavior; do not fake as generic style |
| Animation | Absent as capability | all | fixed transition classes only | Optional/defer; require reduced-motion policy |
| Whole-page templates | Supported | document | global/website templates copied on instantiate; schema version stored | Keep; distinguish from reusable components |
| Reusable/linked blocks | Absent | subtree | no definition/instance/link/update model | Later package; requires version/override decisions |
| Variants | Partial | Button enum only | ordinary prop, no design-system variant model | Generalize after tokens |
| Global styles/design tokens | Absent | all | no token document/registry/reference type | Foundational but after property contract |
| Document compatibility | Partial | document/releases | only schema v1 accepted; releases record schema/renderer version and remain immutable | Add explicit readers/upgraders before schema v2 |
| Custom CSS | Absent by design | page/node | unknown props rejected; static-first security docs exclude arbitrary CSS/JS | Explicitly exclude from early roadmap; later sandboxed/allowlisted option only |
| Supported HTML attributes | Absent except renderer-owned | nodes | IDs/classes/ARIA/rel/loading are generated, not user-defined | Add allowlisted typed attributes only |
| Custom component extensions | Absent | registry | adding a type requires PHP/TS/renderers/tests; no plugin interface | Defer public extension SDK; improve internal extension workflow first |
| Preview/published parity | Partial/unverified | all | server preview shares publisher, but canvas has separate Vue renderers and TS CSS mappings; DropZones/wrappers affect grid; no browser visual harness | Add browser parity harness before claiming parity |

## 7. Responsive implementation assessment (Phase 2B)

Phase 2B materially improved the product and is present in the working tree:

- `ComponentRegistry` publishes breakpoints and per-type overridable prop allowlists.
- Editor sessions hydrate that contract instead of globally caching it.
- Desktop base props plus tablet/mobile override dictionaries implement inheritance.
- Mobile resolves mobile -> tablet -> base; explicit `visible` can reset inherited hidden state.
- Editor presentation is computed from the selected canvas viewport rather than the actual browser width.
- Published HTML emits enum-safe node-specific CSS with media queries at 768 and 1024.
- Release renderer version is 2, while existing release files remain immutable.

The representation is adequate for expansion; a schema rewrite is unnecessary. Its limits are:

1. Only `props` values can be responsively overridden; there is no typed shared style namespace.
2. The allowlist and value-to-CSS mappings remain handwritten in PHP and TypeScript.
3. Desktop is represented by base props, so content semantics and presentation are mixed in the same dictionary.
4. Existing intrinsic Tailwind breakpoint classes and generated responsive declarations must remain ordered correctly.
5. Unit tests establish mapping behavior, not real-browser visual parity.

## 8. Architectural bottlenecks

### 8.1 Registry contract is useful but too shallow

`ComponentDefinition` already owns type, label, category, root/children capability, prop types, constraints, defaults, URL/HTML flags, validation, and public metadata. This is the correct seam to deepen. It currently lacks editor group/control/label/help, responsive declaration, semantic capability tags, and presentation mapping identity.

Because Inspector grouping is a list of prop names and controls infer behavior from `enum`, `isUrl`, `isHtml`, or special key `items`, a new valid property may silently land in Settings or receive the wrong control. This is a shallow interface: callers must know naming conventions and special cases.

### 8.2 Defaults are duplicated

Optional defaults exist in PHP schemas, while creation defaults live in `DEFAULT_COMPONENT_PROPS` in TypeScript. Required content values have no PHP default but need TypeScript insertion defaults. Compatibility tests cover selected facts, not every schema/default/renderer path. New properties can validate but be missing on insertion, or insert but not match server semantics.

### 8.3 Renderer parity is duplicated across languages

Vue component renderers, `responsive.ts`, and `DocumentHtmlRenderer` separately encode mappings. Semantic markup should remain component-specific, but reusable enum-to-CSS declarations should not need three independent implementations. The current split reduces locality: one visual change touches many modules and test sets.

### 8.4 Validation creates canonical output but callers ignore it

The validator has a high-value canonicalization interface, but save/template/render paths frequently continue with raw accepted input. This causes alias failure and retains unsanitized/non-defaulted data. The module is not earning full depth because callers must remember to sanitize again.

### 8.5 Mutation interface does not enforce all document invariants

`useDocumentState` enforces many limits for insert/duplicate, but `moveNode` can produce a tree the server later rejects. Executed reproductions moved a three-level subtree beneath a depth-8 target and produced depth 11, and moved into a 50-child target to produce 51 children. Mutation success should mean the document remains valid.

### 8.6 Inspector R-15 improved ownership, not property expressiveness

R-15 successfully splits stable group modules while keeping document mutation in the parent. It is a good seam for adding schema-driven controls. It does not remove hard-coded grouping, special control rules, or the full-document deep-watch/save coupling.

### 8.7 Release compatibility is stronger than authoring compatibility

Immutable release HTML plus `renderer_version` protects already published output. Drafts/templates/revisions store schema versions, but there is no version-reader/upgrader pipeline beyond accepting version 1. Adding optional props with defaults is compatible; changing value shapes or semantics requires explicit compatibility work.

## 9. Target architecture

### 9.1 Deepen the existing registry; do not add a parallel framework

Recommended logical modules:

1. **Component Contract module** - the existing `ComponentRegistry`/`ComponentDefinition` seam, extended so each property declares:
   - value kind and constraints;
   - canonical default or insertion default;
   - Inspector group, label, help text, control kind, and control options;
   - responsive eligibility;
   - semantic role (`content`, `layout`, `style`, `behavior`, `attribute`);
   - optional presentation primitive key.
2. **Document Canonicalizer module** - validation returns the one canonical document used by persistence, templates, revisions, preview, and publishing. Alias normalization, defaults, and HTML sanitization happen once at the seam.
3. **Presentation Primitives module** - bounded values such as spacing, max width, alignment, grid count/gap, typography scale, color token, border, and visibility. It maps validated values to safe CSS declarations/classes and exposes an editor-consumable contract. It must not own semantic HTML.
4. **Component Renderers** - retain component-specific markup, accessibility, content behavior, and composition. They consume canonical props and shared primitives.
5. **Document Mutation module** - every public mutation preserves node/depth/child/cycle/root invariants and returns a result; tests use this same interface.
6. **Compatibility module** - selects schema reader/upgrader and renderer version. Add it when a second schema shape is actually necessary, not preemptively.

This design increases leverage and locality without a new dependency, database migration, or generic schema framework.

### 9.2 Capability Registry evaluation

A separate registry would duplicate `ComponentRegistry`. Treat capabilities as structured metadata on each component definition and expose a derived capability view to the editor. Examples: `canContainChildren`, `rootEligible`, `layout: grid`, `supports: [spacing, background, visibility]`, `states: []`, `attributes: [linkTarget]`.

Capability tags must describe supported contracts, not automatically enable arbitrary CSS. Component-specific constraints remain possible: Heading owns semantic `level`; Image owns `alt` and media validation; Button owns navigation behavior; FAQ owns item structure.

### 9.3 Responsive model

Keep base values plus tablet/mobile sparse overrides for now. Generalize resolution and reset across all responsive-capable typed properties. Do not add user-defined breakpoints until product need and output cost are proven. If future token references or composite values cannot fit `Record<string, unknown>`, introduce schema v2 with an explicit upgrader; do not silently reinterpret v1.

### 9.4 Shared versus component-specific concerns

Generalize:

- control metadata and value coercion;
- enum/number/boolean/string/token/reference validation;
- responsive inheritance/reset;
- spacing, sizing, alignment, grid/flex, typography, color, border, radius, shadow, opacity primitives;
- safe attribute names/value kinds;
- parity/coverage tests.

Keep component-specific:

- semantic tag and accessible markup;
- Image/media rules, Button/CTA navigation, FAQ disclosure/item schema;
- allowed child slots where meaningful;
- publish-only semantic checks;
- natural responsive behavior that is intrinsic to a component.

### 9.5 Example: add `Section.minHeight`

1. Add one property descriptor to Section: enum `auto|screen|half-screen`, default `auto`, group Layout, select control, responsive true, primitive `minHeight`.
2. Registry API exposes the descriptor; no Inspector key-set edit is required.
3. New-node defaults are derived from the contract (or a generated checked artifact), not hand-copied.
4. Desktop mutation writes `props.minHeight`; tablet/mobile write sparse overrides and Reset prunes them.
5. Canonicalizer validates and persists the canonical value.
6. Shared presentation primitive maps the enum to safe declarations; Vue canvas and PHP renderer consume the same declared mapping or a generated parity fixture.
7. Tests required: registry metadata, invalid value rejection, insertion default, Inspector control, mutation/history/autosave, responsive cascade/reset, PHP output CSS, Vue presentation, preview/public browser parity, old v1 document default behavior, and immutable old-release behavior.

## 10. Prioritized implementation roadmap

The order below adjusts the requested themes to address demonstrated correctness risks before expanding capability.

### Package 1 - Authoring correctness and canonical property contract (foundation, high priority, M/L)

**Outcome:** one edit is never lost or published stale; every accepted document has a canonical representation; adding a property has an explicit complete path.

**Current gap/evidence:** first-edit watch suppression; raw persistence/rendering; alias render failure; incomplete mutation invariants; duplicated defaults and Inspector metadata.

**Likely modules:** `EditorShell.vue`, `useAutosave.ts`, `useDocumentState.ts`, `ComponentDefinition.php`, `ComponentRegistry.php`, `DocumentValidator.php`, page/template persistence, `DocumentHtmlRenderer.php`, registry types/Inspector helpers.

**Acceptance criteria:**

- First actual mutation marks dirty, autosaves, and cannot publish before that exact document is saved.
- Changes arriving during an in-flight pre-publish save cannot be published stale.
- All accepted aliases are canonicalized before storage/render, or aliases are rejected at input.
- Persistence, templates, revisions, preview, and publish use canonical validated documents.
- Every public tree mutation preserves max depth, max children, node total, root, leaf, uniqueness, and cycle invariants.
- Registry metadata explicitly declares Inspector group/control/responsive support for every prop.
- A contract coverage test fails when a property lacks a default strategy, control, validation path, editor presentation where needed, or publish mapping.

**Tests:** mounted real `EditorShell` first-edit/save/publish; in-flight edit race; PHP alias/canonicalization; raw HTML canonical persistence; mutation property tests around limits; registry snapshot/coverage; existing persistence/concurrency/release suites.

**Migration/release:** no database migration required. Canonicalizing future saves changes draft JSON/hash/revision content; document and media hash consequences must be characterized. Existing release files remain unchanged.

### Package 2 - Shared Element and Section style primitives (user-visible, L)

**Outcome:** coherent bounded controls for spacing, dimensions, typography, colors, backgrounds, borders, radii, shadows, opacity, and alignment on explicitly capable components.

**Gap:** current small enums and baked classes; no unified primitive catalog.

**Likely modules:** enriched registry descriptors, Inspector control modules, responsive resolver, Vue/PHP presentation mappings/renderers, validator tests.

**Dependencies:** Package 1.

**Acceptance:** each primitive has typed values, defaults, component capability list, responsive behavior decision, editor/public mapping, sanitization rule, and parity tests. Heading semantic level remains separate from visual typography. No raw CSS strings.

**Tests:** descriptor/control tests, invalid values, responsive matrices, renderer snapshots/declarations, browser parity at 375/768/1280, accessibility contrast decisions where tokens are selectable.

**Migration/release:** optional defaults keep schema v1 compatibility; composite/token value shapes may require schema v2 and upgrader. Relative effort L; parity and CSS cascade are main risks.

### Package 3 - Structure and Layers (M then L)

**Outcome:** searchable tree, select parent/child, lock/hide, keyboard navigation, then safe reorder/reparent.

**Gap:** canvas-only selection and sibling buttons; latent unsafe `moveNode`; no layers/lock.

**Likely modules:** new Layers UI, document mutation module, node toolbar, selection/focus state.

**Dependencies:** Package 1 invariant-safe mutations.

**Acceptance:** read/select/navigation first; no mutation in Viewer mode; root/locked behavior explicit; reparent rejects cycles, invalid parent/slot, depth >10, child >50; keyboard alternative to pointer DnD; undo/redo/autosave integration.

**Tests:** mutation boundary/unit tests, mounted integration, keyboard/a11y, browser DnD, deep-tree performance. No schema migration for UI-only Layers; `locked`/desktop visibility would require documented optional fields.

### Package 4 - Responsive expansion and ordering (M/L)

**Outcome:** all eligible new primitives support predictable inheritance/reset; layout ordering and alignment become responsive where explicitly supported.

**Gap:** responsive engine is sound but property list/mappings are manual and narrow.

**Dependencies:** Packages 1-2; Package 3 for child ordering.

**Acceptance:** registry-derived capability, base/tablet/mobile source shown in Inspector, reset prunes data, editor/public declaration parity, no dependence on actual browser width, deterministic CSS ordering.

**Tests:** exhaustive value x viewport contract fixtures in TS/PHP plus browser screenshots. Keep breakpoints fixed unless separately approved.

### Package 5 - Design tokens and reusable definitions (L/XL)

**Outcome:** website-level semantic color/type/spacing tokens; reusable subtree definitions with explicit instance/update behavior.

**Gap:** templates are copied whole documents; no linked instances, tokens, variants, or component versions.

**Dependencies:** typed property values and compatibility module.

**Acceptance:** token fallback/deletion/rename rules; tenant isolation; release captures immutable resolved output; reusable definition version and instance override policy; detach workflow; circular-reference prevention.

**Tests:** token resolution, missing tokens, definition upgrades, overrides/detach, cross-tenant authorization, publish immutability. This likely needs data migrations and must be split into tokens, reusable snapshot blocks, then linked/versioned instances.

### Package 6 - Advanced behavior and data binding (optional, XL)

**Outcome:** deliberate interactive/form/data components without arbitrary code execution.

**Gap:** only links and static FAQ disclosure; form backend is not represented in the builder.

**Dependencies:** capability/compatibility/security contracts.

**Acceptance:** component-specific action schemas, allowlisted targets, static-first fallback, CSP compatibility, form validation/spam/PII policy, publish-time data snapshot versus runtime-fetch decision.

**Tests:** security abuse cases, keyboard/a11y, public integration, failure/offline states. Defer generic data binding until concrete product stories exist.

### Package 7 - Custom CSS and extension capabilities (explicitly deferred/excluded by default, XL/high risk)

**Outcome:** only if approved, a narrowly scoped extension model.

**Gap:** arbitrary CSS/JS and client components are current non-goals and incompatible with the strongest static/security assumptions.

**Dependencies:** token/style system, CSP threat model, compatibility/versioning, operational support model.

**Acceptance before implementation:** decide tenant trust, selector scoping, URL/font/import policy, size limits, sanitizer/parser, CSP, preview/public parity, rollback, and support/version contract. Arbitrary JavaScript should remain excluded. Prefer allowlisted attributes and bounded CSS custom properties over raw stylesheets.

## 11. Test strategy

### Contract/unit

- Registry completeness for every component/property/capability.
- Canonicalization and version-reader fixtures.
- Mutation invariants at exact limits and randomized tree operations.
- Responsive resolution/reset and CSS declaration mapping.
- Sanitizer parity corpus for RichText/FAQ URLs/attributes.
- Renderer coverage for every registered type and mapped property.

### Integration

- Real Inspector -> mutation -> history -> autosave -> persistence -> revision.
- First-edit and edit-during-save publish ordering.
- Template save/instantiate canonical documents and media ownership.
- Preview and release render the same canonical revision.
- Old documents without new optional props retain prior behavior.

### Browser

The repository currently has mounted DOM tests but no Playwright/Cypress/browser-provider fixture for the authenticated builder. Add a disposable authenticated page fixture and compare editor, server preview, and sealed public release at 375/768/1280. Cover grid wrappers/drop zones, hidden selection, rich text, FAQ, typography, keyboard Layers/DnD, focus, and axe critical/serious findings. Until this exists, do not claim visual parity.

### Release compatibility

Every renderer behavior change must prove: existing active release files are byte-untouched; new releases record the intended renderer version; rollback still serves old files; schema upgraders do not mutate historical revision/release rows in place.

## 12. Verification performed

Executed safe targeted tests against the current working tree:

```text
npm run test:frontend -- tests/frontend/document-state.test.ts tests/frontend/inspector-helpers.test.ts tests/frontend/inspector-groups.test.ts tests/frontend/inspector-interactions.test.ts tests/frontend/renderer-registry-compat.test.ts tests/frontend/responsive.test.ts tests/frontend/use-autosave.test.ts tests/frontend/publish-guard.test.ts tests/frontend/publish-protocol.test.ts
Result: 9 files, 72/72 tests passed.

php artisan test tests/Unit/DocumentEngine/ComponentRegistryTest.php tests/Feature/DocumentEngine/ComponentRegistryApiTest.php tests/Unit/DocumentEngine/DocumentValidatorTest.php tests/Unit/DocumentEngine/HtmlSanitizerTest.php tests/Unit/Publishing/DocumentHtmlRendererTest.php tests/Feature/Pages/PagePersistenceTest.php tests/Feature/Publishing/PagePublishingControllerTest.php tests/Feature/Publishing/PagePreviewControllerTest.php tests/Feature/Publishing/ReleaseStagingTest.php
Result: 96/96 tests passed, 491 assertions.
```

Additional read-only/in-memory reproductions established:

- first real mutation in the current watcher pattern produces no dirty notification;
- alias `Rich Text` validates but fails renderer dispatch;
- `moveNode` can produce depth 11;
- `moveNode` can produce 51 children.

No fresh browser visual run, GitHub-hosted CI run, PostgreSQL integration run, full build, or deployment test was performed for this audit. Prior handoff claims remain historical evidence, not newly re-executed evidence.

## 13. Risks and unresolved decisions

1. Should valid documents be stored only in canonical sanitized form? Recommendation: yes, after hash/revision/media regression characterization.
2. Should aliases remain accepted external input? Recommendation: canonicalize at the validation seam or remove them; never persist/render raw aliases.
3. Is desktop visibility needed, or is desktop always-visible intentional?
4. Which structural nodes receive generic flex/grid controls, and which preserve fixed semantic layout?
5. Are style values semantic tokens only, or may editors enter bounded literals/units?
6. What is the desired relationship between Columns count and children: free-flow grid, named slots, or enforced count?
7. Should restore clear undo history or create an undo boundary? Existing roadmap leaves this open.
8. What is the reusable component instance/update/override model?
9. Is self-contained inline CSS mandatory for every release, or may immutable versioned assets be retained?
10. Is custom CSS still a non-goal? Recommendation: keep it excluded until token/primitives satisfy proven needs and a threat model is approved.

## 14. Recommended first slice

Start with Package 1, not new visual controls. The minimum acceptance slice is:

1. Add mounted `EditorShell` coverage proving first edit marks dirty and publish waits for persistence of that exact document, including an edit during an in-flight save.
2. Make validation/canonicalization output the document used by persistence and rendering; add the alias regression.
3. Repair and test `moveNode` depth/child invariants before any Layers/DnD exposure.
4. Extend registry property metadata with explicit group/control/responsive/default strategy and replace Inspector key-name partitioning for the existing 12 components.
5. Add one no-new-feature contract coverage test proving every existing property is wired through validation, Inspector, and renderer expectations.

This slice gives a reliable deep interface for subsequent property work, changes no public visual design by intent, requires no database migration, and has a bounded rollback surface.

## 15. Git state after the audit

Final verification after writing this report:

| Item | State |
|---|---|
| Branch / HEAD | `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c` |
| Remotes / upstream | none / none |
| Staged changes | 0 files |
| Modified tracked files | 49, unchanged from before the audit |
| Untracked files | 29: the original 28 plus this report |
| Audit delta | only `page_builder_project_docs/optimization/06-page-builder-capability-architecture-audit.md` |

The existing roadmap and handoff were not edited because all packages in this report are proposed work, not approved or completed work. Existing line-ending warnings remain untouched.

## 16. A1 implementation result - dirty tracking and save/publish integrity

Date: 2026-10-24

### Verified task baseline

- Branch / HEAD: `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c`.
- Remotes / upstream: none / none.
- Staged changes: 0 files.
- Existing content diffs: 49 tracked files, plus pre-existing line-ending status warnings in frontend tests.
- Existing untracked files: 29, including this audit, the Phase 2A-2C handoff/work, and `skills-lock.json`.
- No reset, checkout, stash, clean, delete, commit, push, migration, dependency change, API change, or database-schema change was performed. `skills-lock.json` was not modified.

### Reproduced root cause

The mounted `EditorShell` regression test failed before the fix with the first Inspector edit still reporting `saveStatus = saved`. The exact cause was the non-immediate document watcher combined with `isInitialMount = true`: Vue evaluates the initial watch source without invoking the callback, so the callback's first invocation is already the first real mutation. The guard discarded that mutation and never called `notifyChange()`.

The same investigation confirmed three adjacent integrity gaps:

1. `saveNow()` returned `true` after the request snapshot succeeded even when a newer mutation had arrived during that explicit pre-publish save. The newer edit was queued for a later autosave, but publish could start first and compile the older draft.
2. `pendingChangeAfterSave` was a plain boolean used by a computed property, so an edit arriving while `saveStatus` remained `saving` was not reactively exposed through `isDirty` until another reactive state changed.
3. The one-shot internal-update boolean could remain armed when a restored revision serialized identically to the current document, causing the next real edit to be ignored.

### Mutation, persistence, and release trace

1. `InspectorPanel` emits `update-prop`, `update-style`, or `reset-style` to `EditorShell`.
2. `EditorShell` records history and delegates document mutation to `useDocumentState`.
3. `EditorShell` observes the serialized document value and calls `useAutosave.notifyChange()` only when persisted document content changes.
4. `useAutosave` marks the draft dirty, debounces autosave, serializes the current document through `requestJson`, and preserves optimistic `lock_version` handling, retries, errors, and conflicts.
5. `PageContentController` delegates to `PagePersistenceService`, which validates, checks media, locks the page, rejects stale lock versions, stores `document_json`, increments `lock_version`, and applies the existing revision policy.
6. `createPublishProtocol` retains `publishGuard.ts` save-before-publish ordering and double-submit protection. Explicit saves now drain edits queued during their requests before resolving success.
7. `PagePublishingController` invokes `PagePublishingService`, which locks and validates the persisted draft, selects or creates an immutable revision, creates a pending release, renders/seals it, and atomically activates it without mutating an existing sealed release.

### Implemented fix

- `EditorShell.vue` now watches `JSON.stringify(document.value)`. The watch source establishes its initial baseline automatically, catches the first real edit, and does not fire for assignments that leave the persisted document semantically unchanged.
- Revision restoration records the exact serialized internal document instead of arming a blind one-shot boolean. Identical restores no longer suppress the following user edit.
- `useAutosave.ts` makes queued-change state reactive. Error, conflict, and queued-change states remain exposed as unsaved.
- A non-autosave/manual save, including the pre-publish save, immediately saves again if a mutation arrived during its request and returns success only after the latest queued document succeeds. Failure or conflict in any required save stops publish through the existing protocol and guard.
- Normal autosave behavior remains debounced: an edit during an autosave stays queued and schedules the next autosave after the in-flight request completes.

### Invariants verified

| Invariant | Evidence |
|---|---|
| First meaningful Inspector edit becomes dirty | Mounted real `EditorShell` test; red before fix, green after fix |
| Semantic no-op does not dirty or autosave | Same-value edit tested before and after a successful save |
| First edit after save, undo/redo, and identical revision restore is tracked | Mounted toolbar/history/restore tests |
| In-flight save cannot mark a newer edit saved | Existing autosave queue test plus reactive pending-state coverage |
| Failed save preserves local changes and retry payload | Mounted failed pre-publish save/retry test; `isDirty` and unload warning assertions |
| Save-before-publish is mandatory | Exact PUT-before-POST request ordering asserted |
| Save failure/conflict prevents publish | Mounted failure test plus existing `publishGuard.ts`, protocol, and conflict tests |
| Double-submit protection remains intact | Existing publish protocol busy-state test remains green |
| Responsive override/reset participates in dirty tracking | Mounted override, reset, pruning, and no-op reset assertions |
| Mutation during pre-publish save is persisted before publish | Mounted request race asserts PUT, PUT, POST with the second document |
| Mutation during publish remains dirty and autosaves next | Mounted deferred publish request test verifies the queued document payload |
| Release creation remains immutable and fail-closed | Existing publishing, staging, preview, and reliability suites pass; server code unchanged by A1 |

### Tests and quality gates

The red reproduction command was:

```text
npm run test:frontend -- tests/frontend/editor-save-integrity.test.ts
Result before fix: 1 failed; expected dirty, received saved.
```

Final results:

```text
Targeted integrity set: 6 files, 49/49 tests passed.
npm run check: pass; 121 files formatted, 120 files lint-clean.
npm run types:check: pass.
npm run test:frontend: 18 files, 124/124 tests passed.
npm run build: pass; 3,538 modules; Builder 139.83 kB raw / 37.89 kB gzip.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 308 discovered, 282 passed, 26 PostgreSQL-only skipped, 1,390 assertions.
git diff --check: pass.
```

The build retained the existing non-blocking optional `fontaine` warning. No browser/E2E, visual parity, GitHub-hosted CI, or fresh PostgreSQL concurrency run was performed. The 26 PostgreSQL-only tests are therefore explicitly unverified in this A1 session rather than claimed from historical results.

### Remaining risks and scope boundary

- Serialized watching traverses the full bounded document on mutation; this is comparable in scope to the prior deep watcher but was not separately profiled at the 512 KiB limit.
- A user edit that occurs after the publish POST has begun cannot be part of that already-started release. It remains dirty and is autosaved next; it is not lost or falsely marked saved.
- Canonical document persistence, alias normalization, reparent limits, property/capability schema work, and Flexbox/Grid controls remain outside A1 and unchanged.
- Browser visual parity and PostgreSQL-specific publish concurrency remain unverified in this task.

### Git state after A1

- Branch / HEAD and remote/upstream state are unchanged.
- Staged changes remain 0 files.
- Content diffs are now 50 tracked files; the only newly content-modified tracked test is `tests/frontend/use-autosave.test.ts`, while `EditorShell.vue` and `useAutosave.ts` already contained Phase 2 changes and were edited in place.
- Untracked files are now 30; the only new file is `tests/frontend/editor-save-integrity.test.ts`.
- No commit or push was created.

## 17. A2 implementation result - canonicalization and alias dispatch

Date: 2026-10-24

### Reproduction and root cause

- Current registry aliases are exactly `Rich Text -> RichText` and `Card Grid -> CardGrid`, declared in `ComponentRegistry`.
- Pre-fix reproduction passed `DocumentValidator`, whose sanitized document contained `RichText`, then failed `DocumentHtmlRenderer` with `No render implementation for registered component 'Rich Text'.`
- Root cause: validation already produced the canonical document, including canonical types, schema defaults, pruned styles, and sanitized RichText/FAQ HTML, but render, page persistence, revision restore, template persistence/instantiation, starter-template seeding, and publish snapshot selection continued with raw input.

### Canonicalization contract implemented

- `ValidationResult::getSanitizedDocument()` remains the single existing internal contract; no parallel registry or canonicalizer framework was added.
- Successful validation returns a deterministic canonical document. Revalidating that result returns the same structure.
- Only aliases declared by `ComponentRegistry` are accepted and mapped. Unknown component types still fail closed.
- Canonicalization preserves valid nested children, IDs, page settings, media URLs, and user content; applies existing optional defaults; and applies the existing server HTML sanitizer to RichText and FAQ answers.
- A missing canonical result after nominal validation is treated as a failure and blocks persistence or publishing.

### Consumers updated

- `DocumentHtmlRenderer` renders and derives page settings from the canonical validation result in preview and publish modes.
- `PagePersistenceService` scans media, stores drafts, hashes/creates revisions, and restores revision copies from canonical data while leaving historical revision rows unchanged.
- `PageTemplateController`, `TemplateInstantiationService`, and `StarterTemplateSeeder` store or return canonical template documents and scan media against that same data.
- `PagePublishingService` hashes/selects/creates the publish revision from canonical data, compares the latest revision by canonical semantics to avoid unnecessary duplicates, scans the canonical snapshot, and leaves `pages.document_json`, historical revisions, and existing sealed release files untouched.
- Release staging can render a legacy revision containing an accepted alias, but does not rewrite that revision.

### Verified compatibility

- Repeated alias/default/sanitization input persists the same canonical document and does not create an extra revision under the existing hash deduplication policy.
- Optimistic locking remains unchanged and existing stale-lock tests pass.
- Media ownership and availability checks run on canonical data; focused template/page/publish media suites pass.
- Publishing canonicalizes the selected snapshot without silently rewriting the working draft. Existing sealed `index.html` and `manifest.json` files remain byte-for-byte unchanged after a later publish.
- Invalid drafts/templates do not persist, and invalid non-null publish documents do not create revisions or releases.

### Verification

```text
Red renderer regression: 1 failed before fix with the documented Rich Text dispatch exception.
Focused A2 PHP set: 128/128 tests passed, 699 assertions.
npm run check: pass; 121 formatted, 120 lint-clean.
npm run types:check: pass.
npm run test:frontend: 18 files, 124/124 tests passed.
npm run build: pass; 3,538 modules; Builder 139.83 kB raw / 37.89 kB gzip.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 318 discovered, 292 passed, 26 PostgreSQL-only skipped, 1,475 assertions.
git diff --check: pass with the pre-existing frontend line-ending warnings only.
```

No browser/E2E, hosted CI, or fresh PostgreSQL concurrency run was performed. A3 tree mutation invariants are complete; A4 registry-driven Inspector metadata and Enhanced Page Builder features remain pending.

## 18. A3 implementation result - tree mutation invariants

Date: 2026-10-25

### Reproduction and root cause

- The depth regression moved a three-level subtree below a depth-8 destination. `moveNode` returned success and produced depth 11 because `getMaxDepth(search.node)` was already subtree-local, but the implementation subtracted the source node's old absolute depth before evaluating the destination.
- The capacity regression moved a node into a destination with 50 direct children. `moveNode` returned success and produced 51 children because it did not check destination capacity.
- Boundary review also reproduced duplicate generated IDs under repeated random/time entropy, non-atomic rejected insertion when an absent `children` array was initialized before depth rejection, unconditional invalid replacement, and undo history consumption after replacement rejection.

### Invariant-safe mutation contract

| Public operation | A3 invariant coverage |
|---|---|
| Insert | Total 250, direct children 50, depth 10, leaf eligibility, valid unique generated ID; rejection no longer initializes `children` early. |
| Delete | Root prohibited; subtree removal preserves remaining tree; selected node/descendant selection clears only on success. |
| Duplicate | Root prohibited; total/direct-child limits retained; subtree depth unchanged; all clone IDs are fresh and unique even when entropy repeats. |
| Sibling reorder | Root prohibited; no node/depth/child-count change; existing behavior retained. |
| Reparent/move | Root/self/descendant/missing/leaf target rejected; full subtree height used; 49 -> 50 allowed, 50 -> 51 rejected, same-parent 50 allowed; IDs and total count preserved. |
| Replace/restore | Full structural validation occurs before assignment; invalid replacement returns failure and preserves document/selection. Revision restore state updates only after success. |
| Undo/redo | Valid reparent round-trips with stable IDs; rejected snapshot leaves document, past, and future unchanged. |

`DOCUMENT_LIMITS` remains the only client limit source. The existing root and container eligibility contract is reused; no parallel tree-management framework or new product semantics were introduced.

### Tests and quality gates

```text
Pre-fix depth regression: failed; expected success false, received true.
Pre-fix 50-child regression: failed; expected success false, received true.
Additional red proofs: invalid replacement returned undefined; rejected undo returned true; repeated entropy duplicated IDs; rejected insert changed serialized JSON.
Focused A3 frontend: 2 files, 32/32 tests passed.
npm run check: pass; 121 formatted, 120 lint-clean.
npm run types:check: pass.
npm run test:frontend: 18 files, 138/138 tests passed.
npm run build: pass; 3,538 modules; Builder 142.12 kB raw / 38.61 kB gzip.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 318 discovered, 292 passed, 26 PostgreSQL-only skipped, 1,475 assertions.
git diff --check: pass.
```

The build retains the existing non-blocking optional `fontaine` warning. Browser/E2E, hosted CI, deployment, and a fresh PostgreSQL concurrency run were not performed. No Layers/DnD UI, A4 Inspector metadata, enhanced layout controls, schema/migration, dependency, or database change was included.

Final Git state remained `master` at `ec7e07430c98ab24b59602eda2dfc44e628cee2c`, without remote/upstream or staged changes. `git status` marked 66 tracked files modified (62 content diffs) and 30 untracked files; all pre-existing user changes were preserved and no forbidden Git or destructive operation was used.

## 19. A4 implementation result - schema-driven Inspector metadata

Date: 2026-10-25

### Canonical property-capability contract

- The existing `ComponentDefinition` property schema is the sole source for validation, defaults, and constraints. Inspector annotations are colocated with each registered property; no second registry or independent property schema was introduced.
- Public Inspector descriptors contain a stable property path, label, group, bounded control type, responsive capability, and only the constraints already present in the property schema.
- Responsive flags are derived from `ComponentRegistry::RESPONSIVE_OVERRIDABLE_PROPS`. The common visibility descriptor uses `styles.*.display` and the existing `visible|hidden` model.
- The supported control set is limited to current behavior: text, textarea, URL, media URL, select, toggle, and FAQ list. No speculative conditional visibility or future layout/style capabilities were added.
- The registry API exposes the existing A2 aliases, and the editor resolves metadata through that canonical alias map.

### Inspector migration and safety

- `InspectorPanel.vue` removed the content/style key allowlists and fallback settings bucket. Groups now come only from validated registry metadata.
- `InspectorContentGroup.vue`, `InspectorStyleGroup.vue`, and `InspectorSettingsGroup.vue` render labels and controls from the typed descriptor contract while preserving current UI structure.
- Metadata is rejected client-side if it references an undeclared property, an arbitrary path, a duplicate path, an unsupported control, or an incompatible control/schema combination.
- Values are coerced and checked before mutation. Unsupported enums, non-integer numeric selections, explicit unsafe URL schemes, maximum lengths/items, and malformed FAQ list items do not reach document state.
- Existing mutation ownership is unchanged: `InspectorPanel` emits, `EditorShell` records history, and `useDocumentState` mutates. Responsive update/reset and inheritance remain on the existing pathways.
- Media selection is pinned to the node that opened the picker, preventing a late modal event from mutating a newly selected node.

### Coverage and gates

- Table-driven PHP coverage asserts exact Inspector metadata for every property of all 12 registered components, including path/control/group and responsive alignment.
- Mounted frontend coverage verifies metadata-driven content/style/settings edits through `useDocumentState`, invalid-value rejection, responsive override/inheritance/reset, node-switch isolation, stale media selection, malformed metadata, and unknown component safety.
- Existing Inspector, responsive, document state/history, A1 save/publish integrity, autosave, publish protocol, persistence, publishing, release staging, and A3 mutation suites remain green.

```text
Focused A4 frontend: 6 files, 40/40 tests passed.
Focused PHP regression: 69/69 tests passed, 555 assertions.
npm run check: pass; 121 formatted, 120 lint-clean.
npm run types:check: pass.
npm run test:frontend: 19 files, 144/144 tests passed.
npm run build: pass; 3,538 modules; Builder 142.11 kB raw / 38.98 kB gzip.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 319 discovered, 293 passed, 26 PostgreSQL-only skipped, 1,674 assertions.
git diff --check: pass.
```

No browser/E2E, visual regression, hosted CI, deployment, or fresh PostgreSQL concurrency run was performed. The existing optional `fontaine` warning remains non-blocking. No dependency, database schema, migration, persisted document format, or release format changed.

## 20. Package 2 implementation result - shared style primitives

Date: 2026-10-25

### Result

Package 2's bounded first slice is complete and stops before Package 3. The implementation adds a reusable semantic-value contract without creating a second component registry or a generic CSS engine:

- `resources/page-builder/style-primitives.json` defines stable primitive IDs, category, scalar type, ordered enum values, and responsive eligibility for spacing, dimensions, typography, and color/background capabilities.
- `ComponentRegistry` remains the only component/property applicability authority. `stylePrimitive` is colocated with each A4 property schema, and `ComponentDefinition` rejects unknown or incompatible primitive metadata at construction.
- The registry API exports the contract. The TypeScript client compares it with the locally imported shared JSON before accepting component metadata; malformed or mismatched primitive descriptors are filtered from the Inspector.
- Vue presentation uses bounded helpers plus the selected editor viewport. PHP presentation uses deterministic allowlisted classes and responsive declarations at the existing 768/1024 breakpoints. No document value is interpolated as an arbitrary CSS property, selector, class, URL, or declaration.

### Capability matrix

| Category | Primitive and support | Compatibility/default behavior |
|---|---|---|
| Spacing | `Section.padding`; shared `Heading/RichText.blockSpacing`; existing `Columns/CardGrid.gap`; `Spacer.size` | Existing values/defaults retained; text default preserves `my-2` |
| Dimensions | `Container/Heading/RichText.maxWidth`; `Image.aspectRatio`; `Button.size` | Container remains `lg`; text defaults `full`; old documents render with prior defaults |
| Typography | shared `Heading/RichText.fontSize`; shared `Heading/RichText/Hero.align` | `semantic` preserves level-derived Heading size and existing RichText size; `Heading.level` remains semantic markup |
| Color/background | shared `Heading/RichText.textColor`; shared `Section/Hero.background`; existing `Divider.color` | Semantic token enums only; old Section/Divider values unchanged; Hero default transparent |

All new text and Hero properties are explicitly responsive. Desktop uses base props; tablet/mobile remain sparse, mobile inherits tablet then base, and reset continues through the existing pruning mutation API. Old documents with no new properties receive compatibility fallbacks without changing schema version.

### Verification and limitations

```text
Focused Package 2 frontend/A1-A4 regression: 15 files, 112/112 passed.
Focused PHP registry/validator/persistence/template/publishing/release/reliability: 139/139 passed, 1,059 assertions.
npm run check: pass; 123 formatted, 121 lint-clean.
npm run types:check: pass.
npm run test:frontend: 21 files, 150/150 passed.
npm run build: pass; 3,540 modules; Builder 145.74 kB raw / 39.87 kB gzip; existing optional fontaine warning only.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 324 discovered, 298 passed, 26 PostgreSQL-only skipped, 1,783 assertions.
git diff --check: pass; only the pre-existing CRLF conversion warnings were printed.
```

The existing release tests cover active/sealed release preservation, and this package does not change renderer version or release format. Browser visual parity remains unverified because the repository still has no authenticated Playwright/Cypress/browser-provider fixture; mounted DOM/unit tests are not described as screenshot evidence. PostgreSQL-only tests were skipped, not passed.

Deferred: generalized border/radius/shadow/opacity, per-side spacing, arbitrary units, font family/weight/line-height/letter-spacing, gradients/background images, design-token authoring, and RichText stylesheet work. These are excluded to keep schema v1, avoid raw CSS/composite shapes, and preserve the package boundary.

Final safety state: branch/HEAD remain `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c`, with no remote/upstream, 0 staged files, 71 tracked paths marked modified (68 content diffs), and 36 untracked paths. No destructive Git operation, branch change, commit/push, dependency change, migration/database change, document/release format change, historical rewrite, sealed-release edit, or `skills-lock.json` change occurred.

## 21. Package 2 independent-review remediation

Date: 2026-10-25

### Review history and verdict

The independent Package 2 review returned **FAIL**. That verdict is retained here. The bounded slice is now **PASS WITH CAVEATS** after local remediation of the three reported defects; this does not claim completion of the original full Package 2 roadmap.

### Defects remediated

- Hero foregrounds: editor and publisher now select bounded title/subtitle foregrounds for `transparent`, `muted`, `primary`, and `dark`. The `dark` children no longer retain explicit light-surface text classes. Responsive background declarations also update direct Hero title/subtitle colors, while documents without a background preserve the transparent default (`text-foreground` title and `text-muted-foreground` subtitle).
- Inspector consistency: a descriptor is exposed only when its type, ordered enum, `stylePrimitive`, and declared responsive eligibility agree with the paired property schema. Individually valid but contradictory metadata, including the reported `Heading.fontSize`/`dimensions.maxWidth` case, fails closed.
- Responsive eligibility: the JSON catalog flag remains authoritative. PHP filters validation, exported responsive contracts, and metadata through the primitive flag. TypeScript filters the runtime responsive contract through the same local primitive definition. A primitive can remain valid as a base prop while tablet/mobile overrides are rejected.

No arbitrary CSS, user-defined classes, new primitive capabilities, schema version, persisted style object, migration, database schema, renderer/release format, historical revision, or sealed release was changed. Package 3 Layers/DnD was not started.

### Remediation verification

```text
Red proof: frontend metadata tests failed 5 cases; frontend responsive eligibility failed 1 case; PHP Hero/eligibility tests failed 3 cases before the fixes.
Focused frontend: 3 files, 43/43 passed.
Focused PHP registry/validator/renderer: 61/61 passed, 582 assertions.
npm run check: pass; 123 files formatted, 121 files lint-clean.
npm run types:check: pass.
npm run test:frontend: 21 files, 161/161 passed.
npm run build: pass; 3,540 modules; Builder 146.35 kB raw / 40.06 kB gzip; optional fontaine warning only.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 327 discovered, 301 passed, 26 PostgreSQL-only skipped, 1,803 assertions.
git diff --check: pass; only pre-existing CRLF conversion warnings were printed.
```

Browser screenshots, computed visual contrast, accessibility auditing, hosted CI, deployment, and a fresh PostgreSQL/concurrency run were not performed. The semantic mappings are covered by DOM and renderer assertions, but they are not claimed as browser contrast or visual-parity evidence.

### Remaining Package 2 scope

The implemented slice remains limited to bounded spacing, dimensions, alignment, font-size, foreground/background, and existing aspect/control/gap/spacer/Divider values. General border width/style, radius, shadow, opacity, arbitrary/per-side dimensions and spacing, font family/weight/line-height/letter-spacing, gradients, background images, token authoring, and RichText stylesheet work remain deferred. Full roadmap acceptance therefore remains open even though the reviewed defects are remediated locally.

Final safety state remains `master` at `ec7e07430c98ab24b59602eda2dfc44e628cee2c`, with no remote/upstream, 0 staged files, 71 tracked paths marked modified (68 content diffs), and 36 untracked paths. `skills-lock.json` remains the pre-existing untracked file and was not modified.
