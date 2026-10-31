# PENTAGON — Project Status and Next Phases

Ngày cập nhật: 2026-10-31

Nhánh tích hợp local: `operations/phase-c`

Mốc đã kiểm chứng: `b493ab4`

Remote: chưa cấu hình

## 1. Trạng thái tổng quát

Pentagon hiện đã vượt qua giai đoạn prototype. Các module chính—document engine,
builder, media, revision/release, publishing, SEO, templates, submissions admin,
homepage và vận hành backup/restore—đều đã có implementation cùng automated
coverage. Hệ thống đang ở trạng thái **feature-complete cho luồng page cơ bản và
release-ready ở local**, nhưng chưa được coi là production-ready vì hosted CI,
staging deployment, screen-reader acceptance và production cutover chưa diễn ra.

Homepage/Starter Set đã được fast-forward vào nhánh tích hợp. Mỗi website mới có
Home, About và Contact; Home là homepage `/`; database, backfill, public routing,
SEO, UI, concurrency và browser lifecycle đều đã được kiểm chứng local.

## 2. Các phase đã hoàn thành

| Phase | Phạm vi | Trạng thái | Mốc chính |
|---|---|---|---|
| Foundation 00–11 | Laravel/Inertia/Vue foundation, website/page/media, document schema, revision, publish | Hoàn thành | `ec7e074` và lịch sử trước đó |
| Audit 0–1 | Architecture, UX, performance, security audit và roadmap | Hoàn thành | Tài liệu `optimization/` |
| Safety 2A–2C | Backup correctness, publishing correctness, responsive contract, frontend architecture | PASS local | trước `857289e` |
| Capability A1–A4 | Inspector, style primitives có giới hạn, validation và workflow nền | PASS local | trước `7619d9d` |
| Package 3 | Layers, drag/drop, history, templates và workflow Builder | PASS local + browser coverage chính | `7619d9d`, `ffdd70d` |
| Phase A | Stabilization & Git Packaging; PostgreSQL, dependency audit, baseline chuẩn | Hoàn thành | `c2f2eff` |
| Phase B | Playwright, responsive 375/768/1280, axe, publish/unpublish lifecycle | PASS local | `ffdd70d` |
| Phase C | Deploy/rollback guard, backup/restore rehearsal, production health check | PASS local | `74f0f85` |
| D-048 | Homepage `/` và Starter Page Set Home/About/Contact | PASS local, đã merge vào integration | `355fbea`…`b493ab4` |

## 3. Verification tại mốc `b493ab4`

- Frontend format/lint: 131 file đúng format, 129 file không có lint warning/error.
- Vue TypeScript: PASS, 0 lỗi.
- Frontend unit/component: **415/415 PASS** trên 32 test files.
- Backend default/SQLite: **364 PASS, 28 PostgreSQL-only skipped, 0 fail,
  2.205 assertions**.
- Pint: PASS.
- PHPStan: PASS, 0 lỗi.
- PostgreSQL 18 disposable suite: **43/43 PASS, 245 assertions**.
- Fresh PostgreSQL migration: **19/19 migration PASS** trên `pentagon_test`.
- Production build: **3.553 modules**, 0 build error.
- Playwright core workflow: **1/1 PASS**; starter set, homepage publish, canonical
  root 200, technical slug 301, homepage reassignment, responsive và axe.
- Backup/restore rehearsal bằng PostgreSQL thật: PASS.
- Production health check với `APP_ENV=production`, debug tắt: `healthy: true`.
- `git diff --check`: PASS.
- Working tree sau merge: sạch.

Các con số trên là bằng chứng local. Chưa có hosted CI/staging/production evidence.

## 4. Khoảng trống còn mở

### Correctness và reliability

1. PUB advisory lock chỉ bao Phase 1 của publish; activation/rollback vẫn dựa vào
   row lock + partial unique và chưa tái kiểm tra media khi rebuild rollback.
2. Draft/template persistence vẫn giữ document gốc thay vì canonical sanitized
   document; public renderer vẫn sanitize nên đây chưa phải lỗ hổng output, nhưng
   làm dữ liệu lưu trữ thiếu chuẩn hóa.
3. Chưa có pipeline migration cho `schema_version`; hệ thống mới hỗ trợ schema v1.
4. Responsive presentation mapping vẫn tồn tại song song ở PHP renderer và
   TypeScript editor; property mới cần parity test để tránh drift.

### Product và Builder

1. Chưa có component `Form` trong registry; submissions endpoint/admin đã có
   nhưng page builder chưa tự sinh được form.
2. History UI chỉ tải 50 revisions/releases mới nhất, chưa có load-more/pagination.
3. Full style roadmap còn thiếu border/radius/shadow/opacity, per-side spacing,
   arbitrary dimensions/units, typography nâng cao, gradient/background image và
   token authoring.
4. `moveNode` đã có domain behavior nhưng chưa có UI caller cho một số thao tác.

### Verification và vận hành

1. Screenshot browser mới là artifact, chưa có golden-image visual regression.
2. Chưa thực hiện screen-reader acceptance thủ công.
3. Browser suite chưa phủ đầy đủ two-tab conflict, media upload/delete, release
   rollback và cross-tenant flows.
4. Chưa có remote nên chưa push, chưa chạy hosted CI.
5. Chưa có staging/production server inventory, secrets, TLS/systemd/worker và
   backup schedule evidence.

### Performance

