# Testing Plan — FAIR Plugin

> **Status:** Design validated, pending implementation  
> **Date:** 2026-06-09  
> **Branch:** `development` (or dedicated feature branch)

## Overview

Four-layer test pyramid for the FAIR WordPress plugin. The DID-manager package pipeline (`inc/packages/`) is the top-priority functional area. Edge case coverage must be exhaustive; Infection mutation testing will be added in a follow-up pass.

| Layer | Directory | Docker? | Target URL |
|-------|-----------|---------|------------|
| Unit | `tests/unit/` | No | N/A |
| Integration | `tests/integration/` | Yes (ephemeral) | `tests/sites/ephemeral/<suite>/` |
| HTTP | `tests/http/` | Optional | Configurable (ephemeral or pet) |
| Browser | `tests/browser/` | Recommended | Configurable (ephemeral or pet) |

## Documents

- **[test-directory-layout.md](./test-directory-layout.md)** — File structure, bootstrap files, configs
- **[unit-tests.md](./unit-tests.md)** — Packages/DID pipeline strategy, factory/fixture design, module coverage matrix
- **[integration-tests.md](./integration-tests.md)** — Docker Compose harness, mock DID server, site lifecycle, seeding
- **[http-tests.md](./http-tests.md)** — HTTP-level tests against configurable base URL, group annotations
- **[browser-tests.md](./browser-tests.md)** — Playwright specs, auth bootstrap, UI coverage
- **[static-sites.md](./static-sites.md)** — Persistent pet instances for exotic configurations (Bedrock, custom dir layouts, etc.)
- **[ci-strategy.md](./ci-strategy.md)** — GitHub Actions workflow, matrix, group-gated gates
- **[implementation-order.md](./implementation-order.md)** — 13-phase execution plan with parallelization notes
