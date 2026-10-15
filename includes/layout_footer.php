    </main>
  </div>
</div>
<div class="loading-overlay" id="loadingOverlay">
  <div class="loading-box"><span class="spinner"></span><span data-loading-text>Memproses...</span></div>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>