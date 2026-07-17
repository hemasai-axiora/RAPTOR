<?php
$p          = $data['profile'];
$role       = $_SESSION['user_role'];
$isHrOrAdmin = in_array($role, ['admin', 'hr'], true);
$initials   = '';
$words      = explode(' ', $p->name);
$initials   = count($words) > 1
    ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
    : strtoupper(substr($p->name, 0, 2));
?>

<style>
/* ── Profile Page — Light & Dark theme-aware ── */
.profile-card {
    background: var(--panel-dark);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--shadow-soft);
}
.profile-avatar {
    width: 90px; height: 90px; font-size: 28px; font-weight: 700;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-strong));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    border: 3px solid var(--primary-soft);
    flex-shrink: 0;
}
.profile-section-header {
    display: flex; align-items: center; gap: 0.55rem;
    background: var(--primary-soft); padding: 0.65rem 0.9rem;
    border-radius: 8px; margin-bottom: 1.1rem;
    border-left: 4px solid var(--primary);
}
.profile-section-header i  { color: var(--primary); font-size: 0.9rem; }
.profile-section-header h5 { margin: 0; font-size: 0.9rem; font-weight: 700; color: var(--primary); }

.profile-section-header.danger {
    border-left-color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
}
.profile-section-header.danger h5, .profile-section-header.danger i {
    color: var(--danger);
}
.profile-section-header.success {
    border-left-color: var(--success);
    background: rgba(16, 185, 129, 0.1);
}
.profile-section-header.success h5, .profile-section-header.success i {
    color: var(--success);
}

.profile-field-label {
    display: block; font-size: 0.72rem; font-weight: 700;
    color: var(--text-secondary); text-transform: uppercase;
    letter-spacing: 0.4px; margin-bottom: 0.2rem;
}
.profile-field-value {
    color: var(--text-primary); font-size: 0.88rem; font-weight: 600;
}
.profile-field-value.empty { color: var(--text-muted); font-style: italic; font-weight: 400; }
</style>

