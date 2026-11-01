<?php
// Raptor CRM - Audience Segments & Filter Builder View
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-filter text-primary me-2"></i>Audience Segments & Filter Builder</h3>
            <p class="text-secondary small mb-0">Build dynamic, multi-condition rules to target highly specific subscriber groups without duplicating contacts.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createSegmentModal">
                <i class="fa-solid fa-plus me-1"></i>Create Segment
            </button>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
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
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/segments">
                <i class="fa-solid fa-filter me-1"></i>Segments (<?php echo count($segments); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium text-danger" href="index.php?route=email_campaigns/suppressions">
                <i class="fa-solid fa-ban me-1"></i>Global Suppression List
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

    <!-- Segments Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th>Segment Name</th>
                        <th>Condition Match</th>
                        <th>Active Rules</th>
                        <th>Matching Contacts</th>
                        <th>Created By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($segments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-filter-circle-xmark fs-2 mb-2 d-block"></i>
                                No segments defined yet. Segments let you dynamically target subscribers by tags, domain, or status.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($segments as $s): ?>
                            <?php 
                            $segRules = !empty($s->rules_json) ? json_decode($s->rules_json, true) : [];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($s->name); ?></div>
                                    <?php if (!empty($s->description)): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($s->description); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border">
                                        Match <?php echo strtoupper($s->match_type); ?> rules
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-secondary">
                                        <?php if (empty($segRules)): ?>
                                            <span class="text-muted">No rules (matches all subscribed)</span>
                                        <?php else: ?>
                                            <?php foreach ($segRules as $r): ?>
                                                <span class="badge bg-light text-dark border me-1 mb-1">
                                                    <code><?php echo htmlspecialchars($r['field'] ?? ''); ?></code> 
                                                    <?php echo htmlspecialchars($operators[$r['operator']] ?? $r['operator']); ?> 
                                                    "<?php echo htmlspecialchars($r['value'] ?? ''); ?>"
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fs-6 fw-bold px-2 py-1 seg-count" data-seg-id="<?php echo $s->segment_id; ?>">
                                        <i class="fa-solid fa-spinner fa-spin me-1"></i>Counting...
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-secondary"><?php echo htmlspecialchars($s->creator_name ?? 'System'); ?></span>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="index.php?route=email_campaigns/segmentDelete" onsubmit="return confirm('Delete this dynamic segment? Contacts themselves will not be affected.');" class="d-inline">
                                        <input type="hidden" name="segment_id" value="<?php echo $s->segment_id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Segment">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create Segment -->
<div class="modal fade" id="createSegmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="index.php?route=email_campaigns/segmentSave">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create Dynamic Audience Segment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Segment Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Active VIP Customers" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Match Condition</label>
                            <select name="match_type" class="form-select">
                                <option value="all">Match ALL criteria (AND)</option>
                                <option value="any">Match ANY criterion (OR)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Optional notes about audience intention">
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-sliders me-2"></i>Filter Rules</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-rule"><i class="fa-solid fa-plus me-1"></i>Add Rule</button>
                        </div>
                        <div id="rules-container">
                            <div class="row g-2 mb-2 rule-row align-items-center">
                                <div class="col-md-4">
                                    <select name="rule_field[]" class="form-select form-select-sm">
                                        <option value="status">Contact Status</option>
                                        <option value="email">Email Address</option>
                                        <option value="company_name">Company Name</option>
                                        <option value="source">Source Channel</option>
                                        <option value="tag">Tagged With</option>
                                        <option value="list">Member of List</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="rule_operator[]" class="form-select form-select-sm">
                                        <?php foreach ($operators as $opKey => $opLabel): ?>
                                            <option value="<?php echo htmlspecialchars($opKey); ?>"><?php echo htmlspecialchars($opLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="rule_value[]" class="form-control form-control-sm" placeholder="Value (e.g. subscribed or gmail.com)">
                                </div>
                                <div class="col-md-1 text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-rule"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check me-1"></i>Save Segment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live count loader for segments
    document.querySelectorAll('.seg-count').forEach(function(el) {
        const segId = el.getAttribute('data-seg-id');
        fetch('index.php?route=email_campaigns/segmentPreview&id=' + encodeURIComponent(segId))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    el.innerHTML = '<i class="fa-solid fa-user-check me-1"></i>' + data.count + ' contacts';
                } else {
                    el.innerHTML = 'Error';
                }
            })
            .catch(() => {
                el.innerHTML = 'N/A';
            });
    });

    // Rule row adder
    const rulesContainer = document.getElementById('rules-container');
    const btnAddRule = document.getElementById('btn-add-rule');

    btnAddRule.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 rule-row align-items-center';
        row.innerHTML = `
            <div class="col-md-4">
                <select name="rule_field[]" class="form-select form-select-sm">
                    <option value="status">Contact Status</option>
                    <option value="email">Email Address</option>
                    <option value="company_name">Company Name</option>
                    <option value="source">Source Channel</option>
                    <option value="tag">Tagged With</option>
                    <option value="list">Member of List</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="rule_operator[]" class="form-select form-select-sm">
                    <?php foreach ($operators as $opKey => $opLabel): ?>
                        <option value="<?php echo htmlspecialchars($opKey); ?>"><?php echo htmlspecialchars($opLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="rule_value[]" class="form-control form-control-sm" placeholder="Value">
            </div>
            <div class="col-md-1 text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-rule"><i class="fa-solid fa-trash"></i></button>
            </div>
        `;
        rulesContainer.appendChild(row);
    });

    rulesContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-rule')) {
            const row = e.target.closest('.rule-row');
            if (document.querySelectorAll('.rule-row').length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(i => i.value = '');
            }
        }
    });
});
</script>
