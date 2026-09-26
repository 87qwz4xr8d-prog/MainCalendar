document.addEventListener('DOMContentLoaded', () => {
    const departmentModal = document.getElementById('deptModal');
    if (departmentModal) {
        const form = document.getElementById('deptForm');
        const title = document.getElementById('deptModalTitle');
        document.getElementById('btnNewDept').addEventListener('click', () => {
            form.reset();
            document.getElementById('deptId').value = '';
            document.getElementById('deptColor').value = '#1a73e8';
            title.textContent = 'เพิ่มแผนก';
            bootstrap.Modal.getOrCreateInstance(departmentModal).show();
        });
        document.querySelectorAll('.js-edit-dept').forEach((button) => {
            button.addEventListener('click', () => {
                const department = JSON.parse(button.dataset.dept);
                document.getElementById('deptId').value = department.id;
                document.getElementById('deptName').value = department.name;
                document.getElementById('deptColor').value = department.color;
                document.getElementById('deptDescription').value = department.description || '';
                title.textContent = 'แก้ไขแผนก';
                bootstrap.Modal.getOrCreateInstance(departmentModal).show();
            });
        });
    }

    const userModal = document.getElementById('userModal');
    if (userModal) {
        const form = document.getElementById('userForm');
        const title = document.getElementById('userModalTitle');
        const password = document.getElementById('userPassword');
        const hint = document.getElementById('passwordHint');
        const syncEmployeeCode = () => {
            const code = document.getElementById('userCode');
            const note = document.getElementById('userCodeHint');
            code.required = true;
            note.textContent = 'ใช้รหัสนี้หรืออีเมลคู่กับรหัสผ่านเพื่อเข้าสู่ระบบ';
        };
        document.getElementById('userRole').addEventListener('change', syncEmployeeCode);
        const openUser = (user) => {
            form.reset();
            document.getElementById('userActive').checked = true;
            if (!user) {
                document.getElementById('userId').value = '';
                document.getElementById('userCode').value = '';
                password.required = true;
                document.getElementById('userPasswordConfirm').required = true;
                hint.textContent = 'อย่างน้อย 8 ตัวอักษร และต้องมีทั้งตัวอักษรกับตัวเลข';
                title.textContent = 'เพิ่มพนักงาน';
            } else {
                document.getElementById('userId').value = user.id;
                document.getElementById('userName').value = user.name;
                document.getElementById('userCode').value = user.employee_code || '';
                document.getElementById('userEmail').value = user.email;
                document.getElementById('userDepartment').value = String(user.department_id);
                document.getElementById('userRole').value = user.role;
                document.getElementById('userActive').checked = Number(user.is_active) === 1;
                password.required = false;
                document.getElementById('userPasswordConfirm').required = false;
                hint.textContent = 'เว้นว่างหากไม่ต้องการเปลี่ยนรหัสผ่าน';
                title.textContent = 'แก้ไขพนักงาน';
            }
            syncEmployeeCode();
            bootstrap.Modal.getOrCreateInstance(userModal).show();
        };
        document.getElementById('btnNewUser').addEventListener('click', () => openUser(null));
        document.querySelectorAll('.js-edit-user').forEach((button) => {
            button.addEventListener('click', () => openUser(JSON.parse(button.dataset.user)));
        });
        const filter = document.getElementById('userFilter');
        filter?.addEventListener('input', () => {
            const query = filter.value.trim().toLowerCase();
            document.querySelectorAll('#userTable tbody tr').forEach((row) => {
                row.hidden = query !== '' && !row.innerText.toLowerCase().includes(query);
            });
        });
    }
});
