import OrderCustomizationPlugin from "./order-customization/order-customization.plugin";

const PluginManager = window.PluginManager;

PluginManager.register('OrderCustomization', OrderCustomizationPlugin, '[data-order-customization]');
