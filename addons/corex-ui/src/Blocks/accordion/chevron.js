/**
 * The kit's chevron, as the accordion's items draw it in the editor (spec 136, FR-032): the same
 * icon `AccordionItemMarkup::CHEVRON` prints on the page, pointing down, turned by the sheet.
 */
export const chevron = (
	<svg
		className="corex-accordion__icon"
		viewBox="0 0 24 24"
		aria-hidden="true"
		focusable="false"
	>
		<path
			d="m6 9 6 6 6-6"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.75"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);
