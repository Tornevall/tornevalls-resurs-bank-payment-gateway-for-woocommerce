import {LegacyAddressUpdater} from './update-address/legacy';
import {BlocksAddressUpdater} from './update-address/blocks';

// Ignore missing Resursbank_GetAddress renders through Ecom Widget.
declare const Resursbank_GetAddress: any;

/**
 * We use different JS code to update the address fields depending on if the
 * theme uses blocks or not. This script will initialize the correct script
 * depending on whether blocks or legacy is used.
 */
document.addEventListener('DOMContentLoaded', () => {
    const getAddressEnabled = ( // @ts-ignore
        rbFrontendData?.getAddressEnabled === '1' || // @ts-ignore
        rbFrontendData?.getAddressEnabled === true
    );

    // Confirm we are loaded on the checkout page.
    if (!document.querySelector('.woocommerce-checkout')) {
        return;
    }

    // Confirm that the Resursbank_GetAddress function is available.
    if (typeof Resursbank_GetAddress !== 'function') {
        return;
    }

    // Check if blocks exist and initialize, otherwise observe until it appears.
    // Limited to the checkout section. Occurs randomly depending on load speed.
    if (document.querySelector('.wc-block-components-form')) {
        // @ts-ignore
        resursConsoleLog(
            'Address Fetcher found by element (Enabled: ' + getAddressEnabled + ').'
        );
        new BlocksAddressUpdater(getAddressEnabled).initialize(getAddressEnabled);
    } else {
        // When blocks are not present, we need to observe the DOM for changes.
        const observer = new MutationObserver((mutations, obs) => {
            // Check if the required element has been added to the DOM.
            if (document.querySelector('.wc-block-components-form')) {
                new BlocksAddressUpdater(getAddressEnabled).initialize(getAddressEnabled);
                // @ts-ignore
                resursConsoleLog('Address Fetcher found by observer (Enabled: ' + getAddressEnabled + ').');
                obs.disconnect();
            }
        });

        // Observe changes in the checkout page for dynamically added elements.
        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    // Initialize legacy.
    new LegacyAddressUpdater().initialize();
});
