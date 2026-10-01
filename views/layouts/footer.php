<?php
/**
 * Closing tags + JS includes.
 */
?>
<footer class="footer">
    <div class="footer-inner">
        <span>© <?= date('Y') ?> SmarCery · Rekomendasi pintar sesuai profil kesehatan Anda.</span>
        <span class="muted">Built with PHP · MySQL · MongoDB · Neo4j</span>
    </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $src): ?>
        <script src="<?= e(asset($src)) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>