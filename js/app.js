document.querySelectorAll(".search-box").forEach(function (input) {
    input.addEventListener("keyup", function () {
        var q = this.value.toLowerCase();
        var table = document.getElementById(this.getAttribute("data-table"));
        if (!table) {
            return;
        }
        var rows = table.querySelectorAll("tbody tr");
        for (var i = 0; i < rows.length; i++) {
            var text = rows[i].textContent.toLowerCase();
            rows[i].style.display = text.indexOf(q) !== -1 ? "" : "none";
        }
    });
});

document.querySelectorAll("form.delete-form").forEach(function (form) {
    form.addEventListener("submit", function (e) {
        if (!confirm("Delete this record?")) {
            e.preventDefault();
        }
    });
});

(function () {
    var modal = document.getElementById("supplier-modal");
    var openBtn = document.getElementById("open-supplier-modal");
    var saveBtn = document.getElementById("save-supplier-btn");
    var cancelBtn = document.getElementById("cancel-supplier-btn");
    var select = document.getElementById("supplier-select");
    var errEl = document.getElementById("supplier-modal-error");
    var nameEl = document.getElementById("modal-supplier-name");
    var companyEl = document.getElementById("modal-company-name");
    var contactEl = document.getElementById("modal-contact-number");

    if (!modal || !openBtn || !select || !saveBtn || !cancelBtn) {
        return;
    }

    function showModal() {
        errEl.hidden = true;
        errEl.textContent = "";
        nameEl.value = "";
        companyEl.value = "";
        contactEl.value = "";
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        nameEl.focus();
    }

    function hideModal() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
        saveBtn.disabled = false;
    }

    function showError(msg) {
        errEl.textContent = msg;
        errEl.hidden = false;
    }

    openBtn.addEventListener("click", showModal);
    cancelBtn.addEventListener("click", hideModal);

    modal.addEventListener("click", function (e) {
        if (e.target === modal) {
            hideModal();
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal.classList.contains("open")) {
            hideModal();
        }
    });

    saveBtn.addEventListener("click", function () {
        var supplierName = nameEl.value.trim();
        var companyName = companyEl.value.trim();
        var contactNumber = contactEl.value.trim();

        if (!supplierName || !companyName || !contactNumber) {
            showError("All fields are required.");
            return;
        }

        var data = new FormData();
        data.append("action", "add");
        data.append("ajax", "1");
        data.append("supplier_name", supplierName);
        data.append("company_name", companyName);
        data.append("contact_number", contactNumber);

        saveBtn.disabled = true;
        fetch("suppliers.php", {
            method: "POST",
            body: data,
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (json) {
                if (!json || !json.ok) {
                    showError((json && json.error) || "Could not save supplier.");
                    saveBtn.disabled = false;
                    return;
                }

                var opt = document.createElement("option");
                opt.value = String(json.supplier_id);
                opt.textContent = json.supplier_name;
                opt.selected = true;
                select.appendChild(opt);
                select.value = String(json.supplier_id);
                hideModal();
            })
            .catch(function () {
                showError("Could not save supplier.");
                saveBtn.disabled = false;
            });
    });
})();
