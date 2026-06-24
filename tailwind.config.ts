import type { Config } from 'tailwindcss';

/**
 * Tailwind v4 (CSS-first) configuration.
 *
 * The Precision Ledger design tokens — palette, fonts, radius, spacing — are
 * the SINGLE SOURCE OF TRUTH and live in `resources/css/app.css` under the
 * `@theme` directive (translated from
 * `.do-work/user-requests/UR-001/assets/precision_ledger/DESIGN.md`).
 *
 * In Tailwind v4 theme tokens are defined in CSS, not here, so this file only
 * declares content sources for class detection and stays free of duplicated
 * color values (which would risk token drift). Consume tokens via utilities
 * such as `bg-surface`, `bg-primary`, `text-secondary`, `font-mono`,
 * `rounded-lg` — never hardcode hex in components.
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,ts,vue}',
    ],
} satisfies Config;
