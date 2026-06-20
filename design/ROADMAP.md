# Corex Design Roadmap

This file tracks design exploration and approval separately from engineering implementation.

## Workflow

1. Explore and review design direction outside the repository.
2. Record coverage and status in `INVENTORY.md`.
3. Create a focused handoff in `handoffs/` only when an area is approved and implementation-ready.
4. Convert the approved handoff into a reviewed engineering spec.
5. Implement spec by spec; approval here does not authorize code changes.

## Current sequence

1. Maintain the design inventory and identify missing states and responsive/RTL behavior.
2. Use the approved [M2 brand foundation handoff](handoffs/brand-foundation.md) for Spec 057 planning and review.
3. Header, mobile navigation, mega-menu, and footer patterns are approved (see
   [M3 navigation and footer handoff](handoffs/navigation-footer.md)) now that the M2 token contract is reviewed and
   merged (PR #54). This handoff is the input to Spec 058.
4. Approve the company kit and the first required component batches.
5. Continue with admin UI, forms/email, portfolio, WooCommerce, docs, and marketing.

The engineering milestone dependencies and Free/Core versus Pro boundaries remain authoritative in the root `ROADMAP.md`.
