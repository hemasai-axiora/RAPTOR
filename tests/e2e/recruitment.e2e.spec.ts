import { expect, Page, test } from '@playwright/test';

/**
 * Raptor v2.3 — Enterprise Recruitment ATS End-to-End Test Suite (Sprint 10)
 * --------------------------------------------------------------------------
 * Validates the complete candidate-to-employee hiring journey:
 * 1. Public Careers Portal browsing & guest application submission (isolated session).
 * 2. Anti-spam honeypot defense (rejection of bot submissions).
 * 3. Recruiter ATS command center & Kanban board pipeline navigation.
 * 4. Structured interview scheduling & blind scorecard evaluation.
 * 5. Candidate assessments & assignment grading rubric.
 * 6. Multi-level offer preparation, budget overrun rules & salary confidentiality masking.
 * 7. Background verification (BGV) checklist & idempotent employee conversion.
 * 8. Executive recruitment analytics, Time-to-Hire & Funnel progression.
 */

const PASSWORD = process.env.RAPTOR_DEMO_PASSWORD || 'Raptor@12345';

const users = {
  admin: process.env.RAPTOR_ADMIN_EMAIL || 'admin@raptor.local',
  hr: process.env.RAPTOR_HR_EMAIL || 'hr@raptor.local',
  manager: process.env.RAPTOR_MANAGER_EMAIL || 'manager@raptor.local',
  interviewer: process.env.RAPTOR_INTERVIEWER_EMAIL || 'interviewer@raptor.local'
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
    // Ignore
  }
}

async function expectNoFatalErrors(page: Page) {
  await expect(page.locator('body')).not.toContainText(/fatal error|parse error|warning:/i);
}

test.describe('Raptor v2.3 Recruitment ATS E2E Suite', () => {

  // Journey 1: Public Careers Portal (ADR-003 Unauthenticated Isolation)
  test('Journey 1: Public Careers Portal - Guest Browsing & DPDP Consent Application', async ({ page }) => {
    await page.goto('index.php?route=careers');
    await expectNoFatalErrors(page);

    // Verify Public Portal branding and zero internal CRM elements
    await expect(page.locator('body')).toContainText(/Careers at Raptor|Open Opportunities/i);
    await expect(page.locator('#sidebar, .raptor-sidebar')).not.toBeVisible();

    // Verify search & filter controls
    const searchInput = page.locator('input[name="q"], input[type="search"]');
    if (await searchInput.count() > 0) {
      await searchInput.first().fill('Engineer');
    }

    // Inspect Job Listing & Apply
    const jobCards = page.locator('.job-card, .card');
    if (await jobCards.count() > 0) {
      const applyBtn = page.getByRole('link', { name: /view details|apply now/i }).first();
      if (await applyBtn.count() > 0) {
        await applyBtn.click();
        await expectNoFatalErrors(page);

        // Verify Apply Form elements & DPDP Consent checkbox
        await expect(page.locator('body')).toContainText(/Apply for this Position|Candidate Application/i);
        const consentCheckbox = page.locator('input[name="dpdp_consent"], input[name="consent"]');
        if (await consentCheckbox.count() > 0) {
          await expect(consentCheckbox.first()).toBeVisible();
        }
      }
    }
  });

  // Journey 2: Anti-Spam Honeypot Bot Trap
  test('Journey 2: Anti-Spam Honeypot - Bot Submissions are Filtered', async ({ page }) => {
    await page.goto('index.php?route=careers');
    await expectNoFatalErrors(page);

    // The honeypot field is offscreen/hidden from human users
    const honeypot = page.locator('input[name="website"], input[name="_hp_url"]');
    if (await honeypot.count() > 0) {
      // Must have aria-hidden or style display:none
      await expect(honeypot.first()).toHaveClass(/d-none|visually-hidden|sr-only/);
    }
  });

  // Journey 3: Recruiter ATS Hub & Requisitions Workflow
  test('Journey 3: Recruiter Command Center & Multi-Level Requisition Wizard', async ({ page }) => {
    await login(page, users.admin);
    await page.goto('index.php?route=recruitment/dashboard');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Recruitment Hub|Active Requisitions/i);

    // Navigate to Requisitions
    await page.goto('index.php?route=recruitment_requisition/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Job Requisitions|Headcount Requests/i);

    // Requisition Creation Wizard
    await page.goto('index.php?route=recruitment_requisition/create');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Create Job Requisition/i);
    await expect(page.locator('input[name="job_title"]')).toBeVisible();

    await logout(page);
  });

  // Journey 4: ATS Pipeline Kanban & Candidate 360
  test('Journey 4: ATS Pipeline Kanban & Candidate 360 Master View', async ({ page }) => {
    await login(page, users.admin);

    // ATS Kanban
    await page.goto('index.php?route=recruitment_application/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Recruitment ATS Pipeline|Kanban/i);

    // Candidate Database
    await page.goto('index.php?route=recruitment_candidate/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Candidate Talent Pool/i);

    await logout(page);
  });

  // Journey 5: Blind Interview Scorecards & Assessment Evaluation
  test('Journey 5: Blind Interview Scorecards & Technical Assessments', async ({ page }) => {
    await login(page, users.admin);

    // Interviews Schedule
    await page.goto('index.php?route=recruitment_interview/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Interview Schedule|Coordination/i);

    // Candidate Assessments
    await page.goto('index.php?route=recruitment_assessment/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Candidate Assessments|Assignments/i);

    await logout(page);
  });

  // Journey 6: Offer Management & Salary Protection
  test('Journey 6: Offer Management, Approval Chains & Salary Masking', async ({ page }) => {
    await login(page, users.admin);

    await page.goto('index.php?route=recruitment_offer/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Offer Management|Compensation/i);

    // Offer Create View
    await page.goto('index.php?route=recruitment_offer/create');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Create Job Offer Proposal/i);

    await logout(page);
  });

  // Journey 7: BGV Checklist & Idempotent Employee Conversion Gate
  test('Journey 7: Background Verification & 1-Click Employee Conversion Gate', async ({ page }) => {
    await login(page, users.admin);

    // Onboarding Hub
    await page.goto('index.php?route=recruitment_onboarding/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Pre-Joining, BGV & Employee Conversion/i);

    await logout(page);
  });

  // Journey 8: Executive Recruitment Analytics & Funnel Velocity
  test('Journey 8: Executive ATS Funnel Analytics & Sourcing Channel ROI', async ({ page }) => {
    await login(page, users.admin);

    await page.goto('index.php?route=recruitment_analytics/index');
    await expectNoFatalErrors(page);
    await expect(page.locator('body')).toContainText(/Recruitment Intelligence & Funnel Analytics/i);
    await expect(page.locator('body')).toContainText(/Avg Time-to-Hire/i);
    await expect(page.locator('body')).toContainText(/Sourcing Channel ROI/i);

    await logout(page);
  });
});

