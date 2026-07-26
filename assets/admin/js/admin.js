( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var copyButton = document.getElementById( 'vacimg-copy-debug' );
		var debugInfo = document.querySelector( '.vacimg-debug-info' );
		var qualityInput = document.querySelector( '[data-vacimg-quality-input]' );
		var qualityValue = document.querySelector( '[data-vacimg-quality-value]' );
		var profileCards = document.querySelectorAll( '[data-vacimg-profile-cards] .vacimg-profile-card' );
		var queueRoot = document.querySelector( '[data-vacimg-queue]' );
		var migrationRoot = document.querySelector( '[data-vacimg-migration]' );
		var diagnosticsRoot = document.querySelector( '[data-vacimg-diagnostics]' );

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

		if ( queueRoot && window.vacimgQueue ) {
			initQueue( queueRoot );
		}

		if ( migrationRoot && window.vacimgQueue ) {
			initMigration( migrationRoot );
		}

		if ( diagnosticsRoot && window.vacimgQueue ) {
			initDiagnostics( diagnosticsRoot );
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
			scan: 'vacimg_scan_library',
			'scan-all': 'vacimg_scan_library',
			start: 'vacimg_start_queue',
			pause: 'vacimg_pause_queue',
			resume: 'vacimg_resume_queue',
			cancel: 'vacimg_cancel_queue',
			'retry-failed': 'vacimg_retry_failed_queue_jobs',
			'clear-failed': 'vacimg_clear_failed_queue_jobs'
		};

		root.addEventListener( 'click', function ( event ) {
			var actionButton = event.target.closest( '[data-vacimg-queue-action]' );
			var retryButton = event.target.closest( '[data-vacimg-retry-job]' );

			if ( actionButton ) {
				handleQueueAction( actionButton.getAttribute( 'data-vacimg-queue-action' ) );
			}

			if ( retryButton ) {
				retryJob( retryButton.getAttribute( 'data-vacimg-retry-job' ) );
			}
		} );

		request( 'vacimg_queue_status' ).then( updateUi ).catch( showError );

		function handleQueueAction( action ) {
			if ( ! actions[ action ] ) {
				return;
			}

			setBusy( true );
			request( actions[ action ], 'scan-all' === action ? { scan_all: '1' } : {} )
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
			request( 'vacimg_process_batch' )
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
			request( 'vacimg_retry_queue_job', { queue_id: queueId } )
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
			formData.append( 'nonce', window.vacimgQueue.nonce );

			Object.keys( data || {} ).forEach( function ( key ) {
				formData.append( key, data[ key ] );
			} );

			return window.fetch( window.vacimgQueue.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					throw new Error( response && response.data && response.data.message ? response.data.message : window.vacimgQueue.i18n.error );
				}

				return response.data || {};
			} );
		}

		function updateUi( payload ) {
			var stats = payload.stats || {};
			var total = parseInt( stats.total || 0, 10 );
			var completed = parseInt( stats.completed || 0, 10 );
			var percent = total > 0 ? Math.round( ( completed / total ) * 100 ) : 0;

			[ 'total', 'pending', 'processing', 'completed', 'failed', 'remaining', 'scanned' ].forEach( function ( key ) {
				var node = root.querySelector( '[data-vacimg-stat="' + key + '"]' );
				if ( node ) {
					node.textContent = formatNumber( stats[ key ] || 0 );
				}
			} );

			var state = root.querySelector( '[data-vacimg-queue-state]' );
			if ( state ) {
				state.textContent = capitalize( stats.state || 'idle' );
			}

			var progress = root.querySelector( '[data-vacimg-progress-bar]' );
			if ( progress ) {
				progress.style.width = percent + '%';
				if ( progress.parentNode ) {
					progress.parentNode.setAttribute( 'aria-valuenow', String( percent ) );
				}
			}

			var progressLabel = root.querySelector( '[data-vacimg-progress-percent]' );
			if ( progressLabel ) {
				progressLabel.textContent = percent + '%';
			}

			renderFailedJobs( payload.failed || [] );
		}

		function renderFailedJobs( jobs ) {
			var tbody = root.querySelector( '[data-vacimg-failed-jobs]' );
			if ( ! tbody ) {
				return;
			}

			if ( ! jobs.length ) {
				tbody.innerHTML = '<tr class="vacimg-empty-row"><td colspan="4">No failed jobs.</td></tr>';
				return;
			}

			tbody.innerHTML = jobs.map( function ( job ) {
				return '<tr>' +
					'<td>' + escapeHtml( job.attachment || '' ) + '</td>' +
					'<td>' + escapeHtml( job.error || '' ) + '</td>' +
					'<td>' + formatNumber( job.attempts || 0 ) + '</td>' +
					'<td><button type="button" class="vacimg-button vacimg-button--secondary vacimg-button--small" data-vacimg-retry-job="' + parseInt( job.id || 0, 10 ) + '">Retry</button></td>' +
				'</tr>';
			} ).join( '' );
		}

		function setBusy( busy ) {
			root.querySelectorAll( '[data-vacimg-queue-action], [data-vacimg-retry-job]' ).forEach( function ( button ) {
				button.disabled = !! busy;
			} );
		}

		function showNotice( message, type ) {
			var notice = root.querySelector( '[data-vacimg-queue-notice]' );
			if ( ! notice ) {
				return;
			}

			notice.hidden = false;
			notice.className = 'vacimg-notice vacimg-notice--' + ( type || 'info' );
			notice.textContent = message;
		}

		function showError( error ) {
			showNotice( error && error.message ? error.message : window.vacimgQueue.i18n.error, 'error' );
		}
	}

	function initMigration( root ) {
		var actions = { scan: 'vacimg_migration_scan', start: 'vacimg_migration_start', pause: 'vacimg_migration_pause', resume: 'vacimg_migration_resume', cancel: 'vacimg_migration_cancel', retry: 'vacimg_migration_retry' };
		root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-vacimg-migration-action]' );
			if ( ! button || ! actions[ button.getAttribute( 'data-vacimg-migration-action' ) ] ) { return; }
			var action = button.getAttribute( 'data-vacimg-migration-action' );
			requestAction( actions[ action ] ).then( updateMigration ).then( function () { if ( 'start' === action || 'resume' === action || 'retry' === action ) { processMigration(); } } ).catch( function ( error ) { showMigrationNotice( error.message, 'error' ); } );
		} );
		requestAction( 'vacimg_migration_status' ).then( updateMigration ).catch( function () {} );
		function processMigration() {
			requestAction( 'vacimg_migration_process' ).then( function ( payload ) { updateMigration( payload ); if ( payload.state === 'running' ) { window.setTimeout( processMigration, 350 ); } } ).catch( function ( error ) { showMigrationNotice( error.message, 'error' ); } );
		}
		function requestAction( action ) { return migrationRequest( action ); }
		function updateMigration( payload ) {
			var summary = payload.summary || {};
			[ 'scanned', 'ready', 'partial', 'missing', 'stale', 'estimated_operations' ].forEach( function ( key ) { var node = root.querySelector( '[data-vacimg-migration-stat="' + key + '"]' ); if ( node ) { node.textContent = formatNumber( summary[ key ] || 0 ); } } );
			if ( payload.state ) { showMigrationNotice( capitalize( payload.state ), 'info' ); }
			return payload;
		}
		function showMigrationNotice( message, type ) { var node = root.querySelector( '[data-vacimg-migration-notice]' ); if ( node ) { node.hidden = false; node.className = 'vacimg-notice vacimg-notice--' + ( type || 'info' ); node.textContent = message || ''; } }
	}

	function initDiagnostics( root ) {
		root.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest( '[data-vacimg-diagnostics-action]' ) ) { return; }
			var input = root.querySelector( '[data-vacimg-diagnostics-id]' );
			migrationRequest( 'vacimg_migration_diagnostics', { attachment_id: input ? input.value : '' } ).then( function ( payload ) { var output = root.querySelector( '[data-vacimg-diagnostics-output]' ); if ( output ) { output.hidden = false; output.textContent = JSON.stringify( payload, null, 2 ); } } ).catch( function ( error ) { var output = root.querySelector( '[data-vacimg-diagnostics-output]' ); if ( output ) { output.hidden = false; output.textContent = error.message; } } );
		} );
	}

	function migrationRequest( action, data ) {
		var formData = new window.FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', window.vacimgQueue.nonce );
		Object.keys( data || {} ).forEach( function ( key ) { formData.append( key, data[ key ] ); } );
		return window.fetch( window.vacimgQueue.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } ).then( function ( response ) { return response.json(); } ).then( function ( response ) { if ( ! response || ! response.success ) { throw new Error( response && response.data && response.data.message ? response.data.message : window.vacimgQueue.i18n.migrationError ); } return response.data || {}; } );
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
