import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class AddToCartPlugin extends Plugin {
    init() {
        this.initializeAddToCartLogic();
    }

    initializeAddToCartLogic() {
        const addToCartButton = this.el.querySelector('#totalPriceButton');

        if (!addToCartButton) {
            console.error('[AddToCartPlugin] Add to cart button not found.');
            return;
        }

        addToCartButton.addEventListener('click', () => {
            const sizeId = this.getSelectedSizeId();
            const selectedToppings = this.getSelectedToppings();
            const selectedDrinks = this.getSelectedDrinks();

            if (!sizeId) {
                console.error('[AddToCartPlugin] No size selected!');
                return;
            }

            const lineItems = [
                {
                    type: 'product',
                    id: sizeId,
                    quantity: 1,
                    children: selectedToppings, // Füge Toppings hinzu
                },
                ...selectedDrinks, // Füge Getränke hinzu
            ];

            console.debug('[AddToCartPlugin] Line items to send:', lineItems);

            if (lineItems.length === 0) {
                console.warn('[AddToCartPlugin] No items to add to cart.');
                return;
            }

            this.addToCart(lineItems);
        });
    }

    getSelectedToppings() {
        const toppingInputs = this.el.querySelectorAll('input[name="toppings[]"]');
        const selectedToppings = Array.from(toppingInputs)
            .filter(input => input.checked)
            .map(input => ({
                id: input.dataset.id,
                quantity: 1,
            }));

        console.debug('[AddToCartPlugin] Selected Toppings:', selectedToppings);
        return selectedToppings;
    }

    getSelectedSizeId() {
        const sizeInput = this.el.querySelector('input[name="pizzaSize"]:checked');
        if (!sizeInput) {
            console.error('[AddToCartPlugin] No size selected!');
            return null;
        }
        console.debug('[AddToCartPlugin] Selected Size ID:', sizeInput.dataset.id);
        return sizeInput.dataset.id;
    }

    getSelectedDrinks() {
        const drinkInputs = this.el.querySelectorAll('input[name="drink"]');
        const selectedDrinks = Array.from(drinkInputs)
            .filter(input => input.checked)
            .map(input => ({
                type: 'product',
                id: input.dataset.id,
                quantity: 1,
            }));

        console.debug('[AddToCartPlugin] Selected Drinks:', selectedDrinks);
        return selectedDrinks;
    }

    addToCart(lineItems) {
        const httpClient = new HttpClient();

        httpClient.post(
            '/widgets/order-customization/add-to-cart',
            JSON.stringify({ items: lineItems }),
            response => {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        console.info('[AddToCartPlugin] Items successfully added to the cart.');
                        window.location.reload();
                    } else {
                        console.error('[AddToCartPlugin] Failed to add items to the cart:', data.message);
                    }
                } catch (error) {
                    console.error('[AddToCartPlugin] Error parsing response:', error);
                }
            }
        );
    }
}
