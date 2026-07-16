<?php
$p = $data['profile'];
$role = $_SESSION['user_role'];
$isHrOrAdmin = in_array($role, ['admin', 'hr'], true);
?>

<div class="pulse-card">
    <!-- Profile Header Card -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
        <div class="row align-items-center">
            <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                <div class="avatar bg-primary bg-opacity-10 text-primary mx-auto d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 100px; height: 100px; font-size: 32px; border: 2px solid var(--primary);">
                    <?php 
                        $words = explode(' ', $p->name);
                        echo count($words) > 1 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($p->name, 0, 2));
                    ?>
                </div>
            </div>
            <div class="col-md-6 text-center text-md-start">
                <h3 class="fw-bold text-white mb-1"><?php echo htmlspecialchars($p->name); ?></h3>
                <p class="text-secondary mb-2"><?php echo htmlspecialchars($p->job_title ?? 'Employee'); ?> • <?php echo htmlspecialchars($p->department ?? 'Sales'); ?></p>
                <div class="d-inline-flex gap-2">
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">ID: <?php echo htmlspecialchars($p->employee_code ?? 'N/A'); ?></span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo htmlspecialchars($p->employment_type ?? 'Full-time'); ?></span>
                </div>
            </div>
            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                <button class="btn btn-primary btn-sm px-4 py-2" data-bs-toggle="modal" data-bs-target="#editProfileModal" style="border-radius:8px;">
                    <i class="fa-solid fa-user-pen me-1"></i>Edit Profile
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['profile_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['profile_success']; unset($_SESSION['profile_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['profile_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $_SESSION['profile_error']; unset($_SESSION['profile_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Personal Information -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm p-4 mb-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; height: 100%;">
                <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-user-astronaut text-primary me-2"></i>Personal Details</h5>
                <div class="row">
                    <div class="col-6 mb-3">
                        <small class="text-secondary d-block">Email Address</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->email); ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <small class="text-secondary d-block">Phone Number</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->phone_number ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <small class="text-secondary d-block">Reporting Manager</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->manager_name ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <small class="text-secondary d-block">Date of Joining</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->date_of_joining ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <small class="text-secondary d-block">Blood Group</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->blood_group ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <small class="text-secondary d-block">Emergency Contact</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->emergency_contact ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-12 mb-3">
                        <small class="text-secondary d-block">Address</small>
                        <span class="text-white small fw-semibold"><?php echo nl2br(htmlspecialchars($p->address ?? 'N/A')); ?></span>
                    </div>
                    <div class="col-12 mb-3">
                        <small class="text-secondary d-block">Skills</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->skills ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-12">
                        <small class="text-secondary d-block">Total Experience</small>
                        <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->experience_years ?? '0.0'); ?> Years</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Financial & Sensitive details (Admin/HR Only) -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; height: 100%;">
                <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-lock text-danger me-2"></i>Sensitive / Payroll Details (HR/Admin Only)</h5>
                <?php if ($isHrOrAdmin): ?>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <small class="text-secondary d-block">Salary (Monthly)</small>
                            <span class="text-white small fw-semibold">₹<?php echo number_format((float)($p->salary ?? 0.0), 2); ?></span>
                        </div>
                        <div class="col-6 mb-3">
                            <small class="text-secondary d-block">PAN Number</small>
                            <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->pan_number ?? 'N/A'); ?></span>
                        </div>
                        <div class="col-6 mb-3">
                            <small class="text-secondary d-block">Aadhaar Number</small>
                            <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->aadhaar_number ?? 'N/A'); ?></span>
                        </div>
                        <div class="col-6 mb-3">
                            <small class="text-secondary d-block">UAN (PF Number)</small>
                            <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->uan ?? 'N/A'); ?></span>
                        </div>
                        <div class="col-6 mb-3">
                            <small class="text-secondary d-block">ESIC Number</small>
                            <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->esic_number ?? 'N/A'); ?></span>
                        </div>
                        <div class="col-12 mt-3 pt-3 border-top border-secondary">
                            <h6 class="text-white fw-bold mb-3"><i class="fa-solid fa-building-columns text-primary me-2"></i>Bank Details</h6>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <small class="text-secondary d-block">Bank Name</small>
                                    <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->bank_name ?? 'N/A'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-secondary d-block">Account Holder</small>
                                    <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->account_holder_name ?? 'N/A'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-secondary d-block">Account Number</small>
                                    <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->account_number ?? 'N/A'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-secondary d-block">IFSC Code</small>
                                    <span class="text-white small fw-semibold"><?php echo htmlspecialchars($p->ifsc_code ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="text-secondary fs-1 mb-3"><i class="fa-solid fa-lock"></i></div>
                        <p class="text-secondary mb-0">You do not have permissions to view financial and identification records.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-white mb-0" id="editProfileModalLabel">Edit Profile Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=hrms/editProfile/<?php echo $p->user_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label for="phone_number" class="form-label text-secondary small fw-bold">Phone Number</label>
                        <input type="text" class="form-control border-secondary bg-dark text-white" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($p->phone_number ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="emergency_contact" class="form-label text-secondary small fw-bold">Emergency Contact</label>
                        <input type="text" class="form-control border-secondary bg-dark text-white" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($p->emergency_contact ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="blood_group" class="form-label text-secondary small fw-bold">Blood Group</label>
                        <input type="text" class="form-control border-secondary bg-dark text-white" id="blood_group" name="blood_group" value="<?php echo htmlspecialchars($p->blood_group ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="experience_years" class="form-label text-secondary small fw-bold">Experience (Years)</label>
                        <input type="number" step="0.1" class="form-control border-secondary bg-dark text-white" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($p->experience_years ?? '0.0'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="skills" class="form-label text-secondary small fw-bold">Skills</label>
                        <input type="text" class="form-control border-secondary bg-dark text-white" id="skills" name="skills" value="<?php echo htmlspecialchars($p->skills ?? ''); ?>" placeholder="e.g. PHP, HTML, CSS, MySQL">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label text-secondary small fw-bold">Address</label>
                        <textarea class="form-control border-secondary bg-dark text-white" id="address" name="address" rows="3"><?php echo htmlspecialchars($p->address ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
