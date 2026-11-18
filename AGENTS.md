# AI Agent Instructions

## Mandatory Documentation Review

Before analyzing, planning, modifying, testing, or otherwise executing any user request, read the project documentation in [`docs/`](docs/README.md).

Follow the reading order defined in [`docs/README.md`](docs/README.md), beginning with:

1. [`docs/PROJECT_CONTEXT.md`](docs/PROJECT_CONTEXT.md) for the active project context.
2. [`docs/core-architecture/02-critical-rules.md`](docs/core-architecture/02-critical-rules.md) for non-negotiable architectural rules.
3. [`docs/core-architecture/01-architecture-overview.md`](docs/core-architecture/01-architecture-overview.md) for architecture and bounded-context boundaries.
4. [`docs/development-lifecycle/01-development-workflow.md`](docs/development-lifecycle/01-development-workflow.md) for the required implementation workflow.

Read any additional documents in [`docs/`](docs/README.md) relevant to the requested work before proceeding. Treat architectural principles as invariant unless the user explicitly authorizes an approved architectural change, as governed by [`docs/DOCUMENTATION_GOVERNANCE.md`](docs/DOCUMENTATION_GOVERNANCE.md).

## Working Rules

- Apply the documented DDD, Hexagonal Architecture, CQRS, HTTP-layer, quality, and performance standards to every task.
- Keep [`docs/PROJECT_CONTEXT.md`](docs/PROJECT_CONTEXT.md) and other living documentation current when the work changes documented project context.
- Update task checklists and audit documentation when required by the applicable workflow.
- If user instructions conflict with mandatory architectural rules, explain the conflict and request explicit architectural approval before violating those rules.
