---
description: "Perform an exhaustive architectural and design analysis of the current workspace. Identify every deviation from readability, maintainability, and core design principles (KISS, DRY, SOLID, YAGNI, etc.). Output findings and suggested remedies to a file in the docs folder."
name: "Architectural Design Analysis"
argument-hint: "Optional path or glob to scope the analysis (defaults to entire workspace)"
agent: "agent"
---

Analyze the codebase in the current workspace to produce an exhaustive architectural and design review. Catalog every issue that deviates from established design standards and best practices. Focus on the following areas:

1. **Architecture & Design**
   - Separation of concerns and modularity
   - Coupling and cohesion between components
   - Layering and dependency direction
   - Use of design patterns (appropriate vs. over-engineered)

2. **Code Quality & Readability**
   - Naming clarity and consistency
   - Function/class size and responsibility
   - Control-flow complexity and nesting
   - Comments and documentation coverage

3. **Maintainability**
   - Code duplication (DRY violations)
   - Configuration vs. hard-coding
   - Testability and test coverage
   - Error handling and logging consistency

4. **Principle Adherence**
   - **KISS**: Identify over-engineered or unnecessarily complex solutions
   - **DRY**: Spot duplicated logic, structures, or configurations
   - **SOLID**: Assess Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion
   - **YAGNI**: Flag speculative or unused abstractions

**Scope & Exclusions:**
- Exclude common non-source directories such as `vendor/`, `node_modules/`, `build/`, `dist/`, `.git/`, `tests/`, and generated asset folders unless explicitly included in the argument.
- The analysis should remain language-agnostic and applicable to any codebase.

**Instructions:**
- Explore the workspace structure and read key source files to build a holistic understanding.
- Cite specific files, classes, or functions when identifying issues.
- For each finding, provide a **Severity** (Critical / High / Medium / Low) and a **Suggested Remedy**.
- Create a Markdown file named `ARCHITECTURE_REVIEW.md` in the `docs/` folder containing:
  - Executive Summary
  - Detailed Findings (grouped by category)
  - Suggested Remedies (with file references)
  - Priority Roadmap (Quick Wins vs. Long-term Refactors)
- If the user provided a path or glob in the argument, scope the analysis to those files.
