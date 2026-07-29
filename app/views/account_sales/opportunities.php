<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-0"><i class="fa-solid fa-kanban text-primary me-2"></i>Account Growth Pipeline</h4>
        <p class="text-secondary small mb-0">Track post-conversion upsell, renewal, and cross-sell opportunities for existing clients.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?route=account_sales/index" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Sales Dashboard
        </a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOpportunityModal" style="background: var(--primary); border: none;">
            <i class="fa-solid fa-plus me-1"></i>New Opportunity
        </button>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<style>
    .opportunity-board {
        display: grid;
        grid-template-columns: repeat(5, minmax(240px, 1fr));
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }
    .opportunity-column {
        min-height: 520px;
        background: rgba(0,0,0,0.18);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1rem;
    }
    .kebab-btn {
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.6);
        padding: 2px 6px;
        border-radius: 4px;
    }
    .kebab-btn:hover { color: #fff; background: rgba(255, 255, 255, 0.15); }
    @media (max-width: 991.98px) {
        .opportunity-board { display: block; }
        .opportunity-column { min-height: auto; margin-bottom: 1rem; }
    }
</style>

<!-- Kanban Opportunity Board -->
<div class="opportunity-board">
    <?php 
    $stages = ['Identified', 'Proposed', 'Negotiating', 'Won', 'Lost'];
    $validNextMap = [
        'Identified' => ['Proposed', 'Lost'],
        'Proposed' => ['Negotiating', 'Won', 'Lost'],
        'Negotiating' => ['Won', 'Lost'],
        'Won' => [],
        'Lost' => []
    ];
    ?>

    <?php foreach ($stages as $st): ?>
        <?php $items = $pipeline[$st] ?? []; ?>
        <section class="opportunity-column">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white mb-0" style="font-size: 0.95rem;"><?php echo strtoupper($st); ?></h5>
                <span class="badge bg-secondary"><?php echo count($items); ?></span>
            </div>

            <div class="d-flex flex-column gap-3">
                <?php if (empty($items)): ?>
                    <div class="text-secondary small">No opportunities in this stage.</div>
                <?php endif; ?>

                <?php foreach ($items as $opp): ?>
                    <?php $nextStages = $validNextMap[$opp->stage] ?? []; ?>
                    <div class="pulse-card p-3" style="border-radius: 10px;" id="opp-card-<?php echo $opp->opportunity_id; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-dark border border-secondary text-primary font-monospace" style="font-size:0.75rem;">
                                <?php echo htmlspecialchars($opp->opportunity_code); ?>
                            </span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                <?php echo htmlspecialchars($opp->opportunity_type); ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="text-white fw-bold" style="font-size:0.95rem;">
                                <?php echo htmlspecialchars($opp->title); ?>
                            </div>
                            <?php if (!empty($nextStages)): ?>
                                <div class="dropdown">
                                    <button class="kebab-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Stage Actions">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-secondary bg-dark text-white">
                                        <li><h6 class="dropdown-header text-uppercase text-secondary" style="font-size:0.7rem;">Advance Stage</h6></li>
                                        <?php foreach ($nextStages as $nextSt): ?>
                                            <li>
                                                <button type="button" class="dropdown-item text-white btn-move-opp py-1 px-3"
                                                        data-opp-id="<?php echo $opp->opportunity_id; ?>"
                                                        data-opp-title="<?php echo htmlspecialchars($opp->title); ?>"
                                                        data-current-stage="<?php echo $opp->stage; ?>"
                                                        data-target-stage="<?php echo $nextSt; ?>">
                                                    <i class="fa-solid <?php echo $nextSt === 'Lost' ? 'fa-circle-xmark text-danger' : 'fa-circle-right text-primary'; ?> me-2"></i>
                                                    Move to <strong><?php echo $nextSt; ?></strong>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-secondary small mb-2">
                            <i class="fa-solid fa-building me-1"></i><?php echo htmlspecialchars($opp->company_name ?: $opp->first_name); ?>
                        </div>
                        <div class="d-flex justify-content-between text-secondary small">
                            <span><i class="fa-solid fa-user-tie me-1"></i><?php echo htmlspecialchars($opp->rep_name ?: 'Unassigned'); ?></span>
                            <span>Probability: <strong><?php echo $opp->probability; ?>%</strong></span>
                        </div>
                        <div class="text-success fw-bold font-monospace mt-2 fs-5">
                            $<?php echo number_format((float)$opp->expected_value, 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<!-- Add Opportunity Modal -->
<div class="modal fade" id="addOpportunityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <form action="index.php?route=account_sales/addOpportunity" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fa-solid fa-folder-plus me-2 text-primary"></i>Create Growth Opportunity</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="opp_customer_id" class="form-label text-secondary small">Target Customer *</label>
                        <select name="customer_id" id="opp_customer_id" class="form-select bg-dark border-secondary text-white" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?php echo $cust->customer_id; ?>">
                                    <?php echo htmlspecialchars($cust->company_name ?: $cust->first_name); ?> (<?php echo htmlspecialchars($cust->customer_code); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="opp_title" class="form-label text-secondary small">Opportunity Title *</label>
                        <input type="text" name="title" id="opp_title" class="form-control bg-dark border-secondary text-white" required placeholder="e.g. Enterprise SEO Upsell Q3">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="opp_type" class="form-label text-secondary small">Opportunity Type</label>
                            <select name="opportunity_type" id="opp_type" class="form-select bg-dark border-secondary text-white">
                                <option value="Upsell">Upsell</option>
                                <option value="Renewal">Renewal</option>
                                <option value="Cross-sell">Cross-sell</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="opp_stage" class="form-label text-secondary small">Initial Stage</label>
                            <select name="stage" id="opp_stage" class="form-select bg-dark border-secondary text-white">
                                <option value="Identified">Identified</option>
                                <option value="Proposed">Proposed</option>
                                <option value="Negotiating">Negotiating</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="opp_expected_value" class="form-label text-secondary small">Expected Value ($)</label>
                            <input type="number" step="0.01" name="expected_value" id="opp_expected_value" class="form-control bg-dark border-secondary text-white" value="5000.00">
                        </div>
                        <div class="col-md-6">
                            <label for="opp_probability" class="form-label text-secondary small">Win Probability (%)</label>
                            <input type="number" min="0" max="100" name="probability" id="opp_probability" class="form-control bg-dark border-secondary text-white" value="50">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="opp_assigned_rep_employee_id" class="form-label text-secondary small">Assigned Sales Rep</label>
                            <select name="assigned_rep_employee_id" id="opp_assigned_rep_employee_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp->employee_id; ?>"><?php echo htmlspecialchars($emp->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="opp_target_close_date" class="form-label text-secondary small">Target Close Date</label>
                            <input type="date" name="target_close_date" id="opp_target_close_date" class="form-control bg-dark border-secondary text-white">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="opp_notes" class="form-label text-secondary small">Notes &amp; Deal Context</label>
                        <textarea name="notes" id="opp_notes" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="Details regarding product scope, requirements, or timeline..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold" style="background: var(--primary); border: none;">Save Opportunity</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Move Opportunity Stage Modal -->
<div class="modal fade" id="moveOppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <form action="index.php?route=account_sales/moveStage" method="POST" id="moveOppForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="id" id="move_opp_id" value="">
                <input type="hidden" name="stage" id="move_opp_stage" value="">

                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="moveOppModalTitle"><i class="fa-solid fa-arrows-split-up-and-left me-2 text-primary"></i>Move Opportunity Stage</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Opportunity Title</label>
                        <input type="text" id="move_opp_title" class="form-control bg-secondary text-white border-0" readonly>
                    </div>
                    <div class="d-flex justify-content-around align-items-center p-3 mb-3 rounded bg-body-tertiary border border-secondary">
                        <div class="text-center">
                            <div class="text-secondary small">Current Stage</div>
                            <span id="move_current_stage_badge" class="badge bg-secondary text-uppercase fs-6 mt-1"></span>
                        </div>
                        <div class="text-secondary fs-4"><i class="fa-solid fa-arrow-right-long text-primary"></i></div>
                        <div class="text-center">
                            <div class="text-secondary small">Target Stage</div>
                            <span id="move_target_stage_badge" class="badge bg-primary text-uppercase fs-6 mt-1"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Confirm Move</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-move-opp').on('click', function() {
        const id = $(this).data('opp-id');
        const title = $(this).data('opp-title');
        const currStage = $(this).data('current-stage');
        const targetStage = $(this).data('target-stage');

        $('#move_opp_id').val(id);
        $('#move_opp_stage').val(targetStage);
        $('#move_opp_title').val(title);

        $('#move_current_stage_badge').text(currStage);
        $('#move_target_stage_badge').text(targetStage);

        const modal = new bootstrap.Modal(document.getElementById('moveOppModal'));
        modal.show();
    });
});
</script>
