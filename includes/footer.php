<?php
// SplitMate App Footer Layout
?>
        </main>

        <footer class="py-3 px-4 bg-white border-top text-center text-muted no-print" style="font-size: 0.8rem;">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                <div>&copy; <?= date('Y') ?> <strong>SplitMate</strong>. Smart Multi-Admin Settlement System.</div>
                <div>Status: <span class="badge bg-success-subtle text-success">Active & Isolated</span></div>
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom App Main JS -->
<script src="<?= base_url('assets/js/main.js') ?>"></script>

<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>

