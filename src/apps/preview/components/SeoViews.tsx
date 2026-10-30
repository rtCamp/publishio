import type { SearchDetails, SocialDetails } from '../types';

export function SearchResult( { search }: { search: SearchDetails } ) {
	if ( search.hidden ) {
		return <p className="serp-hidden">Hidden from search engines</p>;
	}

	return (
		<div className="serp">
			<span className="u">{ search.address }</span>
			<span className="t">{ search.title }</span>
			{ search.description ? (
				<span className="d">{ search.description }</span>
			) : (
				<span className="d none">
					No description. Search engines will pick text from the page.
				</span>
			) }
		</div>
	);
}

export function SocialCard( { social }: { social: SocialDetails } ) {
	return (
		<div className="share">
			{ social.image && (
				<img className="img" src={ social.image } alt="" />
			) }
			<div className="txt">
				<small>{ social.site }</small>
				<b>{ social.title }</b>
				{ social.description && <span>{ social.description }</span> }
			</div>
		</div>
	);
}
