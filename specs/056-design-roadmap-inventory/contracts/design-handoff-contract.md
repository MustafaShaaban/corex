# Contract: Design-to-Engineering Handoff

## Purpose

This contract defines the minimum evidence required before an external Corex design area may become a detailed engineering spec.

## Required sections

Every handoff must contain:

1. **Identity**: title, source inventory area, design source/reference, review date, approval state, and owner.
2. **Outcome**: the user/product result this design enables.
3. **In scope**: screens, components, variants, and states included in the handoff.
4. **Out of scope**: adjacent concepts and future variants explicitly excluded.
5. **Content model**: required/optional content, maximum practical lengths, missing content, media ratios, localization, and user-generated content constraints.
6. **Interaction behavior**: triggers, keyboard behavior, focus movement, dismissal, navigation, loading, empty, error, success, disabled, and dependency states as relevant.
7. **Responsive behavior**: desktop, tablet, and mobile layout and interaction changes; overflow and touch behavior.
8. **Directionality**: LTR, RTL, icon/arrow mirroring, ordered content, mixed-script text, numbers, and directional controls.
9. **Accessibility**: semantics, accessible names, keyboard order, visible focus, contrast, target size, announcements, reduced motion, and non-color cues.
10. **Performance**: conditional assets, server-rendered fallback, media loading, animation limits, and third-party dependency constraints.
11. **Tokens and primitives**: semantic token roles and reusable component relationships; no client-specific identity embedded in Corex defaults.
12. **Open questions**: unresolved issues, each either answered before specification or explicitly moved outside the selected scope.
13. **Approval evidence**: confirmation that the handoff, including non-visual behavior, is ready to enter Spec Kit.

## Readiness rules

A handoff is ready for specification only when:

- its inventory item is `approved`;
- every required section is present;
- critical responsive, RTL, accessibility, interaction-state, and performance behavior is explicit;
- open questions do not change the selected scope or acceptance criteria;
- exclusions prevent future/Pro concepts from leaking into current Core scope;
- the target engineering spec is one of the next two or three implementation items.

If any rule fails, the inventory item returns to `needs revision` and no product implementation starts.

## Change control

If the approved external design changes after specification:

1. update the inventory status to `needs revision`;
2. revise and reapprove the handoff;
3. update and review the engineering spec;
4. resume implementation only after the revised spec is authoritative.

The handoff never overrides the constitution, architecture reference, active engineering spec, or Free/Core security and accessibility baseline.
