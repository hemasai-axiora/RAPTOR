# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## UI/UX 03. Interactive Kanban Board & Interview Calendar

```
Document:             UI-UX/KANBAN-AND-INTERVIEW-UI.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/UI-UX/
Classification:       Interactive UI Components & Modal Specifications
Target Release:       Raptor v2.3
Technologies:         Vanilla JavaScript ES6+ & jQuery 3.7
```

---

## 1. Recruitment Pipeline Kanban Board

The ATS Kanban board (`views/recruitment/applications/index.php`) provides an interactive multi-column visual pipeline allowing talent acquisition teams to track and advance applicants.

### 1.1 Column Taxonomy
1. **Applied** (`applied`)
2. **Screening** (`screening`)
3. **Shortlisted** (`shortlisted`)
4. **Technical Interview** (`technical_interview`)
5. **HR Interview** (`hr_interview`)
6. **Offer Stage** (`offer_preparation` / `offer_released`)
7. **Hired** (`hired`)

### 1.2 Kanban Card Design & Data Density
Each draggable card represents a single active candidate application:
- **Card Header**: Candidate Name, Application Reference Code, Total Experience.
- **Card Body**: Primary Skills badges (e.g., `PHP 8.2`, `MySQL`), Current Organization.
- **Card Footer**: Assigned Recruiter Avatar, Waiting Duration Pill (`"3 days in stage"`), Advisory Match Indicator (`88%`).
- **Privacy Rule**: Salary expectations and confidential personal contacts are strictly excluded from Kanban cards.

### 1.3 Drag-and-Drop Safety & Optimistic UI
```javascript
// On card drop into new column:
function onApplicationStageDrop(applicationId, newStage) {
    const cardElement = document.getElementById(`app-card-${applicationId}`);
    const originalColumn = cardElement.closest('.kanban-column');

    // 1. Optimistic UI update
    targetColumn.appendChild(cardElement);

    // 2. Dispatch AJAX request to backend state machine
    fetch(`/api/v1/recruitment/applications/${applicationId}/stage`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.RAPTOR_CSRF_TOKEN
        },
        body: JSON.stringify({ to_stage: newStage })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            // Revert on validation or permission failure
            originalColumn.appendChild(cardElement);
            RaptorToast.error(data.error.message || 'Stage transition rejected.');
        } else {
            RaptorToast.success('Candidate advanced successfully.');
        }
    })
    .catch(() => {
        originalColumn.appendChild(cardElement);
        RaptorToast.error('Network error during stage update.');
    });
}
```

---

## 2. Interview Calendar & Scheduling Engine

```
+----------------------------------------------------------------------------------------------------+
|  INTERVIEW CALENDAR                                [< Prev] [ Today ] [ Next >]   [+ Schedule New] |
+----------------------------------------------------------------------------------------------------+
|  Filter by Interviewer: [ All Interviewers v ]   Department: [ Engineering v ]   Mode: [ All v ]   |
+-------------------+-------------------+-------------------+-------------------+--------------------+
|  MONDAY Nov 16    |  TUESDAY Nov 17   |  WEDNESDAY Nov 18 |  THURSDAY Nov 19  |  FRIDAY Nov 20     |
+-------------------+-------------------+-------------------+-------------------+--------------------+
|  10:00 AM - 11:00 |  02:00 PM - 03:00 |  11:00 AM - 12:00 |  03:30 PM - 04:30 |  10:00 AM - 11:00  |
|  [Priya Sharma]   |  [Vikram Singh]   |  [Ananya Roy]     |  [Rahul Verma]    |  [Sneha Patel]     |
|  Round 1 (Tech)   |  Round 2 (System) |  HR Discussion    |  Leadership Round |  Round 1 (Tech)    |
|  Interviewer: Alex|  Interviewer: Dan |  Interviewer: Mia |  Interviewer: CEO |  Interviewer: Alex |
|  Status: Confirmed|  Status: Confirmed|  Status: Scheduled|  Status: Scheduled|  Status: Confirmed |
+-------------------+-------------------+-------------------+-------------------+--------------------+
```

### 2.1 Conflict Detection Engine
Before an interview can be saved, the backend verifies:
1. Interviewer calendar overlaps (no interviewer can be booked in overlapping time windows).
2. Candidate interview overlaps across multiple job applications.
3. If an overlap is detected, the UI displays a warning banner suggesting alternative free slots.

---

## 3. Blind Interview Evaluation Modal

```
+----------------------------------------------------------------------------------------------------+
|  SUBMIT INTERVIEW EVALUATION: Round 1 (Technical Architecture)                                     |
+----------------------------------------------------------------------------------------------------+
|  Candidate: Priya Sharma | Requisition: Senior Backend PHP Engineer | Round: 1 of 3                |
|  Note: Your evaluation is confidential. Peer interviewers cannot see your score until submitted.   |
+----------------------------------------------------------------------------------------------------+
|  1. Technical Depth (PHP 8.2, OOP, Architecture):        [ 1 ] [ 2 ] [ 3 ] [ 4 ] [ (5) ]           |
|  2. Problem Solving & Database Design:                   [ 1 ] [ 2 ] [ 3 ] [ (4) ] [ 5 ]           |
|  3. System Communication & Articulation:                [ 1 ] [ 2 ] [ 3 ] [ (4) ] [ 5 ]           |
|  4. Cultural Alignment & Teamwork:                       [ 1 ] [ 2 ] [ 3 ] [ 4 ] [ (5) ]           |
+----------------------------------------------------------------------------------------------------+
|  Key Strengths:                                                                                    |
|  [ Exceptional understanding of concurrency, row locking (SKIP LOCKED), and PSR standards.       ] |
|                                                                                                    |
|  Areas of Concern / Further Assessment:                                                           |
|  [ Needs validation on front-end CSS token architecture in Round 2.                              ] |
|                                                                                                    |
|  Hiring Recommendation:                                                                            |
|  (o) Strongly Recommend    ( ) Recommend    ( ) Hold / Re-evaluate    ( ) Do Not Recommend         |
+----------------------------------------------------------------------------------------------------+
|  [ Cancel ]                                            [ SUBMIT & LOCK FINAL SCORECARD ]           |
+----------------------------------------------------------------------------------------------------+
```