<div style="background: transparent; padding: 0; border: none; box-shadow: none;">

    <!-- ── Profile Header ── -->
    <div class="profile-card p-4 mb-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <div class="profile-avatar"><?php echo $initials; ?></div>
            <div class="flex-grow-1">
                <h3 style="color: #1E293B; font-weight: 700; margin: 0 0 0.25rem;"><?php echo htmlspecialchars($p->name); ?></h3>
                <p style="color: #64748B; margin: 0 0 0.5rem; font-size: 0.9rem;">
                    <?php echo htmlspecialchars($p->job_title ?? 'Employee'); ?>
                    <span style="margin: 0 0.35rem; color: #CBD5E1;">•</span>
                    <?php echo htmlspecialchars($p->department ?? 'General'); ?>
                </p>
                <div class="d-inline-flex gap-2 flex-wrap">
                    <span style="background:#F1F5F9; color:#475569; border:1px solid #E2E8F0; border-radius:20px; font-size:0.75rem; font-weight:600; padding:0.2rem 0.65rem;">
                        ID: <?php echo htmlspecialchars($p->employee_code ?? 'N/A'); ?>
                    </span>
                    <span style="background:#DBEAFE; color:#1D4ED8; border:1px solid #BFDBFE; border-radius:20px; font-size:0.75rem; font-weight:600; padding:0.2rem 0.65rem;">
                        <?php echo htmlspecialchars($p->employment_type ?? 'Full-time'); ?>
                    </span>
                    <span style="background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; border-radius:20px; font-size:0.75rem; font-weight:600; padding:0.2rem 0.65rem;">
                        <?php echo ucfirst($p->role_name ?? $role); ?>
                    </span>
                </div>
            </div>
            <div class="ms-auto">
                <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal"
                        style="background:#2563EB; color:#fff; border:none; border-radius:8px; padding:0.45rem 1.1rem; font-weight:600;"
                        onmouseover="this.style.background='#1D4ED8'" onmouseout="this.style.background='#2563EB'">
                    <i class="fa-solid fa-user-pen me-1"></i>Edit Profile
                </button>
            </div>
        </div>
    </div>

    <!-- ── Flash Alerts ── -->
    <?php if (isset($_SESSION['profile_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['profile_success']; unset($_SESSION['profile_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['profile_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $_SESSION['profile_error']; unset($_SESSION['profile_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ── Left: Personal Details ── -->
        <div class="col-lg-6">
            <div class="profile-card p-4 h-100">
                <div class="profile-section-header">
                    <i class="fa-solid fa-user-astronaut"></i>
                    <h5>Personal Details</h5>
                </div>
                <div class="row g-3">
                    <?php
                    $fields = [
                        ['Email Address',     $p->email],
                        ['Phone Number',      $p->phone_number      ?? null],
                        ['Reporting Manager', $p->manager_name      ?? null],
                        ['Date of Joining',   $p->date_of_joining   ?? null],
                        ['Date of Birth',     $p->date_of_birth     ?? null],
                        ['Blood Group',       $p->blood_group       ?? null],
                        ['Emergency Contact', $p->emergency_contact ?? null],
                        ['Work Location',     $p->work_location     ?? null],
                        ['Total Experience',  isset($p->experience_years) ? $p->experience_years . ' Years' : null],
                    ];
                    foreach ($fields as [$label, $val]):
                        $isEmpty = ($val === null || $val === '');
                    ?>
                    <div class="col-6">
                        <span class="profile-field-label"><?php echo $label; ?></span>
                        <span class="profile-field-value <?php echo $isEmpty ? 'empty' : ''; ?>">
                            <?php echo $isEmpty ? '—' : htmlspecialchars($val); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>

                    <div class="col-12">
                        <span class="profile-field-label">Address</span>
                        <span class="profile-field-value <?php echo empty($p->address) ? 'empty' : ''; ?>">
                            <?php echo !empty($p->address) ? nl2br(htmlspecialchars($p->address)) : '—'; ?>
                        </span>
                    </div>
                    <div class="col-12">
                        <span class="profile-field-label">Skills</span>
                        <?php if (!empty($p->skills)): ?>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php foreach (explode(',', $p->skills) as $skill): ?>
                                    <span style="background:var(--primary-soft); color:var(--primary); border:1px solid var(--primary-soft); border-radius:20px; font-size:0.75rem; font-weight:600; padding:0.15rem 0.55rem;">
                                        <?php echo htmlspecialchars(trim($skill)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="profile-field-value empty">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right: Sensitive / Payroll / Bank ── -->
        <div class="col-lg-6">
            <div class="profile-card p-4 h-100">
                <?php if ($isHrOrAdmin): ?>
                    <div class="profile-section-header danger">
                        <i class="fa-solid fa-lock"></i>
                        <h5>Sensitive / Payroll Details <small style="font-weight:400; font-size:0.78rem;">(HR / Admin Only)</small></h5>
                    </div>
                    <div class="row g-3 mb-4">
                        <?php
                        $payrollFields = [
                            ['Salary (Monthly)', '₹' . number_format((float)($p->salary ?? 0), 2)],
                            ['PAN Number',       $p->pan_number    ?? null],
                            ['Aadhaar Number',   $p->aadhaar_number ?? null],
                            ['UAN (PF Number)',  $p->uan           ?? null],
                            ['ESIC Number',      $p->esic_number   ?? null],
                            ['PF Applicable',    isset($p->pf_applicable) ? ($p->pf_applicable ? 'Yes' : 'No') : null],
                        ];
                        foreach ($payrollFields as [$label, $val]):
                            $isEmpty = ($val === null || $val === '' || $val === '₹0.00');
                        ?>
                        <div class="col-6">
                            <span class="profile-field-label"><?php echo $label; ?></span>
                            <span class="profile-field-value <?php echo $isEmpty ? 'empty' : ''; ?>">
                                <?php echo $isEmpty ? '—' : htmlspecialchars($val); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Bank Details -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
                        <div class="profile-section-header success">
                            <i class="fa-solid fa-building-columns"></i>
                            <h5>Bank Details</h5>
                        </div>
                        <div class="row g-3">
                            <?php
                            $bankFields = [
                                ['Bank Name',       $p->bank_name            ?? null],
                                ['Account Holder',  $p->account_holder_name  ?? null],
                                ['Account Number',  $p->account_number       ?? null],
                                ['IFSC Code',       $p->ifsc_code            ?? null],
                            ];
                            foreach ($bankFields as [$label, $val]):
                                $isEmpty = ($val === null || $val === '');
                            ?>
                            <div class="col-6">
                                <span class="profile-field-label"><?php echo $label; ?></span>
                                <span class="profile-field-value <?php echo $isEmpty ? 'empty' : ''; ?>">
                                    <?php echo $isEmpty ? '—' : htmlspecialchars($val); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="profile-section-header danger">
                        <i class="fa-solid fa-lock"></i>
                        <h5>Sensitive / Payroll Details</h5>
                    </div>
                    <div class="text-center py-5">
                        <div style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 1rem;"><i class="fa-solid fa-lock"></i></div>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;">You don't have permission to view financial and identification records.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Edit Profile Modal (Light Theme) ── -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#ffffff; border:1px solid #E2E8F0; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.10);">
            <div class="modal-header" style="background:#EFF6FF; border-bottom:1px solid #E2E8F0; border-radius:16px 16px 0 0; padding:1rem 1.25rem;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-pen" style="color:#2563EB;"></i>
                    <h5 class="modal-title" style="margin:0; font-weight:700; color:#1E40AF;" id="editProfileModalLabel">Edit Profile Details</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=hrms/editProfile/<?php echo $p->user_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body" style="padding:1.5rem;">
                    <?php
                    $editFields = [
                        ['phone_number',      'tel',    'Phone Number',         $p->phone_number      ?? ''],
                        ['emergency_contact', 'text',   'Emergency Contact',    $p->emergency_contact ?? ''],
                        ['blood_group',       'text',   'Blood Group',          $p->blood_group       ?? ''],
                        ['experience_years',  'number', 'Experience (Years)',   $p->experience_years  ?? '0.0'],
                        ['skills',            'text',   'Skills (comma-separated)', $p->skills       ?? ''],
                    ];
                    foreach ($editFields as [$name, $type, $label, $val]):
                    ?>
                    <div class="mb-3">
                        <label for="<?php echo $name; ?>" style="display:block; font-size:0.76rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.35rem;">
                            <?php echo $label; ?>
                        </label>
                        <input type="<?php echo $type; ?>" <?php echo $type === 'number' ? 'step="0.1"' : ''; ?>
                               class="form-control" id="<?php echo $name; ?>" name="<?php echo $name; ?>"
                               value="<?php echo htmlspecialchars($val); ?>"
                               style="border-color:#CBD5E1; border-radius:8px;">
                    </div>
                    <?php endforeach; ?>
                    <div class="mb-3">
                        <label for="address" style="display:block; font-size:0.76rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.35rem;">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"
                                  style="border-color:#CBD5E1; border-radius:8px;"><?php echo htmlspecialchars($p->address ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E2E8F0; padding:1rem 1.25rem;">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal"
                            style="border:1px solid #CBD5E1; color:#64748B; border-radius:8px; padding:0.45rem 1rem;">
                        Close
                    </button>
                    <button type="submit" class="btn btn-sm"
                            style="background:#2563EB; color:#fff; border:none; border-radius:8px; padding:0.45rem 1.2rem; font-weight:600;"
                            onmouseover="this.style.background='#1D4ED8'" onmouseout="this.style.background='#2563EB'">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
