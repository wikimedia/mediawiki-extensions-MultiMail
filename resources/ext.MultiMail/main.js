'use strict';

function main() {
	var NewEmailAddressDialog = require( './NewEmailAddressDialog.js' ),
		ConfirmActionDialog = require( './ConfirmActionDialog.js' ),
		ReauthenticationRequestDialog = require( './ReauthenticationRequestDialog.js' ),
		config = require( './config.json' ),
		windowManager = new OO.ui.WindowManager(),
		$addEmailButton = $( '#ext-multimail-new-email' ),
		api = new mw.Api(),
		addEmailButton,
		cancelButtonAction = {
			flags: 'safe',
			label: mw.msg( 'multimail-js-dialog-cancel' )
		},
		activeForm = Number( new mw.Uri( window.location.href ).query.activeform );

	if ( !$addEmailButton.length ) {
		return;
	}

	/**
	 * Verify authentication status before opening the given form.
	 *
	 * @private
	 *
	 * @param {number} buttonId
	 * @param {string} win
	 * @param {object} data
	 */
	function verifyAuthenticationStatus( buttonId, win, data ) {
		// This is equivalent to OO.ui.alert, but uses the current window manager.
		windowManager.openWindow( 'authenticationProgress', {
			message: mw.msg(
				'multimail-js-reauthentication-check'
			)
		} ).closed.then( function() {
			api.abort();
		} );

		api.postWithToken( 'multimail', {
			action: 'multimail',
			'check-authentication-status': true,
			errorformat: 'html',
			errorlang: mw.config.get( 'wgUserLanguage' ),
			errorsuselocal: true,
			formatversion: 2
		} ).done( function ( result ) {
			windowManager.closeWindow( 'authenticationProgress' );

			if ( result.multimail.status === 'ok' ) {
				windowManager.openWindow( win, data );

				return;
			}

			// This is equivalent to OO.ui.confirm, but uses the current window manager.
			windowManager.openWindow( 'confirm', {
				message: mw.msg(
					'multimail-js-reauthentication-required',
					mw.msg( 'ooui-dialog-message-accept' )
				)
			} ).closed.then( function ( data ) {
				if ( !data || data.action !== 'accept' ) {
					return;
				}

				window.location.href = mw.util.getUrl( 'Special:UserLogin', {
					returnto: mw.config.get( 'wgPageName' ),
					returntoquery: 'activeform=' + buttonId,
					force: 'ChangeEmail'
				} );
			} );
		} ).fail( function ( code, details ) {
			windowManager.closeWindow( 'authenticationProgress' );

			if ( code === 'http' && details.exception === 'abort' ) {
				return;
			}

			mw.log.error( code, details );
		} );
	}

	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( {
		confirm: new OO.ui.MessageDialog(),
		authenticationProgress: new ReauthenticationRequestDialog(),
		addEmail: new NewEmailAddressDialog( {
			emailAuthenticationEnabled: config.EmailAuthentication
		} ),
		confirmPrimary: new ConfirmActionDialog( {
			apiAction: 'primary'
		} ),
		confirmDelete: new ConfirmActionDialog( {
			apiAction: 'delete'
		} )
	} );

	addEmailButton = OO.ui.infuse( $addEmailButton ).on( 'click', function () {
		verifyAuthenticationStatus( 0, 'addEmail' );
	} );

	if ( activeForm === 0 ) {
		addEmailButton.emit( 'click' );
	}

	$( '.ext-multimail-primary' ).each( function () {
		var widget = OO.ui.infuse( $( this ) ),
			data = widget.getData();

		widget.on( 'click', function () {
			verifyAuthenticationStatus( data.buttonId, 'confirmPrimary', {
				id: data.id,
				message: mw.msg(
					'multimail-special-change-primary-view-confirmation',
					data.primary,
					data.address
				),
				actions: [
					cancelButtonAction,
					{
						flags: [ 'primary', 'progressive' ],
						label: mw.msg( 'multimail-emails-pager-make-primary-button-label' ),
						action: 'confirm'
					}
				]
			} );
		} );

		if ( data.buttonId === activeForm ) {
			widget.emit( 'click' );
		}
	} );

	$( '.ext-multimail-delete' ).each( function () {
		var widget = OO.ui.infuse( $( this ) ),
			data = widget.getData();

		widget.on( 'click', function () {
			verifyAuthenticationStatus( data.buttonId, 'confirmDelete', {
				id: data.id,
				message: mw.msg(
					'multimail-special-delete-view-confirmation',
					data.address
				),
				actions: [
					cancelButtonAction,
					{
						flags: [ 'primary', 'destructive' ],
						label: mw.msg( 'multimail-special-delete-view-confirmation-submit-label-message' ),
						action: 'confirm'
					}
				]
			} );
		} );

		if ( data.buttonId === activeForm ) {
			widget.emit( 'click' );
		}
	} );
}

main();
