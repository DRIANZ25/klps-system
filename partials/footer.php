</main>
</div>
</div>

<div class="logout-modal-overlay" id="logoutModal">
    <div class="logout-modal">
        <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
        <h3>Sign Out</h3>
        <p>Are you sure you want to sign out of your account? You'll need to sign in again to access your knowledge library.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeLogoutModal()"><i class="fas fa-times me-1"></i> Cancel</button>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket me-1"></i> Sign Out</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ((array) ($page_js ?? []) as $page_js_file): ?>
<script src="<?= e(asset('js/' . $page_js_file)) ?>"></script>
<?php endforeach; ?>
</body>
</html>