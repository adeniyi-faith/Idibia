## 2026-05-20 - Added ARIA Labels to Icon-Only Modal Close Buttons
**Learning:** Icon-only buttons used for closing modals were missing `aria-label`s, making them inaccessible to screen readers.
**Action:** Added `aria-label="Close modal"` to these buttons across the customer and admin interfaces.

## 2026-05-22 - Added ARIA Labels to Interactive Icon-Only Elements (Star Ratings)
**Learning:** Icon-only interactive elements, such as star rating buttons, lack text content and are inherently inaccessible to screen readers without descriptive attributes.
**Action:** Always provide an explicit `aria-label` for icon-only buttons (e.g., `aria-label="Rate 5 stars"`) so screen reader users can understand and interact with the elements properly.

## 2026-05-23 - Added ARIA Labels to Secondary Icon-Only Interactive Elements
**Learning:** While primary icon-only buttons like modal closures had `aria-label`s added previously, secondary interactive elements (like password visibility toggles, map actions, location swap buttons, back buttons, and topbar notification icons) were missing them, causing accessibility issues for screen readers.
**Action:** Always verify that *all* icon-only buttons, regardless of their position or perceived importance (e.g., toggles, back navigation, topbar icons), have an explicit `aria-label` so screen reader users can understand their function.
