'use strict';

function NewEmailAddressDialog( config ) {
	NewEmailAddressDialog.super.call( this, config );

	this.emailAuthenticationEnabled = config.emailAuthenticationEnabled;
}
OO.inheritClass( NewEmailAddressDialog, OO.ui.ProcessDialog );

NewEmailAddressDialog.static.name = 'newEmailAddressDialog';
NewEmailAddressDialog.static.actions = [
	{
		flags: [ 'primary', 'progressive' ],
		label: mw.msg( 'multimail-special-add-email-view-submit-button-label-message' ),
		action: 'add',
		icon: 'add'
	},
	{
		flags: 'safe',
		label: mw.msg( 'multimail-js-dialog-cancel' )
	}
];

NewEmailAddressDialog.prototype.initialize = function () {
	NewEmailAddressDialog.super.prototype.initialize.call( this );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.content = new OO.ui.FieldsetLayout();

	this.emailInput = new OO.ui.TextInputWidget( {
		type: 'email',
		// This is in UTF-16 code points, and will allow more than 255 bytes.
		// Provide it as a courtesy nonetheless.
		maxLength: 255,
		// Don't use regular autocomplete, hint to browsers that this is for emails.
		autocomplete: 'email'
	} );

	this.field = new OO.ui.FieldLayout( this.emailInput, {
		label: mw.msg(
			this.emailAuthenticationEnabled ?
				'multimail-special-add-email-view-with-confirmation-summary' :
				'multimail-special-add-email-view-summary'
		),
		align: 'top'
	} );

	this.content.addItems( [ this.field ] );
	this.panel.$element.append( this.content.$element );
	this.$body.append( this.panel.$element );

	this.emailInput.connect( this, { change: 'onEmailInputChange' } );
};

NewEmailAddressDialog.prototype.getBodyHeight = function () {
	return 250;
};

// Don't allow submitting when not a valid email address.
NewEmailAddressDialog.prototype.onEmailInputChange = function ( value ) {
	this.actions.setAbilities( {
		add: value.indexOf( '@' ) !== -1
	} );
};

NewEmailAddressDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this,
		api = new mw.Api(),
		actionWidget = dialog.getActions().get( { actions: 'add' } )[ 0 ],
		dfd = new $.Deferred();

	if ( action !== 'add' ) {
		return NewEmailAddressDialog.super.prototype.getActionProcess.call( this, action );
	}

	actionWidget.pushPending();
	dialog.pushPending();

	api.postWithToken( 'multimail', {
		action: 'multimail',
		email: dialog.emailInput.getValue(),
		errorformat: 'html',
		errorlang: mw.config.get( 'wgUserLanguage' ),
		errorsuselocal: true,
		formatversion: 2
	} ).done( function () {
		actionWidget.popPending();
		dialog.popPending();
		dialog.close();

		dfd.resolve.apply( dialog );

		if ( dialog.emailAuthenticationEnabled ) {
			return OO.ui.alert( mw.msg( 'multimail-special-add-email-view-confirmation-sent' ) ).done( function () {
				window.location.href = mw.util.getUrl( 'Special:EmailAddresses' );
			} );
		} else {
			window.location.href = mw.util.getUrl( 'Special:EmailAddresses' );
		}
	} ).fail( function ( code, result ) {
		actionWidget.popPending();
		dialog.popPending();

		dfd.reject.apply( dialog, [ new OO.ui.Error( api.getErrorMessage( result ) ) ] );
	} );

	return new OO.ui.Process( dfd.promise(), this );
};

module.exports = NewEmailAddressDialog;
