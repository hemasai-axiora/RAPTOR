import zipfile
import os

root_dir = r"c:\Users\Axiora User-30\Desktop\RAPTOR-main\RAPTOR-main"
zip_path = r"c:\Users\Axiora User-30\Desktop\update.zip"

files_to_include = [
    'app/config/config.php',
    'app/core/App.php',
    'app/core/Controller.php',
    'app/core/Model.php',
    'app/core/PermissionService.php',
    'app/core/Policy.php',
    'app/core/Database.php',
    'app/controllers/AuthController.php',
    'app/controllers/FollowupsController.php',
    'app/controllers/LeadsController.php',
    'app/controllers/CustomersController.php',
    'app/controllers/CommunicationsController.php',
    'app/controllers/MeetingsController.php',
    'app/models/User.php',
    'app/models/FollowUp.php',
    'app/models/Lead.php',
    'app/models/Customer.php',
    'app/models/Communication.php',
    'app/models/Meeting.php',
    'app/views/layouts/main.php',
    'app/views/followups/index.php',
    'app/views/leads/index.php',
    'app/views/customers/index.php',
    'app/views/communications/index.php',
    'app/views/meetings/index.php',
    'app/views/errors/403.php',
    'public/index.php',
    'public/patch.php',
    'migrations/0041_fix_employee_crm_permissions.php'
]

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for rel_path in files_to_include:
        abs_path = os.path.join(root_dir, rel_path.replace('/', os.sep))
        if os.path.exists(abs_path):
            zipf.write(abs_path, rel_path)
            print(f"Added: {rel_path}")
        else:
            print(f"MISSING: {abs_path}")

print(f"\nCreated Zip File at: {zip_path} (Size: {os.path.getsize(zip_path)} bytes)")
