<?php
/**
 * Raptor CRM — Visual Team & Organization Hierarchy Tree View
 * Release: Raptor v2.2 (Sprint 5)
 */
?>
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php?route=users/index" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h3 class="fw-bold mb-1"><i class="fa-solid fa-sitemap text-primary me-2"></i>Organization & Team Hierarchy</h3>
                <p class="text-secondary small mb-0">Interactive visual reporting tree mapping leadership, department managers, and operational teams.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=users/index" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-address-book me-1"></i>Employee Directory
            </a>
            <a href="index.php?route=hrms/dashboard" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-gauge me-1"></i>HRMS Dashboard
            </a>
        </div>
    </div>

    <!-- Filter & Department Selector Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="tree-search-input" class="form-control border-start-0" placeholder="Filter employee by name, job title, or code...">
                    </div>
                </div>
                <div class="col-md-7 d-flex justify-content-md-end flex-wrap gap-1" id="dept-filter-buttons">
                    <button type="button" class="btn btn-sm btn-primary active py-1 px-3 dept-btn" data-dept="all">All Departments</button>
                    <?php 
                    $depts = !empty($departments) ? $departments : ['Executive', 'Sales', 'Engineering', 'HR', 'Finance', 'Marketing'];
                    foreach ($depts as $dept): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 dept-btn" data-dept="<?php echo htmlspecialchars($dept); ?>">
                            <?php echo htmlspecialchars($dept); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Visual Hierarchy Canvas -->
    <div class="card border-0 shadow-sm p-4 text-center bg-white" style="min-height: 550px; overflow-x: auto;">
        <!-- Top Level Leadership Node -->
        <div class="d-flex flex-column align-items-center mb-4">
            <div class="org-node-card p-3 rounded-3 shadow-sm border border-2 border-primary bg-primary-subtle text-start position-relative" style="min-width: 280px; max-width: 320px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px; font-size: 1.1rem;">
                        <?php echo !empty($leader->name) ? strtoupper(substr($leader->name, 0, 2)) : 'CEO'; ?>
                    </div>
                    <div>
                        <span class="badge bg-primary text-white text-uppercase" style="font-size: 0.65rem;">Executive Leadership</span>
                        <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($leader->name ?? 'Axiora Executive Team'); ?></h6>
                        <small class="text-secondary"><?php echo htmlspecialchars($leader->job_title ?? 'Chief Executive Officer'); ?></small>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-primary-subtle small">
                    <span class="text-muted"><i class="fa-solid fa-users text-primary me-1"></i><?php echo count($managers ?? []); ?> Direct Divisions</span>
                    <div class="d-flex gap-1">
                        <?php if (!empty($leader->phone_number)): ?>
                            <a href="tel:<?php echo htmlspecialchars($leader->phone_number); ?>" class="btn btn-xs btn-outline-success py-0 px-1" title="Call"><i class="fa-solid fa-phone"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($leader->email)): ?>
                            <a href="mailto:<?php echo htmlspecialchars($leader->email); ?>" class="btn btn-xs btn-outline-primary py-0 px-1" title="Email"><i class="fa-solid fa-envelope"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Stem connector -->
            <div style="width: 2px; height: 32px; background: #cbd5e1;"></div>
        </div>

        <!-- Level 2: Division Managers & Department Leads -->
        <div class="d-flex justify-content-center flex-wrap gap-4 position-relative mb-4">
            <?php if (!empty($managers)): ?>
                <?php foreach ($managers as $mgr): ?>
                    <div class="org-branch-column d-flex flex-column align-items-center dept-node-<?php echo strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $mgr->department ?? 'general')); ?>" data-dept="<?php echo htmlspecialchars($mgr->department ?? ''); ?>" style="min-width: 260px; max-width: 300px;">
                        <!-- Node Card -->
                        <div class="org-node-card p-3 rounded-3 shadow-sm border text-start bg-white w-100 position-relative">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px;">
                                    <?php echo strtoupper(substr($mgr->name ?? 'MG', 0, 2)); ?>
                                </div>
                                <div class="overflow-hidden">
                                    <span class="badge bg-info-subtle text-info-emphasis" style="font-size: 0.65rem;"><?php echo htmlspecialchars($mgr->department ?? 'Division'); ?></span>
                                    <h6 class="fw-bold mb-0 text-truncate text-dark"><?php echo htmlspecialchars($mgr->name ?? ''); ?></h6>
                                    <small class="text-secondary text-truncate d-block"><?php echo htmlspecialchars($mgr->job_title ?? 'Department Manager'); ?></small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small">
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="fa-solid fa-user-group me-1"></i><?php echo count($mgr->reports ?? []); ?> Reports
                                </span>
                                <div class="d-flex gap-1">
                                    <?php if (!empty($mgr->phone_number)): ?>
                                        <a href="tel:<?php echo htmlspecialchars($mgr->phone_number); ?>" class="btn btn-xs btn-outline-success py-0 px-1" title="Call"><i class="fa-solid fa-phone"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($mgr->email)): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($mgr->email); ?>" class="btn btn-xs btn-outline-primary py-0 px-1" title="Email"><i class="fa-solid fa-envelope"></i></a>
                                    <?php endif; ?>
                                    <a href="index.php?route=hrms/profile/<?php echo $mgr->user_id; ?>" class="btn btn-xs btn-outline-secondary py-0 px-1" title="Profile"><i class="fa-solid fa-user"></i></a>
                                </div>
                            </div>
                        </div>

                        <!-- Level 3: Direct Reports / Team Members -->
                        <?php if (!empty($mgr->reports)): ?>
                            <div style="width: 2px; height: 24px; background: #cbd5e1;"></div>
                            <div class="d-flex flex-column gap-2 w-100">
                                <?php foreach ($mgr->reports as $rep): ?>
                                    <div class="p-2 rounded-2 border bg-light text-start shadow-2xs report-card" data-name="<?php echo strtolower($rep->name ?? ''); ?>">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                                    <?php echo strtoupper(substr($rep->name ?? 'EP', 0, 2)); ?>
                                                </div>
                                                <div class="overflow-hidden">
                                                    <div class="fw-semibold text-truncate small text-dark"><?php echo htmlspecialchars($rep->name ?? ''); ?></div>
                                                    <div class="text-muted text-truncate" style="font-size: 0.7rem;"><?php echo htmlspecialchars($rep->job_title ?? ''); ?></div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <?php if (!empty($rep->phone_number)): ?>
                                                    <a href="tel:<?php echo htmlspecialchars($rep->phone_number); ?>" class="text-success p-1" title="Call"><i class="fa-solid fa-phone" style="font-size: 0.75rem;"></i></a>
                                                <?php endif; ?>
                                                <?php if (!empty($rep->email)): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars($rep->email); ?>" class="text-primary p-1" title="Email"><i class="fa-solid fa-envelope" style="font-size: 0.75rem;"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info py-3 px-4">
                    <i class="fa-solid fa-info-circle me-1"></i>Organizational hierarchy nodes are being initialized. Assign reporting managers in Employee Management.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('tree-search-input');
    const deptButtons = document.querySelectorAll('.dept-btn');
    const branchColumns = document.querySelectorAll('.org-branch-column');
    const reportCards = document.querySelectorAll('.report-card');

    // Department Filter
    deptButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            deptButtons.forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary', 'active');

            const dept = this.getAttribute('data-dept').toLowerCase();
            branchColumns.forEach(col => {
                const colDept = (col.getAttribute('data-dept') || '').toLowerCase();
                if (dept === 'all' || colDept === dept) {
                    col.style.display = 'flex';
                } else {
                    col.style.display = 'none';
                }
            });
        });
    });

    // Search filter
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            reportCards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                if (query === '' || name.includes(query)) {
                    card.style.display = 'block';
                    card.style.opacity = '1';
                } else {
                    card.style.opacity = '0.3';
                }
            });
        });
    }
});
</script>

