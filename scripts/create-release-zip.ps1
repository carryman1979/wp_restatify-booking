param(
    [string]$Version = ""
)

$ErrorActionPreference = 'Stop'

$pluginRoot = Split-Path -Parent $PSScriptRoot
Set-Location $pluginRoot

$pluginMainFile = Join-Path $pluginRoot 'wp_restatify-booking.php'

if ([string]::IsNullOrWhiteSpace($Version)) {
    $pluginHeader = Get-Content $pluginMainFile -Raw
    $versionMatch = [regex]::Match($pluginHeader, 'Version:\s*([^\r\n]+)')

    if (-not $versionMatch.Success) {
        throw 'Could not detect plugin version from wp_restatify-booking.php'
    }

    $Version = $versionMatch.Groups[1].Value.Trim()
}

$releaseDir = Join-Path $pluginRoot 'release'
New-Item -ItemType Directory -Path $releaseDir -Force | Out-Null

$tempRoot = Join-Path $pluginRoot '.release-tmp'
$stagingDir = Join-Path $tempRoot 'wp_restatify-booking'

if (Test-Path $tempRoot) {
    Remove-Item $tempRoot -Recurse -Force
}

New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null

$excludeNames = @(
    '.git',
    '.github',
    'node_modules',
    '.release-tmp',
    'release'
)

Get-ChildItem -Path $pluginRoot -Force | Where-Object { $excludeNames -notcontains $_.Name } | ForEach-Object {
    Copy-Item $_.FullName -Destination $stagingDir -Recurse -Force
}

$zipPath = Join-Path $releaseDir ("wp_restatify-booking-$Version.zip")
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Compress-Archive -Path (Join-Path $tempRoot 'wp_restatify-booking') -DestinationPath $zipPath -CompressionLevel Optimal
Remove-Item $tempRoot -Recurse -Force

Write-Output "Created release package: $zipPath"