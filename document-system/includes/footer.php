<?php

declare(strict_types=1);

$inlineError = isset($formError) && is_string($formError) ? $formError : null;
$year = (int) date('Y') + 543;
?>
            </div>
        </section>
    </div>
    <footer class="main-footer text-sm">
        <strong><?= e(APP_NAME) ?></strong>
        <span class="float-right d-none d-sm-inline">พ.ศ. <?= $year ?></span>
    </footer>
</div>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/adminlte/adminlte.min.js"></script>
<script src="assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="assets/js/app.js"></script>
<?php render_alerts($inlineError); ?>
</body>
</html>
