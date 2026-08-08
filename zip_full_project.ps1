$OutputEncoding = [System.Text.Encoding]::UTF8
$src = "C:\xampp\htdocs\1"
$temp = "C:\xampp\htdocs\1\gtlm_temp_package"
$zipPath = "C:\xampp\htdocs\GTLM_Full_Project.zip"

Write-Host ">>> Preparing temporary directory..."
If (Test-Path $temp) {
    Remove-Item $temp -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $temp | Out-Null

If (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Write-Host ">>> Copying all source code (excluding .git, .vscode, logs and session folders)..."
robocopy $src $temp /E /XD .git .vscode vendor node_modules gtlm_temp_package casino_release_temp bot\sessions /XF *.log /NJH /NJS

Start-Sleep -Seconds 2

Write-Host ">>> Compressing files into zip package at $zipPath..."
Compress-Archive -Path "$temp\*" -DestinationPath $zipPath -Force

Start-Sleep -Seconds 1
Remove-Item $temp -Recurse -Force

If (Test-Path $zipPath) {
    $sizeInfo = (Get-Item $zipPath).Length / 1MB
    $sizeFormatted = "{0:N2} MB" -f $sizeInfo
    Write-Host "SUCCESS: Entire project packaged!"
    Write-Host "ZIP FILE LOCATION: $zipPath (Size: $sizeFormatted)"
} else {
    Write-Host "ERROR: Could not create zip file."
}
