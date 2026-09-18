# Skill Registry — mesero-app

Generated: 2026-05-27

## User Skills (active for this project)

| Skill | Trigger | Path |
|-------|---------|------|
| branch-pr | creating/opening PRs | ~/.claude/skills/branch-pr/SKILL.md |
| chained-pr | PRs >400 lines, stacked PRs | ~/.claude/skills/chained-pr/SKILL.md |
| work-unit-commits | commits, implementation, splitting | ~/.claude/skills/work-unit-commits/SKILL.md |
| modern-frontend-design | frontend, UI, landing page, dashboard, SaaS UI | ~/.claude/skills/modern-frontend-design/SKILL.md |
| modern-web-guidance | HTML/CSS, clientside JS, modals, scroll, animations | ~/.claude/skills/modern-web-guidance/SKILL.md |
| tailwindcss | Tailwind utility classes, responsive design | ~/.claude/skills/tailwindcss/SKILL.md |
| ux-design-systems | design tokens, component library, theming, dark mode | ~/.claude/skills/ux-design-systems/SKILL.md |
| ui-design | UI exploration, single HTML mockup | ~/.claude/skills/ui-design/SKILL.md |
| code-review | review diff, correctness, reuse, efficiency | ~/.claude/skills/code-review/SKILL.md (built-in) |
| issue-creation | creating GitHub issues, bug reports, feature requests | ~/.claude/skills/issue-creation/SKILL.md |
| cognitive-doc-design | guides, READMEs, RFCs, onboarding, architecture docs | ~/.claude/skills/cognitive-doc-design/SKILL.md |
| judgment-day | dual review, adversarial review | ~/.claude/skills/judgment-day/SKILL.md |

## Compact Rules

### branch-pr
- Check for existing open PR on branch before creating a new one
- Use `gh pr create` with body via HEREDOC
- Title: under 70 chars; body: Summary + Test plan bullets
- Never force-push without explicit user confirmation

### chained-pr
- Split when estimated diff >400 lines or >3 logical concerns
- Each PR must be independently reviewable and mergeable
- Base each PR on the previous one in the chain
- Include migration path in PR description

### work-unit-commits
- One commit = one reviewable unit of work (feature slice, test, doc together)
- Tests and implementation in the same commit when possible
- Conventional commits: feat/fix/refactor/test/docs/chore/perf/ci
- No Co-Authored-By attribution

### tailwindcss
- Use Tailwind v4 utility-first; project uses OKLCH design tokens
- Responsive: mobile-first (`sm:`, `md:`, `lg:`)
- Dark mode via class strategy if configured
- Avoid arbitrary values unless no utility fits

### modern-frontend-design / modern-web-guidance
- Vue 3 + Inertia.js SPA — no separate API, use Inertia page components
- Tailwind v4 with OKLCH tokens — use CSS variables over hardcoded colors
- Components follow atomic design (atoms → molecules → organisms)
- Run dev server and verify in browser before marking complete

## Project Conventions (from openspec/README.md + config)

- **IVA**: MontoGravable = Total / 1.12 (12% Guatemala)
- **State machine**: CheckItemStatus — forward-only transitions
- **Feature flags**: config/restaurant.php + .env
- **Dual-mode hosting**: Docker (Reverb+Redis) / DreamHost (sync+Ably)
- **PKs**: ULIDs
- **Tests**: SQLite :memory:, RefreshDatabase trait
