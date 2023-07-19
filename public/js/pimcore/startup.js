pimcore.registerNS("pimcore.plugin.ecGinkoiaBundle");

pimcore.plugin.ecGinkoiaBundle = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));
    },

    pimcoreReady: function (e) {
        // alert("ecGinkoiaBundle ready!");
    }
});

var ecGinkoiaBundlePlugin = new pimcore.plugin.ecGinkoiaBundle();
