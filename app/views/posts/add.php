<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="pulse-card">
            <h4 class="text-white mb-4"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Create Content Post</h4>
            
            <form action="index.php?route=posts/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="row g-3">
                    <!-- Post ID (Auto-generated) -->
                    <div class="col-md-6">
                        <label for="post_code" class="form-label text-secondary">Post ID</label>
                        <input type="text" name="post_code" id="post_code" class="form-control bg-dark border-secondary text-secondary font-monospace" value="<?php echo htmlspecialchars($post_code ?? 'Auto-generated (e.g. PST-2026-00001)'); ?>" readonly disabled>
                    </div>

                    <!-- Client Company -->
                    <div class="col-md-6">
                        <label for="client_id" class="form-label text-secondary">Client Company</label>
                        <select name="client_id" id="client_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">-- Select Client (Optional) --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client->client_id; ?>" <?php echo (string)($client_id ?? '') === (string)$client->client_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client->company_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Post Title -->
                    <div class="col-md-12">
                        <label for="title" class="form-label text-secondary">Post Title *</label>
                        <input type="text" name="title" id="title" 
                               class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($title_input ?? ''); ?>" placeholder="e.g. Product Launch Announcement Video" required>
                        <div class="invalid-feedback"><?php echo $title_err ?? ''; ?></div>
                    </div>

                    <!-- Platform -->
                    <div class="col-md-6">
                        <label for="platform" class="form-label text-secondary">Platform *</label>
                        <select name="platform" id="platform" class="form-select bg-dark border-secondary text-white" required>
                            <?php 
                                $platforms = ['Instagram', 'LinkedIn', 'Facebook', 'X/Twitter', 'YouTube', 'TikTok', 'Pinterest', 'Blog', 'Other'];
                                foreach ($platforms as $p): 
                            ?>
                                <option value="<?php echo $p; ?>" <?php echo ($platform ?? 'LinkedIn') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Content Type -->
                    <div class="col-md-6">
                        <label for="content_type" class="form-label text-secondary">Content Type *</label>
                        <select name="content_type" id="content_type" class="form-select bg-dark border-secondary text-white" required>
                            <?php 
                                $contentTypes = ['Image Post', 'Video', 'Reel/Short', 'Carousel', 'Story', 'Blog Article', 'Infographic', 'GIF', 'Live Stream', 'Other'];
                                foreach ($contentTypes as $ct): 
                            ?>
                                <option value="<?php echo $ct; ?>" <?php echo ($content_type ?? 'Image Post') === $ct ? 'selected' : ''; ?>><?php echo $ct; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Linked Marketing Campaign -->
                    <div class="col-md-6">
                        <label for="campaign_id" class="form-label text-secondary">Marketing Campaign</label>
                        <select name="campaign_id" id="campaign_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">-- Select Campaign (Optional) --</option>
                            <?php foreach ($campaigns as $cmp): ?>
                                <option value="<?php echo $cmp->campaign_id; ?>" <?php echo (string)($campaign_id ?? '') === (string)$cmp->campaign_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cmp->name); ?> (<?php echo htmlspecialchars($cmp->company_name); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Published Date -->
                    <div class="col-md-6">
                        <label for="published_at" class="form-label text-secondary">Published / Scheduled Date *</label>
                        <input type="datetime-local" name="published_at" id="published_at" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($published_at ?? date('Y-m-d\TH:i')); ?>" required>
                    </div>

                    <!-- Media URL -->
                    <div class="col-md-12">
                        <label for="media_url" class="form-label text-secondary">Media Asset URL / Storage Link</label>
                        <input type="url" name="media_url" id="media_url" class="form-control" value="<?php echo htmlspecialchars($media_url ?? ''); ?>" placeholder="https://storage.googleapis.com/raptor-assets/post_image.jpg">
                    </div>

                    <!-- Post Body Content -->
                    <div class="col-md-12">
                        <label for="content" class="form-label text-secondary">Post Content / Caption</label>
                        <textarea name="content" id="content" rows="4" class="form-control" placeholder="Write caption or article summary..."><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label for="status" class="form-label text-secondary">Status</label>
                        <select name="status" id="status" class="form-select bg-dark border-secondary text-white">
                            <option value="published" <?php echo ($status ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="scheduled" <?php echo ($status ?? '') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="draft" <?php echo ($status ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                        <a href="index.php?route=posts/index" class="btn btn-outline-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Create Post</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
