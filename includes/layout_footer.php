  </div><!-- /.page-content -->
</div><!-- /.main-content -->

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if (isset($extraJS)) echo $extraJS; ?>
<?php include 'chatbot-widget.php'; ?>
</body>
</html>
