import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import React, { useEffect, useState } from 'react';
import { dispatchRbPaymentMethodAdded } from './checkout';

const settings = getSetting( 'resursbank2_data', {} );

settings.forEach( ( method ) => {
    const defaultLabel = __(
        'Resursbank',
        'woo-gutenberg-products-block'
    );

    const label = decodeEntities(method.title) || defaultLabel;
    /**
     * Content component
     */
    const Content = () => {
        const div = document.createElement('div');
        const style = document.createElement('style');
        style.textContent = method.read_more_css;
        div.innerHTML =  method.description;
        div.appendChild(style);
        return <div dangerouslySetInnerHTML={{ __html: div.innerHTML }} />;
    };

    /**
     * Label component
     *
     * @param {*} props Props from payment API.
     */
    const Label = (props) => {
        const { PaymentMethodLabel } = props.components;
        const labelWithLogo = (
            <div className="rb-payment-method-title">
                <PaymentMethodLabel text={label} />
                <div className={`rb-payment-method-logo rb-logo-type-${method.logo_type}`} dangerouslySetInnerHTML={{__html: method.logo}}/>
            </div>
        );
        return labelWithLogo;
    };

    /**
     * Dummy payment method config object.
     */
    const Dummy = {
        name: method.name,
        label: <Label/>,
        content: <Content/>,
        edit: <Content/>,
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports,
        },
    };

    registerPaymentMethod(Dummy);

    dispatchRbPaymentMethodAdded(Dummy);
});
