<?php
// Raptor CRM Content Posts Controller

class PostsController extends Controller {
    private $postModel;
    private $clientModel;
    private $campaignModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('social_media', 'view');

        $this->postModel = $this->model('ContentPost');
        $this->clientModel = $this->model('Client');
        $this->campaignModel = $this->model('Campaign');
    }

    public function index() {
        $filters = [
            'client_id' => $_GET['client_id'] ?? '',
            'platform' => $_GET['platform'] ?? '',
            'content_type' => $_GET['content_type'] ?? '',
            'search' => $_GET['search'] ?? '',
            'sort' => $_GET['sort'] ?? 'published_at'
        ];

        $posts = $this->postModel->getPosts($filters);
        $clients = $this->clientModel->getClients();
        $aggregatedDemographics = $this->postModel->getAggregatedDemographics($filters['client_id'] ?: null);

        $data = [
            'title' => 'Content Management & Analytics | Raptor CRM',
            'active_tab' => 'calendar',
            'posts' => $posts,
            'clients' => $clients,
            'filters' => $filters,
            'aggregated_demographics' => $aggregatedDemographics,
            'can_edit' => in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager', 'employee', 'sales_person', 'analyst', 'hr'])
        ];

        $this->viewWithLayout('posts/index', 'main', $data);
    }

    public function add() {
        $this->requirePermission('social_media', 'create');

        $clients = $this->clientModel->getClients();
        $campaigns = $this->campaignModel->getCampaigns();

        $data = [
            'title' => 'Create Content Post | Raptor CRM',
            'active_tab' => 'calendar',
            'clients' => $clients,
            'campaigns' => $campaigns,
            'post_code' => $this->postModel->generatePostCode(),
            'client_id' => '',
            'campaign_id' => '',
            'title_input' => '',
            'content' => '',
            'media_url' => '',
            'content_type' => 'Image Post',
            'platform' => 'LinkedIn',
            'status' => 'published',
            'scheduled_at' => '',
            'published_at' => date('Y-m-d\TH:i'),
            'title_err' => '',
            'post_code_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data['post_code'] = trim($_POST['post_code'] ?? '');
            $data['client_id'] = trim($_POST['client_id'] ?? '');
            $data['campaign_id'] = trim($_POST['campaign_id'] ?? '');
            $data['title_input'] = trim($_POST['title'] ?? '');
            $data['title'] = trim($_POST['title'] ?? '');
            $data['content'] = trim($_POST['content'] ?? '');
            $data['media_url'] = trim($_POST['media_url'] ?? '');
            $data['content_type'] = trim($_POST['content_type'] ?? 'Image Post');
            $data['platform'] = trim($_POST['platform'] ?? 'LinkedIn');
            $data['status'] = trim($_POST['status'] ?? 'published');
            $data['scheduled_at'] = !empty($_POST['scheduled_at']) ? str_replace('T', ' ', $_POST['scheduled_at']) : null;
            $data['published_at'] = !empty($_POST['published_at']) ? str_replace('T', ' ', $_POST['published_at']) : date('Y-m-d H:i:s');

            if (empty($data['post_code'])) {
                $data['post_code'] = $this->postModel->generatePostCode();
            } else {
                if (!$this->postModel->isPostCodeUnique($data['post_code'])) {
                    $data['post_code_err'] = 'This Post ID already exists. Please enter a unique Post ID.';
                }
            }

            if (empty($data['title'])) {
                $data['title_err'] = 'Please enter a post title';
            }

            if (empty($data['title_err']) && empty($data['post_code_err'])) {
                if ($this->postModel->addPost($data)) {
                    $this->redirect('index.php?route=posts/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('posts/add', 'main', $data);
    }

    public function edit($id) {
        $this->requirePermission('social_media', 'edit');

        $post = $this->postModel->getPostById($id);
        if (!$post) {
            $this->redirect('index.php?route=posts/index');
        }

        $clients = $this->clientModel->getClients();
        $campaigns = $this->campaignModel->getCampaigns();

        $data = [
            'title' => 'Edit Content Post | Raptor CRM',
            'active_tab' => 'calendar',
            'clients' => $clients,
            'campaigns' => $campaigns,
            'post_id' => $post->post_id,
            'post_code' => $post->post_code ?: ('PST-' . date('Y') . '-' . sprintf('%05d', $post->post_id)),
            'client_id' => $post->client_id,
            'campaign_id' => $post->campaign_id,
            'title_input' => $post->title,
            'content' => $post->content,
            'media_url' => $post->media_url,
            'content_type' => $post->content_type ?? 'Image Post',
            'platform' => $post->platform ?? 'LinkedIn',
            'status' => $post->status ?? 'published',
            'scheduled_at' => $post->scheduled_at ? date('Y-m-d\TH:i', strtotime($post->scheduled_at)) : '',
            'published_at' => $post->published_at ? date('Y-m-d\TH:i', strtotime($post->published_at)) : '',
            'title_err' => '',
            'post_code_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            // Post ID is locked once created and cannot be modified
            $data['post_code'] = $post->post_code ?: ('PST-' . date('Y') . '-' . sprintf('%05d', $post->post_id));
            $data['client_id'] = trim($_POST['client_id'] ?? '');
            $data['campaign_id'] = trim($_POST['campaign_id'] ?? '');
            $data['title_input'] = trim($_POST['title'] ?? '');
            $data['title'] = trim($_POST['title'] ?? '');
            $data['content'] = trim($_POST['content'] ?? '');
            $data['media_url'] = trim($_POST['media_url'] ?? '');
            $data['content_type'] = trim($_POST['content_type'] ?? 'Image Post');
            $data['platform'] = trim($_POST['platform'] ?? 'LinkedIn');
            $data['status'] = trim($_POST['status'] ?? 'published');
            $data['scheduled_at'] = !empty($_POST['scheduled_at']) ? str_replace('T', ' ', $_POST['scheduled_at']) : null;
            $data['published_at'] = !empty($_POST['published_at']) ? str_replace('T', ' ', $_POST['published_at']) : null;

            if (empty($data['post_code'])) {
                $data['post_code_err'] = 'Post ID cannot be empty.';
            } else {
                if (!$this->postModel->isPostCodeUnique($data['post_code'], $id)) {
                    $data['post_code_err'] = 'This Post ID is already in use by another post.';
                }
            }

            if (empty($data['title'])) {
                $data['title_err'] = 'Please enter a post title';
            }

            if (empty($data['title_err']) && empty($data['post_code_err'])) {
                if ($this->postModel->updatePost($data)) {
                    $this->redirect('index.php?route=posts/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('posts/edit', 'main', $data);
    }

    public function detail($id) {
        $post = $this->postModel->getPostById($id);
        if (!$post) {
            $this->redirect('index.php?route=posts/index');
        }

        $history = $this->postModel->getEngagementHistory($id);
        $demographics = $this->postModel->getLatestDemographics($id);
        $demographicsHistory = $this->postModel->getDemographicsHistory($id);

        $data = [
            'title' => 'Post Insights & Analytics | Raptor CRM',
            'active_tab' => 'calendar',
            'post' => $post,
            'history' => $history,
            'demographics' => $demographics,
            'demographics_history' => $demographicsHistory
        ];

        $this->viewWithLayout('posts/detail', 'main', $data);
    }

    public function updateEngagement() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawPost = $_POST;
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $postId = (int)($_POST['post_id'] ?? 0);
            if ($postId <= 0) {
                $this->redirect('index.php?route=posts/index');
            }

            // 1. Record Engagement metrics
            $engagementData = [
                'post_id' => $postId,
                'likes' => (int)($_POST['likes'] ?? 0),
                'comments' => (int)($_POST['comments'] ?? 0),
                'shares' => (int)($_POST['shares'] ?? 0),
                'saves' => (int)($_POST['saves'] ?? 0),
                'reach' => (int)($_POST['reach'] ?? 0),
                'impressions' => (int)($_POST['impressions'] ?? 0),
                'clicks' => (int)($_POST['clicks'] ?? 0),
                'video_views' => (int)($_POST['video_views'] ?? 0)
            ];
            $this->postModel->recordEngagement($engagementData);

            // 2. Also sync to Social Analytics History timeline
            try {
                $post = $this->postModel->getPostById($postId);
                if ($post) {
                    $analyticsModel = $this->model('AnalyticsEntry');
                    $analyticsModel->logEntry([
                        'platform_id' => $post->platform_id ?? 209,
                        'account_id' => $post->account_id ?? 352,
                        'post_id' => $postId,
                        'likes' => (int)($_POST['likes'] ?? 0),
                        'comments' => (int)($_POST['comments'] ?? 0),
                        'shares' => (int)($_POST['shares'] ?? 0),
                        'views' => max(1, (int)($_POST['reach'] ?? $_POST['views'] ?? 1)),
                        'reach' => (int)($_POST['reach'] ?? 0),
                        'impressions' => (int)($_POST['impressions'] ?? 0),
                        'clicks' => (int)($_POST['clicks'] ?? 0),
                        'followers_gained' => (int)($_POST['followers_gained'] ?? 0),
                        'leads_generated' => (int)($_POST['leads_generated'] ?? 0),
                        'lead_details' => trim($_POST['lead_details'] ?? ''),
                        'custom_notes' => trim($_POST['custom_notes'] ?? 'Engagement updated via Content Management'),
                        'updated_by' => $_SESSION['user_id'] ?? 482
                    ]);
                }
            } catch (Exception $e) {
                error_log("Failed to log social analytics history: " . $e->getMessage());
            }

            // 2. Record Audience Demographics if provided
            if (isset($_POST['followers_pct']) || isset($_POST['non_followers_pct'])) {
                $countries = [];
                if (!empty($rawPost['country_name']) && is_array($rawPost['country_name'])) {
                    foreach ($rawPost['country_name'] as $idx => $cname) {
                        $cpct = (float)($rawPost['country_pct'][$idx] ?? 0);
                        if (!empty($cname) && $cpct > 0) {
                            $countries[] = ['country' => $cname, 'percentage' => $cpct];
                        }
                    }
                }

                $demoData = [
                    'post_id' => $postId,
                    'followers_pct' => (float)($_POST['followers_pct'] ?? 0),
                    'non_followers_pct' => (float)($_POST['non_followers_pct'] ?? 0),
                    'age' => [
                        '13-17' => (float)($_POST['age_13_17'] ?? 0),
                        '18-24' => (float)($_POST['age_18_24'] ?? 0),
                        '25-34' => (float)($_POST['age_25_34'] ?? 0),
                        '35-44' => (float)($_POST['age_35_44'] ?? 0),
                        '45-54' => (float)($_POST['age_45_54'] ?? 0),
                        '55-64' => (float)($_POST['age_55_64'] ?? 0),
                        '65+' => (float)($_POST['age_65_plus'] ?? 0),
                    ],
                    'gender' => [
                        'Men' => (float)($_POST['gender_men'] ?? 0),
                        'Women' => (float)($_POST['gender_women'] ?? 0),
                        'Other' => (float)($_POST['gender_other'] ?? 0),
                    ],
                    'countries' => $countries
                ];
                $this->postModel->recordDemographics($demoData);
            }

            $this->redirect('index.php?route=posts/index');
        }
    }

    // Delete Post (Admin Only with Mandatory Remarks Audit Log)
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Strict Admin Role Check
            $userRole = strtolower($_SESSION['user_role'] ?? '');
            if ($userRole !== 'admin') {
                $_SESSION['flash_error'] = 'Access Denied: Only Admin users are authorized to delete posts.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            // CSRF Security Verification
            $token = $_POST['csrf_token'] ?? '';
            if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
                $_SESSION['flash_error'] = 'Invalid security token.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            $postId = (int)($_POST['post_id'] ?? 0);
            $remarks = trim($_POST['deletion_remarks'] ?? '');

            if ($postId <= 0) {
                $_SESSION['flash_error'] = 'Invalid Post ID specified for deletion.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            if (empty($remarks)) {
                $_SESSION['flash_error'] = 'Deletion failed: Mandatory deletion remarks/reason must be provided.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $userName = $_SESSION['user_name'] ?? 'Admin User';

            if ($this->postModel->deletePostWithAudit($postId, $remarks, $userId, $userName)) {
                $_SESSION['flash_success'] = 'Post deleted successfully and logged to security archives with your remarks.';
            } else {
                $_SESSION['flash_error'] = 'Unable to delete post record.';
            }

            $this->redirect('index.php?route=posts/index');
        }
    }

    // Delete Multiple Posts (Admin Only Bulk Action with Mandatory Remarks Audit Log)
    public function deleteMultiple() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Strict Admin Role Check
            $userRole = strtolower($_SESSION['user_role'] ?? '');
            if ($userRole !== 'admin') {
                $_SESSION['flash_error'] = 'Access Denied: Only Admin users are authorized to delete posts.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            // CSRF Security Verification
            $token = $_POST['csrf_token'] ?? '';
            if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
                $_SESSION['flash_error'] = 'Invalid security token.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            $rawIds = $_POST['post_ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = explode(',', $rawIds);
            }
            $postIds = array_filter(array_map('intval', (array)$rawIds), function($id) { return $id > 0; });
            $remarks = trim($_POST['deletion_remarks'] ?? '');

            if (empty($postIds)) {
                $_SESSION['flash_error'] = 'Please select at least one post to delete.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            if (empty($remarks)) {
                $_SESSION['flash_error'] = 'Deletion failed: Mandatory deletion remarks/reason must be provided.';
                $this->redirect('index.php?route=posts/index');
                return;
            }

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $userName = $_SESSION['user_name'] ?? 'Admin User';

            $deletedCount = $this->postModel->deleteMultiplePostsWithAudit($postIds, $remarks, $userId, $userName);
            if ($deletedCount > 0) {
                $_SESSION['flash_success'] = "Successfully deleted {$deletedCount} post(s) and logged to security archives.";
            } else {
                $_SESSION['flash_error'] = 'Unable to delete selected post records.';
            }

            $this->redirect('index.php?route=posts/index');
        }
    }
}

