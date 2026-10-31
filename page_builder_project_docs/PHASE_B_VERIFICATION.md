# Phase B - Browser Verification

Ngày kiểm chứng local: 2026-10-31
Branch: `verification/phase-b`
Base: `stabilization/phase-a` / `c2f2eff`

## Kết quả

Phase B browser harness đã được thiết lập bằng Playwright và database SQLite
cô lập tại `storage/framework/testing/pentagon-e2e.sqlite`. Global setup luôn
migrate fresh, seed một owner đã verify cùng starter templates, và dọn release
artifact của lần chạy trước.

Luồng browser thực đã PASS:

1. Login bằng owner fixture.
2. Tạo website qua Inertia UI.
3. Tạo page từ starter template và mở Page Builder.
4. Load Builder tại 375x812, 768x1024 và 1280x900.
5. Chạy axe WCAG 2 A/AA tại cả ba viewport; không còn violation mức
   `critical` hoặc `serious`.
6. Chụp screenshot từng viewport và gắn vào Playwright report.
7. Publish page, kiểm tra public URL trả 200 và chứa page title.
8. Unpublish page, kiểm tra live-region announcement và public URL trả 404.

## Remediation phát hiện bởi browser

- Muted foreground light theme được tăng contrast.
- Các label Component Library và Sidebar bỏ opacity gây contrast dưới 4.5:1.
- Editor Canvas scroll region nhận `tabindex=0` và focus-visible ring để keyboard
  user truy cập được vùng scroll.
- Vue type checker mới phát hiện standard Inspector field nằm ngoài scope mà
  checker có thể chứng minh; template được tách thành loop rõ ràng.

## CI

Workflow có job `browser` chạy sau job `ci`, cài Chromium cùng system
dependencies, dùng SQLite/pdo_sqlite và chạy `npm run test:e2e`.

## Giới hạn còn lại

- Hosted CI chưa chạy vì repo chưa có remote.
- Screenshot hiện là evidence attachment, chưa phải golden-image pixel diff.
- Chưa có two-tab conflict, media upload/delete, release rollback và cross-tenant
  browser flows; các contract này vẫn có backend/frontend automated coverage.
- npm runtime audit: 0 vulnerability. Full dev audit còn 4 moderate advisory từ
  `js-beautify -> editorconfig/glob/minimatch`; không còn high/critical sau khi
  nâng Vite+ 1.4.0 và Vitest 5.0.5.
- Đây không thay thế PostgreSQL concurrency suite; Phase A đã chạy riêng 36/36.
