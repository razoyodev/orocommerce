define([
    'oroui/js/app/controllers/base/controller'
], function(BaseController) {
    'use strict';

    /**
     * Init ContentManager's handlers
     */
    BaseController.loadBeforeAction([
        'jquery', 'jquery.validate'
    ], function($) {
        var constraints = [
            'orosale/js/validator/quote-product-offer-quantity'
        ];

        $.validator.loadMethod(constraints);
    });
});
