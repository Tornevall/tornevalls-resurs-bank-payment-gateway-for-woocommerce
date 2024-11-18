/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

jQuery( document ).ready( function () {
	jQuery( '.variations_form' ).each( function () {
		jQuery( this ).on( 'found_variation', function ( event, variation ) {
			// Make sure we only trigger this method if it exists.
			// By means, it usually exists when we have available part payment alternatives.
			if ( typeof getStartingAtCost === 'function' ) {
				let price = variation.display_price;
				getStartingAtCost( price );
			}
		} );
	} );
} );
