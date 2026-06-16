---
description: "Use when writing or modifying code to enforce core design principles: KISS, DRY, SOLID, and YAGNI. Ensures readability, maintainability, and architectural consistency."
name: "Design Principles Enforcement"
applyTo: "**/*.{php,js,ts,jsx,tsx,cs,py,java,rb,go,rs,c,cpp,h,hpp}"
---

# Design Principles Enforcement

Apply these principles to every code change. Reject additions that violate them.

## KISS (Keep It Simple, Stupid)
- Prefer the simplest solution that satisfies the requirement.
- Avoid premature abstraction, indirection, or speculative generalization.
- If a function, class, or module is hard to explain, it is too complex.

## DRY (Don't Repeat Yourself)
- Extract duplicated logic, constants, or structures into reusable functions, classes, or configuration.
- Duplication of intent (not just text) is also a violation.
- Do not over-abstract: similar code with different purposes should not be forced together.

## SOLID
- **Single Responsibility**: One reason to change per class/function/module.
- **Open/Closed**: Open for extension, closed for modification. Favor composition over inheritance.
- **Liskov Substitution**: Subtypes must be substitutable for their base types without altering correctness.
- **Interface Segregation**: Clients should not depend on interfaces they do not use. Split fat interfaces.
- **Dependency Inversion**: Depend on abstractions, not concretions. Inject dependencies.

## YAGNI (You Aren't Gonna Need It)
- Do not implement features or abstractions that are not required by the current task.
- Remove dead code, unused parameters, and speculative configuration.

## General Quality
- Keep functions short and focused (ideally under 30–40 lines).
- Use descriptive, intention-revealing names.
- Minimize nesting; return early to reduce cognitive load.
- Document the "why," not just the "what."
- Ensure error handling is consistent and logging is meaningful.
