<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="pulse-card">
            <h4 class="text-white mb-4">Capture Lead</h4>
            <form action="index.php?route=leads/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <?php require APPROOT . '/views/leads/_form.php'; ?>
                <div class="d-flex justify-content-end gap-3 mt-4">
                    <a href="index.php?route=leads/index" class="btn btn-outline-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Save Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>
