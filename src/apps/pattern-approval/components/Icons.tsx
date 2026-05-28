export const Spark = ( { size = 14 }: { size?: number } ) => (
	<svg viewBox="0 0 16 16" width={ size } height={ size } aria-hidden="true">
		<path
			d="M8 1.5l1.6 4.4 4.4 1.6-4.4 1.6L8 13.5 6.4 9.1 2 7.5l4.4-1.6L8 1.5z"
			fill="currentColor"
		/>
	</svg>
);

export const Check = ( { size = 13 }: { size?: number } ) => (
	<svg viewBox="0 0 16 16" width={ size } height={ size } aria-hidden="true">
		<path
			d="M3.5 8.4l3 3 6-7"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.8"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

export const Swap = ( { size = 13 }: { size?: number } ) => (
	<svg viewBox="0 0 16 16" width={ size } height={ size } aria-hidden="true">
		<path
			d="M3 6h9l-2.2-2.2M13 10H4l2.2 2.2"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

export const ExternalLink = ( { size = 10 }: { size?: number } ) => (
	<svg
		width={ size }
		height={ size }
		viewBox="0 0 24 24"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<path
			d="M10 4H6C4.89543 4 4 4.89543 4 6V18C4 19.1046 4.89543 20 6 20H18C19.1046 20 20 19.1046 20 18V14M11 13L20 4M20 4V9M20 4H15"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);
