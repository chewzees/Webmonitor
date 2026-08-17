<?php
declare(strict_types=1);

$authLayout = !empty($authLayout);
$publicLayout = !empty($publicLayout);
?>
<?php if ($authLayout): ?>
  </div>
<?php elseif ($publicLayout): ?>
  </main>
  <footer class="site-footer">
    <div class="footer-inner">
      <span>WebMonitor</span>
      <span>Public status page</span>
    </div>
  </footer>
<?php else: ?>
      </main>
    </div>
  </div>
  <div class="sidebar-backdrop" data-sidebar-close hidden></div>
<?php endif; ?>
<script>
  window.WM_BASE = <?= json_encode(rtrim(baseUrl(), '/'), JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(baseUrl('assets/js/app.js')) ?>" defer></script>
</body>
</html>
