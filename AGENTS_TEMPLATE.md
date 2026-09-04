# AGENTS.md — Module Template

> **All shared standards live in the master file:**
> `~/Documents/AGENTS.md`
>
> This file contains only **module-specific** details. Don't duplicate what's
> already in the master.

---

## Overview

<!-- One-paragraph description of what this module does -->

## Namespace

<!-- PSR-4 namespace for this module -->

```
Ksfraser\FrontAccounting\<ModuleName>\
```

## Table Ownership

<!-- List tables this module owns. Use 0_ prefix. -->

| Table | Purpose |
|-------|---------|
| `0_<module>_<table>` | Description |

## Dependencies

<!-- List other ksf modules this depends on -->

## ProjectDocs

<!-- Link to Requirements.md, RTM.md, BABOK.md, UML.md if they exist -->

---

## Appendix Files

If this module needs guidelines beyond the master, add:
- `AGENTS.local.md` — Repo-specific extensions / operational notes (**committed**, like the code). Keeps repo-specific detail out of the shared `AGENTS.md`.
- `AGENTS_APPENDIX.md` — Reserved name: shared-docs hardlink mechanism (do not create per-repo; see `~/Documents/AGENTS_APPENDIX.md`).

---

*See [~/Documents/AGENTS.md](../../AGENTS.md) for all shared standards.*
