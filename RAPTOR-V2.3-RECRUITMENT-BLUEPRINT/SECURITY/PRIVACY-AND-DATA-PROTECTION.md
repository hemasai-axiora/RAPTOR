# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## Security 03. Privacy, DPDP Act 2023 & Candidate Data Protection

```
Document:             SECURITY/PRIVACY-AND-DATA-PROTECTION.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SECURITY/
Classification:       Data Privacy Compliance, Consent Architecture & Retention
Target Release:       Raptor v2.3
Regulatory Framework: Digital Personal Data Protection Act (DPDP 2023) & GDPR
```

---

## 1. Compliance Architecture Overview

Candidate data is classified as **High-Sensitivity Personal Data**. Under the **Digital Personal Data Protection Act (DPDP 2023)** and international standards (GDPR), enterprises processing candidate records must implement strict data minimization, lawful consent capture, purpose limitation, storage limitation, and candidate data rights.

Raptor v2.3 incorporates automated privacy controls directly into the ATS database and service layer.

---

## 2. Core Privacy Pillars

### 2.1 Consent Logging & Verification
1. **Notice & Consent Capture**:
   - Before submitting an application via the career portal, candidates must review a clear privacy notice detailing data processing purposes (application screening, interview scheduling, background verification).
   - Candidate clicks trigger an immutable entry in `recruitment_candidate_consents` recording:
     `candidate_id`, `consent_type`, `is_granted`, `ip_address`, `user_agent`, and `granted_at`.
2. **Consent Withdrawal**:
   - If a candidate withdraws consent, their status is updated to `consent_revoked`, triggering a notification to the assigned recruiter and halting automated communications.

### 2.2 Data Minimization & Sensitive Data Masking
1. **Initial Application Sourcing**:
   - Initial intake collects only necessary professional data (name, contact details, resume, work history, skills).
   - Sensitive government identification (Aadhaar, PAN, Passport) is **never** collected during initial application; collection is deferred strictly to the pre-joining/onboarding stage (`recruitment_pre_joining_checklist`) after an offer is formally accepted.
2. **Salary Masking**:
   - Historical and expected compensation data is encrypted or access-restricted. Interviewers and technical evaluators are presented with masked strings (`₹ ** **,***`) to prevent compensation bias during candidate evaluation.

### 2.3 Automated Data Retention & Anonymization Engine (`RecruitmentRetentionService`)
Organizations cannot retain unsuccessful candidate data indefinitely without legitimate justification:
- **Default Retention Policy**: Candidate records and resumes for unhired applicants are retained for a configurable period (default: **180 days** or **365 days**).
- **Scheduled Retention Job**: A nightly CLI cron job (`bin/raptor recruitment:retention`) scans for applications past their retention threshold.
- **Anonymization vs. Hard Deletion**:
  - To preserve operational reporting and statistical metrics (e.g. historical applications per department, time-to-fill analytics), the candidate profile is **anonymized** rather than destroyed:
    - `first_name` → `Anonymized`
    - `last_name` → `Candidate #XXXX`
    - `email` → `anon_xxxx@privacy.invalid`
    - `phone` → `+910000000000`
    - `current_company` → `Confidential`
    - Associated resume files in `STORAGE_PATH` are securely deleted from disk (`Storage::delete($key)`).
    - An immutable audit entry is recorded in `activity_logs` documenting lawful anonymization under DPDP Act.

---

## 3. Candidate Rights Self-Service

| Candidate Right | Statutory Basis | ATS Implementation Mechanism |
|---|---|---|
| **Right to Access** | DPDP Sec 11(1) / GDPR Art 15 | Candidate can view submitted application history, uploaded resume metadata, and interview status via external candidate portal. |
| **Right to Correction** | DPDP Sec 12(1) / GDPR Art 16 | Candidate can submit updated contact information or a revised resume before an application moves to the interview stage. |
| **Right to Erasure** | DPDP Sec 12(2) / GDPR Art 17 | Candidate can submit an automated "Request Deletion" ticket; upon review by HR, the record is anonymized and storage files unlinked. |
| **Right to Grievance Redressal** | DPDP Sec 13 | Designates an internal Data Protection Officer (DPO) contact point on the public career site. |

---

## 4. AI Processing Privacy Guardrails

When candidate resumes or application data are processed through external LLMs (`AiService.php` via Gemini, OpenAI, or Claude):
1. **PII Sanitization**:
   Before dispatching a prompt, the `RecruitmentAiService` strips candidate PII:
   ```php
   $sanitizedResume = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL_REDACTED]', $rawResume);
   $sanitizedResume = preg_replace('/(\+?\d{1,3}[- ]?)?\d{10}/', '[PHONE_REDACTED]', $sanitizedResume);
   ```
2. **Zero Training Retention**:
   API calls are made using enterprise commercial tiers with contractual guarantees that input tokens are not retained for public foundation model training.
3. **No Solely Automated Decisions**:
   Under DPDP and EU AI Act principles, no candidate may be automatically rejected or shortlisted by AI; human HR recruiter review is mandatory for all stage transitions.
