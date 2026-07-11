import { useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { blogReducer, initialBlogState, normalizeAnalytics } from './blogProState.js';

export default function BlogProApp( { config = {} } ) {
	const [ state ] = useReducer( blogReducer, {
		...initialBlogState(),
		status: 'ready',
		analytics: normalizeAnalytics( config.analytics || {} ),
		editorial: config.editorial || null,
		comments: Array.isArray( config.comments ) ? config.comments : [],
		authors: Array.isArray( config.authors ) ? config.authors : [],
		shareControls: Array.isArray( config.shareControls ) ? config.shareControls : [],
	} );
	const posts = Array.isArray( config.posts ) ? config.posts : [];

	return (
		<div className="corex-blog-pro-app">
			<section className="corex-blog-pro__panel">
				<div>
					<p className="corex-blog-pro__eyebrow">{ __( 'Analytics', 'corex' ) }</p>
					<h2>{ __( 'First-party reading signals', 'corex' ) }</h2>
				</div>
				<div className="corex-blog-pro__stats">
					<Metric label={ __( 'Views', 'corex' ) } value={ state.analytics.cards.views } />
					<Metric label={ __( 'Reads', 'corex' ) } value={ state.analytics.cards.reads } />
					<Metric label={ __( 'Share clicks', 'corex' ) } value={ state.analytics.cards.shareClicks } />
					<Metric label={ __( 'Avg. read seconds', 'corex' ) } value={ state.analytics.cards.averageReadSeconds } />
				</div>
			</section>

			<section className="corex-blog-pro__grid">
				<Card title={ __( 'Native posts', 'corex' ) }>
					{ posts.length === 0 ? (
						<p>{ __( 'Create a WordPress post to start using Blog Pro workflows.', 'corex' ) }</p>
					) : (
						<ul>
							{ posts.map( ( post ) => (
								<li key={ post.id }>
									<span>{ post.title || __( 'Untitled post', 'corex' ) }</span>
									<code>{ post.status }</code>
								</li>
							) ) }
						</ul>
					) }
				</Card>

				<Card title={ __( 'Editorial workflow', 'corex' ) }>
					{ state.editorial ? (
						<dl>
							<dt>{ __( 'CoreX state', 'corex' ) }</dt>
							<dd>{ state.editorial.editorial_state }</dd>
							<dt>{ __( 'Native status', 'corex' ) }</dt>
							<dd>{ state.editorial.native_status }</dd>
						</dl>
					) : (
						<p>{ __( 'No editable native post is selected.', 'corex' ) }</p>
					) }
				</Card>

				<Card title={ __( 'Moderation queue', 'corex' ) }>
					<List
						empty={ __( 'No comments are waiting for review.', 'corex' ) }
						items={ state.comments }
						renderItem={ ( comment ) => `${ comment.author || __( 'Anonymous', 'corex' ) } · ${ comment.state }` }
					/>
				</Card>

				<Card title={ __( 'Authors', 'corex' ) }>
					<List
						empty={ __( 'No authors have published posts yet.', 'corex' ) }
						items={ state.authors }
						renderItem={ ( author ) => `${ author.name } · ${ author.post_count || 0 }` }
					/>
				</Card>

				<Card title={ __( 'Sharing', 'corex' ) }>
					<List
						empty={ __( 'No share controls are configured.', 'corex' ) }
						items={ state.shareControls }
						renderItem={ ( control ) => `${ control.label } · ${ control.target }` }
					/>
				</Card>
			</section>
		</div>
	);
}

function Metric( { label, value } ) {
	return (
		<div className="corex-blog-pro__metric">
			<span>{ label }</span>
			<strong>{ value }</strong>
		</div>
	);
}

function Card( { title, children } ) {
	return (
		<div className="corex-blog-pro__card">
			<h3>{ title }</h3>
			{ children }
		</div>
	);
}

function List( { empty, items, renderItem } ) {
	if ( items.length === 0 ) {
		return <p>{ empty }</p>;
	}

	return (
		<ul>
			{ items.map( ( item, index ) => (
				<li key={ item.id || item.comment_id || item.target || item.name || index }>{ renderItem( item ) }</li>
			) ) }
		</ul>
	);
}
