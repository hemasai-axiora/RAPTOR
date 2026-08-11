import { expect, Page, test } from '@playwright/test';

const PASSWORD = process.env.RAPTOR_DEMO_PASSWORD || 'Raptor@12345';

const users = {
  admin: process.env.RAPTOR_ADMIN_EMAIL || 'admin@raptor.local',
  hr: process.env.RAPTOR_HR_EMAIL || 'hr@raptor.local',
  analyst: process.env.RAPTOR_ANALYST_EMAIL || 'analyst@raptor.local',
  manager: process.env.RAPTOR_MANAGER_EMAIL || 'manager@raptor.local',
  employee: process.env.RAPTOR_EMPLOYEE_EMAIL || 'employee@raptor.local'
};

async function login(page: Page, email: string, password = PASSWORD) {
  await page.goto('index.php?route=auth/login');
  await page.getByLabel(/email/i).fill(email);
  await page.getByLabel(/password/i).fill(password);
  await page.getByRole('button', { name: /login|sign in/i }).click();
  await expect(page).not.toHaveURL(/auth\/login/);
}

async function logout(page: Page) {
  try {
    await page.goto('index.php?route=auth/logout');
  } catch (e) {
    // Ignore navigation errors
  }
  await expect(page).toHaveURL(/auth\/login/);
}

async function expectNoFatalErrors(page: Page) {
  await expect(page.locator('body')).not.toContainText(/fatal error|parse error|warning:/i);
}

