(function () {
    var list = document.getElementById("lines");
    var template = document.getElementById("line-template");
    if (!list || !template) {
        return;
    }

    function money(value) {
        return value.toLocaleString("th-TH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renumber() {
        list.querySelectorAll(".line-row").forEach(function (row, index) {
            var cell = row.querySelector(".line-no");
            if (cell) {
                cell.textContent = String(index + 1);
            }
        });
    }

    function readNumber(input) {
        if (!input || input.value.trim() === "") {
            return null;
        }
        var value = Number(input.value);
        return Number.isFinite(value) ? value : null;
    }

    function recalc() {
        var subtotal = 0;
        var complete = true;
        list.querySelectorAll(".line-row").forEach(function (row) {
            var qty = readNumber(row.querySelector('[name="qty[]"]'));
            var price = readNumber(row.querySelector('[name="unit_price[]"]'));
            var cell = row.querySelector(".line-amount");
            if (qty === null || price === null) {
                complete = false;
                if (cell) {
                    cell.textContent = "-";
                }
                return;
            }
            var amount = Math.round(qty * price * 100) / 100;
            subtotal += amount;
            if (cell) {
                cell.textContent = money(amount);
            }
        });
        var freightInput = document.getElementById("freight");
        var freight = readNumber(freightInput);
        if (freight === null) {
            freight = 0;
        }
        var grand = complete ? subtotal + freight : null;
        var subtotalNode = document.getElementById("preview-subtotal");
        var grandNode = document.getElementById("preview-grand");
        var thbNode = document.getElementById("preview-thb");
        if (subtotalNode) {
            subtotalNode.textContent = complete ? money(subtotal) : "-";
        }
        if (grandNode) {
            grandNode.textContent = grand === null ? "-" : money(grand);
        }
        if (thbNode) {
            var currency = document.getElementById("currency");
            var rateInput = document.getElementById("exchange_rate");
            var rate = currency && currency.value === "THB" ? 1 : readNumber(rateInput);
            thbNode.textContent = grand === null || rate === null ? "-" : money(Math.round(grand * rate * 100) / 100);
        }
    }

    function addLine() {
        list.appendChild(template.content.cloneNode(true));
        renumber();
        recalc();
    }

    document.getElementById("add-line").addEventListener("click", addLine);
    list.addEventListener("click", function (event) {
        var button = event.target.closest(".remove-line");
        if (!button) {
            return;
        }
        if (list.querySelectorAll(".line-row").length <= 1) {
            return;
        }
        button.closest(".line-row").remove();
        renumber();
        recalc();
    });
    document.addEventListener("input", recalc);
    document.addEventListener("change", recalc);

    var picker = document.getElementById("supplier_pick");
    if (picker) {
        picker.addEventListener("change", function () {
            var option = picker.selectedOptions[0];
            if (!option || !option.dataset.name) {
                return;
            }
            document.getElementById("supplier_name").value = option.dataset.name;
            document.getElementById("supplier_country").value = option.dataset.country || "";
            document.getElementById("supplier_contact").value = option.dataset.contact || "";
        });
    }

    renumber();
    recalc();
})();

(function () {
    var form = document.getElementById("pr-form");
    if (!form || document.getElementById("lines")) {
        return;
    }

    function money(value) {
        return value.toLocaleString("th-TH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalc() {
        var sum = 0;
        var complete = true;
        form.querySelectorAll("tr.line-row").forEach(function (row) {
            var input = row.querySelector("input[name^='qty']");
            var price = Number(row.getAttribute("data-price"));
            var cell = row.querySelector(".line-amount");
            if (!input || !Number.isFinite(price)) {
                return;
            }
            var qty = Number(input.value);
            if (!Number.isFinite(qty)) {
                complete = false;
                if (cell) {
                    cell.textContent = "-";
                }
                return;
            }
            var amount = Math.round(qty * price * 100) / 100;
            sum += amount;
            if (cell) {
                cell.textContent = money(amount);
            }
        });
        var freight = Number(form.getAttribute("data-freight") || "0");
        var currency = form.getAttribute("data-currency") || "";
        var rawRate = form.getAttribute("data-rate") || "";
        var rate = currency === "THB" ? 1 : (rawRate === "" ? null : Number(rawRate));
        var grand = complete && Number.isFinite(freight) ? Math.round((sum + freight) * 100) / 100 : null;
        var subtotalNode = document.getElementById("preview-subtotal");
        var grandNode = document.getElementById("preview-grand");
        var thbNode = document.getElementById("preview-thb");
        if (subtotalNode) {
            subtotalNode.textContent = complete ? money(sum) : "-";
        }
        if (grandNode) {
            grandNode.textContent = grand === null ? "-" : money(grand);
        }
        if (thbNode) {
            thbNode.textContent = grand === null || rate === null || !Number.isFinite(rate) || rate <= 0 ? "-" : money(Math.round(grand * rate * 100) / 100);
        }
    }

    form.addEventListener("input", recalc);
})();
