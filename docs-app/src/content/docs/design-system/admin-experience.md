---
title: Admin experience
description: The complete scoped CoreX login and wp-admin product experience, built on truthful runtime state.
---

The CoreX admin is a dark-first product surface inside WordPress, with a complete light mapping. It preserves
WordPress admin chrome and authentication behavior while giving every current CoreX-owned screen one visual and
state contract. It never styles the public frontend.

PR #58 delivered the truthful-state foundation: add-on state resolution, state-aware settings, captcha gating,
write-only secrets, and initial Add-ons badge CSS. The Spec 060 admin-design implementation then applied the approved
visual design across the complete current admin journey — and has **landed (merged via PR #59)**, render-verified in
dark and light. The real `wp-login.php` carries the CoreX login design (branded mark, ambient grid + glow, a separate
disabled SSO slot with an "or" divider, leading user/lock field icons, brass button, reduced-motion-aware motion;
dark-first, with the saved CoreX appearance controlling it for logged-out users), and every CoreX admin screen uses
the full-bleed CoreX shell scoped to CoreX screens only.

## Current surfaces

- Native WordPress login, lost-password, reset-password, message, and error markup with additive CoreX branding.
- `COREX FRAMEWORK` Overview with live site stat cards and onboarding/domain status cards.
- Add-ons as truthful cards with labelled states and enable/disable controls for installed add-ons only.
- Data as a truthful explorer: a Sources/Models rail that drives the active source, a real 14-day records chart from
  submission timestamps, a derived field schema, search/source-filter/reset/CSV export, row selection with a
  nonce-confirmed bulk delete, an accessible record drawer (focus trap, Escape, focus return), pagination, and
  explicit loading/empty/filtered-empty/error states.
- Settings as real tabs (Brand → Mail → Forms → Captcha → Insights) over state-aware sections, with a live admin
  appearance setting (System/Light/Dark), an admin-logo preview, an SSO-slot toggle, and write-only secret indicators.
- Setup Wizard with guided progress, kit cards, empty state, and apply success notice.
- Readiness & Insights with performance/readiness cards, loading/error/result states, and honest environment gating.
- Declarative CoreX option pages registered by applications.

## Truthful add-on states

`Corex\Foundation\AddonStatus` resolves every add-on to exactly one state through the pure `AddonStatusResolver`:

| State | Meaning |
|---|---|
| `not_installed` | The package/plugin is absent; no admin enable or install action. |
| `inactive` | Installed, but the WordPress plugin is not active. |
| `feature_off` | Active, but the CoreX feature flag is off. |
| `dependency_missing` | A required CoreX add-on is unmet. |
| `woocommerce_missing` | WooCommerce is required and unavailable. |
| `pro_required` | Static disabled future/commercial indicator; no licensing or purchase flow. |
| `active` | Active and fully satisfied; the only usable state. |

Status meaning is always written as text and reinforced with icon/tone. Color never carries the meaning alone.
The admin manages installed add-ons only. Package installation remains developer/CLI/deployment work.

## Settings and secrets

Settings sections derive from `Corex\Config\Settings\SettingsSectionState`: not installed, disabled, configuration
needed, or normal. Captcha is the worked example, and its fields are **provider-specific and driver-reactive**: each
driver shows only its own fields, descriptions, and official references — **None** (disabled notice), **Honeypot**
(a no-key spam-trap, no keys shown), **reCAPTCHA** (site/secret keys + v3 score/action + Google links), **hCaptcha**
(keys + hCaptcha links), and **Cloudflare Turnstile** (keys + Cloudflare links). Provider fields never appear usable
while the add-on is absent or inactive.

Password-typed settings, including captcha and Insights credentials, are write-only. A saved value is represented by
a “Saved” indicator; the input value remains empty. Submitting an empty secret preserves the stored value. Saves use
the shared `AdminGuard` capability and nonce path.

## Visual and accessibility contract

The registered `corex-admin-tokens` adapter supplies only `--corex-admin-*` roles. A shared shell and each
screen-specific stylesheet are conditionally enqueued through an explicit CoreX screen allow-list. Selectors are
rooted in `.corex-admin`; login uses the separate `body.login.corex-login` root. Unrelated wp-admin pages and public
frontend pages do not receive these assets.

The component layer includes page headers, stat cards, add-on cards, settings sections, data tables/toolbars, notices,
badges, helper text, setup progress, readiness checks, and loading/empty/error/success/warning/disabled/
permission-denied states. It uses logical CSS properties for RTL, collapses at narrow admin widths, has visible focus,
keeps text contrast at the WCAG 2.2 AA target, and disables non-essential motion under `prefers-reduced-motion`.

## Loading states

A surface that asks the server for its content is in one of four states, and it says which on its own element as
`data-corex-state`:

| State | What a person sees |
|---|---|
| `loading` | A placeholder in the shape of the content. Never the empty state: nothing has been said yet. |
| `refreshing` | What was there, kept in place, dimmed and out of reach, with a bar along its top edge. |
| `ready` | The answer, which may be the empty state. |
| `error` | The shared error state, with **Try again** where asking again can help. |

The Notifications screen, its preferences, the header drawer and the Data screen work this way. The other screens
are moved to it one at a time; until then they keep the loading sentence or spinner they had.

### In a React screen

`CorexLoadable` is the wrapper and `CorexSkeleton` the placeholder, both in
`plugins/corex-config/src/admin/components/`:

```jsx
<CorexLoadable
	status={ status }
	skeleton={ <NotificationSkeleton place="screen" count={ 4 } /> }
	loadingLabel={ __( 'Loading notifications…', 'corex' ) }
	errorMessage={ __( 'Notifications could not be loaded.', 'corex' ) }
	onRetry={ load }
>
	{ /* the content, including its empty state */ }
</CorexLoadable>
```

`status` is the screen's own: `loading` until the first answer, `refreshing` for every load after one has been
shown, `ready`, or `error`. Leave `onRetry` out where asking again cannot help, and no button is drawn.
`errorTitle` names what failed where the sentence alone does not, and `errorDetail` carries whatever the server
said.

A list that stays on screen while it is replaced can be asked for twice before it has answered once. Keep the
answer to the request that is current, and drop the others: an effect's cleanup, or a counter, is enough.

What stands beside a waiting list and belongs to the same answer, a total above a table, takes
`data-corex-waiting="true"` while the list is `refreshing`, and is dimmed with it.

A search box asks when typing pauses. `useDebounced( callback, 300 )`, in
`plugins/corex-config/src/admin/useDebounced.js`, returns a function to call on every change; keep what is typed
in the component's own state so the box shows it at once.

A screen draws its placeholder inside the markup its content uses, with a `SkeletonBar` where a line of text will
be and a `SkeletonBox` where an icon or a checkbox will be:

```jsx
<CorexSkeleton>
	<ul className="corex-notifications-prefs">
		<li className="corex-notifications-prefs__row">
			<span className="corex-notifications-prefs__label">
				<SkeletonBox />
				<SkeletonBar width="short" />
			</span>
		</li>
	</ul>
</CorexSkeleton>
```

Where a `div` may not go, in a paragraph or a heading, `<CorexSkeleton as="span">` draws a `span`.

A bar is as tall as a line of the element it is in, so the row is the height it will be with its text, from the
same rules. `width` is `full`, `long`, `medium` or `short`. A bar's width is a share of its parent's, so a
parent that is only as wide as its text (an inline flex label, say) has to be given a width.

### Elsewhere

The class names are the contract, for a screen that is not React: `.corex-admin-skeleton` round the placeholder
(with `aria-hidden="true"`), `.corex-admin-skeleton__bar` (and `--long`, `--medium`, `--short`),
`.corex-admin-skeleton__box`, and `.corex-loadable` with `data-corex-state` on the surface and
`.corex-loadable__body` round what is dimmed. They are styled in the admin shell stylesheet, for anything inside
`.corex-admin`.

### What it does for you

- Nothing is drawn for the first 160ms of a wait (`--corex-admin-loading-delay`), so a fast answer replaces a
  placeholder nobody saw. The space is held from the first frame.
- Under `prefers-reduced-motion` the placeholder is still drawn and nothing moves.
- The placeholder is hidden from assistive technology. A load that lasts a second is announced with
  `loadingLabel`, and its end with "Loaded."; a quicker one, and a refresh, are not announced.
- Content that is `refreshing` is `inert`. If the keyboard's focus was inside it, focus moves to the surface.

### A control that is working

A button whose request is on its way is given `workingProps`, from
`plugins/corex-config/src/admin/components/working.js`, spread after its own props:

```jsx
<button type="button" disabled={ ! dirty } onClick={ save } { ...workingProps( saving ) }>
	{ __( 'Save changes', 'corex' ) }
</button>
```

While `saving` is true the button is disabled, says it is busy to assistive technology, and shows the CoreX loader
in its own colour in place of its label. The label stays in the layout, so the button keeps its width and its name;
do not change the label to "Saving…". When `saving` is false nothing is added, so a `disabled` the button has for
a reason of its own is left alone.

Say the outcome where the action was taken: a failure the person cannot see is the same as a button that did
nothing.

Where one flag disables every button on a screen while any request is out (Email Studio, Forms and flows), only
the button that was pressed works; the others wait. The screen provides the name of the control whose request is
out through `PendingControl`, from the same file, and each button compares it with its own:

```jsx
const pending = useContext( PendingControl );

<button disabled={ busy } { ...workingProps( pending === 'draft' ) }>
```

A component that sends its own requests gets the name from `usePending`, also in that file:

```jsx
const [ pending, during ] = usePending();

<Button
	disabled={ pending !== '' }
	onClick={ () => during( 'commit', commit ) }
	{ ...workingProps( pending === 'commit' ) }
>
```

`during` names the control for the whole of the task, through its failure, and returns what the task returns.
For a row of a list, put the row's id in the name.

Keep it working until the screen is current again. A save is a write and then a read of what was written, and a
button that stops after the write looks finished while the screen still shows what was there before.

On a screen that is not React, write the three attributes by hand: `disabled`, `aria-busy="true"` and
`data-corex-working="true"`. They are styled for any control on a CoreX admin screen, including one in a WordPress
`Modal`, which is drawn outside `.corex-admin`.

WordPress's `isBusy` is not used in the admin; a test fails if it comes back.

### In a browser test

Wait on the state, which fails when a surface never becomes ready:

```js
await expect( page.locator( '.corex-notifications-screen .corex-loadable' ) )
	.toHaveAttribute( 'data-corex-state', 'ready' );
```

Waiting for a loading sentence to be hidden passes when the sentence was never there.

## Verification boundary

Headless PHP/JS contracts verify asset scoping, native-login preservation, screen shell coverage, text-labelled
states, add-on truth, write-only secrets, driver-aware captcha visibility, and empty-secret preservation. The Spec
060 visual-evidence record holds the rendered browser matrix — every CoreX surface was rendered dark and light
(authenticated; the login logged-out) and compared against the approved captures. The remaining manual sweep (RTL
mirroring, 200% zoom, full-keyboard) is tracked as backlog.

## Out of scope

Public company-site design, marketplace/download/install UI, Pro licensing or entitlement, client portal, editor
workspace, and generators remain outside M6.
