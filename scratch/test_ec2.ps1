[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12 -bor [System.Net.SecurityProtocolType]::Tls11 -bor [System.Net.SecurityProtocolType]::Tls
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}

$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginUrl = "https://98.94.227.211/public/index.php?route=auth/login"

$r1 = Invoke-WebRequest -Uri $loginUrl -WebSession $web
$token = ""
if ($r1.Content -match 'name="csrf_token" value="([^"]+)"') {
    $token = $Matches[1]
}

$body = @{
    email = "employee@raptor.local"
    password = "Raptor@12345"
    csrf_token = $token
}

$r2 = Invoke-WebRequest -Uri $loginUrl -Method Post -Body $body -WebSession $web
Write-Host "Login Status: $($r2.StatusCode)"

$routes = @("followups/index", "leads/index", "customers/index", "communications/index", "meetings/index")
foreach ($route in $routes) {
    try {
        $u = "https://98.94.227.211/public/index.php?route=$route"
        $res = Invoke-WebRequest -Uri $u -WebSession $web
        Write-Host "--- $route -> $($res.StatusCode)"
        Write-Host ($res.Content.Substring(0, [Math]::Min(300, $res.Content.Length)))
    } catch {
        Write-Host "--- $route -> Exception: $($_.Exception.Message)"
        if ($_.Exception.Response) {
            $stream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($stream)
            Write-Host "Error Body: $($reader.ReadToEnd())"
        }
    }
}
