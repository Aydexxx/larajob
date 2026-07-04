# LaraJob Design System

LaraJob has a deliberate visual identity — not a default Breeze/Tailwind theme.
Everything below is a **token**: defined once (in `tailwind.config.js` or
`resources/css/app.css`) and referenced everywhere. If you find yourself writing
a raw hex value or a one-off spacing/shadow in a Blade view, reach for a token
instead.

## Brand color

The signature color is a **deep, confident teal** — chosen deliberately over the
indigo/violet that every other job board reaches for. It's the same hue as the
"strong match" ring in the AI matching feature, so the brand and the product's
core idea (great matches) are literally the same color.

Defined as the `brand` scale in [`tailwind.config.js`](../tailwind.config.js):

| Token | Hex | Typical use |
| --- | --- | --- |
| `brand-50` | `#eefdfb` | Tinted backgrounds, subtle badges |
| `brand-100` | `#d3f8f3` | Hover fills, soft chips |
| `brand-500` | `#14ada6` | Focus rings, logo gradient start, accents |
| `brand-600` | `#0b8c87` | **Primary buttons, links, active states** |
| `brand-700` | `#0e6f6c` | Button hover, gradient depth |
| `brand-800`–`950` | … | Hero/CTA gradients, deep surfaces |

**Single source of truth.** Tailwind's default `indigo` is aliased to the `brand`
scale, so any stray `indigo-*` utility still renders on-brand — but new code
should always use `brand-*`. All views have been migrated off `indigo-*`.

### Amber accent

`accent` (amber, `#f59e0b` at 500) is the intentional counterweight to teal —
used sparingly for salary, "new", and mid-strength matches. Never a primary
action color.

### Semantic status colors

Domain statuses map to a fixed palette in `components/ui/badge.blade.php` and
`components/status-badge.blade.php` (green = active/accepted, red = closed/rejected,
amber = pending, etc.). Purple is reserved for the admin surface.

## Typography

- **Family:** [Figtree](https://fonts.bunny.net) for both body and display
  (`font-sans` / `font-display`), loaded in the layout `<head>`.
- **Type scale:** headings get consistent, confident defaults from the base layer
  in `resources/css/app.css` — tight tracking, balanced wrapping (`text-wrap:
  balance`), and a step scale (`h1` → `text-3xl sm:text-4xl`, down to `h4`).
  Explicit size utilities in the markup (e.g. the hero's `text-6xl`) always win,
  so pages scale up without fighting the base.
- **Weights:** `400/500/600/700/800`. Headings are `700`+ (`800` for `h1` and
  hero/section titles); body is `400`.
- **Eyebrow:** the `.eyebrow` component class — a small teal, tracked, uppercase
  kicker with a leading rule — is part of the voice above section titles.

## Depth (shadows)

One elevation ladder, defined as `boxShadow` tokens — never ad-hoc:

| Token | Use |
| --- | --- |
| `shadow-soft` | Resting cards, sticky navbar |
| `shadow-card` | Slightly lifted surfaces |
| `shadow-elevated` | Hover state for interactive cards |
| `shadow-glow` / `shadow-glow-lg` | Teal-tinted glow for the logo mark & CTA |

## Radius & surface

- Cards and panels use the `.surface` component class (`bg-white`, `border`,
  `rounded-2xl`, `shadow-soft`) — see `resources/css/app.css`. Use
  `<x-ui.card>` rather than re-deriving it.
- Buttons and inputs are `rounded-xl`; pills/badges are `rounded-full`;
  the largest containers (CTA) use `rounded-4xl` (`2rem`).

## Components

The `resources/views/components/ui/` library is the one visual language for
interactive elements — always prefer these over hand-rolled markup:

- `<x-ui.button variant="…" size="…">` — variants: `primary`, `secondary`,
  `outline`, `ghost`, `ghost-light`, `danger`, `white`.
- `<x-ui.card>`, `<x-ui.badge>`, `<x-ui.input>`, `<x-ui.avatar>`,
  `<x-ui.alert>`, `<x-ui.empty-state>`, `<x-ui.toast>`.

The legacy Breeze components (`primary-button`, `secondary-button`,
`text-input`, `nav-link`, …) have been re-skinned to the same brand voice
(teal, `rounded-xl`, proper focus rings) so both systems match.

## Accessibility & motion

- **Focus:** a single `:focus-visible` ring (`ring-2 ring-brand-500
  ring-offset-2`) is applied globally in the base layer; buttons/links reinforce
  it. A "Skip to content" link opens the app layout.
- **Contrast:** primary text is `gray-900`/`gray-700`; teal actions use
  `brand-600`+ on white for AA contrast.
- **Motion:** all scroll-reveal, counters, and float animations degrade
  gracefully — fully visible with no JS, and suppressed under
  `prefers-reduced-motion`.
