import { decodeEntities } from '@wordpress/html-entities';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('axytoswc_data', {});

const label = decodeEntities(settings.title || 'Axytos Payment');
const description = settings.description || '';

const Content = () => (
    <p dangerouslySetInnerHTML={{ __html: description }} />
);

const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={label} />;
};

registerPaymentMethod({
    name: 'axytoswc',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || [],
    },
});


(function () {
    const { __experimentalRegisterCheckoutFilters } = wp.data;

    wp.domReady(() => {
        const checkoutElement = document.querySelector('.wp-block-woocommerce-checkout');

        const observer = new MutationObserver(() => {
            const errorMessage = checkoutElement.querySelector('.is-error .wc-block-components-notice-banner__content div');

            if (errorMessage && errorMessage.textContent.trim() === 'This Payment Method is not allowed for this order. Please try a different payment method.') {
                const checkoutContainer = document.querySelector('.wc-block-checkout');
                if (checkoutContainer) {
                    // document.location.reload();
                    wp.data.dispatch('wc/store').cartDataUpdated({ type: 'extensionCartUpdate' });

                }
            }
        });

        observer.observe(checkoutElement, {
            childList: true,
            subtree: true,
        });
    });
})();

