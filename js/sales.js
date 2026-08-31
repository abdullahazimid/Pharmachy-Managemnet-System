function addSaleRow() {
    var select = document.getElementById("med-select");
    var qtyInput = document.getElementById("med-qty");
    var opt = select.options[select.selectedIndex];
    var id = select.value;
    var qty = parseInt(qtyInput.value, 10);

    if (!id || !qty || qty < 1) {
        alert("Select a medicine and quantity");
        return;
    }

    var stock = parseInt(opt.getAttribute("data-stock"), 10);
    if (qty > stock) {
        alert("Only " + stock + " in stock");
        return;
    }

    var tbody = document.getElementById("cart-body");
    var existing = tbody.querySelector('input[name="medicine_id[]"][value="' + id + '"]');
    if (existing) {
        alert("This medicine is already in the list");
        return;
    }

    var price = parseFloat(opt.getAttribute("data-price"));
    var name = opt.getAttribute("data-name");
    var tr = document.createElement("tr");
    tr.innerHTML =
        "<td>" + name +
        '<input type="hidden" name="medicine_id[]" value="' + id + '">' +
        '<input type="hidden" name="quantity[]" value="' + qty + '"></td>' +
        "<td>" + qty + "</td>" +
        "<td>" + price.toFixed(2) + "</td>" +
        '<td class="line-total">' + (price * qty).toFixed(2) + "</td>" +
        '<td><button type="button" class="delete" onclick="removeSaleRow(this)">Remove</button></td>';
    tbody.appendChild(tr);
    updateSaleTotal();
}

function removeSaleRow(btn) {
    btn.closest("tr").remove();
    updateSaleTotal();
}

function updateSaleTotal() {
    var lines = document.querySelectorAll("#cart-body .line-total");
    var sub = 0;
    for (var i = 0; i < lines.length; i++) {
        sub += parseFloat(lines[i].textContent) || 0;
    }
    var disc = parseFloat(document.getElementById("discount").value) || 0;
    var amount = sub * disc / 100;
    document.getElementById("sub-total").textContent = sub.toFixed(2);
    document.getElementById("disc-amount").textContent = amount.toFixed(2);
    document.getElementById("grand-total").textContent = (sub - amount).toFixed(2);
}

document.addEventListener("DOMContentLoaded", function () {
    var addBtn = document.getElementById("add-item-btn");
    if (addBtn) {
        addBtn.addEventListener("click", addSaleRow);
    }
    var disc = document.getElementById("discount");
    if (disc) {
        disc.addEventListener("input", updateSaleTotal);
    }
    var form = document.getElementById("sale-form");
    if (form) {
        form.addEventListener("submit", function (e) {
            var rows = document.querySelectorAll("#cart-body tr");
            if (rows.length === 0) {
                e.preventDefault();
                alert("Add at least one medicine");
            }
        });
    }
});
