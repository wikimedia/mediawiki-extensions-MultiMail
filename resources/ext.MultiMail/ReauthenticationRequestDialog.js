'use strict';

function ReauthenticationRequestDialog( config ) {
	ReauthenticationRequestDialog.super.call( this, config );
}
OO.inheritClass( ReauthenticationRequestDialog, OO.ui.MessageDialog );

ReauthenticationRequestDialog.static.name = 'reauthenticationRequestDialog';
ReauthenticationRequestDialog.static.actions = [
	OO.ui.MessageDialog.static.actions[ 1 ]
];

ReauthenticationRequestDialog.prototype.initialize = function () {
	ReauthenticationRequestDialog.super.prototype.initialize.call( this );

	this.widget = new OO.ui.ProgressBarWidget();
	this.text.$element.append( this.widget.$element );
};

module.exports = ReauthenticationRequestDialog;
