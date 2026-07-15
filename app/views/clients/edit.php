<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="pulse-card">
            <h4 class="text-white mb-4">Edit Client Company</h4>
            
            <form action="index.php?route=clients/edit/<?php echo $client_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-label text-secondary">Company Name *</label>
                        <input type="text" name="company_name" id="company_name" 
                               class="form-control <?php echo (!empty($company_name_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($company_name); ?>" required>
                        <div class="invalid-feedback"><?php echo $company_name_err; ?></div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="email" class="form-label text-secondary">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label text-secondary">Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label text-secondary">Status</label>
                        <select name="status" id="status" class="form-select" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="contract_start" class="form-label text-secondary">Contract Start Date</label>
                        <input type="date" name="contract_start" id="contract_start" class="form-control" value="<?php echo htmlspecialchars($contract_start); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="contract_end" class="form-label text-secondary">Contract End Date</label>
                        <input type="date" name="contract_end" id="contract_end" class="form-control" value="<?php echo htmlspecialchars($contract_end); ?>">
                    </div>

                    <div class="col-12">
                        <label for="package_details" class="form-label text-secondary">Package & Retainer Details</label>
                        <textarea name="package_details" id="package_details" class="form-control" rows="3"><?php echo htmlspecialchars($package_details); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="billing_address" class="form-label text-secondary">Billing Address</label>
                        <textarea name="billing_address" id="billing_address" class="form-control" rows="2"><?php echo htmlspecialchars($billing_address); ?></textarea>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                        <a href="index.php?route=clients/index" class="btn btn-outline-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Stakeholders / Contacts Section -->
        <div class="pulse-card mt-4">
            <?php if (!empty($_SESSION['client_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['client_success']); unset($_SESSION['client_success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <h4 class="text-white mb-4">Client Stakeholders & Contacts</h4>
            
            <div class="row g-4">
                <!-- Contacts List -->
                <div class="col-lg-7">
                    <h5 class="text-white mb-3" style="font-size: 1.05rem;">Current Stakeholders</h5>
                    <?php if (empty($contacts)): ?>
                        <p class="text-secondary small">No stakeholders or contact persons added yet for this client company.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle border-secondary text-white small">
                                <thead>
                                    <tr class="text-secondary">
                                        <th>Name</th>
                                        <th>Role / Title</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td class="font-weight-bold text-white"><?php echo htmlspecialchars($contact->name); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($contact->role_or_title ?: 'N/A'); ?></span></td>
                                            <td><?php echo htmlspecialchars($contact->email); ?></td>
                                            <td><?php echo htmlspecialchars($contact->phone ?: 'N/A'); ?></td>
                                            <td class="text-end">
                                                <form action="index.php?route=clients/deleteContact/<?php echo $contact->contact_id; ?>" method="POST" onsubmit="return confirm('Remove this contact person?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add Contact Form -->
                <div class="col-lg-5" style="border-left: 1px solid var(--border-color); padding-left: 1.5rem;">
                    <h5 class="text-white mb-3" style="font-size: 1.05rem;">Add Stakeholder</h5>
                    <form action="index.php?route=clients/addContact" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                        <div class="mb-2">
                            <label class="form-label text-secondary small mb-1">Full Name *</label>
                            <input type="text" name="name" class="form-control form-control-sm bg-dark border-secondary text-white" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary small mb-1">Email Address *</label>
                            <input type="email" name="email" class="form-control form-control-sm bg-dark border-secondary text-white" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary small mb-1">Phone Number</label>
                            <input type="text" name="phone" class="form-control form-control-sm bg-dark border-secondary text-white">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small mb-1">Role / Designation</label>
                            <input type="text" name="role_or_title" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="e.g. CTO, Billing Contact">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100" style="background: var(--primary); border: none;">
                            <i class="fa-solid fa-plus me-1"></i>Add Stakeholder
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
