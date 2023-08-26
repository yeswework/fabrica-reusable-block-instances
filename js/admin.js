
document.addEventListener('DOMContentLoaded', () => {
	const $waitingCells = document.querySelectorAll(`.${app.ns}-instances--waiting`);
	if (!$waitingCells || !$waitingCells.length) { return; }

	for (const $cell of $waitingCells) {
		const blockId = $cell.dataset.blockId,
			postType = document.getElementById('block_post_type')?.value,
			body = new FormData();
		// ~~ #TODO: body.append('_wpnonce', document.getElementById('_wpnonce')?.value);
		body.append('block_post_type', postType == 'all' ? '' : postType);
		body.append('block_id', blockId);
		$cell.innerHTML = '<span class="spinner is-active" style="float:none;margin-top:0"></span>loading...';

		fetch(`${app.url.ajax}?action=${app.ns}_get_block_instances`, {method: 'POST', credentials: 'include', body})
		.then(response => response.json())
		.then(result => {
			console.log('~~>', {result}); // ~~
			if (!result.success) { // ~~ #TODO
				$cell.innerText = 'error';
				return;
			}
			$cell.innerText = '—';
			if (result.data.instances > 0) {
				$cell.innerHTML = `<a href="${app.url.edit}?post_type=wp_block&block_instances=${blockId}">${result.data.instances}</a>`;
			}
			// ~~ #TODO: finishFetch('Error getting export status. Please check if export file is available or try again. ' + (status?.message || ''));
		}).catch(reason => {
			console.log('~~> error:', {reason}); // ~~
			// ~~ #TODO: replace loading spinner and allow re-try: finishFetch('Error while exporting or getting export status.');
		});
	}
});