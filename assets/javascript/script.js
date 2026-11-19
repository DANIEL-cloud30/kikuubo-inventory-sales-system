function togglePassword() {

    const passwordInput = document.getElementById("password");
    const toggleButton = document.querySelector(".toggle-password");

    if (passwordInput.type === "password") {

        passwordInput.type = "text";
        toggleButton.textContent = "Hide";

    } else {

        passwordInput.type = "password";
        toggleButton.textContent = "Show";

    }

}

/* SALES MANAGEMENT */

function addSaleRow() {

    const container = document.getElementById("saleItems");

    const firstRow = container.querySelector(".sale-row");

    const newRow = firstRow.cloneNode(true);

    newRow.querySelector(".sale-product").selectedIndex = 0;

    newRow.querySelector(".sale-quantity").value = "";

    newRow.querySelector(".sale-price").value = "UGX 0";

    newRow.querySelector(".sale-subtotal").value = "UGX 0";

    container.appendChild(newRow);

    calculateGrandTotal();

}


function removeSaleRow(button) {

    const container = document.getElementById("saleItems");

    const rows = container.querySelectorAll(".sale-row");

    if (rows.length > 1) {

        button.closest(".sale-row").remove();

        calculateGrandTotal();

    }

}


function calculateSaleRow(row) {

    const productSelect = row.querySelector(".sale-product");

    const quantityInput = row.querySelector(".sale-quantity");

    const priceInput = row.querySelector(".sale-price");

    const subtotalInput = row.querySelector(".sale-subtotal");

    const selectedOption = productSelect.options[
        productSelect.selectedIndex
    ];

    const price = Number(selectedOption.dataset.price || 0);

    const stock = Number(selectedOption.dataset.stock || 0);

    const quantity = Number(quantityInput.value || 0);

    quantityInput.max = stock;

    priceInput.value = "UGX " + price.toLocaleString("en-UG");

    if (quantity > stock) {

        quantityInput.setCustomValidity(
            "Only " + stock + " units are available."
        );

    } else {

        quantityInput.setCustomValidity("");

    }

    const subtotal = price * quantity;

    subtotalInput.value =
        "UGX " + subtotal.toLocaleString("en-UG");

    calculateGrandTotal();

}


function calculateGrandTotal() {

    const rows = document.querySelectorAll(".sale-row");

    let total = 0;

    rows.forEach(row => {

        const productSelect = row.querySelector(".sale-product");

        const quantityInput = row.querySelector(".sale-quantity");

        const selectedOption = productSelect.options[
            productSelect.selectedIndex
        ];

        const price = Number(selectedOption.dataset.price || 0);

        const quantity = Number(quantityInput.value || 0);

        total += price * quantity;

    });

    document.getElementById("grandTotal").textContent =
        "UGX " + total.toLocaleString("en-UG");

}


// LISTEN FOR CHANGES

document.addEventListener("change", function(event) {

    if (
        event.target.classList.contains("sale-product") ||
        event.target.classList.contains("sale-quantity")
    ) {

        calculateSaleRow(
            event.target.closest(".sale-row")
        );

    }

});


document.addEventListener("input", function(event) {

    if (event.target.classList.contains("sale-quantity")) {

        calculateSaleRow(
            event.target.closest(".sale-row")
        );

    }

});


// VALIDATE BEFORE SUBMITTING

document.addEventListener("submit", function(event) {

    if (event.target.id !== "salesForm") {
        return;
    }

    const rows = document.querySelectorAll(".sale-row");

    let total = 0;

    for (const row of rows) {

        const productSelect = row.querySelector(".sale-product");

        const quantityInput = row.querySelector(".sale-quantity");

        const option = productSelect.options[
            productSelect.selectedIndex
        ];

        const price = Number(option.dataset.price || 0);

        const stock = Number(option.dataset.stock || 0);

        const quantity = Number(quantityInput.value || 0);

        if (
            !productSelect.value ||
            !Number.isInteger(quantity) ||
            quantity <= 0 ||
            quantity > stock
        ) {

            event.preventDefault();

            alert("Please check your selected products and quantities.");

            return;

        }

        total += price * quantity;

    }

    if (total <= 0) {

        event.preventDefault();

        alert("Please enter at least one valid product.");

        return;

    }

    if (!confirm(
        "Complete this sale for UGX " +
        total.toLocaleString("en-UG") + "?"
    )) {

        event.preventDefault();

    }

});