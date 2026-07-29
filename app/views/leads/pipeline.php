<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <h4 class="text-white mb-0">Lead Pipeline</h4>
    <div class="d-flex gap-2">
        <a href="index.php?route=leads/index" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-table-list me-2"></i>List</a>
        <a href="index.php?route=leads/add" class="btn btn-primary btn-sm" style="background: var(--primary); border: none;"><i class="fa-solid fa-user-plus me-2"></i>Capture Lead</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<style>
    .pipeline-board {
        display: grid;
        grid-template-columns: repeat(6, minmax(200px, 1fr));
        gap: 0.65rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }
    .pipeline-column {
        min-height: 460px;
        background: rgba(0,0,0,0.18);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 0.6rem;
    }
    .pulse-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .pulse-card:hover {
        border-color: rgba(255, 255, 255, 0.25);
    }
    .kebab-btn {
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.6);
        padding: 2px 6px;
        border-radius: 4px;
        transition: color 0.15s ease, background 0.15s ease;
        min-width: 28px;
        min-height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .kebab-btn:hover, .kebab-btn:focus {
        color: #fff;
        background: rgba(255, 255, 255, 0.15);
    }
    @media (max-width: 991.98px) {
        .pipeline-board { display: block; overflow: visible; }
        .pipeline-column { min-height: auto; margin-bottom: 1rem; }
    }
</style>

