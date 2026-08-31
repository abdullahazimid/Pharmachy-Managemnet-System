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
