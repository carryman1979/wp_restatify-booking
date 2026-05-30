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

$sharedVersionMatch = [regex]::Match((Get-Content $pluginMainFile -Raw), "RESTATIFY_BOOKING_SHARED_VERSION'\s*,\s*'([^']+)'")
if (-not $sharedVersionMatch.Success) {
    throw 'Could not detect RESTATIFY_BOOKING_SHARED_VERSION from wp_restatify-booking.php'
}

$sharedVersion = $sharedVersionMatch.Groups[1].Value.Trim()

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
    'vendor',
    'tests',
    'test',
    'scripts',
    'wiki',
    '.phpunit.cache',
    '.release-tmp',
    'release'
)

Get-ChildItem -Path $pluginRoot -Force | Where-Object { $excludeNames -notcontains $_.Name } | ForEach-Object {
    Copy-Item $_.FullName -Destination $stagingDir -Recurse -Force
}

$sharedSourcePhpDir = Join-Path $pluginRoot '../../../wp_restatify-shared/src/php'
$sharedSourcePhpDir = [System.IO.Path]::GetFullPath($sharedSourcePhpDir)
if (-not (Test-Path $sharedSourcePhpDir)) {
    throw "Shared source directory not found: $sharedSourcePhpDir"
}

$sharedTargetPhpDir = Join-Path $stagingDir "shared-install/wp_restatify-shared/versions/$sharedVersion/src/php"
New-Item -ItemType Directory -Path $sharedTargetPhpDir -Force | Out-Null
Copy-Item (Join-Path $sharedSourcePhpDir '*') -Destination $sharedTargetPhpDir -Recurse -Force

$removeDirectoryNames = @('tests', 'test')
Get-ChildItem -Path $stagingDir -Directory -Recurse | Where-Object {
    $removeDirectoryNames -contains $_.Name.ToLowerInvariant()
} | Sort-Object FullName -Descending | ForEach-Object {
    Remove-Item $_.FullName -Recurse -Force
}

$removeFileNames = @('phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache')
Get-ChildItem -Path $stagingDir -File -Recurse | Where-Object {
    $removeFileNames -contains $_.Name.ToLowerInvariant()
} | ForEach-Object {
    Remove-Item $_.FullName -Force
}

$removeRootFiles = @(
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'phpunit.xml',
    'phpunit.xml.dist',
    '.phpunit.result.cache'
)

foreach ($fileName in $removeRootFiles) {
    $candidate = Join-Path $stagingDir $fileName
    if (Test-Path $candidate) {
        Remove-Item $candidate -Force
    }
}

$zipPath = Join-Path $releaseDir ("wp_restatify-booking-$Version.zip")
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$stagingRoot = Join-Path $tempRoot 'wp_restatify-booking'
$files = Get-ChildItem -Path $stagingRoot -Recurse -File

$zipArchive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $files) {
        $relativePath = $file.FullName.Substring($stagingRoot.Length).TrimStart([char[]]'\\/')
        $entryPath = ('wp_restatify-booking/' + $relativePath).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zipArchive,
            $file.FullName,
            $entryPath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $zipArchive.Dispose()
}

$archive = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $entries = $archive.Entries | Select-Object -ExpandProperty FullName

    if (($entries | Where-Object { $_ -match '\\' }).Count -gt 0) {
        throw "Invalid ZIP entry separators found. Use '/' in archive entries only."
    }

    if (-not ($entries -contains 'wp_restatify-booking/wp_restatify-booking.php')) {
        throw 'Invalid package layout: missing main plugin file in archive root.'
    }
}
finally {
    $archive.Dispose()
}

Remove-Item $tempRoot -Recurse -Force

Write-Output "Created release package: $zipPath"