1. R-21 mới có deterministic benchmark; chưa có browser trace/paint/heap budget.
2. R-24 chưa có page-list/history pagination measurement trên dataset lớn.
3. R-22 immutable asset strategy và R-25 image variants vẫn pending; chỉ triển
   khai sau khi measurement chứng minh cần thiết.

## 5. Các phase tiếp theo

### Phase D — Integration and Hosted CI

Mục tiêu: đưa mốc `b493ab4` lên repository dùng chung và có bằng chứng CI độc lập.

- Cấu hình Git remote và branch protection.
- Push nhánh tích hợp/feature, mở PR và chạy toàn bộ workflow.
- Xác nhận CI PostgreSQL, browser, shell operation guards và dependency audit.
- Lưu artifact Playwright, build logs và test summary.
- Chỉ merge remote khi mọi required check xanh.

Điều kiện hoàn thành: hosted CI PASS trên commit bất biến; không chỉ dựa vào local.

Phụ thuộc: cần repository URL và quyền push. Nếu chưa có, có thể triển khai Phase E
local song song.

### Phase E — Reliability Closure

Mục tiêu: đóng các rủi ro correctness còn lại trước khi thêm nhiều feature.

- Thiết kế lại publish/rollback serialization để activation và Case B rollback
  không race, có media revalidation rõ ràng.
- Chuẩn hóa document persistence hoặc ghi ADR nếu tiếp tục lưu source document.
- Thiết kế schema-version migration pipeline và fixture migration v1→vNext.
- Thêm parity contract chung cho responsive editor/public mapping.
- Bổ sung PostgreSQL concurrency và chaos tests cho các thay đổi trên.

Điều kiện hoàn thành: không còn finding correctness mức cam/đỏ trong §21 System
Reference; rollback/publish failure không làm mất active release.

### Phase F — Product Completion: Forms

Mục tiêu: hoàn tất luồng thu lead từ Builder tới submissions admin.

- Thêm `Form` component vào registry, inspector và document validation.
- Render form parity giữa Vue canvas và PHP public renderer.
- Field schema, validation, honeypot, CSRF/origin và rate-limit UX.
- Submission success/error state, accessibility và privacy copy.
- Browser E2E: tạo form → publish → submit → xem/cập nhật/xóa submission.

Điều kiện hoàn thành: người dùng tạo và publish form hoàn toàn trong Builder;
submission hoạt động end-to-end và cross-tenant/security tests xanh.

### Phase G — Builder Completeness and History Navigation

Mục tiêu: đóng các phần UI/capability đang partial.

- History/release load-more hoặc cursor pagination.
- Page listing measurement và pagination khi dataset vượt ngưỡng.
- Hoàn thiện style primitives theo thứ tự nhu cầu sản phẩm.
- Nối các node actions còn chưa có UI caller; giảm transaction boilerplate trong
  `EditorShell` sau khi có characterization tests.
- Mở rộng browser coverage cho two-tab conflict, media lifecycle và rollback.

Điều kiện hoàn thành: không còn mục product workflow quan trọng ở trạng thái
PARTIAL; dataset lớn vẫn điều hướng được trong budget đã đặt.

### Phase H — Measured Performance and Asset Delivery

Mục tiêu: tối ưu dựa trên số đo, không tối ưu theo giả định.

- Thiết lập browser trace cho 10/100/250 nodes: scripting, paint, heap, interaction.
- Đo page listing, history, public transfer, LCP/CLS và cache hit behavior.
- Chỉ thực hiện R-22 immutable/versioned asset strategy nếu transfer/cache là nút
  thắt; giữ compatibility với release cũ.
- Chỉ thực hiện R-25 image variants nếu LCP/bandwidth chứng minh lợi ích.
- Gắn performance budgets vào CI ở mức ổn định, tránh flaky threshold.

Điều kiện hoàn thành: có baseline/before-after và rollback strategy cho từng tối ưu.

### Phase I — Release Acceptance and Production Launch

Mục tiêu: xác nhận hệ thống trên hạ tầng thật và phát hành có khả năng rollback.

- Golden visual regression cho viewport chính và screen-reader acceptance.
- Deploy staging bằng script thật; kiểm tra Nginx/TLS/PHP-FPM/scheduler/queue.
- Chạy smoke, security headers, backup, restore rehearsal và rollback staging.
- Xác nhận monitoring/log rotation, backup retention, RTO/RPO và incident runbook.
- Production cutover, post-deploy health check và rollback drill.

Điều kiện hoàn thành: staging acceptance ký duyệt, production health xanh, backup
khôi phục được và rollback đã diễn tập trên cùng loại hạ tầng.

## 6. Thứ tự đề xuất

```text
Phase D (khi có remote)
        └── hosted CI evidence

Phase E → Phase F → Phase G → Phase H
                           └──────────────┐
Phase D + local phases hoàn tất ─────────┴→ Phase I
```

Ưu tiên thực thi ngay: **Phase E — Reliability Closure**. Phase D nên được kích
hoạt song song ngay khi có remote; không cần để thiếu remote chặn công việc local.

## 7. Git packaging hiện tại

- `operations/phase-c` đã tích hợp toàn bộ Homepage/Starter Set qua `b493ab4`;
  `feature/homepage-starter-set` giữ mốc review `b493ab4`.
- `verification/phase-b` giữ mốc `ffdd70d`.
- `stabilization/phase-a` giữ mốc `c2f2eff`.
- `master` vẫn ở mốc lịch sử `ec7e074`; chưa fast-forward vì chưa có quyết định
  nhánh release/remote.
- Không tuyên bố hosted CI hoặc deployment pass trước khi có bằng chứng bên ngoài.
