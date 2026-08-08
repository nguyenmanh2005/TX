$src = "C:\xampp\htdocs\1"
$temp = "C:\xampp\htdocs\1\casino_release_temp"
$zipPath = "C:\xampp\htdocs\casino_project_release.zip"

If (Test-Path $temp) {
    Remove-Item $temp -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $temp | Out-Null

If (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

robocopy $src $temp /E /XD .git .vscode vendor node_modules casino_release_temp bot\sessions /XF *.log .env.php .env .gitignore zip_project.ps1 zip_project.bat test*.php

# Chờ 3 giây để Windows Defender nhả file lock
Start-Sleep -Seconds 3

# Nén thư mục
Compress-Archive -Path "$temp\*" -DestinationPath $zipPath -Force

# Dọn dẹp
Start-Sleep -Seconds 2
Remove-Item $temp -Recurse -Force

Write-Host "Zipping completed successfully!"

Write-Host "Zipping completed successfully!"
