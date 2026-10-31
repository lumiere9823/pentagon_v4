# Phase C - Operations Rehearsal

Ngày kiểm chứng local: 2026-10-31  
Branch: `operations/phase-c`  
Base: `verification/phase-b` / `ffdd70d`

## Kết quả

Phase C đã đóng các khoảng trống local trong quy trình deploy, rollback, backup và
restore. Các thay đổi không được xem là một lần production deployment; chúng là
rehearsal và guard automation trước khi có remote cùng máy chủ đích.

### Backup và restore

- Restore bắt buộc có `SHA256SUMS`, đúng một PostgreSQL dump và đúng một archive
  `media_and_releases`.
- `pg_restore` chạy với `--exit-on-error`; lỗi restore không còn bị bỏ qua.
- Database rehearsal là transient, có thể đổi qua `REHEARSAL_DB`, và luôn được
  cleanup bằng trap.
- Sau restore, script xác nhận các bảng `pages`, `page_revisions`,
  `page_releases`, `media_assets` và row count hợp lệ.
- Archive được bung vào thư mục tạm; bắt buộc có `public/media` và
  `private/releases` trước khi báo thành công.
- Rehearsal thật bằng PostgreSQL 18 đã PASS: dump database `pentagon_test`, kiểm
  checksum, restore vào `pentagon_rehearsal_restore`, kiểm schema/assets và cleanup.

### Deploy và rollback

- Deploy bắt buộc nhận repository qua `PENTAGON_REPOSITORY_URL`; không còn clone
  URL placeholder khóa cứng.
- `PENTAGON_APP_ROOT`, `PENTAGON_PHP_FPM_SERVICE` và
  `PENTAGON_KEEP_RELEASES` cho phép cấu hình hạ tầng mà không sửa script.
- Release directory mới phải được tạo mới hoàn toàn; collision làm deploy dừng.
- Chuyển `current` dùng symlink tạm rồi `mv -T` để thay thế nguyên tử trên Linux.
- Rollback thủ công chỉ nhận timestamp 14 chữ số, target phải tồn tại và resolve
  bên trong thư mục releases.

## Bằng chứng tự động

- `bash -n` PASS cho bốn operation scripts và ba shell test scripts.
- `tests/Scripts/BackupScriptTest.sh`: PASS.
- `tests/Scripts/RestoreRehearsalScriptTest.sh`: PASS cả success, restore failure,
  checksum corruption và missing archive.
- `tests/Scripts/DeploymentScriptGuardTest.sh`: PASS cho missing repository,
  invalid retention, path-like rollback target và missing release.
- `app:production-health-check --json` với `APP_ENV=production`,
  `APP_DEBUG=false`, PostgreSQL 18: `healthy: true`; database, migrations,
  storage, security configuration và cache đều `OK`.
- CI job đã chạy các shell guard sau frontend build.

## Việc cần hạ tầng bên ngoài

- Repo chưa có Git remote, nên chưa thể push branch hoặc quan sát hosted CI.
- Chưa có SSH/server inventory, repository URL thật, production secrets hay quyền
  reload PHP-FPM; chưa thực hiện deploy/cutover hoặc rollback trên máy chủ thật.
- Nginx/TLS/systemd, scheduler, queue worker, backup schedule và restore RTO/RPO
  phải được xác nhận trên staging/production.
- Production migration vẫn phải tuân thủ additive/backward-compatible vì migrate
  diễn ra trước khi đổi symlink.

