# 05 — Roadmap, Phases & Modules

## Quy tắc triển khai
- Mỗi phase có mục tiêu, phạm vi, dependencies, acceptance criteria và test evidence.
- Không chuyển phase chỉ vì code đã viết; phải qua quality gates.
- Các quyết định liên quan security, schema, publish và data ownership cần được chốt trước khi triển khai sâu.
- Security, tests, performance và documentation là công việc xuyên suốt, không để dồn cuối dự án.

## Modules
| ID | Module | Phạm vi |
|---|---|---|
| M01 | Foundation & Engineering Standards | Repo conventions, CI, lint/typecheck/test, env config |
| M02 | Identity & Access Control | Authentication, roles, permissions, website membership |
| M03 | Website & Page Management | Website, page, slug, status, metadata |
| M04 | Page Document Engine | Document schema, node IDs, validation, migration |
| M05 | Component Registry & Schema | Registry, props schema, defaults, renderer contract |
| M06 | Design System | Tokens, spacing, typography, colors, breakpoints |
| M07 | Visual Editor Shell | Editor layout, palette, canvas shell, inspector shell |
| M08 | Canvas & Drag-and-Drop | Select, move, insert, nest, reorder |
| M09 | Inspector & Style Editor | Content fields, style controls, validation |
| M10 | Editor Command History | Undo/redo, command model, selection behavior |
| M11 | Responsive Layout Engine | Desktop/tablet/mobile preview and style overrides |
| M12 | Media Library | Upload, validation, metadata, variants, references |
| M13 | Templates & Reusable Sections | Starter templates and reusable sections |
| M14 | Draft Persistence & Autosave | Debounced save, status, conflict handling |
| M15 | Revision History & Rollback | Immutable revisions, diff/rollback |
| M16 | Preview & Public Renderer | Preview authorization, server rendering, output validation |
| M17 | Static Publishing Pipeline | Build release, atomic switch, retry, rollback |
| M18 | Dynamic & Hybrid Content | Form/API contracts and dynamic components |
| M19 | SEO & Routing | Metadata, canonical, sitemap, redirects |
| M20 | Audit, Security & Reliability | Audit events, rate limiting, hardening, logs |
| M21 | Performance & Resource Budget | Measurement, budgets, optimization |
| M22 | Testing & QA | Unit, integration, E2E, security and load checks |
| M23 | Deployment & Release Management | Environment, backup, deploy, health checks, rollback |

## Phase 00 — Product Discovery & Technical Feasibility
**Mục tiêu:** giảm rủi ro trước khi code.

Tasks:
1. Kiểm tra repo, framework versions, auth, frontend setup, DB schema, tests, CI, deploy process.
2. Xác nhận hosting, RAM, CPU, disk, Nginx/PHP-FPM, PostgreSQL cùng máy hay tách.
3. Chốt MVP và non-goals.
4. Chốt document schema, component contract và migration policy.
5. Chốt draft/revision/publish state model.
6. Xác minh static output và atomic switch khả thi trên hosting.
7. Viết ADR, risk register và implementation plan chi tiết.
8. Chạy baseline tests và ghi lại hiện trạng.

Exit criteria:
- PRD và ADR được review.
- Có architecture/data model đủ để triển khai.
- Rủi ro blocking có phương án.
- Có plan Phase 01 chia nhỏ thành task có acceptance criteria.
- Chưa tạo feature code ngoài những gì cần cho discovery.

## Phase 01 — Foundation & CI
**Modules:** M01, phần nền tảng M22/M23.
- Thiết lập code style, lint, typecheck, test, CI.
- Xác định env/config conventions, secrets policy.
- Xác nhận build và deploy baseline.
- Thêm smoke test và health check tối thiểu.

Exit: pipeline chạy được; lệnh kiểm tra được ghi trong README; không phá vỡ baseline.

## Phase 02 — Identity, Permissions & Website Management
**Modules:** M02, M03, M20.
- Authentication theo scaffold phù hợp.
- Website membership và permission checks.
- CRUD website/page, slug constraints, status.
- Feature/integration tests cho authorization.

Exit: user không thể truy cập website/page ngoài quyền; DB constraints hoạt động.

