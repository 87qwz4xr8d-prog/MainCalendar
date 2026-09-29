(function () {
    document.querySelectorAll("[data-confirm]").forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();
            var formId = button.getAttribute("data-form");
            var form = formId ? document.getElementById(formId) : null;
            if (!form || typeof Swal === "undefined") {
                return;
            }
            var kind = button.getAttribute("data-confirm") || "action";
            Swal.fire({
                title: kind === "delete" ? "ยืนยันการลบ" : "ยืนยันการทำรายการ",
                text: button.getAttribute("data-message") || "ต้องการดำเนินการต่อหรือไม่?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: kind === "delete" ? "#9b2335" : "#0f4c81",
                cancelButtonColor: "#5c6b7a",
                confirmButtonText: kind === "delete" ? "ลบ" : "ยืนยัน",
                cancelButtonText: "ยกเลิก",
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    var actionValue = button.getAttribute("data-action");
                    var actionInput = form.querySelector("[data-action-input]");
                    if (actionInput && actionValue) {
                        actionInput.value = actionValue;
                    }
                    form.submit();
                }
            });
        });
    });
})();
