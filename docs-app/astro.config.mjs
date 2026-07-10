// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// Corex documentation site.
//
// Local use:
//   - Instant dev server:  npm run dev   →  http://localhost:4321
//   - Static build:        npm run build →  ./dist  (serve with Apache)
//
// Apache (WAMP): build, then point a vhost `docs.corex.local` at `docs-app/dist`
// (DocumentRoot), or browse the repo path http://localhost/corex/docs-app/dist/.
// `base` stays '/' so the build moves to a dedicated public site unchanged — for the
// repo-subpath URL above, set `base: '/corex/docs-app/dist'` before building.
//
// Search is Pagefind (bundled with Starlight): instant, fuzzy, keyboard-driven, and
// fully client-side — it indexes every page + heading at build time, no server needed.
export default defineConfig({
	integrations: [
		starlight({
			title: 'Corex',
			description:
				'A professional, Laravel-inspired WordPress framework — blocks, forms, queries, CLI, and add-ons.',
			tagline: 'A Laravel-inspired WordPress framework',
			social: [
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/MustafaShaaban/corex' },
			],
			// RTL is first-class: add an Arabic locale and Starlight renders it
			// right-to-left automatically. English ships now; AR content is additive.
			defaultLocale: 'en',
			sidebar: [
				{ label: 'Introduction', slug: 'index' },
				{
					label: 'Getting Started',
					items: [
						{ label: 'Overview', slug: 'getting-started/overview' },
						{ label: 'Start your first company site', slug: 'getting-started/company-site' },
						{ label: 'WAMP / Apache + WP-CLI', slug: 'getting-started/wamp-apache' },
						{ label: 'wp-env / Docker', slug: 'getting-started/wp-env-docker' },
						{ label: 'Wiring the monorepo', slug: 'getting-started/monorepo-wiring' },
						{ label: 'First run & brand', slug: 'getting-started/first-run' },
					],
				},
				{
					label: 'Guides',
					items: [
						{ label: 'Forms & Flows', slug: 'guides/forms-flows' },
						{ label: 'Submissions Inbox', slug: 'guides/submissions' },
						{ label: 'Code-defined forms', slug: 'guides/forms' },
						{ label: 'Response contract & runtime', slug: 'guides/frontend-runtime' },
						{ label: 'Manage submission data', slug: 'guides/data' },
						{ label: 'Security Center', slug: 'guides/security' },
						{ label: 'REST resources', slug: 'guides/rest' },
						{ label: 'Headless WordPress', slug: 'guides/headless' },
						{ label: 'Assets & cache-busting', slug: 'guides/assets' },
						{ label: 'Image optimization', slug: 'guides/media' },
						{ label: 'Build a client site', slug: 'guides/client-site' },
						{ label: 'Company Site Kit v1', slug: 'guides/company-kit' },
						{ label: 'Using AI agents safely', slug: 'guides/ai-agents' },
						{ label: 'Team roles & agent modes', slug: 'guides/team-roles' },
						{ label: 'Deploy & distribute', slug: 'guides/deployment' },
						{ label: 'Free/Core vs Pro', slug: 'guides/free-core-vs-pro' },
						{ label: 'Design Language System', slug: 'guides/design-system' },
						{ label: 'Create a block (CLI)', slug: 'guides/blocks' },
						{ label: 'Write queries', slug: 'guides/queries' },
						{ label: 'Apply a brand', slug: 'guides/branding' },
						{ label: 'The CLI', slug: 'guides/cli' },
						{ label: 'Settings & feature flags', slug: 'guides/configuration' },
						{ label: 'Custom option pages', slug: 'guides/option-pages' },
						{ label: 'Send email (Corex Mail)', slug: 'guides/mail' },
						{ label: 'Email Studio', slug: 'guides/email-studio' },
						{ label: 'Blog Pro', slug: 'guides/blog-pro' },
						{ label: 'Model · Controller · Service', slug: 'guides/mvc' },
						{ label: 'Updates & distribution', slug: 'guides/updates' },
						{ label: 'Insights (performance & readiness)', slug: 'guides/insights' },
					],
				},
				{
					label: 'Design System',
					items: [
						{ label: 'Overview', slug: 'design-system' },
						{ label: 'Foundations', slug: 'design-system/foundations' },
						{ label: 'Components', slug: 'design-system/components' },
						{ label: 'Patterns', slug: 'design-system/patterns' },
						{ label: 'Navigation & footer', slug: 'design-system/navigation' },
						{ label: 'Admin experience', slug: 'design-system/admin-experience' },
						{ label: 'Design gap surfaces (Spec 063)', slug: 'design-system/design-gap-surfaces' },
						{ label: 'Templates', slug: 'design-system/templates' },
						{ label: 'Client readiness', slug: 'design-system/client-readiness' },
						{ label: 'Gap analysis', slug: 'design-system/gap-analysis' },
					],
				},
				{
					label: 'Architecture',
					items: [{ label: 'Overview', slug: 'architecture/overview' }],
				},
				{
					label: 'Internals Reference',
					items: [{ autogenerate: { directory: 'reference' } }],
				},
				{ label: 'FAQ', slug: 'faq' },
				{ label: 'Troubleshooting', slug: 'troubleshooting' },
			],
		}),
	],
});
