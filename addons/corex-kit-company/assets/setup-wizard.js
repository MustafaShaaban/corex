/**
 * CoreX Setup Wizard — the nine-step guided flow (spec 068 T196). Vanilla JS over window.Corex.api:
 * it loads the real config + progress from /setup/state, walks the nine steps, previews a kit plan
 * with real conflicts, and applies with the operator's conflict choices only after the person confirms
 * they have a full backup of their own (FR-143). CoreX takes no backup: it records the pages a plan
 * replaces or adopts and the front-page settings, so a reset can put them back (spec 115). Progressive enhancement — the server-rendered flow is the
 * no-JS fallback. No build step; accessible, keyboard-operable.
 *
 * @param {Object} wp   The global `window.wp` (apiFetch + i18n).
 * @param {Object} data The localized `window.corexSetup` config.
 */
( function ( wp, data ) {
	const root = document.getElementById( 'corex-setup-app' );
	if ( ! root || ! window.Corex || ! window.Corex.api || ! data ) {
		return;
	}

	const api = window.Corex.api;
	const { restUrl, readinessUrl, nonce, adminUrl } = data;
	// eslint-disable-next-line @wordpress/i18n-no-variables -- runtime translate helper; string literals are passed at every call site so extraction still works.
	const t = ( s ) => ( wp.i18n ? wp.i18n.__( s, 'corex' ) : s );

	const STEPS = [
		'welcome',
		'brand',
		'kit',
		'demo',
		'plan',
		'backup',
		'apply',
		'launch',
		'done',
	];

	const state = {
		index: 0,
		config: null,
		progress: null,
		kit: '',
		level: 'standard',
		choices: {},
		backupConfirmed: false,
		plan: null,
		// Why the plan could not be read, or could not be applied. Both used to fail without a
		// word: the plan step said "0 pages", and "Apply plan" came back as if never pressed.
		planError: '',
		applied: null,
		applyError: '',
		// The one launch checklist (spec 118): null until read, `false` when it could not be.
		readiness: null,
		// What the mode form answered, when the launch step's Go live came back here.
		launchStatus: '',
	};

	function escape( s ) {
		const d = document.createElement( 'div' );
		d.textContent = s === null || s === undefined ? '' : String( s );
		return d.innerHTML;
	}

	/**
	 * The admin's error for an action that did not happen, written as markup: this screen is
	 * not React.
	 *
	 * @param {string} message What to say.
	 * @return {string} The error's HTML.
	 */
	function failure( message ) {
		return `<div class="corex-error-state corex-error-state--action" role="alert"><p class="corex-error-state__message">${ escape(
			message
		) }</p></div>`;
	}

	function sectionUrl( page ) {
		return `${ adminUrl }admin.php?page=${ page }`;
	}

	function stepper() {
		const items = STEPS.map( ( key, i ) => {
			let cls = '';
			if ( i === state.index ) {
				cls = 'is-current';
			} else if ( i < state.index ) {
				cls = 'is-done';
			}
			const label = state.progress
				? ( state.progress.steps[ i ] || {} ).label || key
				: key;
			return `<li class="corex-setup__step ${ cls }"><span class="corex-setup__step-num">${
				i + 1
			}</span><span>${ escape( label ) }</span></li>`;
		} ).join( '' );
		const pct = Math.round( ( state.index / ( STEPS.length - 1 ) ) * 100 );
		return (
			`<ol class="corex-setup__stepper" aria-label="${ escape(
				t( 'Setup steps' )
			) }">${ items }</ol>` +
			`<div class="corex-setup__progress" role="progressbar" aria-valuenow="${ pct }" aria-valuemin="0" aria-valuemax="100"><span style="inline-size:${ pct }%"></span></div>`
		);
	}

	function kitOptions() {
		return ( state.config.kits || [] )
			.map(
				( kit ) =>
					`<label class="corex-setup__option"><input type="radio" name="corex-kit" value="${ escape(
						kit.name
					) }"${
						state.kit === kit.name ? ' checked' : ''
					} /> <strong>${ escape( kit.name ) }</strong></label>`
			)
			.join( '' );
	}

	function levelOptions() {
		return ( state.config.demoLevels || [] )
			.map(
				( lvl ) =>
					`<label class="corex-setup__option"><input type="radio" name="corex-level" value="${ escape(
						lvl.id
					) }"${
						state.level === lvl.id ? ' checked' : ''
					} /> <strong>${ escape(
						lvl.label
					) }</strong><span class="corex-setup__muted">${ escape(
						lvl.description
					) }</span></label>`
			)
			.join( '' );
	}

	function conflictControls() {
		const conflicts = ( state.plan && state.plan.conflicts ) || [];
		if ( conflicts.length === 0 ) {
			return `<p class="corex-setup__muted">${ escape(
				t( 'No conflicts — no existing page will be changed.' )
			) }</p>`;
		}
		const choices = state.config.conflictChoices || [];
		return conflicts
			.map( ( c ) => {
				const radios = choices
					.map(
						( ch ) =>
							`<label><input type="radio" name="conflict-${ escape(
								c.slug
							) }" value="${ escape( ch.id ) }"${
								( state.choices[ c.slug ] || 'keep' ) === ch.id
									? ' checked'
									: ''
							} /> ${ escape( ch.label ) }</label>`
					)
					.join( ' ' );
				return `<div class="corex-setup__conflict"><strong>${ escape(
					c.title
				) }</strong><div>${ radios }</div></div>`;
			} )
			.join( '' );
	}

	/**
	 * What CoreX records before applying, said on the welcome and on the Backup step, and by the
	 * server when an apply arrives without the confirmation.
	 *
	 * @return {string} The sentence, translated.
	 */
	function recordedSentence() {
		return t(
			'Before applying, CoreX records the pages this plan replaces or adopts and your front-page settings, so a reset can put them back. It does not back up anything else.'
		);
	}

	/**
	 * The owner's pages this plan will record: the ones it adopts, and the conflicts the person
	 * chose to replace. Each by the title the page has now.
	 *
	 * @return {string[]} The titles.
	 */
	function recordedTitles() {
		const plan = state.plan || {};
		// A page an earlier apply made is the kit's, and nothing of it is recorded.
		const owners = ( page ) => page.recorded !== false;
		const adopted = ( plan.adopted || [] )
			.filter( owners )
			.map( ( page ) => page.pageTitle );
		const replaced = ( plan.conflicts || [] )
			.filter(
				( c ) => owners( c ) && state.choices[ c.slug ] === 'replace'
			)
			.map( ( c ) => c.pageTitle || c.title );
		return [ ...adopted, ...replaced ];
	}

	function backupStep() {
		const titles = recordedTitles();
		const list =
			titles.length > 0
				? `<p>${ escape(
						t( 'The pages that will be recorded:' )
					) }</p><ul class="corex-setup__recorded">${ titles
						.map( ( title ) => `<li>${ escape( title ) }</li>` )
						.join( '' ) }</ul>`
				: `<p class="corex-setup__muted">${ escape(
						t( 'This plan changes none of your existing pages.' )
					) }</p>`;
		return (
			`<p>${ escape( recordedSentence() ) }</p>` +
			list +
			`<p>${ escape(
				t(
					'Take a full backup with your host or a backup plugin before you apply. Apply stays off until you confirm you have one.'
				)
			) }</p>` +
			`<label class="corex-setup__confirm"><input type="checkbox" id="corex-setup-backup"${
				state.backupConfirmed ? ' checked' : ''
			} /> ${ escape( t( 'I have a full backup of this site.' ) ) }</label>`
		);
	}

	function stepBody() {
		const key = STEPS[ state.index ];
		if ( key === 'welcome' ) {
			return (
				`<p>${ escape(
					t(
						'This wizard configures your brand, chooses a starter kit and demo level, and shows you the plan before it applies it.'
					)
				) }</p>` + `<p>${ escape( recordedSentence() ) }</p>`
			);
		}
		if ( key === 'brand' ) {
			const rows = ( state.config.brandFields || [] )
				.map( ( f ) => `<li>${ escape( f.label ) }</li>` )
				.join( '' );
			return (
				`<p>${ escape(
					t(
						'Set your company brand details — these save to CoreX Settings and are read across the site.'
					)
				) }</p>` +
				`<ul class="corex-setup__fields">${ rows }</ul>` +
				`<p><a class="button" href="${ escape(
					sectionUrl( 'corex-settings-config' )
				) }">${ escape( t( 'Open Brand settings' ) ) }</a></p>`
			);
		}
		if ( key === 'kit' ) {
			return `<p>${ escape(
				t( 'Choose the starter kit for this site.' )
			) }</p><div class="corex-setup__options">${ kitOptions() }</div>`;
		}
		if ( key === 'demo' ) {
			return `<p>${ escape(
				t( 'Choose how much demo content to seed.' )
			) }</p><div class="corex-setup__options">${ levelOptions() }</div>`;
		}
		if ( key === 'plan' ) {
			if ( state.planError ) {
				return failure( state.planError );
			}
			const pages = state.plan ? state.plan.plan.pages.length : 0;
			return (
				`<p>${ escape(
					t(
						'Review what will be applied. Existing pages are never overwritten unless you choose Replace.'
					)
				) }</p>` +
				`<p class="corex-setup__muted">${ pages } ${ escape(
					t( 'pages will be created or adopted.' )
				) }</p>` +
				`<div class="corex-setup__conflicts">${ conflictControls() }</div>`
			);
		}
		if ( key === 'backup' ) {
			return backupStep();
		}
		if ( key === 'apply' ) {
			if ( state.applied && ! state.applied.applied ) {
				// Some page could not be written: the kit is not recorded as applied (spec 118).
				return failure(
					t(
						'The plan was applied in part: some pages could not be written, so the kit is not marked applied. Check the pages, then apply again.'
					)
				);
			}
			if ( state.applied ) {
				return `<p class="corex-setup__ok">${ escape(
					t( 'Applied.' )
				) } ${ escape( String( state.applied.pages ) ) } ${ escape(
					t( 'pages processed.' )
				) }</p>`;
			}
			return (
				( state.applyError ? failure( state.applyError ) : '' ) +
				`<p>${ escape(
					t( 'Apply the plan now with your choices.' )
				) }</p>` +
				`<p><button type="button" class="button button-primary" id="corex-setup-apply"${
					state.backupConfirmed ? '' : ' disabled'
				}>${ escape( t( 'Apply plan' ) ) }</button></p>`
			);
		}
		if ( key === 'launch' ) {
			return launchStep();
		}
		return doneStep();
	}

	/**
	 * What the mode form answered, said on the step it came back to.
	 *
	 * @return {string} The notice's HTML, or ''.
	 */
	function launchNotice() {
		const messages = {
			saved: t( 'The site is now in Production.' ),
			unchanged: t( 'The site was already in Production.' ),
			production_confirm: t(
				'Going live needs the confirmation phrase. Type it and try again.'
			),
			blocked: t(
				'Going live was refused: checks still block it. Resolve them, or confirm the override.'
			),
		};
		const message = messages[ state.launchStatus ];
		if ( ! message ) {
			return '';
		}
		return [ 'saved', 'unchanged' ].includes( state.launchStatus )
			? `<p class="corex-setup__ok" role="status">${ escape(
					message
				) }</p>`
			: failure( message );
	}

	/**
	 * The launch step (spec 118, US-WIZ-06): the one launch checklist, in its two groups, each check
	 * in the same words as the Operations screen; and, while the site is not live, Go live through
	 * the Operations screen's own mode form, so it asks the same confirmation and writes the same
	 * history row.
	 *
	 * @return {string} The step's HTML.
	 */
	function launchStep() {
		if ( state.readiness === null ) {
			return `<p class="corex-setup__muted" role="status">${ escape(
				t( 'Reading the launch checklist…' )
			) }</p>`;
		}
		if ( state.readiness === false ) {
			return (
				failure( t( 'The launch checklist could not be read.' ) ) +
				`<p><button type="button" class="button" id="corex-setup-readiness-retry">${ escape(
					t( 'Retry' )
				) }</button></p>`
			);
		}
		const list = state.readiness.checklist;
		const groups = ( list.groups || [] )
			.map(
				( group ) =>
					`<h3 class="corex-checklist__group-head"><span>${ escape(
						group.title
					) }</span><span class="corex-checklist__group-count">${ escape(
						group.count
					) }</span></h3>` +
					`<ul class="corex-checklist">${ group.rows
						.map( checklistRow )
						.join( '' ) }</ul>`
			)
			.join( '' );

		return (
			launchNotice() +
			`<p class="corex-setup__summary"><strong>${ escape(
				list.count
			) }</strong> · ${ escape( list.blockingText ) }</p>` +
			groups +
			goLive()
		);
	}

	/**
	 * One check, as the server draws it on the Hardening tab.
	 *
	 * @param {Object} row A checklist row.
	 * @return {string} The row's HTML.
	 */
	function checklistRow( row ) {
		const label = row.href
			? `<a href="${ escape( row.href ) }">${ escape( row.label ) }</a>`
			: escape( row.label );
		return (
			`<li class="corex-checklist__row is-${ escape(
				row.chip
			) }" data-check="${ escape( row.key ) }">` +
			`<span class="corex-checklist__label">${ label }</span>` +
			`<span class="corex-state-chip is-${ escape(
				row.chip
			) }"><span class="corex-state-chip__icon" aria-hidden="true"></span>${ escape(
				row.word
			) }</span>` +
			`<span class="corex-checklist__note">${ escape(
				row.note
			) }</span></li>`
		);
	}

	/**
	 * Go live: the Operations screen's mode form, proposing Production with its phrase field.
	 *
	 * @return {string} The form's HTML, or '' once the site is live.
	 */
	function goLive() {
		const { mode, goLive: form } = state.readiness;
		if ( mode.production ) {
			return `<p class="corex-setup__ok">${ escape(
				t( 'The site is live.' )
			) }</p>`;
		}
		return (
			`<form class="corex-setup__golive" method="post" action="${ escape(
				form.url
			) }">` +
			`<input type="hidden" name="action" value="${ escape(
				form.action
			) }" />` +
			`<input type="hidden" name="${ escape(
				form.nonceField
			) }" value="${ escape( form.nonce ) }" />` +
			`<input type="hidden" name="corex_mode" value="${ escape(
				form.mode
			) }" />` +
			`<input type="hidden" name="corex_return" value="${ escape(
				form.return
			) }" />` +
			`<label for="corex-setup-golive-phrase">${ escape(
				t( 'To go live, type the confirmation phrase' )
			) } <code>${ escape( form.phrase ) }</code></label>` +
			`<input type="text" id="corex-setup-golive-phrase" name="corex_confirm_phrase" autocomplete="off" />` +
			`<p><button type="submit" class="button button-primary">${ escape(
				t( 'Go live' )
			) }</button></p></form>`
		);
	}

	/**
	 * The Done step says what is left, or that the site is live; never "ready" while a check
	 * blocks going live (spec 118, US-WIZ-06).
	 *
	 * @return {string} The step's HTML.
	 */
	function doneStep() {
		if ( ! state.readiness ) {
			return (
				launchNotice() +
				`<p class="corex-setup__muted">${ escape(
					t(
						'Setup is done. Open the launch checklist to see what is left before going live.'
					)
				) }</p>`
			);
		}
		const { checklist, mode } = state.readiness;
		let text = checklist.blockingText;
		if ( mode.production ) {
			text = t( 'Setup is done and the site is live.' );
		}
		return (
			launchNotice() +
			`<p class="${
				checklist.blocking > 0 ? 'corex-setup__warn' : 'corex-setup__ok'
			}">${ escape( text ) }</p>` +
			`<p><a class="button" href="${ escape(
				sectionUrl( 'corex-operations-security' )
			) }&amp;tab=environment">${ escape(
				t( 'Open Operations & Security' )
			) }</a></p>`
		);
	}

	/** Reads the launch checklist, then draws the step again. */
	function loadReadiness() {
		state.readiness = null;
		return api.get( readinessUrl, { nonce } ).then( ( res ) => {
			state.readiness = res.envelope.ok ? res.envelope.data : false;
			render();
		} );
	}

	function render() {
		const key = STEPS[ state.index ];
		const canBack = state.index > 0;
		const canNext = state.index < STEPS.length - 1;
		root.innerHTML =
			stepper() +
			`<section class="corex-setup__panel corex-surface"><h2>${ escape(
				( state.progress.steps[ state.index ] || {} ).label || key
			) }</h2>` +
			stepBody() +
			`<footer class="corex-setup__nav">` +
			( canBack
				? `<button type="button" class="button" id="corex-setup-back">${ escape(
						t( 'Back' )
					) }</button>`
				: '' ) +
			( canNext
				? `<button type="button" class="button button-primary" id="corex-setup-next">${ escape(
						t( 'Next' )
					) }</button>`
				: '' ) +
			`</footer></section>`;
		wire();
	}

	function wire() {
		const back = root.querySelector( '#corex-setup-back' );
		const next = root.querySelector( '#corex-setup-next' );
		if ( back ) {
			back.addEventListener( 'click', () => go( state.index - 1, back ) );
		}
		if ( next ) {
			next.addEventListener( 'click', () => go( state.index + 1, next ) );
		}
		root.querySelectorAll( 'input[name="corex-kit"]' ).forEach( ( el ) =>
			el.addEventListener( 'change', ( e ) => {
				state.kit = e.target.value;
			} )
		);
		root.querySelectorAll( 'input[name="corex-level"]' ).forEach( ( el ) =>
			el.addEventListener( 'change', ( e ) => {
				state.level = e.target.value;
			} )
		);
		root.querySelectorAll( 'input[name^="conflict-"]' ).forEach( ( el ) =>
			el.addEventListener( 'change', ( e ) => {
				const slug = e.target.name.replace( 'conflict-', '' );
				state.choices[ slug ] = e.target.value;
			} )
		);
		const backup = root.querySelector( '#corex-setup-backup' );
		if ( backup ) {
			backup.addEventListener( 'change', ( e ) => {
				state.backupConfirmed = e.target.checked;
				render();
			} );
		}
		const apply = root.querySelector( '#corex-setup-apply' );
		if ( apply ) {
			apply.addEventListener( 'click', doApply );
		}
		const retry = root.querySelector( '#corex-setup-readiness-retry' );
		if ( retry ) {
			retry.addEventListener( 'click', () => {
				showWorking( retry );
				loadReadiness();
			} );
		}
	}

	/**
	 * The admin's one working state (spec 108), on a button this script drew: it cannot be
	 * pressed again, says it is busy, and the styles draw the loader over its label. The step
	 * is drawn again when the answer arrives, which is what ends it.
	 *
	 * @param {HTMLButtonElement|null} button The button that was pressed.
	 */
	function showWorking( button ) {
		if ( ! button ) {
			return;
		}
		root.querySelectorAll( '.corex-setup__nav button' ).forEach(
			( other ) => {
				other.disabled = true;
			}
		);
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		button.setAttribute( 'data-corex-working', 'true' );
	}

	async function go( index, pressed ) {
		// Fetch the plan preview when entering the plan step.
		if ( STEPS[ index ] === 'plan' && state.kit ) {
			// The step used to stay as it was, with nothing said, until the plan arrived, and
			// a second press asked for it again.
			showWorking( pressed );
			// On a site without pretty permalinks the route is itself a query
			// (`?rest_route=/corex/v1/setup/plan`), and a `?` written after it made the kit
			// part of the route. A `URL` adds to whatever query the address has. This script
			// has no build step, so it cannot use the admin's `withQuery()`.
			const plan = new URL( `${ restUrl }/plan`, window.location.href );
			plan.searchParams.set( 'kit', state.kit );
			plan.searchParams.set( 'level', state.level );
			const res = await api.get( plan.toString(), { nonce } );
			state.plan = res.envelope.ok ? res.envelope.data : null;
			state.planError = res.envelope.ok
				? ''
				: res.envelope.message ||
					t( 'The plan could not be read. Go back and try again.' );
		}
		state.index = Math.max( 0, Math.min( STEPS.length - 1, index ) );
		render();
		if ( needsReadiness() ) {
			loadReadiness();
		}
	}

	/** The launch and Done steps read the checklist each time they are entered. */
	function needsReadiness() {
		return [ 'launch', 'done' ].includes( STEPS[ state.index ] );
	}

	function doApply() {
		showWorking( root.querySelector( '#corex-setup-apply' ) );
		api.post(
			`${ restUrl }/apply`,
			{
				kit: state.kit,
				level: state.level,
				choices: state.choices,
				confirm: true,
			},
			{ nonce }
		).then( ( res ) => {
			state.applied = res.envelope.ok ? res.envelope.data : null;
			state.applyError = res.envelope.ok
				? ''
				: res.envelope.message ||
					t( 'The plan could not be applied. Try again.' );
			render();
		} );
	}

	/*
	 * Until the wizard's state has arrived (spec 108): the step that is coming, as a
	 * placeholder, where the form for a browser without scripts used to stand and then be
	 * swapped for the wizard. The admin's class names, written as markup: this screen is not
	 * React.
	 */
	const fallback = document.querySelector( '.corex-setup-fallback' );
	const bar = ( width ) =>
		`<span class="corex-admin-skeleton__bar corex-admin-skeleton__bar--${ width }"></span>`;

	if ( fallback ) {
		fallback.hidden = true;
	}
	root.setAttribute( 'data-corex-state', 'loading' );
	root.setAttribute( 'aria-busy', 'true' );
	root.innerHTML =
		`<section class="corex-setup__panel corex-surface corex-admin-skeleton" aria-hidden="true">` +
		`<h2>${ bar( 'medium' ) }</h2>` +
		`<p>${ bar( 'full' ) }</p><p>${ bar( 'long' ) }</p>` +
		`</section>`;

	api.get( `${ restUrl }/state`, { nonce } ).then( ( res ) => {
		root.removeAttribute( 'aria-busy' );
		if ( ! res.envelope.ok ) {
			// The wizard cannot be drawn. The form the server drew still works: it comes
			// back, where it used to be left on screen by a script that had given up
			// without a word.
			root.setAttribute( 'data-corex-state', 'error' );
			root.innerHTML = '';
			if ( fallback ) {
				fallback.hidden = false;
			}
			return;
		}
		root.setAttribute( 'data-corex-state', 'ready' );
		state.config = res.envelope.data.config;
		state.progress = res.envelope.data.progress;
		state.kit = ( state.config.kits[ 0 ] || {} ).name || '';
		// Back from the launch step's Go live: open on the step the mode form sent it to, with
		// what it answered (spec 118).
		const back = new URL( window.location.href ).searchParams;
		const step = STEPS.indexOf( back.get( 'corex_step' ) || '' );
		if ( step >= 0 ) {
			state.index = step;
			state.launchStatus = back.get( 'corex_status' ) || '';
		}
		render();
		if ( needsReadiness() ) {
			loadReadiness();
		}
	} );
} )( window.wp, window.corexSetup );
