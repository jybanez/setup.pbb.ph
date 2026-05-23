!macro deletePbbUninstallRegistryKeys
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\fa22339b-af82-5ce0-9613-338994187320"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\ph.pbb.setup"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\Project Bantay Bayan"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\pbb-kit-setup"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\fa22339b-af82-5ce0-9613-338994187320"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\ph.pbb.setup"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\Project Bantay Bayan"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\pbb-kit-setup"
!macroend

!macro cleanupPbbUninstallRegistry
  SetRegView 64
  !insertmacro deletePbbUninstallRegistryKeys

  SetRegView 32
  !insertmacro deletePbbUninstallRegistryKeys
!macroend

!macro customInit
  !insertmacro cleanupPbbUninstallRegistry
!macroend

!macro customInstall
  CreateDirectory "$SMPROGRAMS\Project Bantay Bayan"
  CreateShortCut "$SMPROGRAMS\Project Bantay Bayan\Setup.lnk" "$INSTDIR\Project Bantay Bayan.exe" "--mode setup" "$INSTDIR\Project Bantay Bayan.exe" 0
  CreateShortCut "$SMPROGRAMS\Project Bantay Bayan\Setup DevTools.lnk" "$INSTDIR\Project Bantay Bayan.exe" "--mode setup --devtools" "$INSTDIR\Project Bantay Bayan.exe" 0
  CreateShortCut "$SMPROGRAMS\Project Bantay Bayan\Data Prep.lnk" "$INSTDIR\Project Bantay Bayan.exe" "--mode data-prep" "$INSTDIR\Project Bantay Bayan.exe" 0
!macroend

!macro customUnInstall
  nsExec::ExecToLog 'taskkill /IM "Project Bantay Bayan.exe" /F'
  nsExec::ExecToLog 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "$INSTDIR\resources\app\bin\cleanup-winsw-services.ps1"'
  nsExec::ExecToLog 'netsh advfirewall firewall delete rule name="Project Bantay Bayan HTTP"'
  nsExec::ExecToLog 'netsh advfirewall firewall delete rule name="Project Bantay Bayan HTTPS"'
  Delete "$SMPROGRAMS\Project Bantay Bayan\Setup.lnk"
  Delete "$SMPROGRAMS\Project Bantay Bayan\Setup DevTools.lnk"
  Delete "$SMPROGRAMS\Project Bantay Bayan\Data Prep.lnk"
  RMDir "$SMPROGRAMS\Project Bantay Bayan"

  !insertmacro cleanupPbbUninstallRegistry

  SetShellVarContext current
  RMDir /r "$APPDATA\pbb-kit-setup"
  RMDir /r "$LOCALAPPDATA\pbb-kit-setup"

  ReadEnvStr $0 "APPDATA"
  ReadEnvStr $1 "LOCALAPPDATA"
  RMDir /r "$0\pbb-kit-setup"
  RMDir /r "$1\pbb-kit-setup"
!macroend
