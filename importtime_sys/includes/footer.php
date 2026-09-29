<?php

declare(strict_types=1);

$inlineError = isset($formError) && is_string($formError) ? $formError : null;
$year = (int) date('Y') + 543;
$scripts = isset($pageScripts) && is_array($pageScripts) ? $pageScripts : [];
?>
</main>
<footer class="footer no-print">พ.ศ. <?= $year ?> · <?= e(company_name()) ?></footer>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="assets/js/app.js"></script>
<?php foreach ($scripts as $script): ?>
    <?php if (is_string($script) && preg_match('/^[a-z0-9_-]+\.js$/', $script) === 1): ?>
        <script src="assets/js/<?= e($script) ?>"></script>
    <?php endif; ?>
<?php endforeach; ?>
<?php render_alerts($inlineError); ?>
</body>
</html>
