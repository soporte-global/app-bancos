# AGENTS.md

## Project

This repository is a complete rewrite and consolidation of `bancos` and `gestion_usuarios`.

The legacy applications are reference implementations only.

The goal is to preserve required business behavior while replacing the legacy architecture with a cleaner, maintainable architecture.

## Legacy Applications

The legacy repositories are:

- `bancos`
- `gestion_usuarios`

Treat both repositories as read-only reference implementations unless explicitly instructed otherwise.

Do not modify, delete, rename, or reformat files in the legacy applications.

Do not copy legacy architecture blindly.

Legacy code describes existing behavior, but it is not automatically the correct architecture or the canonical business behavior.

When the two legacy applications differ, do not arbitrarily choose one implementation.

## Feature Implementation Workflow

Before implementing a feature:

1. Inspect the corresponding functionality in `bancos`.
2. Inspect the corresponding functionality in `gestion_usuarios`.
3. Identify similarities and differences.
4. Identify the underlying business rules.
5. Determine the canonical behavior.
6. Document important decisions and assumptions.
7. Design the new implementation according to the target architecture.
8. Implement the new version.
9. Add or update tests.
10. Run the relevant validation.
11. Update project status and documentation when necessary.

Do not begin implementation when the required business behavior is unclear.

## Architecture

Backend:

- PHP 7.4.30
- PostgreSQL

Frontend:

- TBD

Architecture:

- TBD

The target architecture is not necessarily required to match either legacy application.

Architectural decisions must be documented before or alongside implementation.

## General Rules

- Prefer existing domain services over duplicating business logic.
- Do not introduce global state unless explicitly justified.
- Do not silently change business behavior.
- Do not remove legacy behavior unless the change is explicitly documented.
- Do not introduce abstractions without a concrete reason.
- Prefer simple, cohesive implementations over unnecessary complexity.
- Keep business logic independent from presentation and infrastructure concerns.
- Do not duplicate business rules across controllers, views, API endpoints, or frontend code.
- Database migrations must be reversible whenever technically possible.
- Do not make destructive database changes without explicit confirmation and a migration strategy.

## Before Modifying Code

Before modifying code:

1. Identify the relevant existing code.
2. Inspect related tests.
3. Inspect the corresponding legacy implementations when applicable.
4. Read the relevant project documentation.
5. Identify assumptions and uncertainties.
6. If behavior is ambiguous, stop and investigate before implementing.
7. Implement the smallest coherent change.
8. Run the relevant tests and validation.

Do not modify unrelated code merely because it can be improved.

Avoid opportunistic refactoring outside the scope of the current task.

## Business Behavior

Business behavior has priority over legacy implementation details.

When legacy applications contain different implementations of the same functionality:

1. Identify the reason for the difference when possible.
2. Determine whether the difference represents intentional business behavior, a legacy limitation, or a possible bug.
3. Do not silently choose one behavior.
4. Document the decision.
5. Implement the canonical behavior in the new application.
6. Add regression tests for important business rules.

When the correct behavior cannot be determined from the available evidence, mark it as unresolved instead of guessing.

## Testing

Every non-trivial behavior change should have appropriate tests.

Prefer:

- unit tests for domain logic;
- integration tests for database behavior;
- API tests for externally observable behavior;
- regression/characterization tests for important legacy behavior.

Do not weaken or remove tests simply to make an implementation pass.

## Validation

Run the smallest relevant validation for the change.

Examples:

```bash
# TBD
```

Before completing a task, ensure that:

- relevant tests pass;
- no unintended files were modified;
- database migrations are valid when applicable;
- documentation is updated when required.

## Documentation

Documentation must answer concrete questions:

- What are we building?
- Why is it built this way?
- What behavior must remain?
- What behavior intentionally changed?
- What is already finished?
- What is currently being worked on?
- What is still missing?

Relevant documentation should be updated when architectural or business decisions change.

Important documents include:

- `docs/architecture.md`
- `docs/domain-model.md`
- `docs/business-rules.md`
- `docs/database.md`
- `docs/legacy/comparison.md`
- `docs/status/current-state.md`
- `docs/status/backlog.md`
- `docs/status/session.md`

Do not create documentation for its own sake. Documentation should preserve information that will be useful to future development sessions.

## Task Status

When working on a significant task, keep the following files synchronized with the actual project state:

- `TASK.md`
- `docs/status/current-state.md`
- `docs/status/backlog.md`
- `docs/status/session.md`

For small, isolated changes, do not update every status document unnecessarily.

Never mark a task as completed unless the implementation and relevant validation are actually complete.

## Complexity Policy

Use the fastest available model that can safely complete the task.

Classify the task before implementation.

### LOW

Examples:

- mechanical refactoring;
- straightforward CRUD implementation;
- test updates;
- formatting;
- localized bug fixes;
- simple documentation updates;
- small, well-defined changes.

LOW tasks may be implemented directly.

### MEDIUM

Examples:

- multi-file features;
- non-trivial refactoring;
- API changes;
- SQL optimization;
- changes involving multiple related components.

MEDIUM tasks require careful analysis before implementation.

### HIGH

Examples:

- architecture;
- domain modeling;
- database redesign;
- legacy behavior reconciliation;
- migrations;
- security-sensitive functionality;
- changes affecting multiple domains;
- unclear business requirements;
- complex SQL with significant business implications;
- changes where existing behavior cannot be confidently inferred.

For HIGH tasks:

- Do not implement immediately.
- Analyze the problem first.
- Identify relevant legacy behavior.
- Identify assumptions and unresolved questions.
- Explain the proposed approach.
- Identify whether a stronger reasoning model is appropriate.
- Do not make architectural or business decisions using a lightweight model when the decision requires deeper reasoning.

A model selection instruction in this document is advisory only. Do not assume that changing the model is possible from within the repository.

## Escalation

If a task exceeds the capabilities appropriate for the current model:

- stop before making the critical decision;
- clearly identify why escalation is required;
- identify the relevant files and information already gathered;
- summarize the unresolved problem;
- do not implement a speculative solution.

## Scope Control

Stay within the scope of the current task.

Do not:

- perform unrelated refactors;
- rename unrelated files;
- redesign unrelated modules;
- modify legacy applications;
- introduce new dependencies without justification;
- change database behavior without documenting it;
- "clean up" working code merely because it does not follow the preferred style.

If a related improvement is discovered but is outside the current task, document it as a backlog item instead.

## Completion Criteria

A task is complete only when:

- The requested functionality is implemented.
- Relevant tests are present or updated.
- Relevant validation has passed.
- No unresolved assumptions are hidden in the implementation.
- Required documentation has been updated.
- Project status reflects the actual state.