/**
 * CoreX Setup Wizard — the nine-step guided flow (spec 068 T196). Vanilla JS over window.Corex.api:
 * it loads the real config + progress from /setup/state, walks the nine steps, previews a kit plan
 * with real conflicts, and applies with the operator's conflict choices only after an explicit
 * backup confirmation (FR-140/143). Progressive enhancement — the server-rendered flow is the
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
	const { restUrl, nonce, adminUrl } = data;
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
		applied: null,
	};

	function escape( s ) {
		const d = document.createElement( 'div' );
		d.textContent = s === null || s === undefined ? '' : String( s );
		return d.innerHTML;
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

	function stepBody() {
		const key = STEPS[ state.index ];
		if ( key === 'welcome' ) {
			return `<p>${ escape(
				t(
					'This wizard configures your brand, chooses a starter kit and demo level, previews the plan, and applies it safely with a backup.'
				)
			) }</p>`;
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
			return (
				`<p>${ escape(
					t(
						'Back up your site before applying. Apply is blocked until you confirm.'
					)
				) }</p>` +
				`<label class="corex-setup__confirm"><input type="checkbox" id="corex-setup-backup"${
					state.backupConfirmed ? ' checked' : ''
				} /> ${ escape(
					t(
						'I have a current backup and understand content may change.'
					)
				) }</label>`
			);
		}
		if ( key === 'apply' ) {
			if ( state.applied ) {
				return `<p class="corex-setup__ok">${ escape(
					t( 'Applied.' )
				) } ${ escape( String( state.applied.pages ) ) } ${ escape(
					t( 'pages processed.' )
				) }</p>`;
			}
			return (
				`<p>${ escape(
					t( 'Apply the plan now with your choices.' )
				) }</p>` +
				`<p><button type="button" class="button button-primary" id="corex-setup-apply"${
					state.backupConfirmed ? '' : ' disabled'
				}>${ escape( t( 'Apply plan' ) ) }</button></p>`
			);
		}
		if ( key === 'launch' ) {
			return (
				`<p>${ escape(
					t(
						'Resolve launch blockers — indexing, debug output, email, security, and readiness — before going live.'
					)
				) }</p>` +
				`<p><a class="button" href="${ escape(
					sectionUrl( 'corex-operations-security' )
				) }">${ escape( t( 'Open Operations & Security' ) ) }</a> ` +
				`<a class="button" href="${ escape(
					sectionUrl( 'corex-insights' )
				) }">${ escape( t( 'Open Insights' ) ) }</a></p>`
			);
		}
		return `<p class="corex-setup__ok">${ escape(
			t( 'Setup complete. Your site is ready to review.' )
		) }</p>`;
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
			back.addEventListener( 'click', () => go( state.index - 1 ) );
		}
		if ( next ) {
			next.addEventListener( 'click', () => go( state.index + 1 ) );
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
	}

	async function go( index ) {
		// Fetch the plan preview when entering the plan step.
		if ( STEPS[ index ] === 'plan' && state.kit ) {
			const res = await api.get(
				`${ restUrl }/plan?kit=${ encodeURIComponent(
					state.kit
				) }&level=${ encodeURIComponent( state.level ) }`,
				{ nonce }
			);
			state.plan = res.envelope.ok ? res.envelope.data : null;
		}
		state.index = Math.max( 0, Math.min( STEPS.length - 1, index ) );
		render();
	}

	function doApply() {
		const button = root.querySelector( '#corex-setup-apply' );
		if ( button ) {
			button.disabled = true;
			button.textContent = t( 'Applying…' );
		}
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
			render();
		} );
	}

	api.get( `${ restUrl }/state`, { nonce } ).then( ( res ) => {
		if ( ! res.envelope.ok ) {
			return;
		}
		state.config = res.envelope.data.config;
		state.progress = res.envelope.data.progress;
		state.kit = ( state.config.kits[ 0 ] || {} ).name || '';
		// The JS wizard has taken over — hide the no-JS server fallback.
		const fallback = document.querySelector( '.corex-setup-fallback' );
		if ( fallback ) {
			fallback.hidden = true;
		}
		render();
	} );
} )( window.wp, window.corexSetup );