<div class="pipeline-board">
    <?php foreach ($statuses as $status): ?>
        <?php $items = $pipeline[$status] ?? []; ?>
        <section class="pipeline-column">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-secondary border-opacity-10">
                <h5 class="text-white mb-0 fw-bold" style="font-size: 0.82rem; letter-spacing: 0.5px;"><?php echo strtoupper($status); ?></h5>
                <span class="badge bg-secondary px-2 py-0.5" style="font-size: 0.7rem;"><?php echo count($items); ?></span>
            </div>

            <div class="d-flex flex-column gap-2">
                <?php if (empty($items)): ?>
                    <div class="text-secondary extra-small py-2 text-center">No leads in stage.</div>
                <?php endif; ?>

                <?php foreach ($items as $lead): ?>
                    <?php
                        $priorityTone = [
                            'urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary',
                        ][$lead->priority] ?? 'secondary';
                        $validNextStages = Lead::getValidNextStages($lead->status);
                    ?>
                    <div class="pulse-card p-2 rounded-2" id="lead-card-<?php echo $lead->lead_id; ?>" style="font-size: 0.8rem;">
                        <!-- Row 1: Lead ID badge + Priority badge -->
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-dark border border-secondary text-primary font-monospace px-1.5 py-0.5" style="font-size:0.65rem;">
                                <?php echo htmlspecialchars($lead->lead_code ?: ('LD-' . date('Y') . '-' . sprintf('%05d', $lead->lead_id))); ?>
                            </span>
                            <span class="badge bg-<?php echo $priorityTone; ?>-subtle text-<?php echo $priorityTone; ?> border border-<?php echo $priorityTone; ?>-subtle px-1.5 py-0.5" style="font-size:0.65rem;">
                                <?php echo strtoupper($lead->priority); ?>
                            </span>
                        </div>

                        <!-- Row 2: Lead Name + Kebab menu inline -->
                        <div class="d-flex justify-content-between align-items-center mb-1 gap-1">
                            <a href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>" class="text-white text-decoration-none fw-bold text-truncate me-1" style="font-size:0.83rem;" title="<?php echo htmlspecialchars(trim($lead->first_name . ' ' . ($lead->last_name ?? ''))); ?>">
                                <?php echo htmlspecialchars(trim($lead->first_name . ' ' . ($lead->last_name ?? ''))); ?>
                            </a>
                            <div class="dropdown flex-shrink-0">
                                <button class="kebab-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Stage Actions">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-secondary bg-dark text-white">
                                    <li><h6 class="dropdown-header text-uppercase text-secondary" style="font-size:0.65rem;">Advance Stage</h6></li>
                                    <?php if (!empty($validNextStages)): ?>
                                        <?php foreach ($validNextStages as $nextStage): ?>
                                            <li>
                                                <button type="button" class="dropdown-item text-white btn-move-stage py-1 px-3" 
                                                        data-lead-id="<?php echo $lead->lead_id; ?>"
                                                        data-lead-name="<?php echo htmlspecialchars(trim($lead->first_name . ' ' . ($lead->last_name ?? ''))); ?>"
                                                        data-current-stage="<?php echo $lead->status; ?>"
                                                        data-target-stage="<?php echo $nextStage; ?>">
                                                    <i class="fa-solid <?php echo $nextStage === 'lost' ? 'fa-circle-xmark text-danger' : 'fa-circle-right text-primary'; ?> me-2"></i>
                                                    Move to <strong><?php echo ucfirst($nextStage); ?></strong>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><span class="dropdown-item disabled text-secondary small">Terminal Stage (No Move)</span></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Row 3: Company + Owner + Probability (Condensed single line) -->
                        <div class="d-flex align-items-center justify-content-between text-secondary mb-1" style="font-size:0.75rem;">
                            <span class="text-truncate me-1" style="max-width: 90px;" title="<?php echo htmlspecialchars($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual'); ?>">
                                <i class="fa-solid fa-building me-1 opacity-50"></i><?php echo htmlspecialchars($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual'); ?>
                            </span>
                            <span class="text-truncate me-1" style="max-width: 75px;" title="<?php echo htmlspecialchars($lead->owner_employee_name ?: $lead->assignee_name ?: 'Unassigned'); ?>">
                                <i class="fa-solid fa-user me-1 opacity-50"></i><?php echo htmlspecialchars($lead->owner_employee_name ?: $lead->assignee_name ?: 'Unassigned'); ?>
                            </span>
                            <span class="fw-semibold text-info flex-shrink-0">
                                <?php echo number_format((float) ($lead->probability ?? $lead->conversion_probability), 0); ?>%
                            </span>
                        </div>

                        <!-- Row 4: Value (Tight margin above) -->
                        <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top border-secondary border-opacity-10">
                            <span class="text-success fw-bold" style="font-size:0.83rem;">$<?php echo number_format((float) $lead->lead_value, 2); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<!-- Move Lead Stage Remarks Modal -->
<div class="modal fade" id="moveStageModal" tabindex="-1" aria-labelledby="moveStageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="moveStageModalLabel"><i class="fa-solid fa-arrows-split-up-and-left me-2 text-primary"></i>Move Lead Stage</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="moveStageForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" id="modal_lead_id" name="id" value="">
                <input type="hidden" id="modal_target_status" name="status" value="">

                <div class="modal-body">
                    <div id="modal_alert" class="alert alert-danger d-none mb-3"></div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small mb-1">Lead Name</label>
                        <input type="text" id="modal_lead_name" class="form-control bg-secondary text-white border-0" readonly>
                    </div>

                    <div class="d-flex justify-content-around align-items-center p-3 mb-3 rounded bg-body-tertiary border border-secondary">
                        <div class="text-center">
                            <div class="text-secondary small">Current Stage</div>
                            <span id="modal_current_stage_badge" class="badge bg-secondary text-uppercase fs-6 mt-1"></span>
                        </div>
                        <div class="text-secondary fs-4"><i class="fa-solid fa-arrow-right-long text-primary"></i></div>
                        <div class="text-center">
                            <div class="text-secondary small">Target Stage</div>
                            <span id="modal_target_stage_badge" class="badge bg-primary text-uppercase fs-6 mt-1"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_remarks" class="form-label fw-semibold">Stage Transition Remarks / Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="modal_remarks" name="remarks" rows="3" placeholder="Enter mandatory reason or notes for moving stage..." required></textarea>
                        <div class="invalid-feedback">Remarks are required before confirming the move.</div>
                    </div>

                    <div class="mb-2">
                        <label for="modal_changed_at" class="form-label text-secondary small">Transition Timestamp (Optional)</label>
                        <input type="datetime-local" class="form-control bg-dark text-white border-secondary" id="modal_changed_at" name="changed_at">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnConfirmMove" class="btn btn-primary px-4">
                        <span id="btnConfirmSpinner" class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                        Confirm Move
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="pipelineToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2" id="pipelineToastBody">
                <i class="fa-solid fa-circle-check fs-5"></i>
                <span id="pipelineToastText">Stage updated successfully!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const moveModalEl = document.getElementById('moveStageModal');
    if (!moveModalEl) return;
    const moveModal = new bootstrap.Modal(moveModalEl);
    const form = document.getElementById('moveStageForm');
    const remarksInput = document.getElementById('modal_remarks');
    const alertBox = document.getElementById('modal_alert');
    const toastEl = document.getElementById('pipelineToast');
    const toast = toastEl ? new bootstrap.Toast(toastEl, { delay: 4000 }) : null;

    document.querySelectorAll('.btn-move-stage').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const leadId = this.getAttribute('data-lead-id');
            const leadName = this.getAttribute('data-lead-name');
            const currentStage = this.getAttribute('data-current-stage');
            const targetStage = this.getAttribute('data-target-stage');

            document.getElementById('modal_lead_id').value = leadId;
            document.getElementById('modal_target_status').value = targetStage;
            document.getElementById('modal_lead_name').value = leadName;

            const currBadge = document.getElementById('modal_current_stage_badge');
            currBadge.textContent = currentStage;
            currBadge.className = 'badge text-uppercase fs-6 mt-1 ' + (currentStage === 'lost' ? 'bg-danger' : 'bg-secondary');

            const targetBadge = document.getElementById('modal_target_stage_badge');
            targetBadge.textContent = targetStage;
            targetBadge.className = 'badge text-uppercase fs-6 mt-1 ' + (targetStage === 'lost' ? 'bg-danger' : 'bg-primary');

            document.getElementById('moveStageModalLabel').innerHTML = '<i class="fa-solid fa-arrows-split-up-and-left me-2 text-primary"></i>Move Lead to ' + targetStage.toUpperCase();

            remarksInput.value = '';
            remarksInput.classList.remove('is-invalid');
            alertBox.classList.add('d-none');

            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('modal_changed_at').value = now.toISOString().slice(0, 16);

            moveModal.show();
        });
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        alertBox.classList.add('d-none');
        remarksInput.classList.remove('is-invalid');

        const remarksVal = remarksInput.value.trim();
        if (!remarksVal) {
            remarksInput.classList.add('is-invalid');
            return;
        }

        const confirmBtn = document.getElementById('btnConfirmMove');
        const spinner = document.getElementById('btnConfirmSpinner');
        confirmBtn.disabled = true;
        spinner.classList.remove('d-none');

        const formData = new FormData(form);
        formData.append('ajax', '1');

        try {
            const response = await fetch('index.php?route=leads/moveStage', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                moveModal.hide();
                const targetStatus = document.getElementById('modal_target_status').value;
                const leadId = document.getElementById('modal_lead_id').value;

                if (targetStatus === 'converted') {
                    if (confirm('Lead stage updated to CONVERTED! Would you like to create a linked Customer profile now?')) {
                        window.location.href = 'index.php?route=customers/addFromLead/' + leadId;
                        return;
                    }
                }

                if (toastEl) {
                    toastEl.className = 'toast align-items-center text-white bg-success border-0';
                    document.getElementById('pipelineToastText').textContent = data.message || 'Lead stage updated successfully!';
                    toast.show();
                }
                setTimeout(() => window.location.reload(), 600);
            } else {
                alertBox.textContent = data.message || 'Failed to move lead stage.';
                alertBox.classList.remove('d-none');
            }
        } catch (err) {
            alertBox.textContent = 'An unexpected error occurred. Please try again.';
            alertBox.classList.remove('d-none');
        } finally {
            confirmBtn.disabled = false;
            spinner.classList.add('d-none');
        }
    });
});
</script>
