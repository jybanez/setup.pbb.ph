param(
    [string] $ServiceRoot = ""
)

$ErrorActionPreference = "Continue"

if ($ServiceRoot -eq "") {
    $programData = [Environment]::GetEnvironmentVariable("ProgramData")
    if ([string]::IsNullOrWhiteSpace($programData)) {
        $programData = "C:\ProgramData"
    }
    $ServiceRoot = Join-Path $programData "PBB\Services"
} else {
    $programData = [Environment]::GetEnvironmentVariable("ProgramData")
    if ([string]::IsNullOrWhiteSpace($programData)) {
        $programData = "C:\ProgramData"
    }
}

$PbbProgramDataRoot = Join-Path $programData "PBB"
$KitSetupProgramDataRoot = Join-Path $PbbProgramDataRoot "KitSetup"
$InstallStatePath = Join-Path $KitSetupProgramDataRoot "install-state.json"

function Invoke-LoggedCommand {
    param([string[]] $Command)

    if ($Command.Count -lt 1) {
        return
    }

    Write-Host ("Running: " + ($Command -join " "))
    try {
        & $Command[0] @($Command | Select-Object -Skip 1)
        Write-Host ("Exit code: " + $LASTEXITCODE)
    } catch {
        Write-Host ("Command failed: " + $_.Exception.Message)
    }
}

function Stop-And-DeleteWindowsService {
    param([string] $ServiceName)

    if ([string]::IsNullOrWhiteSpace($ServiceName)) {
        return
    }

    $service = Get-CimInstance Win32_Service -Filter ("Name='" + $ServiceName.Replace("'", "''") + "'") -ErrorAction SilentlyContinue
    if ($null -eq $service) {
        return
    }

    if ($service.State -ne "Stopped") {
        Invoke-LoggedCommand @("sc.exe", "stop", $ServiceName)
        Start-Sleep -Seconds 2
    }

    Invoke-LoggedCommand @("sc.exe", "delete", $ServiceName)
}

$serviceNames = New-Object "System.Collections.Generic.HashSet[string]"

if (Test-Path -LiteralPath $ServiceRoot) {
    Get-ChildItem -LiteralPath $ServiceRoot -Directory -ErrorAction SilentlyContinue | ForEach-Object {
        $serviceDir = $_.FullName
        $serviceId = $_.Name
        $xmlPath = Join-Path $serviceDir ($serviceId + ".xml")
        $exePath = Join-Path $serviceDir ($serviceId + ".exe")

        if (Test-Path -LiteralPath $xmlPath) {
            try {
                [xml] $xml = Get-Content -LiteralPath $xmlPath -Raw
                if ($xml.service.id) {
                    $serviceId = [string] $xml.service.id
                }
            } catch {
                Write-Host ("Unable to read WinSW XML " + $xmlPath + ": " + $_.Exception.Message)
            }
        }

        [void] $serviceNames.Add($serviceId)

        if (Test-Path -LiteralPath $exePath) {
            Invoke-LoggedCommand @($exePath, "stop")
            Invoke-LoggedCommand @($exePath, "uninstall")
        }
    }
}

Get-CimInstance Win32_Service -ErrorAction SilentlyContinue |
    Where-Object {
        ($_.Name -like "pbb-*") -or
        ($_.PathName -like "*\ProgramData\PBB\Services\*") -or
        ($_.PathName -like "*\PBB\Services\*")
    } |
    ForEach-Object {
        [void] $serviceNames.Add([string] $_.Name)
    }

foreach ($serviceName in $serviceNames) {
    Stop-And-DeleteWindowsService -ServiceName $serviceName
}

if (Test-Path -LiteralPath $ServiceRoot) {
    Remove-Item -LiteralPath $ServiceRoot -Recurse -Force -ErrorAction SilentlyContinue
}

if (Test-Path -LiteralPath $InstallStatePath) {
    Remove-Item -LiteralPath $InstallStatePath -Force -ErrorAction SilentlyContinue
}

foreach ($path in @($KitSetupProgramDataRoot, $PbbProgramDataRoot)) {
    if (Test-Path -LiteralPath $path) {
        $remaining = @(Get-ChildItem -LiteralPath $path -Force -ErrorAction SilentlyContinue)
        if ($remaining.Count -eq 0) {
            Remove-Item -LiteralPath $path -Force -ErrorAction SilentlyContinue
        }
    }
}
