$ErrorActionPreference = 'Stop'
$base = 'http://localhost/voiceshop/public'
$logs = Join-Path $PSScriptRoot 'logs'
$jar = Join-Path $logs 'apache-cookies.txt'
$evidence = @()
function Request-Page($name, $url, $fields = @()) {
    $body = Join-Path $logs ($name + '.html')
    $headers = Join-Path $logs ($name + '-headers.txt')
    $arguments = @('--silent','--show-error','--max-time','20','--cookie',$jar,'--cookie-jar',$jar,'--dump-header',$headers,'--output',$body,'--write-out','%{http_code}',$url)
    foreach ($field in $fields) { $arguments += @('--data-urlencode',$field) }
    $status = & curl.exe @arguments
    if ($LASTEXITCODE -ne 0) { throw "curl failed: $name" }
    $html = Get-Content -Raw -Encoding UTF8 $body
    $plain = [Net.WebUtility]::HtmlDecode(($html -replace '<script[\s\S]*?</script>','' -replace '<[^>]+>',' ' -replace '\s+',' ')).Trim()
    $entry = [pscustomobject]@{name=$name;url=$url;status=$status;excerpt=$plain.Substring(0,[Math]::Min(1000,$plain.Length));headers=$headers;body=$body}
    $script:evidence += $entry
    Write-Output "$name HTTP $status $url"
    return $html
}
$html = Request-Page 'curl-setup-get' "$base/setup.php"
$csrf = [regex]::Match(($html -join ''),'name="csrf" value="([a-f0-9]+)"').Groups[1].Value
$null = Request-Page 'curl-setup-seed' "$base/setup.php" @("csrf=$csrf")
$null = Request-Page 'curl-owner-login' "$base/index.php?page=owner" @("csrf=$csrf",'pin=2468')
$null = Request-Page 'curl-orders' "$base/index.php"
$null = Request-Page 'curl-import' "$base/import.php"
$null = Request-Page 'curl-health' "$base/index.php?page=health"
$null = Request-Page 'curl-css' "$base/assets/css/app.css"
$null = Request-Page 'curl-js' "$base/assets/js/import.js"
$sample = Get-Content -Raw -Encoding UTF8 (Join-Path $logs 'curl-sample.json')
$payloadField = 'payload@' + (Join-Path $logs 'curl-sample.json')
$html = Request-Page 'curl-import-submit' "$base/import.php" @("csrf=$csrf",$payloadField)
$id = [regex]::Match(($html -join ''),'Open order #(\d+)').Groups[1].Value
if (!$id) { throw 'Imported order ID missing' }
$null = Request-Page 'curl-detail' "$base/index.php?page=detail&id=$id"
$null = Request-Page 'curl-approve' "$base/index.php?page=detail&id=$id" @("csrf=$csrf",'action=status','status=CONFIRMED')
$null = Request-Page 'curl-approved-detail' "$base/index.php?page=detail&id=$id&saved=1"
$null = Request-Page 'curl-builtin-preserved' 'http://127.0.0.1:8087/index.php'
$evidence | ConvertTo-Json -Depth 5 | Set-Content -Encoding UTF8 (Join-Path $logs 'curl-results.json')
$evidence | Select-Object name,status,url | Format-Table -AutoSize
