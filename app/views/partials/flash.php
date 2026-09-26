<?php if (!empty($flash)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const icon = <?= json_encode($flash['type'], JSON_UNESCAPED_UNICODE) ?>;
    const title = <?= json_encode($flash['message'], JSON_UNESCAPED_UNICODE) ?>;
    if (icon === 'success') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: title,
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        return;
    }
    Swal.fire({
        icon: icon,
        title: title,
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#1a73e8'
    });
});
</script>
<?php endif; ?>
