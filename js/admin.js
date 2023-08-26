
document.addEventListener('DOMContentLoaded', () => {
	const $waitingCells = document.querySelectorAll(`.${app.ns}-instances--waiting`);
	if (!$waitingCells || !$waitingCells.length) { return; }


	const renderError = $cell => {
		const $errorWarning = document.createElement('strong'),
			$retryLink = document.createElement('a');

		$errorWarning.classList.add('dashicons-before', 'dashicons-warning');
		$errorWarning.innerText = ' error';
		$retryLink.innerText = 'retry';
		$retryLink.classList.add(`${app.ns}-instances__retry-link`)
		$retryLink.addEventListener('click', () => loadCell($cell));
		$cell.classList.add(`${app.ns}-instances--error`);
		$cell.replaceChildren($errorWarning, $retryLink);
	};

	const loadNextCell = () => {
		// load next 'waiting' cell
		const $waitingCell = document.querySelector(`.${app.ns}-instances--waiting`);
		if (!$waitingCell) { return; }
		loadCell($waitingCell);
	};

	const loadCell = $cell => {
		const blockId = $cell.dataset.blockId,
			postType = document.getElementById('block_post_type')?.value,
			body = new FormData();
		$cell.classList.remove(`${app.ns}-instances--waiting`);
		body.append('_wpnonce', app.nonce);
		body.append('block_post_type', postType == 'all' ? '' : postType);
		body.append('block_id', blockId);
		$cell.innerHTML = `<span class="${app.ns}-instances__spinner spinner is-active"></span>loading...`;

		fetch(`${app.url.ajax}?action=${app.ns}_get_block_instances`, {method: 'POST', credentials: 'include', body})
		.then(response => response.json())
		.then(result => {
			if (result.success && result.data.instances > 0) {
				$cell.innerHTML = `<a href="${app.url.edit}?post_type=wp_block&block_instances=${blockId}">${result.data.instances}</a>`;
			} else if (result.success) {
				$cell.innerText = '—';
			} else {
				renderError($cell);
			}

			loadNextCell();
		}).catch(reason => {
			console.log('~~> error:', {reason}); // ~~
			renderError($cell);
		});
	};

	let i = 0;
	for (const $cell of $waitingCells) {
		if (++i > 3) { return; } // load only 3 cells at a time
		loadCell($cell);
	}
});