/**
 * Every block-editor warning ("Block contains unexpected or invalid content", "This block has
 * encountered an error", unsupported block, …) renders inside this one wrapper. A CSS selector is
 * used on purpose: matching each message's text would miss the others.
 */
export const BLOCK_WARNING = '.block-editor-warning';
