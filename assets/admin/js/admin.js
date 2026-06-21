( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var copyButton = document.getElementById( 'vio-copy-debug' );
		var debugInfo = document.querySelector( '.vio-debug-info' );
		var qualityInput = document.querySelector( '[data-vio-quality-input]' );
		var qualityValue = document.querySelector( '[data-vio-quality-value]' );
		var profileCards = document.querySelectorAll( '[data-vio-profile-cards] .vio-profile-card' );
		var queueRoot = document.querySelector( '[data-vio-queue]' );

		if ( qualityInput && qualityValue ) {
			qualityInput.addEventListener( 'input', function () {
				qualityValue.textContent = qualityInput.value;
			} );
		}

		profileCards.forEach( function ( card ) {
			var input = card.querySelector( 'input[type="radio"][data-quality]' );

			if ( ! input ) {
				return;
			}

			input.addEventListener( 'change', function () {
				profileCards.forEach( function ( item ) {
					item.classList.remove( 'is-active' );
				} );

				card.classList.add( 'is-active' );

				if ( qualityInput && qualityValue ) {
					qualityInput.value = input.getAttribute( 'data-quality' ) || qualityInput.value;
					qualityValue.textContent = qualityInput.value;
				}
			} );
		} );

		if ( queueRoot && window.vioQueue ) {
			initQueue( queueRoot );
		}

		if ( ! copyButton || ! debugInfo || ! navigator.clipboard ) {
			return;
		}

		copyButton.addEventListener( 'click', function () {
			navigator.clipboard.writeText( debugInfo.value ).then( function () {
				var originalText = copyButton.textContent;
				copyButton.textContent = 'Copied';

				window.setTimeout( function () {
					copyButton.textContent = originalText;
				}, 1600 );
			} );
		} );
	} );

	function initQueue( root ) {
		var processing = false;
		var actions = {
			scan: 'vio_scan_library',
			start: 'vio_start_queue',
			pause: 'vio_pause_queue',
			resume: 'vio_resume_queue'
		};

		root.addEventListener( 'click', function ( event ) {
			var actionButton = event.target.closest( '[data-vio-queue-action]' );
			var retryButton = event.target.closest( '[data-vio-retry-job]' );

			if ( actionButton ) {
				handleQueueAction( actionButton.getAttribute( 'data-vio-queue-action' ) );
			}

			if ( retryButton ) {
				retryJob( retryButton.getAttribute( 'data-vio-retry-job' ) );
			}
		} );

		request( 'vio_queue_status' ).then( updateUi ).catch( showError );

		function handleQueueAction( action ) {
			if ( ! actions[ action ] ) {
				return;
			}

			setBusy( true );
			request( actions[ action ] )
				.then( function ( payload ) {
					updateUi( payload );
					if ( payload.message ) {
						showNotice( payload.message, 'success' );
					}

					if ( 'start' === action || 'resume' === action ) {
						processNextBatch();
					}
				} )
				.catch( showError )
				.finally( function () {
					setBusy( false );
				} );
		}

		function processNextBatch() {
			if ( processing ) {
				return;
			}

			processing = true;
			request( 'vio_process_batch' )
				.then( function ( payload ) {
					updateUi( payload );

					if ( payload.batch && 'running' === payload.batch.state && ! payload.batch.finished ) {
						window.setTimeout( function () {
							processing = false;
							processNextBatch();
						}, 500 );
						return;
					}

					processing = false;
				} )
				.catch( function ( error ) {
					processing = false;
					showError( error );
				} );
		}

		function retryJob( queueId ) {
			setBusy( true );
			request( 'vio_retry_queue_job', { queue_id: queueId } )
				.then( function ( payload ) {
					updateUi( payload );
					showNotice( 'Job requeued.', 'success' );
				} )
				.catch( showError )
				.finally( function () {
					setBusy( false );
				} );
		}

		function request( action, data ) {
			var formData = new window.FormData();
			formData.append( 'action', action );
			formData.append( 'nonce', window.vioQueue.nonce );

			Object.keys( data || {} ).forEach( function ( key ) {
				formData.append( key, data[ key ] );
			} );

			return window.fetch( window.vioQueue.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					throw new Error( response && response.data && response.data.message ? response.data.message : window.vioQueue.i18n.error );
				}

				return response.data || {};
			} );
		}

		function updateUi( payload ) {
			var stats = payload.stats || {};
			var total = parseInt( stats.total || 0, 10 );
			var completed = parseInt( stats.completed || 0, 10 );
			var percent = total > 0 ? Math.round( ( completed / total ) * 100 ) : 0;

			[ 'total', 'pending', 'processing', 'completed', 'failed' ].forEach( function ( key ) {
				var node = root.querySelector( '[data-vio-stat="' + key + '"]' );
				if ( node ) {
					node.textContent = formatNumber( stats[ key ] || 0 );
				}
			} );

			var state = root.querySelector( '[data-vio-queue-state]' );
			if ( state ) {
				state.textContent = capitalize( stats.state || 'idle' );
			}

			var progress = root.querySelector( '[data-vio-progress-bar]' );
			if ( progress ) {
				progress.style.width = percent + '%';
				if ( progress.parentNode ) {
					progress.parentNode.setAttribute( 'aria-valuenow', String( percent ) );
				}
			}

			var progressLabel = root.querySelector( '[data-vio-progress-percent]' );
			if ( progressLabel ) {
				progressLabel.textContent = percent + '%';
			}

			renderFailedJobs( payload.failed || [] );
		}

		function renderFailedJobs( jobs ) {
			var tbody = root.querySelector( '[data-vio-failed-jobs]' );
			if ( ! tbody ) {
				return;
			}

			if ( ! jobs.length ) {
				tbody.innerHTML = '<tr class="vio-empty-row"><td colspan="4">No failed jobs.</td></tr>';
				return;
			}

			tbody.innerHTML = jobs.map( function ( job ) {
				return '<tr>' +
					'<td>' + escapeHtml( job.attachment || '' ) + '</td>' +
					'<td>' + escapeHtml( job.error || '' ) + '</td>' +
					'<td>' + formatNumber( job.attempts || 0 ) + '</td>' +
					'<td><button type="button" class="vio-button vio-button--secondary vio-button--small" data-vio-retry-job="' + parseInt( job.id || 0, 10 ) + '">Retry</button></td>' +
				'</tr>';
			} ).join( '' );
		}

		function setBusy( busy ) {
			root.querySelectorAll( '[data-vio-queue-action], [data-vio-retry-job]' ).forEach( function ( button ) {
				button.disabled = !! busy;
			} );
		}

		function showNotice( message, type ) {
			var notice = root.querySelector( '[data-vio-queue-notice]' );
			if ( ! notice ) {
				return;
			}

			notice.hidden = false;
			notice.className = 'vio-notice vio-notice--' + ( type || 'info' );
			notice.textContent = message;
		}

		function showError( error ) {
			showNotice( error && error.message ? error.message : window.vioQueue.i18n.error, 'error' );
		}
	}

	function escapeHtml( value ) {
		return String( value ).replace( /[&<>"']/g, function ( character ) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[ character ];
		} );
	}

	function capitalize( value ) {
		value = String( value || '' );
		return value.charAt( 0 ).toUpperCase() + value.slice( 1 );
	}

	function formatNumber( value ) {
		return parseInt( value || 0, 10 ).toLocaleString();
	}
}() );