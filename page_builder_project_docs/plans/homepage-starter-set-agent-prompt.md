# Implementation Prompt — Homepage and Starter Page Set

Copy the prompt below into an AI coding agent running at the repository root.

---

You are implementing the Homepage and Starter Page Set feature in the Pentagon
repository. Work autonomously through implementation, verification and
documentation; stop only when external authority or infrastructure is genuinely
required.

## Source of truth

Read this file completely before editing anything:

`page_builder_project_docs/plans/homepage-starter-set-plan.md`

That plan is authoritative for product behavior, invariants, data model, public
routing, UI, backfill, rollout, tests and definition of done. Also inspect the
relevant current code and the matching sections of:

`page_builder_project_docs/PENTAGON_SYSTEM_REFERENCE.md`

When current code and the plan differ, implement the plan while preserving
unrelated established behavior. If a real contradiction makes an invariant
impossible, document the evidence and ask one focused question; do not silently
change the product decision.

## Working protocol

1. Inspect `git status`, current branch and recent commits. Preserve all existing
   user changes. Create a feature branch from the current committed point if the
   user has not already provided one.
2. Map every affected implementation and test location before editing. At
   minimum inspect website creation, page creation/update/delete, page policies,
   publishing/static release delivery, redirects, SEO, sitemap, routes, Vue
   Website/Page screens, factories, migrations, deploy scripts and browser setup.
3. Write characterization or failing acceptance tests first for each behavioral
   slice. Keep the loop red-green-refactor and commit only coherent green slices.
4. Implement the domain behind small interfaces. Controllers validate/authorize,
   call the domain once and translate the result. Keep transactions, locking,
   starter creation and invariants inside their owning modules.
5. Reuse the existing Vue/Inertia UI primitives and visual patterns. Match the
   current English copy, spacing, typography, responsive layout, focus behavior
   and accessibility conventions. Add no UI dependency.
6. Search the full repository for public page URL construction. Homepage
   canonical behavior must be consistent across delivery, redirects, sitemap,
   SEO metadata, UI links and tests.
7. Implement and test the idempotent existing-data backfill. Integrate committed
   backfill into deploy after migration and before atomic activation.
8. Run the targeted test after each slice, then every required gate from the
   plan. Fix regressions within feature scope.
9. Update the system reference and user-facing setup/behavior documentation.
10. Package changes into clear conventional commits and leave the working tree
    clean except for pre-existing unrelated changes.

## Product decisions to preserve

- Every committed website has exactly one homepage.
- New websites receive draft Home, About and Contact pages.
- Home is the initial homepage and has canonical website-relative path `/`.
- The admin/product route `/` remains unchanged.
- Current public root is `/sites/{website-slug}`.
- Page slugs remain ordinary technical slugs; never store `/` or an empty slug.
- Starter pages are never auto-published.
- Homepage can be unpublished or assigned while draft; public root then returns
  404 until a valid active release exists.
- Homepage deletion is rejected with an actionable 422 response until another
  homepage is assigned.
- Assignment uses website-update authorization.
- Current homepage technical slug permanently redirects to canonical root.
- Existing static release, ETag and cache semantics remain intact.

## Implementation quality bar

- Enforce at-most-one homepage in PostgreSQL and SQLite with a partial unique
  index; enforce at-least-one through provisioning, assignment, delete protection
  and rollout/backfill.
- Make provisioning atomic, including membership, pages, documents and initial
  revisions.
- Use database locking for concurrent homepage assignment and prove the behavior
  on PostgreSQL.
- Keep starter definitions in one catalog usable without database seed state.
- Centralize canonical public path calculation; consumers must not reproduce the
  homepage conditional.
- Backfill defaults to dry-run, requires an explicit commit flag, reports totals
  and is idempotent.
- Preserve tenant isolation and existing authorization behavior.
- Use validation-style errors for expected user corrections and operational
  logging for broken invariants.

## UI acceptance

On Website Create, add a read-only Starter pages card listing Home `/`, About
`/about`, Contact `/contact`, with draft explanation.

On Website Show:

- homepage appears first;
- homepage has a clear Homepage badge and `/` path;
- eligible non-home pages offer Set as homepage;
- homepage deletion is unavailable with an explanation;
- public-root action uses the canonical URL;
- keyboard, responsive and axe behavior remain green.

In page edit/builder context, show `/` as the homepage’s effective path while
retaining its technical slug with concise helper text.

## Verification and evidence

Run every command specified under “Required gates” in the plan. Do not replace a
skipped PostgreSQL suite with SQLite evidence, and do not claim hosted CI,
staging or production success unless it actually ran there.

Before finishing, report:

1. delivered behavior;
2. schema/backfill and rollout behavior;
3. UI changes;
4. exact tests and command results, including pass/skip/fail counts;
5. commits created;
6. remaining limitations or external prerequisites;
7. final `git status`.

The task is complete only when the plan’s Definition of done is satisfied. If an
external prerequisite blocks only hosted deployment, finish and package all
local work first, then report that boundary explicitly.

---