## Phase 03 — Document Engine & Component Registry
**Modules:** M04, M05.
- Chốt JSON schema version 1.
- Registry allowlist và per-component schema.
- Server validation, size/depth/node limits.
- Fixtures và schema migration tests.

Exit: document hợp lệ round-trip ổn định; document độc hại/sai schema bị từ chối.

## Phase 04 — Design System & Editor Shell
**Modules:** M06, M07.
- Tokens và breakpoints.
- Editor shell, palette, canvas placeholder, inspector placeholder.
- Lazy-load editor bundle nếu hợp lý.
- Keyboard/focus basics.

Exit: layout editor hoạt động trên viewport mục tiêu, không phụ thuộc backend giả định.

## Phase 05 — Visual Editing Core
**Modules:** M08, M09, M10.
- Insert/select/move/reorder/nest components.
- Inspector edit props/styles.
- Undo/redo bằng command history hoặc snapshot strategy đã benchmark.
- Bảo toàn node IDs và selection ổn định.

Exit: luồng chỉnh sửa cơ bản hoạt động; tests cho thao tác và undo/redo.

## Phase 06 — Responsive, Media & Templates
**Modules:** M11, M12, M13.
- Breakpoint preview và style overrides.
- Upload/validate/media metadata.
- Templates và reusable sections.
- Kiểm tra accessibility cơ bản.

Exit: media validation an toàn; template khởi tạo document hợp lệ.

## Phase 07 — Persistence & Revision Management
**Modules:** M14, M15.
- Debounced autosave, save status, optimistic locking.
- Immutable revisions, history list, rollback tạo revision mới.
- Xử lý retry/conflict.

Exit: reload không mất draft đã xác nhận lưu; stale update không âm thầm ghi đè.

## Phase 08 — Preview, Renderer & Publishing
**Modules:** M16, M17.
- Preview URL được authorize và hết hạn.
- Renderer từ registry/schema đã validate.
- Static release build, output validation, atomic switch.
- Idempotent publish event, logs, rollback release.

Exit: publish thành công cập nhật đúng revision; publish lỗi giữ nguyên public version hiện tại.

## Phase 09 — Dynamic Content & SEO
**Modules:** M18, M19.
- Chỉ triển khai dynamic component đã có yêu cầu rõ.
- SEO metadata, canonical, sitemap, redirects.
- Cache policy tách static/public và private/dynamic.

Exit: không rò rỉ dữ liệu cá nhân vào static output; SEO metadata đúng.

## Phase 10 — Hardening & Performance
**Modules:** M20, M21, M22.
- Security review, authorization matrix, dependency audit.
- Kiểm thử publish failure, retry, rollback.
- Load test nhỏ với workload đại diện.
- Đo RAM, latency, disk và giới hạn process.

Exit: không còn lỗi critical/high chưa có chấp nhận rủi ro rõ ràng; budget có số đo.

## Phase 11 — Production Deployment
**Modules:** M23.
- Backup/restore rehearsal.
- Environment/secrets, permissions, TLS, Nginx routing.
- Deploy, health checks, smoke tests.
- Runbook incident/rollback.

Exit: deploy/rollback đã thử trên staging; có người chịu trách nhiệm và runbook.

## Dependency graph chính
- M01 → tất cả phase sau.
- M02 → M03 và mọi API cần authorization.
- M04 → M05, M08, M09, M14, M15, M16.
- M05 → M09, M16.
- M06 → M07, M09, M11.
- M08 + M09 + M10 → editor MVP.
- M12 → component media/template.
- M14 + M15 + M16 → M17 an toàn.
- M16 → M17.
- M18/M19 có thể hoãn nếu MVP chỉ cần static pages.
- M20/M21/M22/M23 chạy xuyên suốt, tăng cường trước production.

## Definition of Done cho mọi task
- Acceptance criteria được đáp ứng.
- Tests phù hợp đã chạy và kết quả được ghi lại.
- Không bỏ qua lint/typecheck/security checks hiện có.
- Authorization và validation ở server nếu có dữ liệu/endpoint.
- Không đưa secrets vào repo/logs.
- Có migration/rollback considerations nếu đổi DB.
- Có cập nhật docs/status khi cần.
- Không để TODO mơ hồ cho luồng cốt lõi.
