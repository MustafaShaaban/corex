import {
	HealthPanel,
	LayoutPanel,
	LogsPanel,
	OverviewPanel,
	PartialPanel,
	PlainTextPanel,
	PreviewPanel,
	RoutingPanel,
	TemplatePanel,
	TestSendPanel,
	VariablesPanel,
} from './components/index.js';

function templates( studio, busy ) {
	return (
		<TemplatePanel
			templates={ studio.state.data.templates }
			layouts={ studio.state.data.layouts }
			detail={ studio.selection.detail }
			draft={ studio.selection.draft }
			errors={ studio.selection.errors }
			busy={ busy }
			onSelect={ studio.selection.selectTemplate }
			onCreate={ studio.commands.createTemplate }
			onDraftChange={ studio.onDraftChange }
			onSaveDraft={ studio.commands.saveDraft }
			onActivate={ studio.commands.activate }
		/>
	);
}

function layouts( studio, busy ) {
	return (
		<LayoutPanel
			layouts={ studio.state.data.layouts }
			busy={ busy }
			onSave={ studio.assets.saveLayout }
		/>
	);
}

function partials( studio, busy ) {
	return (
		<PartialPanel
			partials={ studio.state.data.partials }
			busy={ busy }
			onSave={ studio.assets.savePartial }
			onInsert={ studio.insertions.insertPartial }
		/>
	);
}

function variables( studio ) {
	return (
		<VariablesPanel
			catalog={ studio.state.data.variables }
			onInsert={ studio.insertions.insertVariable }
		/>
	);
}

function routing( studio, busy ) {
	return (
		<RoutingPanel
			routes={ studio.state.data.routes }
			templates={ studio.state.data.templates }
			busy={ busy }
			onSave={ studio.assets.saveRoute }
		/>
	);
}

function preview( studio ) {
	return (
		<PreviewPanel
			document={ studio.preview.document }
			direction={ studio.preview.direction }
			device={ studio.preview.device }
			onDirection={ studio.preview.setDirection }
			onDevice={ studio.preview.setDevice }
		/>
	);
}

function plain( studio ) {
	return (
		<PlainTextPanel
			value={ studio.preview.plain }
			mode={ studio.selection.draft.plain_text_mode }
		/>
	);
}

function test( studio, busy ) {
	return (
		<TestSendPanel
			delivery={ studio.state.data.delivery }
			draft={ studio.selection.draft }
			busy={ busy }
			lastResult={ studio.delivery.lastResult }
			onSend={ studio.delivery.testSend }
		/>
	);
}

function logs( studio, busy ) {
	return (
		<LogsPanel
			attempts={ studio.state.data.attempts }
			captures={ studio.state.data.captures }
			busy={ busy }
			onResend={ studio.delivery.resend }
		/>
	);
}

function health( studio, busy ) {
	return (
		<HealthPanel
			detail={ studio.selection.detail }
			health={ studio.health.health }
			busy={ busy }
			onRun={ studio.health.runHealth }
		/>
	);
}

function overview( studio, busy, config ) {
	return (
		<OverviewPanel
			data={ studio.state.data }
			settingsUrl={ config.settingsUrl }
		/>
	);
}

const PANEL_RENDERERS = {
	templates,
	layouts,
	partials,
	variables,
	routing,
	preview,
	plain,
	test,
	logs,
	health,
	overview,
};

export function StudioPanel( { tab, studio, config } ) {
	const busy = studio.state.mutating || studio.state.status === 'loading';
	const renderPanel = PANEL_RENDERERS[ tab ] ?? PANEL_RENDERERS.overview;
	return renderPanel( studio, busy, config );
}
