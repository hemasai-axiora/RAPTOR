<?php
// Raptor CRM - Email Templates Gallery
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-file-code text-primary me-2"></i>Email Templates</h3>
            <p class="text-secondary small mb-0">Mobile-responsive, table-based templates with mandatory plain-text alternatives (max 100 KB, zero base64 images).</p>
        </div>
        <div>
            <a href="index.php?route=email_campaigns/templateCreate" class="btn btn-primary btn-sm px-3">
                <i class="fa-solid fa-plus me-1"></i>New Template
            </a>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/campaigns">
                <i class="fa-solid fa-paper-plane me-1"></i>Campaigns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/templates">
                <i class="fa-solid fa-file-code me-1"></i>Templates (<?php echo count($templates); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/contacts">
                <i class="fa-solid fa-users me-1"></i>Contacts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/lists">
                <i class="fa-solid fa-list me-1"></i>Lists & Payroll Sync
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/segments">
                <i class="fa-solid fa-filter me-1"></i>Segments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium text-danger" href="index.php?route=email_campaigns/suppressions">
                <i class="fa-solid fa-ban me-1"></i>Global Suppression
            </a>
        </li>
    </ul>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Templates Grid -->
    <div class="row g-4">
        <?php if (empty($templates)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fa-regular fa-envelope fs-1 mb-2 d-block"></i>
                No templates found. Create your first responsive template.
            </div>
        <?php else: ?>
            <?php foreach ($templates as $t): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <span class="badge bg-primary-subtle text-primary border small text-uppercase"><?php echo htmlspecialchars($t->category); ?></span>
                            <?php if ($t->is_prebuilt): ?>
                                <span class="badge bg-success-subtle text-success small"><i class="fa-solid fa-circle-check me-1"></i>Pre-built System</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($t->name); ?></h5>
                            <?php if (!empty($t->subject_default)): ?>
                                <p class="small text-muted mb-3"><i class="fa-solid fa-heading me-1"></i><?php echo htmlspecialchars($t->subject_default); ?></p>
                            <?php endif; ?>
                            
                            <div class="p-3 bg-light rounded small text-secondary mb-3" style="max-height: 120px; overflow: hidden; text-overflow: ellipsis; border: 1px dashed #cbd5e1;">
                                <?php echo htmlspecialchars(substr(strip_tags($t->plain_text_content), 0, 180)) . '...'; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($t->creator_name ?? 'System'); ?></span>
                            <div class="d-flex gap-2">
                                <a href="index.php?route=email_campaigns/templateEdit&id=<?php echo $t->template_id; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit / Preview
                                </a>
                                <?php if (!$t->is_prebuilt): ?>
                                    <form method="POST" action="index.php?route=email_campaigns/templateDelete" onsubmit="return confirm('Delete this custom template?');" class="d-inline">
                                        <input type="hidden" name="template_id" value="<?php echo $t->template_id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete template">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

