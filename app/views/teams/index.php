<?php $csrf = $_SESSION['csrf_token']; ?>

<?php if (!empty($_SESSION['team_error'])): ?>
    <div class="alert alert-danger border-0 shadow mb-3" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['team_error']; unset($_SESSION['team_error']); ?>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['team_success'])): ?>
    <div class="alert alert-success border-0 shadow mb-3" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
        <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['team_success']; unset($_SESSION['team_success']); ?>
    </div>
<?php endif; ?>

<!-- Section tabs -->
<ul class="nav nav-pills mb-3 gap-2" id="orgTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-teams" type="button"><i class="fa-solid fa-people-group me-2"></i>Teams</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-branches" type="button"><i class="fa-solid fa-building me-2"></i>Branches</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-territories" type="button"><i class="fa-solid fa-map-location-dot me-2"></i>Territories</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-geofences" type="button"><i class="fa-solid fa-location-crosshairs me-2"></i>Geofences</button></li>
</ul>

<div class="tab-content">
    <!-- ===================== TEAMS ===================== -->
    <div class="tab-pane fade show active" id="tab-teams">
        <div class="pulse-card">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
                <h4 class="text-white mb-0">Teams</h4>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="fa-solid fa-user-plus me-2"></i>Assign Member</button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTeamModal" style="background: var(--primary); border: none;"><i class="fa-solid fa-plus me-2"></i>New Team</button>
                </div>
            </div>

            <div class="table-scroll">
                <table class="table table-dark table-hover align-middle table-stack">
                    <thead>
                        <tr class="text-secondary">
                            <th>Team</th><th>Leader</th><th>Manager</th><th>Branch</th><th>Territory</th><th>Members</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($teams)): ?>
                        <tr><td colspan="8" class="text-center text-secondary py-4">No teams yet. Create your first team.</td></tr>
                    <?php else: foreach ($teams as $t): ?>
                        <tr>
                            <td data-label="Team" class="text-white fw-semibold"><?php echo htmlspecialchars($t->name); ?></td>
                            <td data-label="Leader"><?php echo htmlspecialchars($t->leader_name ?? '—'); ?></td>
                            <td data-label="Manager"><?php echo htmlspecialchars($t->manager_name ?? '—'); ?></td>
                            <td data-label="Branch"><?php echo htmlspecialchars($t->branch_name ?? '—'); ?></td>
                            <td data-label="Territory"><?php echo htmlspecialchars($t->territory_name ?? '—'); ?></td>
                            <td data-label="Members"><span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo (int) $t->member_count; ?></span></td>
                            <td data-label="Status"><span class="badge bg-<?php echo $t->status === 'active' ? 'success' : 'danger'; ?>-subtle text-<?php echo $t->status === 'active' ? 'success' : 'danger'; ?>"><?php echo strtoupper($t->status); ?></span></td>
                            <td data-label="Actions" class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-outline-info btn-sm btn-edit-team"
                                        data-id="<?php echo $t->team_id; ?>"
                                        data-name="<?php echo htmlspecialchars($t->name); ?>"
                                        data-leader="<?php echo (int) $t->team_leader_user_id; ?>"
                                        data-manager="<?php echo (int) $t->manager_user_id; ?>"
                                        data-branch="<?php echo (int) $t->branch_id; ?>"
                                        data-territory="<?php echo (int) $t->territory_id; ?>"
                                        data-status="<?php echo $t->status; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editTeamModal"><i class="fa-solid fa-pen"></i></button>
                                    <span class="badge bg-secondary-subtle text-secondary" title="Deletion is disabled by governance policy">No delete</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pulse-card mt-4">
            <h4 class="text-white mb-4"><?php echo $_SESSION['user_role'] === 'manager' ? 'My Team Members' : 'Assigned Team Members'; ?></h4>
            <div class="table-scroll">
                <table class="table table-dark table-hover align-middle table-stack">
                    <thead>
                        <tr class="text-secondary">
                            <th>Employee</th>
                            <th>Email</th>
                            <th>Assigned Team</th>
                            <th>Reporting Manager</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($team_members)): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No team members assigned yet.</td></tr>
                    <?php else: foreach ($team_members as $m): ?>
                        <tr>
                            <td data-label="Employee" class="text-white fw-semibold"><?php echo htmlspecialchars($m->name); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($m->email); ?></td>
                            <td data-label="Assigned Team">
                                <?php if ($m->team_name): ?>
                                    <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($m->team_name); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">No Team</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Reporting Manager"><?php echo htmlspecialchars($m->manager_name ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== BRANCHES ===================== -->
    <div class="tab-pane fade" id="tab-branches">
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="pulse-card">
                    <h4 class="text-white mb-4">Branches</h4>
                    <div class="table-scroll">
                        <table class="table table-dark table-hover align-middle table-stack">
                            <thead><tr class="text-secondary"><th>Name</th><th>Address</th><th>Coordinates</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if (empty($branches)): ?>
                                <tr><td colspan="5" class="text-center text-secondary py-4">No branches yet.</td></tr>
                            <?php else: foreach ($branches as $b): ?>
                                <tr>
                                    <td data-label="Name" class="text-white fw-semibold"><?php echo htmlspecialchars($b->name); ?></td>
                                    <td data-label="Address"><?php echo htmlspecialchars($b->address ?? '—'); ?></td>
                                    <td data-label="Coordinates" class="font-monospace small"><?php echo ($b->lat !== null) ? htmlspecialchars($b->lat . ', ' . $b->lng) : '—'; ?></td>
                                    <td data-label="Status"><span class="badge bg-<?php echo $b->status === 'active' ? 'success' : 'danger'; ?>-subtle text-<?php echo $b->status === 'active' ? 'success' : 'danger'; ?>"><?php echo strtoupper($b->status); ?></span></td>
                                    <td data-label="Actions" class="text-end">
                                        <span class="badge bg-secondary-subtle text-secondary" title="Deletion is disabled by governance policy">No delete</span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="pulse-card">
                    <h5 class="text-white mb-3">Add Branch</h5>
                    <form action="index.php?route=teams/addBranch" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input name="name" required class="form-control bg-dark border-secondary text-white"></div>
                        <div class="mb-3"><label class="form-label text-secondary">Address</label><textarea name="address" rows="2" class="form-control bg-dark border-secondary text-white"></textarea></div>
                        <div class="row g-2 mb-3">
                            <div class="col"><label class="form-label text-secondary">Latitude</label><input name="lat" type="number" step="any" class="form-control bg-dark border-secondary text-white"></div>
                            <div class="col"><label class="form-label text-secondary">Longitude</label><input name="lng" type="number" step="any" class="form-control bg-dark border-secondary text-white"></div>
                        </div>
                        <button class="btn btn-primary w-100" style="background: var(--primary); border: none;">Save Branch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== TERRITORIES ===================== -->
    <div class="tab-pane fade" id="tab-territories">
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="pulse-card">
                    <h4 class="text-white mb-4">Territories</h4>
                    <div class="table-scroll">
                        <table class="table table-dark table-hover align-middle table-stack">
                            <thead><tr class="text-secondary"><th>Name</th><th>Description</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if (empty($territories)): ?>
                                <tr><td colspan="4" class="text-center text-secondary py-4">No territories yet.</td></tr>
                            <?php else: foreach ($territories as $tr): ?>
                                <tr>
                                    <td data-label="Name" class="text-white fw-semibold"><?php echo htmlspecialchars($tr->name); ?></td>
                                    <td data-label="Description"><?php echo htmlspecialchars($tr->description ?? '—'); ?></td>
                                    <td data-label="Status"><span class="badge bg-<?php echo $tr->status === 'active' ? 'success' : 'danger'; ?>-subtle text-<?php echo $tr->status === 'active' ? 'success' : 'danger'; ?>"><?php echo strtoupper($tr->status); ?></span></td>
                                    <td data-label="Actions" class="text-end">
                                        <span class="badge bg-secondary-subtle text-secondary" title="Deletion is disabled by governance policy">No delete</span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="pulse-card">
                    <h5 class="text-white mb-3">Add Territory</h5>
                    <form action="index.php?route=teams/addTerritory" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input name="name" required class="form-control bg-dark border-secondary text-white"></div>
                        <div class="mb-3"><label class="form-label text-secondary">Description</label><textarea name="description" rows="2" class="form-control bg-dark border-secondary text-white"></textarea></div>
                        <button class="btn btn-primary w-100" style="background: var(--primary); border: none;">Save Territory</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== GEOFENCES ===================== -->
    <div class="tab-pane fade" id="tab-geofences">
        <div class="pulse-card mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="text-white mb-1">Geofence Enforcement</h5>
                    <p class="text-secondary small mb-0">When ON, check-ins outside every active office fence are flagged and sent for approval.</p>
                </div>
                <form action="index.php?route=teams/toggleGeofence" method="POST" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="enabled" value="<?php echo $geofence_enabled ? '0' : '1'; ?>">
                    <span class="badge bg-<?php echo $geofence_enabled ? 'success' : 'secondary'; ?>-subtle text-<?php echo $geofence_enabled ? 'success' : 'secondary'; ?>">
                        <?php echo $geofence_enabled ? 'ENABLED' : 'DISABLED'; ?>
                    </span>
                    <button class="btn btn-sm btn-outline-<?php echo $geofence_enabled ? 'danger' : 'success'; ?>">
                        Turn <?php echo $geofence_enabled ? 'Off' : 'On'; ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="pulse-card">
                    <h4 class="text-white mb-4">Office Geofences</h4>
                    <div class="table-scroll">
                        <table class="table table-dark table-hover align-middle table-stack">
                            <thead><tr class="text-secondary"><th>Name</th><th>Type</th><th>Center</th><th>Radius</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if (empty($geofences)): ?>
                                <tr><td colspan="6" class="text-center text-secondary py-4">No geofences defined.</td></tr>
                            <?php else: foreach ($geofences as $g): ?>
                                <tr>
                                    <td data-label="Name" class="text-white fw-semibold"><?php echo htmlspecialchars($g->name); ?></td>
                                    <td data-label="Type"><span class="badge bg-info-subtle text-info"><?php echo strtoupper($g->type); ?></span></td>
                                    <td data-label="Center" class="font-monospace small"><?php echo htmlspecialchars($g->center_lat . ', ' . $g->center_lng); ?></td>
                                    <td data-label="Radius"><?php echo (int)$g->radius_m; ?> m</td>
                                    <td data-label="Active"><span class="badge bg-<?php echo $g->active ? 'success' : 'secondary'; ?>-subtle text-<?php echo $g->active ? 'success' : 'secondary'; ?>"><?php echo $g->active ? 'YES' : 'NO'; ?></span></td>
                                    <td data-label="Actions" class="text-end">
                                        <span class="badge bg-secondary-subtle text-secondary" title="Deletion is disabled by governance policy">No delete</span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="pulse-card">
                    <h5 class="text-white mb-3">Add Geofence</h5>
                    <form action="index.php?route=teams/addGeofence" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <div class="mb-3"><label class="form-label text-secondary">Name *</label><input name="name" required class="form-control bg-dark border-secondary text-white" placeholder="Head Office"></div>
                        <div class="mb-3"><label class="form-label text-secondary">Type</label>
                            <select name="type" class="form-select bg-dark border-secondary text-white">
                                <option value="office">Office</option><option value="client">Client</option><option value="territory">Territory</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col"><label class="form-label text-secondary">Latitude *</label><input name="center_lat" type="number" step="any" required class="form-control bg-dark border-secondary text-white"></div>
                            <div class="col"><label class="form-label text-secondary">Longitude *</label><input name="center_lng" type="number" step="any" required class="form-control bg-dark border-secondary text-white"></div>
                        </div>
                        <div class="mb-3"><label class="form-label text-secondary">Radius (m)</label><input name="radius_m" type="number" value="200" class="form-control bg-dark border-secondary text-white"></div>
                        <button class="btn btn-primary w-100" style="background: var(--primary); border: none;">Save Geofence</button>
                        <p class="text-secondary small mt-2 mb-0"><i class="fa-solid fa-circle-info me-1"></i>Tip: open the office in Google/OpenStreetMap and copy the lat, lng.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== MODALS ===================== -->
<?php
// Reusable option renderers
function leaderOptions($leaders, $selected = 0) {
    foreach ($leaders as $l) {
        $sel = ((int)$selected === (int)$l->user_id) ? 'selected' : '';
        echo '<option value="' . (int)$l->user_id . '" ' . $sel . '>' . htmlspecialchars($l->name) . ' (' . htmlspecialchars($l->role_name) . ')</option>';
    }
}
function branchOptions($branches, $selected = 0) {
    foreach ($branches as $b) {
        $sel = ((int)$selected === (int)$b->branch_id) ? 'selected' : '';
        echo '<option value="' . (int)$b->branch_id . '" ' . $sel . '>' . htmlspecialchars($b->name) . '</option>';
    }
}
function territoryOptions($territories, $selected = 0) {
    foreach ($territories as $t) {
        $sel = ((int)$selected === (int)$t->territory_id) ? 'selected' : '';
        echo '<option value="' . (int)$t->territory_id . '" ' . $sel . '>' . htmlspecialchars($t->name) . '</option>';
    }
}
?>

<!-- Add Team -->
<div class="modal fade" id="addTeamModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-white border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">New Team</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form action="index.php?route=teams/add" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <div class="modal-body">
            <div class="mb-3"><label class="form-label text-secondary">Team Name *</label><input name="name" required class="form-control bg-dark border-secondary text-white"></div>
            <div class="mb-3"><label class="form-label text-secondary">Team Leader</label><select name="team_leader_user_id" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php leaderOptions($leaders); ?></select></div>
            <div class="mb-3"><label class="form-label text-secondary">Manager</label><select name="manager_user_id" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php leaderOptions($leaders); ?></select></div>
            <div class="mb-3"><label class="form-label text-secondary">Branch</label><select name="branch_id" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php branchOptions($branches); ?></select></div>
            <div class="mb-3"><label class="form-label text-secondary">Territory</label><select name="territory_id" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php territoryOptions($territories); ?></select></div>
        </div>
        <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" style="background: var(--primary); border: none;">Create</button></div>
    </form>
</div></div></div>

<!-- Edit Team -->
<div class="modal fade" id="editTeamModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-white border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">Edit Team</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form action="index.php?route=teams/edit" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="team_id" id="et_id">
        <div class="modal-body">
            <div class="mb-3"><label class="form-label text-secondary">Team Name *</label><input name="name" id="et_name" required class="form-control bg-dark border-secondary text-white"></div>
            <div class="mb-3"><label class="form-label text-secondary">Team Leader</label><select name="team_leader_user_id" id="et_leader" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php leaderOptions($leaders); ?></select></div>
            <div class="mb-3"><label class="form-label text-secondary">Manager</label><select name="manager_user_id" id="et_manager" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php leaderOptions($leaders); ?></select></div>
            <div class="mb-3"><label class="form-label text-secondary">Branch</label><select name="branch_id" id="et_branch" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php branchOptions($branches); ?></select></div>
            <div class="mb-3"><label class="form-label text-secondary">Territory</label><select name="territory_id" id="et_territory" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php territoryOptions($territories); ?></select></div>
            <div class="mb-3"><label class="form-label text-secondary">Status</label><select name="status" id="et_status" class="form-select bg-dark border-secondary text-white"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" style="background: var(--primary); border: none;">Save</button></div>
    </form>
</div></div></div>

<!-- Assign Member -->
<div class="modal fade" id="assignModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-white border-secondary">
    <div class="modal-header border-secondary"><h5 class="modal-title">Assign Employee to Team</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form action="index.php?route=teams/assign" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <div class="modal-body">
            <div class="mb-3"><label class="form-label text-secondary">Employee *</label>
                <select name="user_id" required class="form-select bg-dark border-secondary text-white">
                    <option value="">-- Select --</option>
                    <?php foreach ($salespersons as $sp): ?><option value="<?php echo (int)$sp->user_id; ?>"><?php echo htmlspecialchars($sp->name); ?></option><?php endforeach; ?>
                </select>
                <?php if (empty($salespersons)): ?><small class="text-warning">No employee users yet. Create them in Employee Management first.</small><?php endif; ?>
            </div>
            <div class="mb-3"><label class="form-label text-secondary">Team *</label>
                <select name="team_id" required class="form-select bg-dark border-secondary text-white">
                    <option value="">-- Select --</option>
                    <option value="remove">-- No Team / Remove from Team --</option>
                    <?php foreach ($teams as $t): ?><option value="<?php echo (int)$t->team_id; ?>"><?php echo htmlspecialchars($t->name); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label text-secondary">Reporting Manager</label>
                <select name="reporting_manager_id" class="form-select bg-dark border-secondary text-white"><option value="">—</option><?php leaderOptions($leaders); ?></select>
            </div>
        </div>
        <div class="modal-footer border-secondary"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" style="background: var(--primary); border: none;">Assign</button></div>
    </form>
</div></div></div>

<script>
$(function () {
    $('.btn-edit-team').on('click', function () {
        $('#et_id').val($(this).data('id'));
        $('#et_name').val($(this).data('name'));
        $('#et_leader').val(String($(this).data('leader')) === '0' ? '' : $(this).data('leader'));
        $('#et_manager').val(String($(this).data('manager')) === '0' ? '' : $(this).data('manager'));
        $('#et_branch').val(String($(this).data('branch')) === '0' ? '' : $(this).data('branch'));
        $('#et_territory').val(String($(this).data('territory')) === '0' ? '' : $(this).data('territory'));
        $('#et_status').val($(this).data('status'));
    });
});
</script>
