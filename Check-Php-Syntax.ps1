param(
    [Parameter(Mandatory=$false)]
    [string]$Path = "."
)

# Resolve the path to full path
$Target = Resolve-Path $Path

# If it's a file, check only that file
if (Test-Path $Target -PathType Leaf) {
    Write-Host "Checking single file: $Target"
    $result = php -l $Target 2>&1

    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Syntax error in: $Target"
        Write-Host $result
        exit 1
    } else {
        Write-Host "✔ OK"
        exit 0
    }
}

# If it's a directory, recurse
if (Test-Path $Target -PathType Container) {
    Write-Host "Running PHP syntax check recursively in: $Target"
    Write-Host ""

    $Errors = 0
    $files = Get-ChildItem -Path $Target -Recurse -Filter *.php

    foreach ($file in $files) {
        Write-Host "Checking: $($file.FullName)"
        $result = php -l $file.FullName 2>&1

        if ($LASTEXITCODE -ne 0) {
            Write-Host "❌ Syntax error in: $($file.FullName)"
            Write-Host $result
            $Errors++
        } else {
            Write-Host "✔ OK"
        }

        Write-Host ""
    }

    if ($Errors -gt 0) {
        Write-Host "Completed with $Errors error(s)."
        exit 1
    } else {
        Write-Host "All PHP files passed syntax check."
        exit 0
    }
}

Write-Host "Invalid path: $Path"
exit 1