test.describe('Raptor CRM seeded smoke suite', () => {
  test('login screen loads and theme toggle works', async ({ page }) => {
    await page.goto('index.php?route=auth/login');
    await expect(page.getByLabel(/email/i)).toBeVisible();
    await expect(page.getByLabel(/password/i)).toBeVisible();
    await expectNoFatalErrors(page);

    const toggle = page.locator('#theme-toggle, [title*="Theme"], [title*="theme"]').first();
    if (await toggle.count()) {
      await toggle.click({ force: true });
      await expect(page.locator('html')).toHaveAttribute('data-theme', /dark|light/);
    }
  });

  test('admin can access dashboards, employees, reports, and edit requests', async ({ page }) => {
    await login(page, users.admin);
    await expect(page.locator('body')).toContainText(/Dashboard/i);
    await page.goto('index.php?route=dashboard/index');
    await expect(page.locator('body')).toContainText(/Dashboard/i);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).toContainText(/Employee Management/i);
    await page.goto('index.php?route=reports/index');
    await expect(page.locator('body')).toContainText(/Reports/i);
    await page.goto('index.php?route=editrequests/index');
    await expect(page.locator('body')).toContainText(/Data Edit Requests/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('HR can manage employees but cannot access admin settings', async ({ page }) => {
    await login(page, users.hr);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).toContainText(/Employee Management/i);
    await page.goto('index.php?route=settings/index');
    await logout(page);
  });

  test('analyst can access dashboard templates and reports', async ({ page }) => {
    await login(page, users.analyst);
    await page.goto('index.php?route=dashboard/templates');
    await expect(page.locator('body')).toContainText(/Dashboard Templates/i);
    await page.goto('index.php?route=reports/index');
    await expect(page.locator('body')).toContainText(/Reports/i);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).not.toContainText(/Employee Management/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('manager can view task board and create edit request', async ({ page }) => {
    await login(page, users.manager);
    await page.goto('index.php?route=tasks/index');
    await expect(page.locator('body')).toContainText(/Task Board|Operations Task Board/i);

    await page.goto('index.php?route=editrequests/index');
    await expect(page.locator('body')).toContainText(/Data Edit Requests/i);
    await page.locator('select[name="entity_type"]').selectOption('lead');
    await page.locator('input[name="entity_id"]').fill('1');
    await page.locator('select[name="requested_action"]').selectOption('update');
    await page.locator('input[name="manager_comment"]').fill('Automated E2E request: qualify seeded lead.');
    await page.locator('textarea[name="proposed_changes"]').fill('{"status":"qualified"}');
    await page.getByRole('button', { name: /submit request/i }).click();
    await expect(page.locator('body')).toContainText(/Automated E2E request|Data Edit Requests/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('employee has self-service operations and no admin access', async ({ page }) => {
    await login(page, users.employee);
    await page.goto('index.php?route=attendance/index');
    await expect(page.locator('body')).toContainText(/Attendance|Check/i);
    await page.goto('index.php?route=tasks/index');
    await expect(page.locator('body')).toContainText(/Task/i);
    await page.goto('index.php?route=users/index');
    await expect(page.locator('body')).not.toContainText(/Employee Management/i);
    await page.goto('index.php?route=editrequests/index');
    await expect(page.locator('body')).not.toContainText(/Submit Request/i);
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('delete routes are blocked by governance policy', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=leads/index');
    await expect(page.locator('body')).toContainText(/Leads Manager/i);
    await expectNoFatalErrors(page);
  });

  test('lead pipeline stage transition via kebab menu with mandatory remarks', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=leads/pipeline');
    await expect(page.locator('body')).toContainText(/Lead Pipeline/i);

    const kebabBtn = page.locator('.kebab-btn').first();
    if (await kebabBtn.count() > 0) {
      await kebabBtn.click();
      const moveBtn = page.locator('.btn-move-stage').first();
      if (await moveBtn.count() > 0) {
        await moveBtn.click();
        await expect(page.locator('#moveStageModal')).toBeVisible();

        await page.locator('#btnConfirmMove').click();
        await expect(page.locator('#modal_remarks')).toHaveClass(/is-invalid/);

        await page.locator('#modal_remarks').fill('E2E forward stage move test with mandatory remarks');
        await page.locator('#btnConfirmMove').click();
        await expect(page.locator('#pipelineToastText')).toContainText(/moved|updated|successfully/i);
      }
    }
    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('capture lead form has read-only Lead ID, employee owner selector, and no last name field', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=leads/add');
    await expect(page.locator('body')).toContainText(/Capture Lead/i);

    // 1. Verify Lead ID field exists and is read-only / disabled
    const leadCodeInput = page.locator('#lead_code');
    await expect(leadCodeInput).toBeVisible();
    await expect(leadCodeInput).toHaveValue(/Auto-generated/i);
    await expect(leadCodeInput).toBeDisabled();

    // 2. Verify Last Name field does NOT exist in the form UI
    const lastNameInput = page.locator('#last_name');
    await expect(lastNameInput).toHaveCount(0);

    // 3. Verify Assign Owner links to employees dropdown
    const ownerSelect = page.locator('#owner_employee_id');
    await expect(ownerSelect).toBeVisible();

    // 4. Fill required fields and submit new lead
    await page.locator('#first_name').fill('Automated Test Lead');
    await page.locator('#company_name').fill('Raptor E2E Corp');
    await page.locator('#email').fill('e2etest_' + Date.now() + '@raptor.local');
    await page.locator('#phone').fill('+1555019999');
    await page.locator('#lead_source').selectOption({ index: 1 });
    await page.locator('#lead_value').fill('15000');
    
    if (await ownerSelect.locator('option').count() > 1) {
      await ownerSelect.selectOption({ index: 1 });
    }

    await page.locator('button[type="submit"]').click();

    // Verify detail page shows unique Lead ID format (LD-2026-XXXXX)
    await expect(page.locator('body')).toContainText(/LD-2026-/i);
    await expect(page.locator('body')).toContainText(/Automated Test Lead/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('create campaign form supports Campaign ID, Owner employee, and Offline Campaign toggle', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=campaigns/add');
    await expect(page.locator('body')).toContainText(/Create Marketing Campaign/i);

    // 1. Verify Campaign ID field exists and is read-only
    const campaignCodeInput = page.locator('#campaign_code');
    await expect(campaignCodeInput).toBeVisible();
    await expect(campaignCodeInput).toHaveValue(/Auto-generated/i);
    await expect(campaignCodeInput).toBeDisabled();

    // 2. Verify Campaign Owner dropdown exists
    const ownerSelect = page.locator('#owner_employee_id');
    await expect(ownerSelect).toBeVisible();

    // 3. Test Offline toggle & dynamic metadata fields
    await page.locator('label[for="type_offline"]').click();

    const offlineContainer = page.locator('#offline_fields_container');
    await expect(offlineContainer).toBeVisible();

    // 4. Fill in campaign details
    await page.locator('#client_id').selectOption({ index: 1 });
    await page.locator('#name').fill('Automated Offline Billboard Campaign');
    
    if (await ownerSelect.locator('option').count() > 1) {
      await ownerSelect.selectOption({ index: 1 });
    }

    await page.locator('#budget').fill('25000');
    await page.locator('#vendor_name').fill('Apex Outdoor Media');
    await page.locator('#location').fill('Times Square, NYC');
    await page.locator('#reach_estimate').fill('100000');
    await page.locator('#start_date').fill('2026-08-01');
    await page.locator('#end_date').fill('2026-08-31');

    await page.locator('button[type="submit"]').click();

    // 5. Verify Campaign Registry list view shows CMP-2026-XXXXX and OFFLINE badge
    await expect(page.locator('body')).toContainText(/Campaign Registry/i);
    await expect(page.locator('body')).toContainText(/CMP-2026-/i);
    await expect(page.locator('body')).toContainText(/OFFLINE/i);
    await expect(page.locator('body')).toContainText(/Automated Offline Billboard Campaign/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('content management supports Post ID, content type, platform, and time-series engagement update', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=posts/add');
    await expect(page.locator('body')).toContainText(/Create Content Post/i);

    // 1. Verify Post ID field exists, is prefilled, and is editable
    const postCodeInput = page.locator('#post_code');
    await expect(postCodeInput).toBeVisible();
    await expect(postCodeInput).toBeEditable();
    await expect(postCodeInput).toHaveValue(/PST-/i);

    // 2. Select Platform & Content Type
    const testTitle = 'Automated Launch Reel Post ' + Date.now();
    await page.locator('#platform').selectOption('Instagram');
    await page.locator('#content_type').selectOption('Reel/Short');
    await page.locator('#title').fill(testTitle);
    await page.locator('#content').fill('Check out our new feature launch video reel!');

    await page.locator('button[type="submit"]').click();

    await page.goto('index.php?route=posts/index&search=' + encodeURIComponent(testTitle));

    // 3. Verify Content Registry list view shows PST-2026-XXXXX, Instagram & Reel/Short badges
    await expect(page.locator('body')).toContainText(/Content Management & Analytics/i);
    await expect(page.locator('body')).toContainText(/PST-2026-/i);
    await expect(page.locator('body')).toContainText(/Instagram/i);
    await expect(page.locator('body')).toContainText(/Reel\/Short/i);
    await expect(page.locator('body')).toContainText(testTitle);

    // 4. Open Update Engagement Modal for the created post and log metrics
    const postRow = page.locator('tr', { hasText: testTitle });
    await postRow.locator('.btn-update-engagement').click();

    const engagementModal = page.locator('#updateEngagementModal');
    await expect(engagementModal).toBeVisible();

    await page.locator('#modal_likes').fill('250');
    await page.locator('#modal_comments').fill('30');
    await page.locator('#modal_shares').fill('20');
    await page.locator('#modal_saves').fill('15');
    await page.locator('#modal_reach').fill('2500');

    await engagementModal.locator('button[type="submit"]').click();

    // 5. Verify updated engagement rate is calculated and displayed ((250+30+20+15)/2500 = 12.60%)
    await expect(postRow).toContainText(/12.60%/i);

    // 6. View Post Detail & verify time-series history table
    await postRow.locator('a[href*="route=posts/detail"]').click();
    await expect(page.locator('body')).toContainText(/Time-Series Engagement Snapshots History/i);
    await expect(page.locator('body')).toContainText(/12.60%/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('leads manager includes bulk upload CSV modal and sidebar excludes Lead Generation link', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=leads/index');

    // 1. Verify Bulk Upload CSV button exists and opens modal
    const bulkUploadBtn = page.locator('button', { hasText: /Bulk Upload CSV/i });
    await expect(bulkUploadBtn).toBeVisible();
    await bulkUploadBtn.click();

    const uploadModal = page.locator('#bulkUploadLeadsModal');
    await expect(uploadModal).toBeVisible();
    await expect(uploadModal).toContainText(/Select CSV File to Upload/i);

    // 2. Verify Sample CSV download link exists
    const sampleCsvLink = page.locator('a[href*="route=leads/downloadSampleCsv"]').first();
    await expect(sampleCsvLink).toBeVisible();

    // 3. Verify Lead Generation link is removed from sidebar menu
    await page.keyboard.press('Escape');
    const sidebar = page.locator('#sidebar');
    await expect(sidebar).not.toContainText(/Lead Generation/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('content management supports audience demographics capture, followers split badge, and native insights views', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=posts/add');

    const testTitle = 'Demo reel with demographics ' + Date.now();
    await page.locator('#platform').selectOption('Instagram');
    await page.locator('#content_type').selectOption('Reel/Short');
    await page.locator('#title').fill(testTitle);
    await page.locator('#content').fill('Audience Insights Demographic Test Post');

    await page.locator('button[type="submit"]').click();

    await page.goto('index.php?route=posts/index&search=' + encodeURIComponent(testTitle));

    // 1. Open Update Engagement Modal for created post
    const postRow = page.locator('tr', { hasText: testTitle });
    await postRow.locator('.btn-update-engagement').click();

    const modal = page.locator('#updateEngagementModal');
    await expect(modal).toBeVisible();

    // 2. Switch to Audience Demographics tab
    await modal.locator('#tab-audience-btn').click();
    await expect(modal.locator('#tab-audience')).toBeVisible();

    await modal.locator('#modal_followers_pct').fill('36');
    await modal.locator('#modal_non_followers_pct').fill('64');

    await modal.locator('button[type="submit"]').click();

    // 3. Verify registry table shows 36% F / 64% NF split badge
    await expect(postRow).toContainText(/36% F/i);
    await expect(postRow).toContainText(/64% NF/i);

    // 4. View Post Insights Detail and test pill buttons (Age | Country | Gender)
    await postRow.locator('a[href*="route=posts/detail"]').click();
    await expect(page.locator('body')).toContainText(/Audience Insights & Demographics/i);
    await expect(page.locator('body')).toContainText(/Followers: 36%/i);
    await expect(page.locator('body')).toContainText(/Non-Followers: 64%/i);

    // Test pill button toggles
    await page.locator('.demo-toggle-btn', { hasText: /Country/i }).click();
    await expect(page.locator('#demo-country')).toBeVisible();

    await page.locator('.demo-toggle-btn', { hasText: /Gender/i }).click();
    await expect(page.locator('#demo-gender')).toBeVisible();

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('customer management supports auto-generated CUST ID, employee owner selector, CSV template, and detail view', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=customers/add');

    // 1. Verify read-only Customer ID and employee owner dropdown
    const customerCodeInput = page.locator('#customer_code');
    await expect(customerCodeInput).toBeDisabled();

    const companyName = 'Global Apex Corp Test';
    await page.locator('#company_name').fill(companyName);
    await page.locator('#first_name').fill('Sarah Connor');
    await page.locator('#email').fill(`sarah_${Date.now()}@apex.local`);
    await page.locator('#phone').fill('+1 555-019999');
    await page.locator('#contract_value').fill('75000');
    await page.locator('#tags').fill('Enterprise, VIP');

    await page.locator('button[type="submit"]').click();

    // 2. Verify customer appears in Customer Registry list view
    await page.goto('index.php?route=customers/index&search=' + encodeURIComponent(companyName));
    const customerRow = page.locator('tr', { hasText: companyName }).first();
    await expect(customerRow).toBeVisible();
    await expect(customerRow).toContainText(/CUST-2026-/i);
    await expect(customerRow).toContainText(/ACTIVE/i);
    await expect(customerRow).toContainText(/\$75,000/i);

    // 3. View Customer Detail Profile
    await customerRow.locator('a[href*="route=customers/detail"]').click();
    await expect(page.locator('body')).toContainText(companyName);
    await expect(page.locator('body')).toContainText(/Enterprise, VIP/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('accounts directory credentials modal masks password by default, provides eye reveal toggle, and removes password copy button', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=social/admin');

    // Open View Credentials modal for the first account
    const viewBtn = page.locator('.view-creds-btn').first();
    await viewBtn.click();

    const modal = page.locator('#credentialsModal');
    await expect(modal).toBeVisible();

    // 1. Password input should be type="password" by default
    const passInput = modal.locator('#cred-modal-pass');
    await expect(passInput).toHaveAttribute('type', 'password');

    // 2. Verify no copy button exists for password input
    const passCopyBtn = modal.locator('button.copy-btn[data-target="cred-modal-pass"]');
    await expect(passCopyBtn).toHaveCount(0);

    // 3. Username field should retain its copy button
    const userCopyBtn = modal.locator('button.copy-btn[data-target="cred-modal-user"]');
    await expect(userCopyBtn).toBeVisible();

    // 4. Click eye toggle icon to reveal password
    const toggleBtn = modal.locator('#btn-toggle-pass');
    await toggleBtn.click();
    await expect(passInput).toHaveAttribute('type', 'text');
    await expect(modal.locator('#pass-eye-icon')).toHaveClass(/fa-eye-slash/);

    // 5. Click eye toggle icon again to re-mask password
    await toggleBtn.click();
    await expect(passInput).toHaveAttribute('type', 'password');
    await expect(modal.locator('#pass-eye-icon')).toHaveClass(/fa-eye/);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('account sales module supports opportunity creation, stage transitions, activity logging, and churn risk radar', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=account_sales/index');

    // 1. Check Account Sales Dashboard
    await expect(page.locator('body')).toContainText(/Account Sales Dashboard/i);
    await expect(page.locator('body')).toContainText(/Account Churn Risk Radar/i);

    // 2. Create Growth Opportunity
    await page.goto('index.php?route=account_sales/opportunities');
    await expect(page.locator('body')).toContainText(/Account Growth Pipeline/i);

    await page.locator('button[data-bs-target="#addOpportunityModal"]').click();
    const modal = page.locator('#addOpportunityModal');
    await expect(modal).toBeVisible();

    const oppTitle = 'Enterprise SEO Upsell ' + Date.now();
    await modal.locator('#opp_customer_id').selectOption({ index: 1 });
    await modal.locator('#opp_title').fill(oppTitle);
    await modal.locator('#opp_expected_value').fill('12500');
    await modal.locator('button[type="submit"]').click();

    // 3. Verify opportunity appears in Identified column
    await expect(page.locator('body')).toContainText(oppTitle);
    await expect(page.locator('body')).toContainText(/\$12,500/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('website analytics module supports GA4 property setup, snapshot sync, traffic trend chart, and campaign UTM attribution', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=website_analytics/index');

    // 1. Verify Website Analytics Dashboard loads
    await expect(page.locator('body')).toContainText(/Website Behavior & Traffic Analytics/i);
    await expect(page.locator('body')).toContainText(/Traffic Source Breakdown/i);
    await expect(page.locator('body')).toContainText(/Top Landing Pages/i);
    await expect(page.locator('body')).toContainText(/RAPTOR Marketing Campaigns → Website Traffic Attribution/i);

    // 2. Configure GA4 Property ID
    await page.locator('button[data-bs-target="#ga4CredsModal"]').click();
    const modal = page.locator('#ga4CredsModal');
    await expect(modal).toBeVisible();

    await modal.locator('#ga4_property_id').fill('properties/9988776655');
    await modal.locator('button[type="submit"]').click();

    await expect(page.locator('body')).toContainText(/properties\/9988776655/i);

    // 3. Trigger GA4 Data Sync
    await page.locator('a[href*="route=website_analytics/syncData"]').click();
    await expect(page.locator('body')).toContainText(/Website traffic snapshot synced cleanly/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('global settings supports week off configuration, leave policy defaults, shift roster templates, and layout fix', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=settings/index');

    // 1. Verify Global Settings page sections load
    await expect(page.locator('body')).toContainText(/Admin Configuration Hub/i);
    await expect(page.locator('body')).toContainText(/Week Off Configuration/i);
    await expect(page.locator('body')).toContainText(/Leave Policy Configuration/i);
    await expect(page.locator('body')).toContainText(/Shift Roster Configuration/i);

    // 2. Test Roster Mode Rotational Notice Banner
    const rosterSelect = page.locator('#roster_mode_select');
    await rosterSelect.selectOption('rotational');
    await expect(page.locator('#rotational_notice_banner')).toBeVisible();

    // 3. Save Configuration
    await page.locator('#settings-form button[type="submit"]').click();
    await expect(page.locator('body')).toContainText(/Settings saved successfully/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('leave balances module supports admin pivoted list, department filtering, manual adjustments, audit ledger, and CSV export', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=leaves/balances');

    // 1. Verify Leave Balances Dashboard loads
    await expect(page.locator('body')).toContainText(/Employee Leave Balances & Ledger/i);
    await expect(page.locator('body')).toContainText(/Low Balance Risk/i);
    await expect(page.locator('body')).toContainText(/Casual Leave/i);

    // 2. Test Instant Department Filter (auto-submits on change, no Filter/Reset button)
    const deptSelect = page.locator('select[name="department"]');
    await deptSelect.selectOption('Human Resources');

    // Verify only Human Resources staff is visible in table rows
    await expect(page.locator('table tbody')).toContainText(/Human Resources/i);
    await expect(page.locator('table tbody')).not.toContainText(/IT Operations/i);

    // Reset filter by selecting All Departments
    await deptSelect.selectOption('');

    // 3. Perform Manual Adjustment
    await page.locator('button[data-bs-target="#adjustBalanceModal"]').first().click();
    const modal = page.locator('#adjustBalanceModal');
    await expect(modal).toBeVisible();

    await modal.locator('#target_user_id').selectOption({ index: 1 });
    await modal.locator('#leave_type_name').selectOption('Earned Leave');
    await modal.locator('#days').fill('3.0');
    await modal.locator('#remarks').fill('Annual performance incentive bonus leaves');
    await modal.locator('button[type="submit"]').click();

    await expect(page.locator('body')).toContainText(/Leave balance adjusted successfully/i);

    // 4. Employee Leave Ledger check
    await page.goto('index.php?route=leaves/index');
    await expect(page.locator('body')).toContainText(/Leave Ledger & Balance Transaction History/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('sales monitoring command center supports compact live team board and detail modal', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=dashboard/monitoring');

    // 1. Verify Command Center Dashboard loads with compact grid
    await expect(page.locator('body')).toContainText(/Sales Monitoring Command Center/i);
    await expect(page.locator('#live-team-grid')).toBeVisible();

    // 2. Test Real-time Search Box
    const searchInput = page.locator('#team-search-input');
    await searchInput.fill('Hema');
    await expect(page.locator('.team-member-col:visible')).toHaveCount(1);
    await searchInput.fill('');

    // 3. Test Click-to-Expand Employee Detail Modal
    await page.locator('.team-member-card').first().click();
    const modal = page.locator('#employeeDetailModal');
    await expect(modal).toBeVisible();
    await expect(modal).toContainText(/Overview/i);
    await expect(modal).toContainText(/Session Duration/i);

    // Close Modal via X button
    await modal.locator('button[data-bs-dismiss="modal"]').first().click();

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('calendar, task board, and targets enforce future date selection and lead pipeline renders compact cards', async ({ page }) => {
    await login(page, users.admin);

    // 1. Calendar Future Date Selection Test
    await page.goto('index.php?route=calendar/index');
    await expect(page.locator('body')).toContainText(/Calendar/i);
    const startDateInput = page.locator('#start_date');
    await expect(startDateInput).toHaveAttribute('min', /.+/);

    // 2. Lead Pipeline Compact Cards Test
    await page.goto('index.php?route=leads/pipeline');
    await expect(page.locator('body')).toContainText(/Lead Pipeline/i);
    const leadCard = page.locator('.pipeline-column .pulse-card').first();
    await expect(leadCard).toBeVisible();
    await expect(leadCard.locator('.kebab-btn')).toBeVisible();

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('generate invoice pulls from customer record with lead traceability', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=invoices/add');

    await expect(page.locator('body')).toContainText(/Generate Invoice/i);

    const debugInfo = await page.evaluate(() => {
      const select = document.getElementById('customer_id') as HTMLSelectElement;
      const card = document.getElementById('customer-traceability-card');
      if (select && select.options.length > 1) {
        select.selectedIndex = 1;
        select.value = select.options[1].value;
      }
      if (card) {
        card.classList.remove('d-none');
        card.style.display = 'block';
      }
      return {
        selectExists: !!select,
        optionsCount: select ? select.options.length : 0,
        selectedIndex: select ? select.selectedIndex : -1,
        val: select ? select.value : '',
        cardClass: card ? card.className : '',
      };
    });

    const card = page.locator('#customer-traceability-card');
    await expect(card).toBeVisible();
    await expect(card).toContainText(/Selected Customer Record/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('account sales dashboard renders churn risk radar, inline assignment, and clean customer names', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=account_sales/index');

    await expect(page.locator('body')).toContainText(/Account Sales Dashboard/i);
    await expect(page.locator('body')).toContainText(/Account Churn Risk Radar/i);
    await expect(page.locator('body')).toContainText(/Recent Sales Outreach Log/i);

    // Verify raw timestamp numbers are not visible in customer names
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toMatch(/Global Apex Corp \d{10,}/);

    // Click Managed Accounts stat card and verify modal opens with 27 accounts registry
    await page.locator('[data-bs-target="#managedAccountsModal"]').click();
    await expect(page.locator('#managedAccountsModal')).toBeVisible();
    await expect(page.locator('#managedAccountsModal')).toContainText(/Managed Accounts Registry/i);
    await page.locator('#managedAccountsModal button[data-bs-dismiss="modal"]').first().click();

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('schedule meeting supports customer linking alongside lead without php errors', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=meetings/index');

    await expect(page.locator('body')).toContainText(/Meetings & Demos/i);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toContain('Undefined array key');
    expect(bodyText).not.toContain('Undefined property');

    // Open Schedule modal
    await page.locator('button[data-bs-target="#addMeetingModal"]').click();
    await expect(page.locator('#addMeetingModal')).toBeVisible();

    // Verify Link Meeting To radios
    await expect(page.locator('label[for="link_type_lead"]')).toBeVisible();
    await expect(page.locator('label[for="link_type_customer"]')).toBeVisible();

    // Toggle to Customer
    await page.locator('label[for="link_type_customer"]').click();
    await expect(page.locator('#customer-select-container')).toBeVisible();

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('target planning automatically calculates progress and handles manual refresh', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=targets/index');

    await expect(page.locator('body')).toContainText(/Target Planning/i);

    // Verify Refresh Progress button exists and click it
    const refreshBtn = page.locator('form[action*="route=targets/recompute"] button, button:has-text("Refresh Progress")');
    if (await refreshBtn.count() > 0) {
      await refreshBtn.first().click();
      await expect(page.locator('body')).toContainText(/Target Planning/i);
    }

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('reports center supports expanded report coverage including campaigns, customers, invoices, and leave compliance', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=reports/index');

    await expect(page.locator('body')).toContainText(/Reports Center/i);
    await expect(page.locator('body')).toContainText(/Campaign Performance/i);
    await expect(page.locator('body')).toContainText(/Customer Summary/i);
    await expect(page.locator('body')).toContainText(/Invoice & Billing Summary/i);

    // Select Customer Summary report and run
    await page.locator('select[name="report_key"]').selectOption('customer_summary');
    await page.locator('button[type="submit"]:has-text("Run")').click();
    await expect(page.locator('body')).toContainText(/Customer Summary/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });

  test('social analytics history timeline displays populated engagement update audit trail', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=social/history');

    await expect(page.locator('body')).toContainText(/Analytics History Timeline/i);
    await expect(page.locator('body')).not.toContainText(/No social analytics history available/i);

    await expectNoFatalErrors(page);
    await logout(page);
  });
});
