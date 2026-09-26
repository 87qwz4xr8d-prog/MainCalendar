document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-fill]');
    if (!button) {
        return;
    }
    const form = button.closest('form') || document.querySelector('.login-form');
    if (!form) {
        return;
    }
    const email = form.querySelector('[name="email"]');
    const password = form.querySelector('[name="password"]');
    if (email) {
        email.value = button.dataset.fill || '';
    }
    if (password) {
        password.value = button.dataset.pass || '';
        password.focus();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
        return;
    }
    event.preventDefault();
    Swal.fire({
        title: 'ยืนยันการทำรายการ',
        text: form.dataset.confirm,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#d93025',
        reverseButtons: true,
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
});
