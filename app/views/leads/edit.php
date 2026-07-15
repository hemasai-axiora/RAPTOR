<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="pulse-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-white mb-0">Edit Lead & Pipeline Stage</h4>
                <a href="index.php?route=leads/view/<?php echo (int) $lead_id; ?>" class="btn btn-outline-info btn-sm">
                    <i class="fa-solid fa-eye me-2"></i>Detail
                </a>
            </div>
            <form action="index.php?route=leads/edit/<?php echo (int) $lead_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="lead_id" value="<?php echo (int) $lead_id; ?>">
                <?php require APPROOT . '/views/leads/_form.php'; ?>
                <div class="d-flex justify-content-end gap-3 mt-4">
                    <a href="index.php?route=leads/index" class="btn btn-outline-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
