define([
    'Magento_Ui/js/form/components/insert-form'
], function (InsertForm) {
    'use strict';

    return InsertForm.extend({
        defaults: {
            listens: {
                responseData: 'onLoad'
            },
            modules: {
                listing: '${ $.listingProvider }',
                modal: '${ $.modalProvider }'
            }
        },

        /**
         * Close modal, reload feature listing
         */
        onLoad: function (responseData) {
            if (!responseData.error) {
                this.listing().reload({
                    refresh: true
                });
                this.modal().closeModal();
            }
        },

        /**
         * Event method that closes "Edit Feature" modal and refreshes the feature listing
         */
        deleteFeatureAction: function () {
            this.listing().reload({
                refresh: true
            });
            this.modal().closeModal();
        }
    });
});