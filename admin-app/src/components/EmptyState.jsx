export default function EmptyState( { className, eyebrow, title, desc, ctaLabel, onCta } ) {
	return (
		<div className={ className }>
			<p>{ eyebrow }</p>
			<h2>{ title }</h2>
			<p>{ desc }</p>
			{ ctaLabel && (
				<button type="button" className="pp-btn-solid" onClick={ onCta }>
					{ ctaLabel }
				</button>
			) }
		</div>
	);
}
