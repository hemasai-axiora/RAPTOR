<?php if (!empty($_SESSION['user_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['user_error']); unset($_SESSION['user_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['user_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['user_success']); unset($_SESSION['user_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="pulse-card">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="text-white mb-0">Employee Management</h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php?route=users/export" class="btn btn-outline-success btn-sm px-3 py-2" style="border-radius: 8px;" title="Download all employee details as CSV">
                <i class="fa-solid fa-file-csv me-2"></i>Download Details
            </a>
            <button class="btn btn-outline-light btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#bulkUploadModal" style="border-radius: 8px; border-color: rgba(255,255,255,0.15);">
                <i class="fa-solid fa-cloud-arrow-up me-2"></i>Bulk Upload
            </button>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#addUserModal" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-user-plus me-2"></i>Add Employee
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="row g-2 mb-3 p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1);">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="emp-search-input" class="form-control bg-dark border-secondary text-white" placeholder="Search Emp ID, Name, Email, Job Title...">
            </div>
        </div>
        <div class="col-md-3">
            <select id="emp-status-filter" class="form-select bg-dark border-secondary text-white">
                <option value="">Filter by Status: All</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-3">
            <select id="emp-location-filter" class="form-select bg-dark border-secondary text-white">
                <option value="">Filter by Location: All</option>
                <option value="Office">Office</option>
                <option value="Remote">Remote</option>
                <option value="Hybrid">Hybrid</option>
            </select>
        </div>
        <div class="col-md-2">
            <select id="emp-dept-filter" class="form-select bg-dark border-secondary text-white">
                <option value="">Dept: All</option>
                <?php if (!empty($departments)): ?>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary" id="users-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>EmpID</th>
                    <th>Name</th>
                    <th>Email Address</th>
                    <th>Job Title</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Joining Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);"
                        data-status="<?php echo (($user->status ?? 'active') === 'active') ? 'Active' : 'Inactive'; ?>"
                        data-location="<?php echo htmlspecialchars($user->work_location ?? 'Office'); ?>"
                        data-department="<?php echo htmlspecialchars($user->department ?? 'Sales'); ?>">
                        <td class="text-white font-monospace"><?php echo htmlspecialchars($user->employee_code ?? 'SYS-ADMIN'); ?></td>
                        <td class="font-weight-bold">
                            <a href="index.php?route=hrms/profile/<?php echo $user->user_id; ?>" 
                               style="color: var(--primary); font-weight: 600; text-decoration: none;"
                               onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                <?php echo htmlspecialchars($user->name); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($user->email); ?></td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                <?php echo htmlspecialchars($user->job_title ?? strtoupper($user->role_name)); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user->work_location ?? 'Office'); ?></td>
                        <td>
                            <?php if (($user->status ?? 'active') === 'active'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle me-1" style="font-size: 8px;"></i>Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-circle me-1" style="font-size: 8px;"></i>Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo !empty($user->date_of_joining) ? date('Y-m-d', strtotime($user->date_of_joining)) : date('Y-m-d', strtotime($user->created_at)); ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">

                                <button class="btn btn-outline-info btn-sm btn-edit-user" 
                                        data-id="<?php echo $user->user_id; ?>"
                                        data-name="<?php echo htmlspecialchars($user->name); ?>"
                                        data-email="<?php echo htmlspecialchars($user->email); ?>"
                                        data-role="<?php echo $user->role_id; ?>"
                                        data-status="<?php echo $user->status; ?>"
                                        data-employee-code="<?php echo htmlspecialchars($user->employee_code ?? ''); ?>"
                                        data-phone-number="<?php echo htmlspecialchars($user->phone_number ?? ''); ?>"
                                        data-salary="<?php echo (in_array($_SESSION['user_role'], ['admin', 'hr', 'finance'], true)) ? htmlspecialchars($user->salary ?? '') : ''; ?>"
                                        data-date-of-joining="<?php echo htmlspecialchars($user->date_of_joining ?? $user->hire_date ?? ''); ?>"
                                        data-date-of-birth="<?php echo htmlspecialchars($user->date_of_birth ?? ''); ?>"
                                        data-job-title="<?php echo htmlspecialchars($user->job_title ?? ''); ?>"
                                        data-department="<?php echo htmlspecialchars($user->department ?? 'Sales'); ?>"
                                        data-employment-type="<?php echo htmlspecialchars($user->employment_type ?? 'Full-time'); ?>"
                                        data-work-location="<?php echo htmlspecialchars($user->work_location ?? 'Office'); ?>"
                                        data-bio="<?php echo htmlspecialchars($user->bio ?? ''); ?>"
                                        data-emergency-contact="<?php echo htmlspecialchars($user->emergency_contact ?? ''); ?>"
                                        data-pan-number="<?php echo (in_array($_SESSION['user_role'], ['admin', 'hr', 'finance'], true)) ? htmlspecialchars($user->pan_number ?? '') : ''; ?>"
                                        data-aadhaar-number="<?php echo (in_array($_SESSION['user_role'], ['admin', 'hr', 'finance'], true)) ? htmlspecialchars($user->aadhaar_number ?? '') : ''; ?>"
                                        data-uan="<?php echo htmlspecialchars($user->uan ?? ''); ?>"
                                        data-pf-applicable="<?php echo htmlspecialchars($user->pf_applicable ?? 0); ?>"
                                        data-esic-number="<?php echo htmlspecialchars($user->esic_number ?? ''); ?>"
                                        data-pay-grade="<?php echo htmlspecialchars($user->pay_grade ?? ''); ?>"
                                        data-force-password-reset="<?php echo htmlspecialchars($user->force_password_reset ?? 0); ?>"
                                        data-account-holder-name="<?php echo htmlspecialchars($user->account_holder_name ?? ''); ?>"
                                        data-bank-name="<?php echo htmlspecialchars($user->bank_name ?? ''); ?>"
                                        data-account-number="<?php echo htmlspecialchars($user->account_number ?? ''); ?>"
                                        data-ifsc-code="<?php echo htmlspecialchars($user->ifsc_code ?? ''); ?>"
                                        data-branch-name="<?php echo htmlspecialchars($user->branch_name ?? ''); ?>"
                                        data-account-type="<?php echo htmlspecialchars($user->account_type ?? 'Savings'); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal">
                                    <i class="fa-solid fa-user-pen"></i> Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addUserModalLabel">Add Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=users/add" method="POST" enctype="multipart/form-data" class="needs-validation-account">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs border-secondary mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active text-white border-secondary bg-dark" id="add-personal-tab" data-bs-toggle="tab" data-bs-target="#add-personal" type="button" role="tab" aria-controls="add-personal" aria-selected="true">Personal & Job</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-white border-secondary bg-dark" id="add-bank-tab" data-bs-toggle="tab" data-bs-target="#add-bank" type="button" role="tab" aria-controls="add-bank" aria-selected="false">Bank Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-white border-secondary bg-dark" id="add-payroll-tab" data-bs-toggle="tab" data-bs-target="#add-payroll" type="button" role="tab" aria-controls="add-payroll" aria-selected="false">Payroll & Compliance</button>
                        </li>
                    </ul>

                    <!-- Tab panes -->
                    <div class="tab-content">
                        <!-- Personal & Job Tab -->
                        <div class="tab-pane fade show active" id="add-personal" role="tabpanel" aria-labelledby="add-personal-tab">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Full Name *</label>
                                    <input type="text" name="name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z\s'-]{2,}$" title="Please enter a valid full name (alphabetic, spaces, hyphens, and apostrophes allowed, min 2 characters)." required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Email Address *</label>
                                    <input type="email" name="email" class="form-control bg-dark border-secondary text-white" pattern="^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$" title="Please enter a valid email address with a domain suffix (e.g. user@example.com)." required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Employee ID (Manual Entry) *</label>
                                    <input type="text" name="employee_code" class="form-control bg-dark border-secondary text-white" placeholder="e.g. EMP1001" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Phone Number</label>
                                    <input type="text" name="phone_number" class="form-control bg-dark border-secondary text-white" pattern="^[6-9]\d{9}$" maxlength="10" title="Please enter a valid 10-digit phone number starting with 6-9.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Job Title</label>
                                    <input type="text" name="job_title" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Sales Executive" pattern="^[A-Za-z\s&/]{2,}$" title="Please enter a valid job title (alphabetic, spaces, & and / allowed, min 2 characters).">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Department</label>
                                    <select name="department" class="form-select bg-dark border-secondary text-white" required>
                                        <option value="Sales">Sales</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Engineering">Engineering</option>
                                        <option value="HR">HR</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Operations">Operations</option>
                                        <option value="Executive">Executive</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Date of Joining</label>
                                    <input type="date" name="date_of_joining" class="form-control bg-dark border-secondary text-white">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control bg-dark border-secondary text-white" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">System Role *</label>
                                    <select name="role_id" class="form-select bg-dark border-secondary text-white" required>
                                        <option value="">-- Select Role --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role->role_id; ?>"><?php echo strtoupper($role->role_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Employment Type</label>
                                    <select name="employment_type" class="form-select bg-dark border-secondary text-white">
                                        <option value="Full-time">Full-time</option>
                                        <option value="Part-time">Part-time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Intern">Intern</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Work Location</label>
                                    <select name="work_location" class="form-select bg-dark border-secondary text-white">
                                        <option value="Office">Office</option>
                                        <option value="Remote">Remote</option>
                                        <option value="Hybrid">Hybrid</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Profile Photo</label>
                                    <input type="file" name="profile_photo" class="form-control bg-dark border-secondary text-white" accept="image/*">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Emergency Contact</label>
                                    <input type="text" name="emergency_contact" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 9876543210" pattern="^[6-9]\d{9}$" maxlength="10" title="Please enter a valid 10-digit emergency contact phone number starting with 6-9.">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-secondary">Employee Bio / Notes</label>
                                    <textarea name="bio" class="form-control bg-dark border-secondary text-white" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Initial Password *</label>
                                    <input type="password" name="password" class="form-control bg-dark border-secondary text-white" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Status</label>
                                    <select name="status" class="form-select bg-dark border-secondary text-white">
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="force_password_reset" id="add_force_pwd" value="1">
                                        <label class="form-check-label text-secondary" for="add_force_pwd">
                                            Force Password Reset on First Login
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details Tab -->
                        <div class="tab-pane fade" id="add-bank" role="tabpanel" aria-labelledby="add-bank-tab">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Account Holder Name</label>
                                    <input type="text" name="account_holder_name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z\s'-]{2,}$" title="Please enter a valid account holder name.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z\s&.]{2,}$" title="Please enter a valid bank name (letters, spaces, & and . allowed).">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Account Number</label>
                                    <input type="password" name="account_number" class="form-control bg-dark border-secondary text-white main-account-num" pattern="^\d+$" title="Account number must be numeric.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Confirm Account Number</label>
                                    <input type="text" name="confirm_account_number" class="form-control bg-dark border-secondary text-white confirm-account-num" pattern="^\d+$" title="Confirm Account Number must be numeric and match Account Number.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">IFSC Code</label>
                                    <input type="text" name="ifsc_code" class="form-control bg-dark border-secondary text-white" pattern="^[A-Z]{4}0[A-Z0-9]{6}$" title="Please enter a valid IFSC code (e.g. SBIN0001234).">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Branch Name / Address</label>
                                    <input type="text" name="branch_name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z0-9\s,.\#\-]{3,}$" title="Please enter a valid branch name/address.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Account Type</label>
                                    <select name="account_type" class="form-select bg-dark border-secondary text-white">
                                        <option value="Savings">Savings</option>
                                        <option value="Current">Current</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Payroll & Compliance Tab -->
                        <div class="tab-pane fade" id="add-payroll" role="tabpanel" aria-labelledby="add-payroll-tab">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">PAN Number</label>
                                    <input type="text" name="pan_number" class="form-control bg-dark border-secondary text-white" pattern="^[A-Z]{5}[0-9]{4}[A-Z]$" title="Please enter a valid 10-character PAN number (e.g. ABCDE1234F)." oninput="this.value = this.value.toUpperCase()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Aadhaar Number</label>
                                    <input type="text" name="aadhaar_number" class="form-control bg-dark border-secondary text-white" pattern="^\d{12}$" maxlength="12" title="Please enter a valid 12-digit Aadhaar number.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">UAN (PF)</label>
                                    <input type="text" name="uan" class="form-control bg-dark border-secondary text-white" pattern="^\d{12}$" maxlength="12" title="Please enter a valid 12-digit UAN number.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">ESIC Number</label>
                                    <input type="text" name="esic_number" class="form-control bg-dark border-secondary text-white" pattern="^\d{17}$" maxlength="17" title="Please enter a valid 17-digit ESIC number.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Salary / CTC (Monthly)</label>
                                    <input type="number" step="0.01" min="0.01" name="salary" class="form-control bg-dark border-secondary text-white" placeholder="0.00" title="Salary must be a positive number greater than 0.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Pay Grade / Salary Band</label>
                                    <select name="pay_grade" class="form-select bg-dark border-secondary text-white">
                                        <option value="">-- None --</option>
                                        <option value="Band A">Band A</option>
                                        <option value="Band B">Band B</option>
                                        <option value="Band C">Band C</option>
                                        <option value="Band D">Band D</option>
                                        <option value="Band E">Band E</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mt-4">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="pf_applicable" id="add_pf_applicable" value="1">
                                        <label class="form-check-label text-secondary" for="add_pf_applicable">PF Applicable</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;">Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="editUserModalLabel">Edit Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=users/edit" method="POST" enctype="multipart/form-data" class="needs-validation-account">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="user_id" id="edit_id">
                
                <div class="modal-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs border-secondary mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active text-white border-secondary bg-dark" id="edit-personal-tab" data-bs-toggle="tab" data-bs-target="#edit-personal" type="button" role="tab" aria-controls="edit-personal" aria-selected="true">Personal & Job</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-white border-secondary bg-dark" id="edit-bank-tab" data-bs-toggle="tab" data-bs-target="#edit-bank" type="button" role="tab" aria-controls="edit-bank" aria-selected="false">Bank Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-white border-secondary bg-dark" id="edit-payroll-tab" data-bs-toggle="tab" data-bs-target="#edit-payroll" type="button" role="tab" aria-controls="edit-payroll" aria-selected="false">Payroll & Compliance</button>
                        </li>
                    </ul>

                    <!-- Tab panes -->
                    <div class="tab-content">
                        <!-- Personal & Job Tab -->
                        <div class="tab-pane fade show active" id="edit-personal" role="tabpanel" aria-labelledby="edit-personal-tab">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Full Name *</label>
                                    <input type="text" name="name" id="edit_name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z\s'-]{2,}$" title="Please enter a valid full name (alphabetic, spaces, hyphens, and apostrophes allowed, min 2 characters)." required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Email Address *</label>
                                    <input type="email" name="email" id="edit_email" class="form-control bg-dark border-secondary text-white" pattern="^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$" title="Please enter a valid email address with a domain suffix (e.g. user@example.com)." required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Employee ID (Manual Entry) *</label>
                                    <input type="text" name="employee_code" id="edit_employee_code" class="form-control bg-dark border-secondary text-white" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Phone Number</label>
                                    <input type="text" name="phone_number" id="edit_phone_number" class="form-control bg-dark border-secondary text-white" pattern="^[6-9]\d{9}$" maxlength="10" title="Please enter a valid 10-digit phone number starting with 6-9.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Job Title</label>
                                    <input type="text" name="job_title" id="edit_job_title" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z\s&/]{2,}$" title="Please enter a valid job title (alphabetic, spaces, & and / allowed, min 2 characters).">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Department</label>
                                    <select name="department" id="edit_department" class="form-select bg-dark border-secondary text-white" required>
                                        <option value="Sales">Sales</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Engineering">Engineering</option>
                                        <option value="HR">HR</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Operations">Operations</option>
                                        <option value="Executive">Executive</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Date of Joining</label>
                                    <input type="date" name="date_of_joining" id="edit_date_of_joining" class="form-control bg-dark border-secondary text-white">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control bg-dark border-secondary text-white" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">System Role *</label>
                                    <select name="role_id" id="edit_role" class="form-select bg-dark border-secondary text-white" required>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role->role_id; ?>"><?php echo strtoupper($role->role_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Employment Type</label>
                                    <select name="employment_type" id="edit_employment_type" class="form-select bg-dark border-secondary text-white">
                                        <option value="Full-time">Full-time</option>
                                        <option value="Part-time">Part-time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Intern">Intern</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Work Location</label>
                                    <select name="work_location" id="edit_work_location" class="form-select bg-dark border-secondary text-white">
                                        <option value="Office">Office</option>
                                        <option value="Remote">Remote</option>
                                        <option value="Hybrid">Hybrid</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Profile Photo (Leave blank to keep current)</label>
                                    <input type="file" name="profile_photo" class="form-control bg-dark border-secondary text-white" accept="image/*">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Emergency Contact</label>
                                    <input type="text" name="emergency_contact" id="edit_emergency_contact" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 9876543210" pattern="^[6-9]\d{9}$" maxlength="10" title="Please enter a valid 10-digit emergency contact phone number starting with 6-9.">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-secondary">Employee Bio / Notes</label>
                                    <textarea name="bio" id="edit_bio" class="form-control bg-dark border-secondary text-white" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">New Password (Leave blank to keep current)</label>
                                    <input type="password" name="password" id="edit_password" class="form-control bg-dark border-secondary text-white">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Status</label>
                                    <select name="status" id="edit_status" class="form-select bg-dark border-secondary text-white">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="force_password_reset" id="edit_force_password_reset" value="1">
                                        <label class="form-check-label text-secondary" for="edit_force_password_reset">
                                            Force Password Reset on First Login
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details Tab -->
                        <div class="tab-pane fade" id="edit-bank" role="tabpanel" aria-labelledby="edit-bank-tab">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Account Holder Name</label>
                                    <input type="text" name="account_holder_name" id="edit_account_holder_name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z\s'-]{2,}$" title="Please enter a valid account holder name.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Bank Name</label>
                                    <input type="text" name="bank_name" id="edit_bank_name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z\s&.]{2,}$" title="Please enter a valid bank name (letters, spaces, & and . allowed).">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Account Number</label>
                                    <input type="password" name="account_number" id="edit_account_number" class="form-control bg-dark border-secondary text-white main-account-num" pattern="^\d+$" title="Account number must be numeric.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Confirm Account Number</label>
                                    <input type="text" name="confirm_account_number" id="edit_confirm_account_number" class="form-control bg-dark border-secondary text-white confirm-account-num" pattern="^\d+$" title="Confirm Account Number must be numeric and match Account Number.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">IFSC Code</label>
                                    <input type="text" name="ifsc_code" id="edit_ifsc_code" class="form-control bg-dark border-secondary text-white" pattern="^[A-Z]{4}0[A-Z0-9]{6}$" title="Please enter a valid IFSC code (e.g. SBIN0001234).">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Branch Name / Address</label>
                                    <input type="text" name="branch_name" id="edit_branch_name" class="form-control bg-dark border-secondary text-white" pattern="^[A-Za-z0-9\s,.\#\-]{3,}$" title="Please enter a valid branch name/address.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Account Type</label>
                                    <select name="account_type" id="edit_account_type" class="form-select bg-dark border-secondary text-white">
                                        <option value="Savings">Savings</option>
                                        <option value="Current">Current</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Payroll & Compliance Tab -->
                        <div class="tab-pane fade" id="edit-payroll" role="tabpanel" aria-labelledby="edit-payroll-tab">
                            <div class="row g-3">
                                <?php if (in_array($_SESSION['user_role'], ['admin', 'hr', 'finance'], true)): ?>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary">PAN Number</label>
                                        <input type="text" name="pan_number" id="edit_pan_number" class="form-control bg-dark border-secondary text-white" pattern="^[A-Z]{5}[0-9]{4}[A-Z]$" title="Please enter a valid 10-character PAN number (e.g. ABCDE1234F)." oninput="this.value = this.value.toUpperCase()">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary">Aadhaar Number</label>
                                        <input type="text" name="aadhaar_number" id="edit_aadhaar_number" class="form-control bg-dark border-secondary text-white" pattern="^\d{12}$" maxlength="12" title="Please enter a valid 12-digit Aadhaar number.">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary">Salary / CTC (Monthly)</label>
                                        <input type="number" step="0.01" min="0.01" name="salary" id="edit_salary" class="form-control bg-dark border-secondary text-white" placeholder="0.00" title="Salary must be a positive number greater than 0.">
                                    </div>
                                <?php else: ?>
                                    <!-- Keep fields hidden but submit current values so they don't get cleared -->
                                    <input type="hidden" name="pan_number" id="edit_pan_number">
                                    <input type="hidden" name="aadhaar_number" id="edit_aadhaar_number">
                                    <input type="hidden" name="salary" id="edit_salary">
                                    <div class="col-12 text-warning small mb-3">
                                        <i class="fa-solid fa-lock me-1"></i> Highly sensitive compliance fields (PAN, Aadhaar, Salary) are hidden from your role.
                                    </div>
                                <?php endif; ?>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">UAN (PF)</label>
                                    <input type="text" name="uan" id="edit_uan" class="form-control bg-dark border-secondary text-white" pattern="^\d{12}$" maxlength="12" title="Please enter a valid 12-digit UAN number.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">ESIC Number</label>
                                    <input type="text" name="esic_number" id="edit_esic_number" class="form-control bg-dark border-secondary text-white" pattern="^\d{17}$" maxlength="17" title="Please enter a valid 17-digit ESIC number.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary">Pay Grade / Salary Band</label>
                                    <select name="pay_grade" id="edit_pay_grade" class="form-select bg-dark border-secondary text-white">
                                        <option value="">-- None --</option>
                                        <option value="Band A">Band A</option>
                                        <option value="Band B">Band B</option>
                                        <option value="Band C">Band C</option>
                                        <option value="Band D">Band D</option>
                                        <option value="Band E">Band E</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mt-4">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="pf_applicable" id="edit_pf_applicable" value="1">
                                        <label class="form-check-label text-secondary" for="edit_pf_applicable">PF Applicable</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-deactivate-user-modal">
                            <i class="fa-solid fa-user-slash me-2"></i>Deactivate
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Deactivate User Hidden Form -->
<form id="deactivate-user-form" action="" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
</form>

<script>
$(document).ready(function() {
    // Deactivate User from Modal
    $('.btn-deactivate-user-modal').on('click', function() {
        const id = $('#edit_id').val();
        if (confirm('Deactivate this employee account? No data will be deleted.')) {
            const form = $('#deactivate-user-form');
            form.attr('action', 'index.php?route=users/deactivate/' + id);
            form.submit();
        }
    });

    // Populate Edit Modal
    $('.btn-edit-user').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const email = $(this).data('email');
        const role = $(this).data('role');
        const status = $(this).data('status');

        $('#edit_id').val(id);
        $('#edit_name').val(name);
        $('#edit_email').val(email);
        $('#edit_role').val(role);
        $('#edit_status').val(status);
        $('#edit_password').val('');
        
        // Show/hide Deactivate button based on current user session
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;
        if (id == currentUserId) {
            $('.btn-deactivate-user-modal').hide();
        } else {
            $('.btn-deactivate-user-modal').show();
        }

        // Populate new fields
        $('#edit_employee_code').val($(this).data('employee-code'));
        $('#edit_phone_number').val($(this).data('phone-number'));
        $('#edit_salary').val($(this).data('salary'));
        $('#edit_date_of_joining').val($(this).data('date-of-joining'));
        $('#edit_date_of_birth').val($(this).data('date-of-birth'));
        $('#edit_job_title').val($(this).data('job-title'));
        $('#edit_department').val($(this).data('department'));
        $('#edit_employment_type').val($(this).data('employment-type'));
        $('#edit_work_location').val($(this).data('work-location'));
        $('#edit_bio').val($(this).data('bio'));
        $('#edit_emergency_contact').val($(this).data('emergency-contact'));
        $('#edit_pan_number').val($(this).data('pan-number'));
        $('#edit_aadhaar_number').val($(this).data('aadhaar-number'));
        $('#edit_uan').val($(this).data('uan'));
        $('#edit_pf_applicable').prop('checked', $(this).data('pf-applicable') == 1);
        $('#edit_esic_number').val($(this).data('esic-number'));
        $('#edit_pay_grade').val($(this).data('pay-grade'));
        $('#edit_force_password_reset').prop('checked', $(this).data('force-password-reset') == 1);
        
        // Bank details
        $('#edit_account_holder_name').val($(this).data('account-holder-name'));
        $('#edit_bank_name').val($(this).data('bank-name'));
        $('#edit_account_number').val($(this).data('account-number'));
        $('#edit_confirm_account_number').val($(this).data('account-number'));
        $('#edit_ifsc_code').val($(this).data('ifsc-code'));
        $('#edit_branch_name').val($(this).data('branch-name'));
        $('#edit_account_type').val($(this).data('account-type'));
    });

    // Client-side validation for account number matching
    $('.needs-validation-account').on('submit', function(e) {
        const acc = $(this).find('.main-account-num').val();
        const conf = $(this).find('.confirm-account-num').val();
        if (acc && conf && acc !== conf) {
            e.preventDefault();
            alert('Bank Account Numbers do not match!');
            return false;
        }
    });

    if ($('#users-table tbody tr').length > 0 && !$('#users-table tbody tr td').hasClass('text-center')) {
        const table = $('#users-table').DataTable({
            "pageLength": 10,
            "lengthChange": false,
            "info": false,
            "searching": true,
            "stateSave": false,
            "dom": "rtip",
            "language": {
                "emptyTable": "No employees found in directory"
            }
        });

        // Custom Text Search
        $('#emp-search-input').on('keyup change', function() {
            table.search(this.value).draw();
        });

        // Custom Filters for Status, Location, Department
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'users-table') return true;

                const selectedStatus = $('#emp-status-filter').val();
                const selectedLocation = $('#emp-location-filter').val();
                const selectedDept = $('#emp-dept-filter').val();

                const row = table.row(dataIndex).node();
                if (!row) return true;

                const rowStatus = $(row).attr('data-status') || '';
                const rowLocation = $(row).attr('data-location') || '';
                const rowDept = $(row).attr('data-department') || '';

                if (selectedStatus && rowStatus !== selectedStatus) return false;
                if (selectedLocation && rowLocation !== selectedLocation) return false;
                if (selectedDept && rowDept.toLowerCase() !== selectedDept.toLowerCase()) return false;

                return true;
            }
        );

        $('#emp-status-filter, #emp-location-filter, #emp-dept-filter').on('change', function() {
            table.draw();
        });
    }

    function displayUploadError(title, message, errorsList) {
        $('#upload-error-title').text(title || 'Upload Failed');
        $('#upload-error-message').text(message || 'The CSV file could not be parsed.');
        
        let errorRowsHtml = '';
        if (errorsList && errorsList.length > 0) {
            errorsList.forEach(function(err) {
                let rowNum = (err.row && err.row > 0) ? 'Row ' + err.row : 'Header/File';
                let fieldName = err.field || 'General';
                let msg = err.message || 'Validation error';
                errorRowsHtml += '<tr>';
                errorRowsHtml += '<td><span class="badge bg-danger">' + rowNum + '</span></td>';
                errorRowsHtml += '<td class="fw-bold text-warning">' + fieldName + '</td>';
                errorRowsHtml += '<td>' + msg + '</td>';
                errorRowsHtml += '</tr>';
            });
            $('#upload-error-table-body').html(errorRowsHtml);
            $('#upload-error-table-wrapper').removeClass('d-none');
        } else {
            $('#upload-error-table-wrapper').addClass('d-none');
        }
        $('#upload-error-container').removeClass('d-none');
    }

    function clearUploadError() {
        $('#upload-error-container').addClass('d-none');
        $('#upload-error-table-body').html('');
    }

    // Bulk Upload Modal Scripting
    $('#btn-upload-preview').on('click', function() {
        clearUploadError();
        const fileInput = document.getElementById('csv_file');
        if (!fileInput.files || fileInput.files.length === 0) {
            displayUploadError('File Required', 'Please select a CSV file to upload.');
            return;
        }

        const file = fileInput.files[0];
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'csv') {
            displayUploadError('Invalid File Format', 'Only .csv files are supported. Please select a valid CSV file.');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            displayUploadError('File Too Large', 'File size exceeds the 5MB maximum limit.');
            return;
        }

        const formData = new FormData();
        formData.append('csv_file', file);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        $('#btn-upload-preview').html('<span class="spinner-border spinner-border-sm me-2"></span>Validating...').prop('disabled', true);

        $.ajax({
            url: 'index.php?route=users/bulkUpload&csrf_token=<?php echo $_SESSION['csrf_token']; ?>',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': '<?php echo $_SESSION['csrf_token']; ?>'
            },
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                $('#btn-upload-preview').html('Upload & Preview').prop('disabled', false);
                if (res.success === false) {
                    let errs = res.errors || res.structured_errors || [];
                    displayUploadError('Validation Failure', res.message || 'File validation failed.', errs);
                    return;
                }

                $('#summary-total').text(res.total_rows);
                $('#summary-valid').text(res.valid_count);
                $('#summary-errors').text(res.error_count);

                let dupCount = 0;
                res.rows.forEach(function(row) {
                    if (row.action === 'duplicate') dupCount++;
                });

                if (dupCount > 0) {
                    $('#duplicate-warning-notice').removeClass('d-none').html(
                        '<i class="fa-solid fa-triangle-exclamation me-1"></i> ' +
                        '<strong>' + dupCount + ' duplicate record(s) detected in CSV.</strong> ' +
                        'If you want to update them in the database, set Duplicate Resolution Strategy to <em>"Overwrite/Update existing database records"</em>.'
                    );
                } else {
                    $('#duplicate-warning-notice').addClass('d-none');
                }

                let html = '';
                res.rows.forEach(function(row) {
                    let statusBadge = '';
                    let rowClass = '';
                    if (row.status === 'valid') {
                        if (row.action === 'duplicate') {
                            statusBadge = '<span class="badge bg-warning text-dark">Duplicate</span>';
                            rowClass = 'table-warning text-dark';
                        } else {
                            statusBadge = '<span class="badge bg-success">Valid</span>';
                        }
                    } else {
                        statusBadge = '<span class="badge bg-danger">Error</span>';
                        rowClass = 'table-danger text-dark';
                    }

                    let errorList = [];
                    for (let field in row.errors) {
                        errorList.push('<strong>' + field + ':</strong> ' + row.errors[field]);
                    }
                    if (row.action === 'duplicate' && row.data._duplicate_message) {
                        errorList.push('<span class="text-warning"><strong>Duplicate Warning:</strong> ' + row.data._duplicate_message + '</span>');
                    }
                    let errorHtml = errorList.join('<br>');

                    html += '<tr class="' + rowClass + '">';
                    html += '<td>' + row.index + '</td>';
                    html += '<td>' + statusBadge + '</td>';
                    html += '<td>' + (row.data.employee_code || '') + '</td>';
                    html += '<td>' + (row.data.first_name || '') + '</td>';
                    html += '<td>' + (row.data.last_name || '') + '</td>';
                    html += '<td>' + (row.data.email || '') + '</td>';
                    html += '<td>' + (row.data.department || '') + '</td>';
                    html += '<td>' + (row.data.job_title || '') + '</td>';
                    html += '<td>' + errorHtml + '</td>';
                    html += '</tr>';
                });

                $('#preview-table-body').html(html);
                $('#upload-step').addClass('d-none');
                $('#preview-step').removeClass('d-none');

                if (res.valid_count === 0) {
                    $('#btn-confirm-import').prop('disabled', true);
                } else {
                    $('#btn-confirm-import').prop('disabled', false);
                }
            },
            error: function(xhr) {
                $('#btn-upload-preview').html('Upload & Preview').prop('disabled', false);
                let msg = 'Upload failed.';
                let errs = [];
                if (xhr.responseJSON) {
                    msg = xhr.responseJSON.message || msg;
                    errs = xhr.responseJSON.errors || xhr.responseJSON.structured_errors || [];
                } else if (xhr.responseText) {
                    try {
                        let parsed = JSON.parse(xhr.responseText);
                        msg = parsed.message || xhr.responseText;
                        errs = parsed.errors || parsed.structured_errors || [];
                    } catch(e) {
                        msg = xhr.responseText.replace(/<[^>]*>/g, '').trim() || 'Upload failed.';
                    }
                }
                displayUploadError('Upload Failed', msg, errs);
            }
        });
    });

    $('#btn-back-upload').on('click', function() {
        clearUploadError();
        $('#preview-step').addClass('d-none');
        $('#upload-step').removeClass('d-none');
        document.getElementById('csv_file').value = '';
    });

    $('#btn-confirm-import').on('click', function() {
        const strategy = $('#duplicate_strategy').val();
        const payload = {
            duplicate_strategy: strategy,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        };

        $('#btn-confirm-import').html('<span class="spinner-border spinner-border-sm me-2"></span>Importing...').prop('disabled', true);

        $.ajax({
            url: 'index.php?route=users/bulkImport&csrf_token=<?php echo $_SESSION['csrf_token']; ?>',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': '<?php echo $_SESSION['csrf_token']; ?>'
            },
            data: JSON.stringify(payload),
            contentType: 'application/json',
            success: function(res) {
                $('#btn-confirm-import').html('Confirm & Import').prop('disabled', false);
                if (res.success === false) {
                    alert(res.message);
                    return;
                }

                $('#result-imported').text(res.imported);
                $('#result-skipped').text(res.skipped);
                $('#result-failed').text(res.failed);

                if (res.failed > 0) {
                    $('#btn-download-errors').removeClass('d-none');
                } else {
                    $('#btn-download-errors').addClass('d-none');
                }

                $('#preview-step').addClass('d-none');
                $('#results-step').removeClass('d-none');
            },
            error: function(xhr) {
                $('#btn-confirm-import').html('Confirm & Import').prop('disabled', false);
                let msg = 'Import failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    });

    $('#bulkUploadModal').on('hidden.bs.modal', function() {
        $('#upload-step').removeClass('d-none');
        $('#preview-step').addClass('d-none');
        $('#results-step').addClass('d-none');
        document.getElementById('csv_file').value = '';
    });
});
</script>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary shadow-lg" style="border-radius: 16px; background: var(--panel-dark);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="bulkUploadModalLabel"><i class="fa-solid fa-cloud-arrow-up text-primary me-2"></i>Bulk Upload Employees</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="index.php?route=users/importCsv" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <p class="text-secondary small mb-3">
                        Upload a CSV file containing employee details to import them into the system.
                    </p>
                    <div class="mb-3 p-3 rounded border border-secondary" style="background: var(--surface-soft) !important;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 fw-bold text-white">Need a template?</h6>
                                <span class="text-secondary small">Download our sample CSV template with standard employee fields.</span>
                            </div>
                            <a href="index.php?route=users/downloadTemplate" class="btn btn-outline-info btn-sm px-3">
                                <i class="fa-solid fa-download me-1"></i>Download Template
                            </a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="csv_file" class="form-label text-secondary small fw-semibold">Select CSV File</label>
                        <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="modal-footer border-0 px-0 pb-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import me-1"></i>Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
