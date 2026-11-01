<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-users-viewfinder me-2 text-primary"></i>Customer 360 Registry</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Unified identity graph across Leads, Customers, Email Subscribers, and WhatsApp Prospects.</div>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-dark border border-secondary px-3 py-2 text-info fs-6">
                <i class="fa-solid fa-database me-1"></i> Total Profiles: <strong><?= number_format($total ?? 0); ?></strong>
            </span>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card bg-dark border-secondary p-3 mb-4" style="border-radius: 10px;">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="route" value="contacts/index">
            <div class="col-md-4">
                <label class="form-label text-secondary small mb-1">Search Contact / Company / Phone</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Name, email, phone, or company..." value="<?= htmlspecialchars($filters['search'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-secondary small mb-1">Lifecycle Stage</label>
                <select name="lifecycle_stage" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Lifecycle Stages</option>
                    <?php foreach ($stages as $st): ?>
                        <option value="<?= $st; ?>" <?= (($filters['lifecycle_stage'] ?? '') === $st) ? 'selected' : ''; ?>><?= ucfirst($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-secondary small mb-1">Lead Quality</label>
                <select name="lead_quality" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Qualities</option>
                    <?php foreach ($qualities as $q): ?>
                        <option value="<?= $q; ?>" <?= (($filters['lead_quality'] ?? '') === $q) ? 'selected' : ''; ?>><?= ucfirst($q); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-secondary small mb-1">Suppression</label>
                <select name="is_suppressed" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Statuses</option>
                    <option value="0" <?= (($filters['is_suppressed'] ?? '') === '0') ? 'selected' : ''; ?>>Active / Deliverable</option>
                    <option value="1" <?= (($filters['is_suppressed'] ?? '') === '1') ? 'selected' : ''; ?>>Suppressed / Opt-out</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Apply Filter"><i class="fa-solid fa-filter"></i></button>
                <a href="index.php?route=contacts/index" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <!-- Contacts Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>CONTACT NAME & IDENTITIES</th>
                    <th>COMPANY</th>
                    <th>STAGE</th>
                    <th>SCORE & QUALITY</th>
                    <th>SUPPRESSION</th>
                    <th>SOURCE</th>
                    <th>LAST UPDATED</th>
                    <th class="text-end">ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-folder-open fa-2x mb-2 d-block"></i>
                            No unified contacts found matching current criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contacts as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white">
                                    <a href="index.php?route=contacts/view/<?= $c->contact_id; ?>" class="text-decoration-none text-info">
                                        <?= htmlspecialchars(trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: 'Anonymous Contact'); ?>
                                    </a>
                                </div>
                                <div class="small text-secondary">
                                    <?php if (!empty($c->primary_email)): ?>
                                        <span class="me-2"><i class="fa-regular fa-envelope me-1"></i><?= htmlspecialchars($c->primary_email); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($c->primary_phone)): ?>
                                        <span><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($c->primary_phone); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($c->company_name ?: '—'); ?>
                            </td>
                            <td>
                                <?php
                                $stageColors = [
                                    'subscriber' => 'secondary',
                                    'lead' => 'info',
                                    'mql' => 'primary',
                                    'sql' => 'warning',
                                    'opportunity' => 'primary',
                                    'customer' => 'success',
                                    'evangelist' => 'success',
                                    'churned' => 'danger'
                                ];
                                $stColor = $stageColors[$c->lifecycle_stage] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $stColor; ?> px-2 py-1"><?= strtoupper($c->lifecycle_stage); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary text-white me-1">
                                    <i class="fa-solid fa-bolt text-warning me-1"></i><?= (int)$c->lead_score; ?>
                                </span>
                                <?php
                                $qColors = ['hot' => 'danger', 'warm' => 'warning', 'cold' => 'secondary'];
                                $qColor = $qColors[$c->lead_quality] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $qColor; ?>-subtle text-<?= $qColor; ?> border border-<?= $qColor; ?> px-2 py-0.5">
                                    <?= ucfirst($c->lead_quality); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int)$c->is_suppressed === 1): ?>
                                    <span class="badge bg-danger" title="Reason: <?= htmlspecialchars($c->suppression_reason ?? 'Opt-out'); ?>">
                                        <i class="fa-solid fa-ban me-1"></i>Suppressed
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <i class="fa-solid fa-check me-1"></i>Active
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $c->source ?? 'direct'))); ?>
                            </td>
                            <td class="small text-secondary">
                                <?= date('M d, Y', strtotime($c->updated_at ?? $c->created_at)); ?>
                            </td>
                            <td class="text-end">
                                <a href="index.php?route=contacts/view/<?= $c->contact_id; ?>" class="btn btn-outline-info btn-sm px-2 py-1" title="View Customer 360 Profile">
                                    <i class="fa-solid fa-eye me-1"></i>360°
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (($total_pages ?? 1) > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary">
            <span class="text-secondary small">Showing page <?= $page; ?> of <?= $total_pages; ?></span>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                    <li class="page-item <?= ($i === $page) ? 'active' : ''; ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

