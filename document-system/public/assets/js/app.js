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
            var text = button.getAttribute("data-message") || "ต้องการดำเนินการต่อหรือไม่?";
            Swal.fire({
                title: kind === "delete" ? "ยืนยันการลบ" : "ยืนยันการทำรายการ",
                text: text,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: kind === "delete" ? "ลบ" : "ยืนยัน",
                cancelButtonText: "ยกเลิก",
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
})();
