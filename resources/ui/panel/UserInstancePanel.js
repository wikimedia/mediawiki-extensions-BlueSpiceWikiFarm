bs.util.registerNamespace( 'bs.bluespiceWikiFarm.ui' );

require( './InstancePanel.js' );

bs.bluespiceWikiFarm.ui.UserInstancePanel = function () {
	bs.bluespiceWikiFarm.ui.UserInstancePanel.parent.call( this, {
		expanded: false,
		framed: false
	} );

	this.makeTabs();
	if ( location.hash ) {
		const tab = location.hash.replace( '#', '' );
		this.setTabPanel( tab );
	}
};

OO.inheritClass( bs.bluespiceWikiFarm.ui.UserInstancePanel, OO.ui.IndexLayout );

bs.bluespiceWikiFarm.ui.UserInstancePanel.prototype.makeTabs = function () {
	this.favouriteContent = new OO.ui.TabPanelLayout( 'favourite', {
		label: mw.msg( 'wikifarm-instances-tab-favourite-label' ),
		expanded: false
	} );

	this.favouritePanel = new bs.bluespiceWikiFarm.ui.InstancePanel( {
		favourite: true
	} );
	this.favouriteContent.$element.append( this.favouritePanel.$element );

	this.allInstanceContent = new OO.ui.TabPanelLayout( 'all', {
		label: mw.msg( 'wikifarm-instances-tab-all-label' ),
		expanded: false
	} );
	this.allInstancePanel = new bs.bluespiceWikiFarm.ui.InstancePanel( {
		favourite: false
	} );
	this.allInstanceContent.$element.append( this.allInstancePanel.$element );
	this.addTabPanels( [ this.favouriteContent, this.allInstanceContent ] );

	this.connect( this, {
		set: ( page ) => {
			location.hash = page.getName();
			if ( page.getName() === 'all' ) {
				this.allInstancePanel.reload();
			} else {
				this.favouritePanel.reload();
			}
		}
	} );
};
