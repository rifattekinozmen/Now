$loginUrl = "http://127.0.0.1:8000/login"
$loginStoreUrl = "http://127.0.0.1:8000/login"

# First, get the login page to extract CSRF token
Write-Host "Getting login page..."
$response = Invoke-WebRequest -Uri $loginUrl -UseBasicParsing -SessionVariable session

# Extract CSRF token
$tokenPattern = 'name="csrf_token"\s+value="([^"]+)"'
if ($response.Content -match $tokenPattern) {
    $csrfToken = $Matches[1]
    Write-Host "CSRF Token: $csrfToken"
} elseif ($response.Content -match 'content="([^"]+)"\s+name="csrf-token"') {
    $csrfToken = $Matches[1]
    Write-Host "CSRF Token (meta): $csrfToken"
} else {
    Write-Host "Could not find CSRF token, searching for _token..."
    if ($response.Content -match 'name="_token"\s+value="([^"]+)"') {
        $csrfToken = $Matches[1]
        Write-Host "CSRF Token (_token): $csrfToken"
    } else {
        Write-Host "ERROR: Could not extract CSRF token!"
        exit 1
    }
}

# Now attempt login with CSRF token
Write-Host "Attempting login..."
$loginBody = @{
    'email' = 'test@example.com'
    'password' = 'password'
    'csrf_token' = $csrfToken
    'remember' = 'on'
}

try {
    $loginResponse = Invoke-WebRequest -Uri $loginStoreUrl -Method POST -Body $loginBody -WebSession $session -UseBasicParsing -FollowRelLink -AllowRedirect
    
    Write-Host "Login Response Status: $($loginResponse.StatusCode)"
    if ($loginResponse.BaseResponse) {
        Write-Host "Final URL: $($loginResponse.BaseResponse.RequestMessage.RequestUri.AbsoluteUri)"
    }
    
    Write-Host "✓ Request completed"
} catch {
    Write-Host "Error during login: $_"
}
