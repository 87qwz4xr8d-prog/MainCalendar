<?php
$pageTitle = 'ปฏิทิน';
$fullWidth = true;
$scripts = ['js/calendar.js'];
$cal = [
    'me' => $currentUser ? [
        'id' => (int) $currentUser['id'],
        'name' => $currentUser['name'],
        'role' => $currentUser['role'],
        'department_id' => (int) $currentUser['department_id'],
    ] : null,
    'departments' => $departments,
    'endpoints' => [
        'events' => url('api/events'),
        'save' => url('api/events/save'),
        'delete' => url('api/events/delete'),
        'login' => url('login'),
    ],
];
?>
<div class="cal-page">
    <div class="cal-toolbar">
        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#calSide">ตัวกรอง</button>
        <button class="btn btn-outline-secondary" type="button" id="btnToday">วันนี้</button>
        <button class="btn btn-light" type="button" id="btnPrev" aria-label="ก่อนหน้า"><i class="bi bi-chevron-left"></i></button>
        <button class="btn btn-light" type="button" id="btnNext" aria-label="ถัดไป"><i class="bi bi-chevron-right"></i></button>
        <div class="cal-title" id="calTitle"></div>
        <div class="cal-search">
            <i class="bi bi-search"></i>
            <input class="form-control" id="searchInput" type="search" placeholder="ค้นหางาน ชื่อพนักงาน สถานที่" autocomplete="off">
            <div class="search-results" id="searchResults" hidden></div>
        </div>
        <div class="view-switch" role="group" aria-label="มุมมองปฏิทิน">
            <button type="button" data-view="day">วัน</button>
            <button type="button" data-view="week">สัปดาห์</button>
            <button type="button" data-view="month">เดือน</button>
            <button type="button" data-view="year">ปี</button>
        </div>
    </div>
    <div id="calProgress" class="cal-progress" hidden></div>
    <div class="cal-body">
        <div class="offcanvas-lg offcanvas-start cal-side" tabindex="-1" id="calSide">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5">ตัวกรอง</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#calSide" aria-label="ปิด"></button>
            </div>
            <div class="offcanvas-body side-block">
                <button class="btn-create" type="button" data-create><i class="bi bi-plus-lg"></i> สร้างงาน</button>
                <div id="miniCal"></div>
                <div>
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>แผนก</strong>
                        <span>
                            <button class="btn btn-link btn-sm" type="button" id="deptAll">ทั้งหมด</button>
                            <button class="btn btn-link btn-sm" type="button" id="deptNone">ซ่อน</button>
                        </span>
                    </div>
                    <div id="deptFilters" class="d-grid gap-2 mt-2"></div>
                </div>
                <?php if ($currentUser): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="mineOnly">
                        <label class="form-check-label" for="mineOnly">เฉพาะงานของฉัน</label>
                    </div>
                    <p class="side-note"><?= $currentUser['role'] === 'admin' ? 'ผู้ดูแลแก้ไขงานได้ทุกคน' : 'พนักงานแก้ไขและลบได้เฉพาะงานของตนเอง' ?></p>
                <?php else: ?>
                    <p class="side-note">ดูงานได้ทันที เข้าสู่ระบบเมื่อต้องการเพิ่ม แก้ไข หรือลบงาน</p>
                <?php endif; ?>
            </div>
        </div>
        <div id="calGrid" class="cal-grid"><div class="p-4 text-muted">กำลังโหลดปฏิทิน...</div></div>
    </div>
</div>

<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="eventForm" autocomplete="off">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="eventModalTitle">สร้างงาน</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="eventId">
                    <div class="mb-3">
                        <label class="form-label" for="eventTitle">ชื่องาน</label>
                        <input class="form-control" id="eventTitle" maxlength="150" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="eventStartDate">เริ่ม</label>
                            <div class="input-group">
                                <input class="form-control" id="eventStartDate" type="date" required>
                                <input class="form-control" id="eventStartTime" type="time" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="eventEndDate">สิ้นสุด</label>
                            <div class="input-group">
                                <input class="form-control" id="eventEndDate" type="date" required>
                                <input class="form-control" id="eventEndTime" type="time" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-check form-switch my-3">
                        <input class="form-check-input" type="checkbox" id="eventAllDay">
                        <label class="form-check-label" for="eventAllDay">ทั้งวัน</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="eventDepartment">แผนก</label>
                            <select class="form-select" id="eventDepartment" required>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="eventStatus">สถานะ</label>
                            <select class="form-select" id="eventStatus">
                                <option value="planned">วางแผน</option>
                                <option value="in_progress">กำลังทำ</option>
                                <option value="done">เสร็จแล้ว</option>
                            </select>
                        </div>
                        <?php if ($assignees !== []): ?>
                            <div class="col-12" id="userField">
                                <label class="form-label" for="eventUser">เจ้าของงาน</label>
                                <select class="form-select" id="eventUser">
                                    <?php foreach ($assignees as $assignee): ?>
                                        <option value="<?= (int) $assignee['id'] ?>"><?= e($assignee['name'] . ($assignee['is_active'] ? '' : ' (ปิดใช้งาน)')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label" for="eventLocation">สถานที่</label>
                            <input class="form-control" id="eventLocation" maxlength="150">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="eventDescription">รายละเอียด</label>
                            <textarea class="form-control" id="eventDescription" rows="3" maxlength="4000"></textarea>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-3" id="eventOwnerNote"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-danger me-auto" type="button" id="btnDeleteEvent" hidden>ลบงาน</button>
                    <button class="btn btn-outline-primary me-auto" type="button" id="btnLoginToEdit" hidden>เข้าสู่ระบบเพื่อแก้ไข</button>
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                    <button class="btn btn-primary" type="submit" id="btnSaveEvent">บันทึกงาน</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
window.CAL = <?= json_encode($cal, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
</script>
