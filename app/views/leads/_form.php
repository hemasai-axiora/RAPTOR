<?php if (!empty($duplicates)): ?>
    <div class="alert alert-warning border-warning bg-warning bg-opacity-10 text-warning">
        <div class="fw-semibold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Possible duplicate lead</div>
        <?php foreach ($duplicates as $dup): ?>
            <div>
                <a class="text-warning" href="index.php?route=leads/view/<?php echo $dup->lead_id; ?>">
                    <?php echo htmlspecialchars($dup->first_name . ' ' . ($dup->last_name ?? '')); ?>
                </a>
                <?php echo htmlspecialchars(' - ' . ($dup->email ?: $dup->phone ?: 'No contact') . ' - ' . strtoupper($dup->status)); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-6">
        <label for="first_name" class="form-label text-secondary">First Name *</label>
        <input type="text" name="first_name" id="first_name" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($first_name); ?>" required pattern="[A-Za-z\s'\-]{2,50}" title="First name must be between 2 and 50 characters containing only letters, spaces, hyphens, or apostrophes.">
        <div class="invalid-feedback"><?php echo htmlspecialchars($first_name_err); ?></div>
    </div>

    <div class="col-md-6">
        <label for="last_name" class="form-label text-secondary">Last Name</label>
        <input type="text" name="last_name" id="last_name" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($last_name); ?>" pattern="[A-Za-z\s'\-]{0,50}" title="Last name must contain only letters, spaces, hyphens, or apostrophes.">
        <div class="invalid-feedback"><?php echo htmlspecialchars($last_name_err); ?></div>
    </div>

    <div class="col-md-6">
        <label for="company_name" class="form-label text-secondary">Company Name</label>
        <input type="text" name="company_name" id="company_name" class="form-control" value="<?php echo htmlspecialchars($company_name ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="client_id" class="form-label text-secondary">Associated Client</label>
        <select name="client_id" id="client_id" class="form-select bg-dark border-secondary text-white">
            <option value="">No client association</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?php echo $client->client_id; ?>" <?php echo (string) $client_id === (string) $client->client_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($client->company_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label text-secondary">Email Address</label>
        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
        <div class="invalid-feedback"><?php echo htmlspecialchars($email_err); ?></div>
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label text-secondary">Phone Number</label>
        <input type="text" name="phone" id="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone); ?>">
        <div class="invalid-feedback"><?php echo htmlspecialchars($phone_err); ?></div>
    </div>

    <div class="col-md-6">
        <label for="assigned_to_user_id" class="form-label text-secondary">Assign Owner</label>
        <select name="assigned_to_user_id" id="assigned_to_user_id" class="form-select bg-dark border-secondary text-white" <?php echo Policy::isEmployee() ? 'disabled' : ''; ?>>
            <option value="">Leave unassigned</option>
            <?php foreach ($assignees as $user): ?>
                <option value="<?php echo $user->user_id; ?>" <?php echo (string) $assigned_to_user_id === (string) $user->user_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user->name); ?> (<?php echo htmlspecialchars($user->role_name); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label for="team_id" class="form-label text-secondary">Team</label>
        <select name="team_id" id="team_id" class="form-select bg-dark border-secondary text-white">
            <option value="">No team</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?php echo $team->team_id; ?>" <?php echo (string) $team_id === (string) $team->team_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($team->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label for="lead_source" class="form-label text-secondary">Lead Source *</label>
        <select name="lead_source" id="lead_source" class="form-select <?php echo (!empty($source_err)) ? 'is-invalid' : ''; ?> bg-dark border-secondary text-white" required>
            <option value="">Select source</option>
            <?php foreach ($sources as $source): ?>
                <option value="<?php echo htmlspecialchars($source->name); ?>" <?php echo $lead_source === $source->name ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($source->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="invalid-feedback"><?php echo htmlspecialchars($source_err); ?></div>
    </div>

    <div class="col-md-4">
        <label for="campaign_source" class="form-label text-secondary">Campaign Source</label>
        <input type="text" name="campaign_source" id="campaign_source" class="form-control" value="<?php echo htmlspecialchars($campaign_source ?? ''); ?>" placeholder="e.g. Q3 webinar">
    </div>

    <div class="col-md-4">
        <label for="product_id" class="form-label text-secondary">Product</label>
        <select name="product_id" id="product_id" class="form-select bg-dark border-secondary text-white">
            <option value="">No product</option>
            <?php foreach ($products as $product): ?>
                <option value="<?php echo $product->product_id; ?>" <?php echo (string) $product_id === (string) $product->product_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($product->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label for="status" class="form-label text-secondary">Pipeline Stage</label>
        <select name="status" id="status" class="form-select bg-dark border-secondary text-white">
            <?php foreach ($statuses as $statusOption): ?>
                <option value="<?php echo $statusOption; ?>" <?php echo $status === $statusOption ? 'selected' : ''; ?>><?php echo strtoupper($statusOption); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label for="lead_quality" class="form-label text-secondary">Quality</label>
        <select name="lead_quality" id="lead_quality" class="form-select bg-dark border-secondary text-white">
            <?php foreach ($qualities as $quality): ?>
                <option value="<?php echo $quality; ?>" <?php echo $lead_quality === $quality ? 'selected' : ''; ?>><?php echo strtoupper($quality); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label for="priority" class="form-label text-secondary">Priority</label>
        <select name="priority" id="priority" class="form-select bg-dark border-secondary text-white">
            <?php foreach ($priorities as $priorityOption): ?>
                <option value="<?php echo $priorityOption; ?>" <?php echo $priority === $priorityOption ? 'selected' : ''; ?>><?php echo strtoupper($priorityOption); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label for="probability" class="form-label text-secondary">Probability (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="probability" id="probability" class="form-control" value="<?php echo htmlspecialchars($probability); ?>">
    </div>

    <div class="col-md-4">
        <label for="lead_value" class="form-label text-secondary">Expected Value ($)</label>
        <input type="number" step="0.01" min="0.01" name="lead_value" id="lead_value" class="form-control" value="<?php echo htmlspecialchars($lead_value); ?>">
    </div>

    <div class="col-md-4">
        <label for="next_follow_up_at" class="form-label text-secondary">Next Follow-up</label>
        <input type="datetime-local" name="next_follow_up_at" id="next_follow_up_at" class="form-control" value="<?php echo htmlspecialchars($next_follow_up_at ?? ''); ?>">
    </div>

    <div class="col-md-4">
        <label for="location" class="form-label text-secondary">Location</label>
        <input type="text" name="location" id="location" class="form-control" value="<?php echo htmlspecialchars($location ?? ''); ?>" placeholder="City / region">
    </div>

    <div class="col-12">
        <label for="lost_reason" class="form-label text-secondary">Lost Reason</label>
        <input type="text" name="lost_reason" id="lost_reason" class="form-control" value="<?php echo htmlspecialchars($lost_reason ?? ''); ?>" placeholder="Required only when lost">
    </div>
</div>
