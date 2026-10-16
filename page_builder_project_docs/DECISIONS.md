# Decision Log

Ghi quyết định đã được xác nhận. Các ý trong ADR ở trạng thái Proposed chưa phải quyết định cuối cùng.

| Date | ID | Decision | Context / alternatives | Consequences | Owner |
|---|---|---|---|---|---|
| 2026-10-16 | D-001 | Modular Monolith Architecture | Deploy single package on resource-constrained servers | Clean domain isolation; low operational overhead | User / Architect |
| 2026-10-16 | D-002 | PostgreSQL 18 with JSONB for Documents | High performance relational integrity + JSONB indexing | Relational tables for meta/audit; validated JSONB trees | User / Architect |
| 2026-10-16 | D-003 | Laravel 13 + Vue 3 + Inertia + TypeScript + Tailwind v4 | Official supported starter kit, SPA feel with server routing | Fast DX, type safety, low bundle size | User / Architect |
| 2026-10-16 | D-004 | Database `page_builder_dev` on Local PostgreSQL | Isolated database for project development | Zero impact on other local databases; clean baseline | User / Architect |
| 2026-10-16 | D-005 | Safe Health Check Endpoint (`/api/health`) | Minimal status payload, no secrets or SQL leaks | Resilient monitoring without exposing internal attack surface | User / Architect |

## Template
### D-XXX — Short title
- **Date:**
- **Status:** Proposed / Accepted / Rejected / Superseded
- **Context:**
- **Options considered:**
- **Decision:**
- **Consequences:**
- **Evidence / links:**
