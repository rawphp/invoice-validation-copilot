---
name: Precision Ledger
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#45474c'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#75777d'
  outline-variant: '#c5c6cd'
  surface-tint: '#545f73'
  primary: '#091426'
  on-primary: '#ffffff'
  primary-container: '#1e293b'
  on-primary-container: '#8590a6'
  inverse-primary: '#bcc7de'
  secondary: '#4b41e1'
  on-secondary: '#ffffff'
  secondary-container: '#645efb'
  on-secondary-container: '#fffbff'
  tertiary: '#00190e'
  on-tertiary: '#ffffff'
  tertiary-container: '#00301e'
  on-tertiary-container: '#00a472'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d8e3fb'
  primary-fixed-dim: '#bcc7de'
  on-primary-fixed: '#111c2d'
  on-primary-fixed-variant: '#3c475a'
  secondary-fixed: '#e2dfff'
  secondary-fixed-dim: '#c3c0ff'
  on-secondary-fixed: '#0f0069'
  on-secondary-fixed-variant: '#3323cc'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-caps:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.05em
  data-mono:
    fontFamily: JetBrains Mono
    fontSize: 13px
    fontWeight: '450'
    lineHeight: 18px
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 40px
  column-gutter: 20px
  side-margin: 32px
---

## Brand & Style

The design system is engineered for high-stakes financial environments where accuracy is paramount. The brand personality is **authoritative, analytical, and hyper-efficient**, positioning the AI not as a replacement for the human auditor, but as a high-precision instrument. 

The aesthetic blends **Modern Corporate** reliability with **Technical Minimalism**. While the primary interface remains grounded in structured, legible data views, AI-driven insights are differentiated through subtle **Glassmorphism**. This creates a visual metaphor of an intelligent "overlay" that scans and interprets raw data. The emotional response should be one of absolute confidence and reduced cognitive load amidst complex financial datasets.

## Colors

The palette is rooted in **Deep Slate** (#1E293B) to establish a baseline of institutional trust and professional rigor. **Indigo** (#4F46E5) serves as the primary action and focus color, signaling the "intelligence" of the Copilot.

- **Success & Confidence:** **Emerald** (#10B981) is reserved for validated line items, successful batch processing, and "matched" statuses.
- **Validation & Attention:** **Amber** (#F59E0B) is used critically for discrepancies, missing metadata, and validation warnings that require human intervention.
- **Surface Strategy:** Backgrounds utilize a refined scale of cool grays from the Slate family to prevent eye fatigue during long sessions of data review.

## Typography

This design system prioritizes legibility for dense information. **Inter** is the primary typeface, chosen for its neutral tone and exceptional performance in data tables. To distinguish technical data strings (Invoice IDs, JSON payloads, Raw Extraction), **JetBrains Mono** is introduced for specific label and data roles.

- **Hierarchy:** Use `display-lg` sparingly for dashboard overviews.
- **Data Tables:** Use `body-md` for standard cell content.
- **Technical Strings:** Use `data-mono` for any text that represents raw extracted data or system-generated IDs to emphasize precision.

## Layout & Spacing

The layout utilizes a **12-column fixed-fluid hybrid grid**. The sidebar (Navigation and Copilot Panel) remains at fixed widths, while the central data workbench fluidly expands. 

- **The Workbench:** Designed for side-by-side comparison. The left pane typically displays the document (PDF/Image) while the right pane displays the extracted JSON/Form data.
- **Rhythm:** A strict 4px grid system ensures alignment in dense tables.
- **Breathing Room:** Despite the data density, a 32px external margin and 24px section spacing are maintained to prevent the UI from feeling claustrophobic.

## Elevation & Depth

This design system uses a combination of **Tonal Layers** and **Subtle Outlines** to maintain a "flat-but-structured" appearance.

- **Level 0 (Surface):** The main background using Slate 50.
- **Level 1 (Cards/Tables):** White surfaces with a 1px border (#E2E8F0) and no shadow.
- **AI Elements (The Copilot):** Uses **Glassmorphism**. A background blur of 12px, a semi-transparent Indigo/White tint, and a subtle inner-glow border to make AI suggestions appear to "float" over the static data.
- **Overlays:** Modals and dropdowns use a "Large" shadow role: `0 20px 25px -5px rgba(0, 0, 0, 0.1)`.

## Shapes

The shape language is **Soft (0.25rem base)** to maintain a professional, architectural feel. 

- **Standard Elements:** Buttons, inputs, and checkboxes use `rounded-sm`.
- **Containers:** Dashboard cards and the PDF viewer use `rounded-lg` (0.5rem) to softly frame the content.
- **Status Pills:** Use a full pill-shape (999px) to contrast against the rectangular nature of data rows, making status indicators immediately identifiable.

## Components

- **Buttons:** Primary buttons are Solid Indigo. Secondary buttons use a Ghost style with a Slate 200 border. 
- **Copilot Suggestions:** Floating glass cards with an Indigo-tinted blur. These should include a "Confidence Score" expressed as a percentage in the top-right corner.
- **Data Tables:** Rows use a hover state of Slate 50. Columns with validation errors should have a subtle Amber left-border (4px) to draw the eye during scrolling.
- **Input Fields:** Minimalist design with a 1px Slate 200 border. Focus state moves to a 2px Indigo border.
- **Validation Chips:** Small, uppercase status indicators. 
    - *Matched:* Green text/Green 50 background.
    - *Mismatch:* Amber text/Amber 50 background.
    - *Processing:* Indigo text/Indigo 50 background.
- **JSON Viewers:** Use a dark-mode block (Slate 900) even in light mode to differentiate code-level data from user-interface data, utilizing the `data-mono` typeface.