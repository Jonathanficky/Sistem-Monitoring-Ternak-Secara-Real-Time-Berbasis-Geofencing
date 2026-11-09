        </main>

        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e($__appName ?? APP_NAME) ?> &middot; <?= e(APP_DESCRIPTION) ?></span>
            <span class="footer-version">v<?= e(APP_VERSION) ?></span>
        </footer>
    </div>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<script>
    window.TERNAK = {
        baseUrl: <?= json_encode(BASE_URL) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        pollInterval: <?= (int) ($__pollInterval ?? 5000) ?>,
        offlineAfter: <?= (int) ($__offlineAfter ?? 90) ?>,
        mapCenter: [1.1186, 104.0486]
    };
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/map.js')) ?>"></script>
<?php if (!empty($page_script)): ?>
    <script>
<?= $page_script ?>
    </script>
<?php endif; ?>
</body>
</html>
