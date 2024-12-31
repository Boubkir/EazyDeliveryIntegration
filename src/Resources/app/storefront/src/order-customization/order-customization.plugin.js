import Plugin from 'src/plugin-system/plugin.class';

export default class OrderCustomizationPlugin extends Plugin {
    init() {
        this.initializeCustomizationLogic();
    }

    initializeCustomizationLogic() {
        const totalPriceButton = this.el.querySelector('#totalPriceButton');
        const sizeInputs = this.el.querySelectorAll('input[name="pizzaSize"]');
        const toppingContainer = this.el.querySelector('.toppings-container');
        const toppingInputs = this.el.querySelectorAll('input[name="toppings[]"]');
        const drinkInputs = this.el.querySelectorAll('input[name="drink"]');

        // Funktion zur Aktualisierung der Toppings basierend auf der ausgewählten Option-ID
        const updateToppings = (selectedOptionId) => {
            fetch(`/widgets/order-customization/extras/${selectedOptionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.extras) {
                        // Entferne bestehende Toppings
                        toppingContainer.innerHTML = '';

                        // Füge neue Toppings hinzu
                        data.extras.forEach(topping => {
                            const toppingElement = document.createElement('li');
                            toppingElement.innerHTML = `
                                <input
                                    type="checkbox"
                                    id="topping-${topping.id}"
                                    name="toppings[]"
                                    data-id="${topping.id}"
                                    data-price="${topping.price}"
                                    value="${topping.id}">
                                <label for="topping-${topping.id}">
                                    ${topping.name} -
                                    <span class="topping-price">€${topping.price.toFixed(2)}</span>
                                </label>
                            `;
                            toppingContainer.appendChild(toppingElement);

                            // Event-Listener für neues Topping
                            toppingElement.querySelector('input').addEventListener('change', recalculatePrices);
                        });
                    }
                })
                .catch(error => console.error('Error fetching toppings:', error));
        };

        // Funktion zur Berechnung der Preise
        const recalculatePrices = () => {
            // Hole die Produkt-ID (data-id) der ausgewählten Pizza-Größe
            const selectedSizeInput = this.el.querySelector('input[name="pizzaSize"]:checked');
            const selectedProductId = selectedSizeInput?.dataset.id; // Produkt-ID
            const selectedToppings = Array.from(toppingContainer.querySelectorAll('input[name="toppings[]"]:checked'))
                .map(t => t.dataset.id); // Topping-IDs
            const selectedDrink = this.el.querySelector('input[name="drink"]:checked')?.value;

            if (!selectedProductId) {
                console.error('No size selected!');
                return;
            }

            // AJAX-Aufruf zur Berechnung der Preise
            fetch('/widgets/order-customization/calculate-price', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sizeId: selectedProductId, toppings: selectedToppings, drink: selectedDrink }),
            })
                .then(response => response.json())
                .then(data => {
                    // Aktualisiere den Gesamtpreis
                    if (data.totalPrice) {
                        totalPriceButton.textContent = `€${data.totalPrice.toFixed(2)}`;
                    }

                    // Aktualisiere die Preise der Toppings
                    toppingContainer.querySelectorAll('input[name="toppings[]"]').forEach(topping => {
                        const toppingId = topping.dataset.id;
                        const price = data.toppingPrices?.[toppingId] ?? parseFloat(topping.dataset.price);
                        const priceElement = topping.nextElementSibling.querySelector('.topping-price');
                        if (priceElement) {
                            priceElement.textContent = `€${price.toFixed(2)}`;
                        }
                    });
                })
                .catch(error => console.error('Error calculating price:', error));
        };

        // Event-Listener für Änderungen an Pizza-Größen
        sizeInputs.forEach(input => input.addEventListener('change', () => {
            const selectedSizeInput = this.el.querySelector('input[name="pizzaSize"]:checked');
            const selectedOptionId = selectedSizeInput?.dataset.optionId; // Option-ID
            if (selectedOptionId) {
                updateToppings(selectedOptionId); // Aktualisiere Toppings basierend auf der Option-ID
                recalculatePrices(); // Aktualisiere Preise basierend auf der Produkt-ID
            }
        }));

        // Event-Listener für Getränke
        drinkInputs.forEach(input => input.addEventListener('change', recalculatePrices));

        // Initiale Berechnung der Preise und Laden der Toppings
        const initialSelectedOptionId = this.el.querySelector('input[name="pizzaSize"]:checked')?.dataset.optionId;
        if (initialSelectedOptionId) {
            updateToppings(initialSelectedOptionId);
        }
        recalculatePrices();
    }
}
