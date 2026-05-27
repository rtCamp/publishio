<?php
/**
 * Template for the Pattern Approval MCP App resource.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Resources\Pattern_Approval
 */

declare(strict_types = 1);

/** @var string $style */
/** @var string $script */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style><?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted internal file ?></style>
</head>
<body>
<div class="approval-ui">
	<div id="loading" class="state">Loading preview…</div>
	<div id="ready" class="state hidden">
	<iframe id="preview-frame" title="Pattern preview" style="width:100%;border:1px solid #ddd;border-radius:4px;flex:1;min-height:300px;display:block;"></iframe>
	<div class="actions">
		<button id="btn-insert" class="primary">Insert</button>
		<button id="btn-cancel" class="secondary">Cancel</button>
	</div>
	</div>
	<div id="status"></div>
</div>
<script><?php echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted internal file ?></script>
</body>
</html>
