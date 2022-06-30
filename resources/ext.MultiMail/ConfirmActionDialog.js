'use strict';

function ConfirmActionDialog( config ) {
	ConfirmActionDialog.super.call( this, config );

	this.apiAction = config.apiAction;
}
OO.inheritClass( ConfirmActionDialog, OO.ui.ProcessDialog );

ConfirmActionDialog.static.name = 'confirmActionDialog';

ConfirmActionDialog.prototype.initialize = function () {
	ConfirmActionDialog.super.prototype.initialize.call( this );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.content = new OO.ui.FieldsetLayout();

	this.messageWidget = new OO.ui.MessageWidget( {
		label: 'This should get replaced by getSetupProcess.',
		type: 'warning'
	} );

	this.field = new OO.ui.FieldLayout( this.messageWidget, {
		align: 'top'
	} );

	this.content.addItems( [ this.field ] );
	this.panel.$element.append( this.content.$element );
	this.$body.append( this.panel.$element );
};

ConfirmActionDialog.prototype.getBodyHeight = function () {
	return 250;
};

ConfirmActionDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};

	return ConfirmActionDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.id = data.id;
			this.messageWidget.setLabel( data.message );
		}, this );
};

ConfirmActionDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this,
		api = new mw.Api(),
		dfd = new $.Deferred();

	if ( action !== 'confirm' ) {
		return ConfirmActionDialog.super.prototype.getActionProcess.call( this, action );
	}

	dialog.pushPending();

	api.postWithToken( 'multimail', {
		action: 'multimail',
		id: dialog.id,
		'mail-action': dialog.apiAction,
		errorformat: 'html',
		errorlang: mw.config.get( 'wgUserLanguage' ),
		errorsuselocal: true,
		formatversion: 2
	} ).done( function () {
		dialog.popPending();
		dialog.close();

		dfd.resolve.apply( dialog );

		window.location.href = mw.util.getUrl( 'Special:EmailAddresses' );
	} ).fail( function ( code, result ) {
		dialog.popPending();

		dfd.reject.apply( dialog, [ new OO.ui.Error( api.getErrorMessage( result ) ) ] );
	} );

	return new OO.ui.Process( dfd.promise(), this );
};

module.exports = ConfirmActionDialog;
