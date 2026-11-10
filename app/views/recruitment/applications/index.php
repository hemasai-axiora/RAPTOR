<?php
/**
 * Raptor CRM — ATS Kanban Pipeline View (Sprint 4)
 * -------------------------------------------------------------------------
 * Interactive drag-and-drop recruitment pipeline board with optimistic UI,
 * stage validation, and CSRF protection.
 */
?>
<div class="container-fluid py-4">
    <!-- Top Header & Job Filter -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
                    <li class="breadcrumb-item active" aria-current="page">ATS Application Pipeline</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-0">Recruitment Pipeline Kanban</h2>
            <p class="text-muted small mb-0">Drag and drop candidate cards across stages to advance applications.</p>
        </div>

        <div class="d-flex align-items-center gap-3">
            <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                <input type="hidden" name="route" value="recruitment_application/index">
                <label class="small fw-semibold text-nowrap mb-0">Filter by Vacancy:</label>
                <select name="job_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 260px;">
                    <option value="">All Active Vacancies</option>
                    <?php foreach ($activeJobs as $job): ?>
                        <option value="<?= (int)$job['job_id'] ?>" <?= ($selected_job_id === (int)$job['job_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($job['title']) ?> (<?= htmlspecialchars($job['job_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <a href="index.php?route=recruitment_candidate/create" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus-fill me-1"></i> Add Candidate
            </a>
        </div>
    </div>

    <!-- Kanban Columns Wrapper -->
    <div class="kanban-board-wrapper d-flex gap-3 overflow-auto pb-4" style="min-height: 720px;">
        <?php foreach ($board as $colKey => $col): ?>
            <div class="kanban-column card border-0 bg-light shadow-sm flex-shrink-0" 
                 data-stage="<?= htmlspecialchars($colKey) ?>" 
                 style="width: 320px; min-width: 320px; display: flex; flex-direction: column;">
                
                <!-- Column Header -->
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <?= htmlspecialchars($col['title']) ?>
                    </h6>
                    <span class="badge bg-secondary rounded-pill kanban-col-count"><?= (int)$col['count'] ?></span>
                </div>

                <!-- Column Drop Zone / Card List -->
                <div class="card-body p-2 kanban-dropzone flex-grow-1" 
                     ondragover="kanbanDragOver(event)" 
                     ondragleave="kanbanDragLeave(event)" 
                     ondrop="kanbanDrop(event, '<?= htmlspecialchars($colKey) ?>')">
                    
                    <?php if (empty($col['cards'])): ?>
                        <div class="empty-column-placeholder text-center text-muted py-4 small">
                            <i class="bi bi-inbox d-block fs-3 mb-1 text-secondary opacity-50"></i>
                            No candidates in this stage
                        </div>
                    <?php else: ?>
                        <?php foreach ($col['cards'] as $card): ?>
                            <div class="kanban-card card border shadow-xs mb-3 bg-white" 
                                 id="app-card-<?= (int)$card['application_id'] ?>" 
                                 draggable="true" 
                                 ondragstart="kanbanDragStart(event, <?= (int)$card['application_id'] ?>)" 
                                 style="cursor: grab;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <a href="index.php?route=recruitment_candidate/show/<?= $card['candidate_id'] ?>" class="fw-bold text-dark text-decoration-none">
                                            <?= htmlspecialchars($card['candidate_name']) ?>
                                        </a>
                                        <span class="badge bg-light text-muted border small"><?= (float)$card['total_experience'] ?>y</span>
                                    </div>

                                    <div class="small text-muted mb-2">
                                        <?= htmlspecialchars($card['current_designation']) ?> &bull; <?= htmlspecialchars($card['current_company']) ?>
                                    </div>

                                    <div class="mb-2">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                            <?= htmlspecialchars($card['job_title']) ?>
                                        </span>
                                    </div>

                                    <!-- Primary Skills Badges -->
                                    <div class="mb-2">
                                        <?php foreach (array_slice($card['primary_skills'], 0, 3) as $skill): ?>
                                            <span class="badge bg-light text-secondary border me-1 small"><?= htmlspecialchars($skill) ?></span>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Card Footer -->
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top small text-muted">
                                        <span>
                                            <i class="bi bi-clock me-1"></i><?= (int)$card['days_in_stage'] ?>d in stage
                                        </span>
                                        <?php if ($card['match_score_advisory'] !== null): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <?= (int)$card['match_score_advisory'] ?>% Match
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
window.RAPTOR_CSRF_TOKEN = <?= json_encode($csrf_token) ?>;

let draggedAppId = null;
let draggedCardElement = null;
let originalContainer = null;

function kanbanDragStart(e, applicationId) {
    draggedAppId = applicationId;
    draggedCardElement = document.getElementById('app-card-' + applicationId);
    originalContainer = draggedCardElement ? draggedCardElement.parentElement : null;
    e.dataTransfer.setData('text/plain', applicationId);
    e.dataTransfer.effectAllowed = 'move';
}

function kanbanDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const dropzone = e.currentTarget;
    dropzone.classList.add('bg-primary-subtle');
}

function kanbanDragLeave(e) {
    const dropzone = e.currentTarget;
    dropzone.classList.remove('bg-primary-subtle');
}

function kanbanDrop(e, newStage) {
    e.preventDefault();
    const dropzone = e.currentTarget;
    dropzone.classList.remove('bg-primary-subtle');

    if (!draggedAppId || !draggedCardElement) {
        return;
    }

    const targetColumn = dropzone;
    const placeholder = targetColumn.querySelector('.empty-column-placeholder');
    if (placeholder) {
        placeholder.style.display = 'none';
    }

    // Optimistic DOM update
    targetColumn.appendChild(draggedCardElement);
    updateColumnCounts();

    // Send stage update AJAX request
    fetch('index.php?route=recruitment_application/stage/' + draggedAppId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.RAPTOR_CSRF_TOKEN
        },
        body: JSON.stringify({
            application_id: draggedAppId,
            to_stage: newStage,
            csrf_token: window.RAPTOR_CSRF_TOKEN
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            // Revert back on failure
            if (originalContainer) {
                originalContainer.appendChild(draggedCardElement);
                updateColumnCounts();
            }
            if (typeof RaptorToast !== 'undefined') {
                RaptorToast.error((data.error && data.error.message) || 'Stage transition rejected.');
            } else {
                alert((data.error && data.error.message) || 'Stage transition rejected.');
            }
        } else {
            if (typeof RaptorToast !== 'undefined') {
                RaptorToast.success('Candidate advanced to ' + newStage.replace('_', ' ') + ' successfully.');
            }
        }
    })
    .catch(err => {
        if (originalContainer) {
            originalContainer.appendChild(draggedCardElement);
            updateColumnCounts();
        }
        if (typeof RaptorToast !== 'undefined') {
            RaptorToast.error('Network error during stage advancement.');
        } else {
            alert('Network error during stage advancement.');
        }
    })
    .finally(() => {
        draggedAppId = null;
        draggedCardElement = null;
        originalContainer = null;
    });
}

function updateColumnCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const dropzone = col.querySelector('.kanban-dropzone');
        const cards = dropzone.querySelectorAll('.kanban-card');
        const countBadge = col.querySelector('.kanban-col-count');
        if (countBadge) {
            countBadge.textContent = cards.length;
        }
        const placeholder = dropzone.querySelector('.empty-column-placeholder');
        if (placeholder) {
            placeholder.style.display = cards.length === 0 ? 'block' : 'none';
        }
    });
}
</script>

