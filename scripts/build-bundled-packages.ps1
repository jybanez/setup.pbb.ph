param(
    [string] $OutputRoot = "packages\bundled",
    [string] $ManifestPath = "packages\packages.bundled.json"
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$outputPath = Join-Path $repoRoot $OutputRoot
$manifestFile = Join-Path $repoRoot $ManifestPath

$apps = @(
    @{ id = "pbb-mapserver"; version = "1.0.0"; source = "C:\wamp64\www\mapserver" },
    @{ id = "pbb-maestro"; version = "1.0.0"; source = "C:\wamp64\www\pbb\maestro" },
    @{ id = "pbb-realtime"; version = "1.0.0"; source = "C:\wamp64\www\pbb\realtime" },
    @{ id = "pbb-relay"; version = "1.1.0"; source = "C:\wamp64\www\pbb\relay" },
    @{ id = "pbb-hotline"; version = "5.6.1"; source = "C:\wamp64\www\pbb\hotline" }
)

$excludedSegments = @(
    ".git",
    ".artifacts",
    ".codex",
    ".phpunit.cache",
    ".vscode",
    "coverage",
    "dist",
    "node_modules",
    "out",
    "storage",
    "test-results",
    "tmp"
)

function Test-IncludedFile {
    param(
        [string] $SourceRoot,
        [string] $FullName
    )

    $relative = Get-RelativePath -BasePath $SourceRoot -TargetPath $FullName
    $segments = $relative -split '[\\/]'
    foreach ($segment in $segments) {
        if ($excludedSegments -contains $segment) {
            return $false
        }
        if ($segment -like ".codex_tmp_*" -or $segment -like ".scaffold_tmp_*") {
            return $false
        }
    }

    return $true
}

function Get-RelativePath {
    param(
        [string] $BasePath,
        [string] $TargetPath
    )

    $baseUri = New-Object System.Uri(($BasePath.TrimEnd("\") + "\"))
    $targetUri = New-Object System.Uri($TargetPath)
    return [System.Uri]::UnescapeDataString($baseUri.MakeRelativeUri($targetUri).ToString()).Replace("/", "\")
}

function Add-DirectoryToZip {
    param(
        [string] $SourceRoot,
        [string] $ZipPath
    )

    if (Test-Path $ZipPath) {
        Remove-Item -LiteralPath $ZipPath -Force
    }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $zip = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem -LiteralPath $SourceRoot -Recurse -File -Force |
            Where-Object { Test-IncludedFile -SourceRoot $SourceRoot -FullName $_.FullName } |
            ForEach-Object {
                $entryName = (Get-RelativePath -BasePath $SourceRoot -TargetPath $_.FullName).Replace("\", "/")
                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
            }
    } finally {
        $zip.Dispose()
    }
}

function Get-Sha256Hex {
    param([string] $Path)

    $stream = [System.IO.File]::OpenRead($Path)
    try {
        $sha = [System.Security.Cryptography.SHA256]::Create()
        try {
            $bytes = $sha.ComputeHash($stream)
            return (($bytes | ForEach-Object { $_.ToString("x2") }) -join "")
        } finally {
            $sha.Dispose()
        }
    } finally {
        $stream.Dispose()
    }
}

New-Item -ItemType Directory -Force -Path $outputPath | Out-Null

$entries = @()
foreach ($app in $apps) {
    $source = $app.source
    if (!(Test-Path (Join-Path $source "release.json"))) {
        throw "Missing release.json for $($app.id): $source"
    }

    $zipName = "$($app.id)-$($app.version).zip"
    $zipPath = Join-Path $outputPath $zipName
    Write-Host "Building $zipName from $source"
    Add-DirectoryToZip -SourceRoot $source -ZipPath $zipPath
    $hash = Get-Sha256Hex -Path $zipPath
    $relativeZip = (Get-RelativePath -BasePath (Split-Path $manifestFile -Parent) -TargetPath $zipPath).Replace("\", "/")
    $entries += [ordered]@{
        app_id = $app.id
        version = $app.version
        source_type = "zip"
        path = $relativeZip
        sha256 = $hash
        trusted = $true
        signature_status = "local-trusted"
    }
}

$manifest = [ordered]@{
    schema_version = 1
    generated_for = "Project Bantay Bayan bundled app packages"
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
    packages = $entries
}

$json = $manifest | ConvertTo-Json -Depth 8
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($manifestFile, $json + [Environment]::NewLine, $utf8NoBom)
Write-Host "Wrote $manifestFile"
