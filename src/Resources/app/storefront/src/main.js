import OrderCustomizationPlugin from "./order-customization/order-customization.plugin";
import AddToCartPlugin from "./add-to-cart/add-to-cart.plugin";

const PluginManager = window.PluginManager;

PluginManager.register('OrderCustomization', OrderCustomizationPlugin, '[data-order-customization]');
PluginManager.register('AddToCartPlugin', AddToCartPlugin, '[data-add-to-cart]');

