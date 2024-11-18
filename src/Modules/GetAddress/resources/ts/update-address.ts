import { LegacyAddressUpdater } from './update-address/legacy';
import { BlocksAddressUpdater } from './update-address/blocks';

// Ignore missing Resursbank_GetAddress renders through Ecom Widget.
declare const Resursbank_GetAddress: any;

/**
 * We use different JS code to update the address fields depending on if the
 * theme uses blocks or not. This script will initialize the correct script
 * depending on whether blocks or legacy is used.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Confirm we are loaded on the checkout page.
    if (!document.querySelector('.woocommerce-checkout')) {
        return;
    }

    // Confirm that the Resursbank_GetAddress function is available.
    if (typeof Resursbank_GetAddress !== 'function') {
        return;
    }

    // Initialize blocks.
    if (document.querySelector('.wc-block-components-form')) {
        (new BlocksAddressUpdater()).initialize();
        return;
    }

    // Initialize legacy.
    (new LegacyAddressUpdater).initialize();
});
