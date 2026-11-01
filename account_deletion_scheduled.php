<?php
// Ensure this file is saved as account_deletion_scheduled.php
include 'header.php';
$GRACE_PERIOD_DAYS = 30;
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-lg" style="border: none; border-radius: 10px;">
                <div class="card-body p-5 text-center">
                    <div style="font-size: 56px; margin-bottom: 10px;">👋</div>
                    <h2 style="color: #3d220e; font-weight: 700;">Your account has been deactivated</h2>
                    <p class="text-muted mt-3" style="font-size: 1.05rem; line-height: 1.7;">
                        We're sorry to see you go. Your account is now deactivated and scheduled for
                        permanent deletion in <strong><?= $GRACE_PERIOD_DAYS ?> days</strong>.
                    </p>
                    <div class="restore-box my-4">
                        <i class="fas fa-undo"></i>
                        Changed your mind? Simply <a href="coffee_login.php">log back in</a> before then and
                        your account, orders, and saved details will be automatically restored — no extra steps needed.
                    </div>
                    <p class="text-muted" style="font-size: .9rem;">
                        Our team may also reach out during this period if there's anything we can help with.
                        Full details are in our <a href="terms_deletion.php">Account Deletion Terms</a>.
                    </p>
                    <a href="index.php" class="btn btn-outline-secondary mt-3">← Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.restore-box {
    background: #fdf6ed;
    border: 1px solid #DBB07E;
    border-radius: 10px;
    padding: 16px 22px;
    color: #5a3a24;
    font-size: .95rem;
}
</style>

<?php
include 'footer.php';
?>