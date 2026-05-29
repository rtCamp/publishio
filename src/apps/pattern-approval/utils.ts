function ordinalSuffix( n: number ): string {
	switch ( n % 10 ) {
		case 1:
			return n % 100 === 11 ? 'th' : 'st';
		case 2:
			return n % 100 === 12 ? 'th' : 'nd';
		case 3:
			return n % 100 === 13 ? 'th' : 'rd';
		default:
			return 'th';
	}
}

export function formatPosition( position: number | undefined ): string {
	if ( position === undefined ) {
		return 'the selected location';
	}
	if ( position === -1 ) {
		return 'the end of the page';
	}
	if ( position === 0 ) {
		return 'the top of the page';
	}
	return `after the ${ position }${ ordinalSuffix( position ) } block`;
}
