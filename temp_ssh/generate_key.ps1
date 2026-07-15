$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = "ssh-keygen"
$psi.Arguments = "-t rsa -b 2048 -f .\temp_ssh\id_temp -N `"`""
$psi.UseShellExecute = $false
$psi.CreateNoWindow = $true
$process = [System.Diagnostics.Process]::Start($psi)
$process.WaitForExit()
