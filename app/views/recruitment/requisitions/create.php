<?php
/**
 * Raptor CRM — Create Job Requisition Wizard (Sprint 2)
 * -----------------------------------------------------------------------------------
 * Design System: Bootstrap 5.3 + Glassmorphic Cards + Validation
 */
?>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="index.php?route=recruitment/dashboard" class="text-decoration-none">Recruitment</a></li>
                <li class="breadcrumb-item"><a href="index.php?route=recruitment/requisitions" class="text-decoration-none">Requisitions</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-1 text-main">Create Job Requisition</h1>
        <p class="text-muted mb-0">Define organizational headcount demands, role requirements, compensation parameters, and approval routing.</p>
    </div>

    <!-- Wizard Form Card -->
    <form method="POST" action="index.php?route=recruitment_requisition/store" class="needs-validation">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="row g-4">
            <!-- Left Column: Primary Details -->
            <div class="col-lg-8">
                <!-- 1. Position & Role Overview -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
                    <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0 text-main">
                            <i class="fa-solid fa-briefcase text-primary me-2"></i>Position Details
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Job Description Template (Optional Autofill)</label>
                                <select id="templateSelector" class="form-select">
                                    <option value="">-- Choose a standard template to autofill --</option>
                                    <?php foreach ($templates as $t): ?>
                                    <option value="<?php echo (int)$t['template_id']; ?>" data-dept="<?php echo htmlspecialchars($t['department']); ?>">
                                        <?php echo htmlspecialchars($t['title']); ?> (<?php echo htmlspecialchars($t['department']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Position Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="posTitle" class="form-control" placeholder="e.g. Senior Backend PHP Engineer" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Department <span class="text-danger">*</span></label>
                                <select name="department" id="posDept" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Sales">Sales</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Human Resources">Human Resources</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Customer Success">Customer Success</option>
                                    <option value="Operations">Operations</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Employment Type</label>
                                <select name="employment_type" class="form-select">
                                    <option value="full_time">Full-Time Permanent</option>
                                    <option value="part_time">Part-Time</option>
                                    <option value="contract">Contractor</option>
                                    <option value="internship">Internship</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Workplace Model</label>
                                <select name="workplace_arrangement" class="form-select">
                                    <option value="on_site">On-Site</option>
                                    <option value="hybrid" selected>Hybrid</option>
                                    <option value="remote">Fully Remote</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Number of Vacancies <span class="text-danger">*</span></label>
                                <input type="number" name="vacancies" class="form-control" value="1" min="1" max="100" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Hiring Reason</label>
                                <select name="hiring_reason" class="form-select">
                                    <option value="new_position">New Position / Team Expansion</option>
                                    <option value="replacement">Employee Replacement</option>
                                    <option value="expansion">Business Unit Expansion</option>
                                    <option value="project_based">Specific Project Requirement</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Hiring Priority</label>
                                <select name="hiring_priority" class="form-select">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent / Critical</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Job Description, Responsibilities & Skills -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
                    <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0 text-main">
                            <i class="fa-solid fa-file-lines text-primary me-2"></i>Role Specifications
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Job Description / Summary <span class="text-danger">*</span></label>
                                <textarea name="job_description" id="jobDesc" rows="4" class="form-control" placeholder="Detailed summary of role objectives..." required></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Key Responsibilities</label>
                                <textarea name="responsibilities" id="jobResp" rows="3" class="form-control" placeholder="• Architect services&#10;• Collaborate with team..."></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Required Technical & Functional Skills</label>
                                <textarea name="required_skills" id="jobSkills" rows="2" class="form-control" placeholder="e.g. PHP 8.2, MySQL 8.0, REST APIs, Docker, Git"></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Business Justification</label>
                                <textarea name="business_justification" rows="2" class="form-control" placeholder="Strategic justification for headcount authorization..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Compensation & Hierarchy -->
            <div class="col-lg-4">
                <!-- 3. Compensation & Budget -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
                    <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0 text-main">
                            <i class="fa-solid fa-coins text-warning me-2"></i>Compensation
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Min Salary (INR)</label>
                                <input type="number" name="min_salary" class="form-control" placeholder="e.g. 1200000" step="10000">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Max Salary (INR)</label>
                                <input type="number" name="max_salary" class="form-control" placeholder="e.g. 1800000" step="10000">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" name="is_salary_confidential" value="1" id="confSalary" checked>
                                    <label class="form-check-label small" for="confSalary">
                                        Mask salary from non-HR interviewers
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Min Experience (Yrs)</label>
                                <input type="number" name="min_experience_years" class="form-control" value="0.0" step="0.5" min="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Target Hire Date</label>
                                <input type="date" name="target_hire_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Organizational Routing -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
                    <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0 text-main">
                            <i class="fa-solid fa-sitemap text-info me-2"></i>Routing & Approvals
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Hiring Manager <span class="text-danger">*</span></label>
                                <select name="hiring_manager_id" class="form-select" required>
                                    <?php foreach ($managers as $mgr): ?>
                                    <option value="<?php echo (int)$mgr['user_id']; ?>" <?php echo ((int)$mgr['user_id'] === $user_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mgr['name']); ?> (<?php echo htmlspecialchars($mgr['role_name']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Team / Division</label>
                                <select name="team_id" class="form-select">
                                    <option value="">-- Not Assigned to Specific Team --</option>
                                    <?php foreach ($teams as $tm): ?>
                                    <option value="<?php echo (int)$tm['team_id']; ?>"><?php echo htmlspecialchars($tm['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Branch Office / Location</label>
                                <select name="branch_id" class="form-select">
                                    <option value="">-- Primary Headquarters --</option>
                                    <?php foreach ($branches as $br): ?>
                                    <option value="<?php echo (int)$br['branch_id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Card -->
                <div class="card border-0 shadow-sm rounded-4 bg-card">
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <button type="submit" name="submit_for_approval" value="1" class="btn btn-primary py-2 fw-semibold shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i>Submit for Approval
                            </button>
                            <button type="submit" name="submit_for_approval" value="0" class="btn btn-outline-secondary py-2">
                                <i class="fa-solid fa-floppy-disk me-2"></i>Save as Draft
                            </button>
                            <a href="index.php?route=recruitment/requisitions" class="btn btn-link text-muted small text-decoration-none text-center">
                                Cancel & Return
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

