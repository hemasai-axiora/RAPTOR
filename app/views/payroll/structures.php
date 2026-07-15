<div class="pulse-card">
    <?php if (!empty($_SESSION['payroll_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_success']); unset($_SESSION['payroll_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['payroll_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_error']); unset($_SESSION['payroll_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <h4 class="text-white mb-4"><i class="fa-solid fa-calculator me-2" style="color: var(--primary);"></i>Salary Structures</h4>

    <form method="GET" action="index.php" class="row g-3 align-items-end mb-4">
        <input type="hidden" name="route" value="payroll/structures">
        <div class="col-md-8">
            <label for="employee_id" class="form-label text-secondary font-weight-bold">Select Employee</label>
            <select name="employee_id" id="employee_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">-- Choose Employee --</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp->employee_id; ?>" <?php echo $selected_employee_id === (int)$emp->employee_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-outline-light w-100">Load Structure</button>
        </div>
    </form>

    <?php if ($selected_employee_id > 0): ?>
        <form action="index.php?route=payroll/save_structure" method="POST" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="employee_id" value="<?php echo $selected_employee_id; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-secondary font-weight-bold">Salary Type</label>
                    <select name="salary_type" class="form-select bg-dark border-secondary text-white">
                        <option value="Monthly" <?php echo ($structure && $structure->salary_type === 'Monthly') ? 'selected' : ''; ?>>Monthly Retainer</option>
                        <option value="Hourly" <?php echo ($structure && $structure->salary_type === 'Hourly') ? 'selected' : ''; ?>>Hourly Wages</option>
                    </select>
                </div>

                <div class="col-12 mt-4">
                    <h5 class="text-white border-bottom border-secondary pb-2 mb-3">Earnings Components</h5>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small">Basic Salary / Wage Rate *</label>
                    <input type="number" step="0.01" name="basic_salary" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? (float)$structure->basic_salary : '0.00'; ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small">House Rent Allowance (HRA)</label>
                    <input type="number" step="0.01" name="hra" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? (float)$structure->hra : '0.00'; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small">Special Allowance</label>
                    <input type="number" step="0.01" name="special_allowance" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? (float)$structure->special_allowance : '0.00'; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small">Medical Allowance</label>
                    <input type="number" step="0.01" name="medical_allowance" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? (float)$structure->medical_allowance : '0.00'; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small">Travel / Conveyance Allowance</label>
                    <input type="number" step="0.01" name="travel_allowance" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? (float)$structure->travel_allowance : '0.00'; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small">Regular Monthly Bonus</label>
                    <input type="number" step="0.01" name="bonus" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? (float)$structure->bonus : '0.00'; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small">Other Earnings</label>
                    <input type="number" step="0.01" name="other_earnings" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? (float)$structure->other_earnings : '0.00'; ?>">
                </div>

                <div class="col-12 mt-4">
                    <h5 class="text-white border-bottom border-secondary pb-2 mb-3">Deductions Components</h5>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-secondary small">Provident Fund (PF) Employee Share</label>
                    <input type="number" step="0.01" name="pf" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? (float)$structure->pf : '0.00'; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-secondary small">ESIC Employee Share</label>
                    <input type="number" step="0.01" name="esic" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? (float)$structure->esic : '0.00'; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-secondary small">Professional Tax</label>
                    <input type="number" step="0.01" name="professional_tax" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? (float)$structure->professional_tax : '0.00'; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-secondary small">Tax Deducted at Source (TDS)</label>
                    <input type="number" step="0.01" name="tds" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? (float)$structure->tds : '0.00'; ?>">
                </div>

                <div class="col-12 mt-4 p-3 rounded bg-dark border border-secondary d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small font-weight-bold d-block">Estimated Gross: <span id="lbl-gross" class="text-white">0.00</span></span>
                        <span class="text-secondary small font-weight-bold d-block">Estimated Deductions: <span id="lbl-ded" class="text-white">0.00</span></span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small font-weight-bold d-block text-uppercase">Net Monthly Payout</span>
                        <h3 id="lbl-net" class="text-success mb-0">0.00</h3>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                    <button type="button" class="btn btn-outline-warning px-4" id="btn-random-salary"><i class="fa-solid fa-dice me-2"></i>Generate Random Sample</button>
                    <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Save Salary Structure</button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="text-center py-4 text-secondary">
            <i class="fa-solid fa-arrow-pointer mb-2 fs-4"></i>
            <p>Please select an employee from the dropdown list to manage their salary structure.</p>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2 search option inside the select element
    $('#employee_id').select2({
        placeholder: "-- Choose Employee --",
        allowClear: true
    }).on('select2:select', function (e) {
        $(this).closest('form').submit();
    });

    // Random salary generator click handler
    $('#btn-random-salary').on('click', function() {
        const basic = Math.floor(Math.random() * (45000 - 18000 + 1)) + 18000;
        const hra = Math.round(basic * 0.40);
        const special = Math.floor(Math.random() * 4000) + 1500;
        const medical = 1250;
        const travel = 1600;
        const bonus = Math.random() > 0.5 ? Math.floor(Math.random() * 2000) + 1000 : 0;
        const other = 0;
        
        const pf = Math.round(basic * 0.12);
        const esic = Math.round(basic * 0.0075);
        const pt = 200;
        const tds = basic > 35000 ? Math.round(basic * 0.10) : 0;

        $('input[name="basic_salary"]').val(basic.toFixed(2));
        $('input[name="hra"]').val(hra.toFixed(2));
        $('input[name="special_allowance"]').val(special.toFixed(2));
        $('input[name="medical_allowance"]').val(medical.toFixed(2));
        $('input[name="travel_allowance"]').val(travel.toFixed(2));
        $('input[name="bonus"]').val(bonus.toFixed(2));
        $('input[name="other_earnings"]').val(other.toFixed(2));
        
        $('input[name="pf"]').val(pf.toFixed(2));
        $('input[name="esic"]').val(esic.toFixed(2));
        $('input[name="professional_tax"]').val(pt.toFixed(2));
        $('input[name="tds"]').val(tds.toFixed(2));

        calculateLiveSalary();
    });

    function calculateLiveSalary() {
        let gross = 0;
        $('.calc-gross').each(function() {
            gross += parseFloat($(this).val()) || 0;
        });

        let ded = 0;
        $('.calc-ded').each(function() {
            ded += parseFloat($(this).val()) || 0;
        });

        const net = gross - ded;

        $('#lbl-gross').text(gross.toFixed(2));
        $('#lbl-ded').text(ded.toFixed(2));
        $('#lbl-net').text(net.toFixed(2));
    }

    $('.calc-gross, .calc-ded').on('input change', calculateLiveSalary);
    calculateLiveSalary();
});
</script>
